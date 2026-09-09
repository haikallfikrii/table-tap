# Print Bridge — Wi-Fi / LAN thermal printing

TableTap already prints silently over **Web Bluetooth** from the kasir and station
tablets. Print Bridge adds a second, parallel path for shops that use **network
ESC/POS printers** (the ones with an Ethernet or Wi-Fi port, not USB/Bluetooth).

Both paths can be on at the same time. Turning Print Bridge on never changes the
Bluetooth behaviour.

## Why a bridge app instead of localhost

The production site runs on HTTPS. Chrome refuses to let an HTTPS page call
`http://127.0.0.1:9100` (mixed content), and thermal printers do not speak HTTPS.
So the web app cannot reach a LAN printer directly, on any browser, on any OS.

The queue-and-poll design works around it:

```
kasir web app (HTTPS)
        │  order saved / marked paid
        ▼
   MySQL print_jobs  ──── polled over HTTPS ────►  Android Print Bridge APK
                                                          │ raw TCP :9100
                                                          ▼
                                        kasir / dapur / minuman printers
```

Only the Android app talks to the printers, and it does so over plain TCP on the
local network, which has no mixed-content restriction.

## Hardware

- ESC/POS thermal printer with a network port (Epson TM-T82X/T88, Xprinter XP-N160II,
  Rongta RP80xx and most 58 mm / 80 mm clones).
- Printer set to a **static IP** or a DHCP reservation on the router. If the IP moves,
  printing stops until the app is updated.
- Default raw printing port is **9100**.
- One Android phone or tablet on the same Wi-Fi runs the bridge app. The kitchen and
  drinks stations do **not** need their own device — they can be printer-only.

Find a printer's IP by holding FEED while powering on; most models print a self-test
slip with the IP address.

## Turning it on

1. Owner → Settings → **Print Bridge (printer Wi-Fi / LAN)**.
2. Tick **Enable Print Bridge** and save. A device token is generated.
3. Install the APK (see `apps/print-bridge/README.md`) on one tablet.
4. In the app: paste the token, set `https://tabletap.my` as the server, enter each
   printer IP with port 9100, then tap **Start**.
5. Back in Owner Settings, use **Test print per station** to confirm.

## What gets queued

| Trigger | Job type | Station |
| --- | --- | --- |
| New order saved (QR, cafe, or staff order) | `kitchen` | one ticket per station + ticket group |
| Order marked paid (kasir) | `receipt` | `kasir` |
| Delivery payment confirmed | `receipt` (+ held kitchen tickets) | `kasir` / stations |
| Split bill paid | `receipt` | `kasir` |
| Owner test button | `test` | chosen station |

Kitchen tickets group exactly like the Bluetooth path: one ticket per station, split
further by menu category for counters that handle several ticket types (e.g. a drinks
counter that also plates Western food).

Receipts respect the existing **Auto-print receipt when marked paid** setting. Beep
counts come from the same printer settings the Bluetooth path uses.

## Data model

`print_jobs`

| Column | Notes |
| --- | --- |
| `shop_id`, `station_kod` | routing |
| `jenis` | `kitchen` / `receipt` / `test` |
| `order_id` | used for idempotency — an order is queued once |
| `payload_format` | `json` today; `escpos_base64` reserved for pre-rendered bytes |
| `payload` | MEDIUMTEXT, structured ticket JSON |
| `status` | `pending` → `printing` → `done` / `failed` |
| `attempts`, `last_error` | retry bookkeeping |
| `claim_token`, `claimed_at` | atomic hand-off to one device |

Index `(shop_id, status, id)` drives the claim query.

Payloads are **structured JSON**, not raw ESC/POS — the binary builder lives only in
the Flutter app, so there is no duplicated byte-layout code in PHP.

```json
{
  "type": "kitchen",
  "station": "dapur",
  "station_name": "Dapur",
  "ticket_label": "MINUMAN",
  "shop_name": "Warung Pak Mat",
  "order_id": 1281,
  "table": "5",
  "serve": "Makan sini",
  "guest": "Ali",
  "time": "2026-09-10 12:31:04",
  "beep": 4,
  "lang": "my",
  "labels": { "table": "Meja", "order": "Order", "kitchen_ticket": "TIKET DAPUR" },
  "items": [{ "qty": 2, "name": "Nasi Lemak Ayam", "note": "Kurang pedas" }]
}
```

## API

All three endpoints authenticate with the header `X-Print-Bridge-Token: <token>`
(the token is also accepted as `?token=` for quick curl checks). The shop must have
`print_bridge_enabled = 1` and `status = 'aktif'`.

### `GET /public/api/print_bridge/claim.php?stations=kasir,dapur,minuman&limit=5`

Atomically flips up to `limit` pending jobs to `printing` and returns them. Two
devices polling the same shop never receive the same job.

```json
{ "ok": true, "shop_id": 3, "jobs": [ { "id": 91, "station": "dapur", "jenis": "kitchen", "payload": { } } ] }
```

### `POST /public/api/print_bridge/ack.php`

```json
{ "jobs": [ { "id": 91, "ok": true }, { "id": 92, "ok": false, "error": "connection timed out" } ] }
```

Failed jobs return to `pending` until 5 attempts, then become `failed`.

### `GET /public/api/print_bridge/heartbeat.php`

Token check + station list + queue counters. Used by the app's **Check token** button.

Two more endpoints use the normal staff session (same-origin, kasir/owner only):
`admin/api/print_bridge_test.php` queues a test ticket, and
`admin/api/print_bridge_receipt.php` re-queues a receipt from the kasir screen.

## Reliability

- A job stuck in `printing` for more than 120 s is requeued on the next claim.
- Each claim increments `attempts`; after 5 the job is marked `failed` and left alone.
- `done` / `failed` jobs older than 48 h are purged in small batches during idle polls.
- Enqueue failures are swallowed — an order is never blocked because a printer is down.

## Troubleshooting

**Nothing prints, queue keeps growing.**
The bridge app is not polling. Check that it is open and shows AKTIF, and that Owner
Settings shows a recent "Bridge app last seen".

**"no printer mapped for dapur" in the app log.**
That station has no IP or its switch is off in app Settings. Unmapped stations fall
back to the kitchen printer, then the cashier printer, so at least one must be set.

**Printer prints garbage.**
Wrong protocol — the printer is probably in ESC/POS-over-HTTP or a vendor SDK mode.
Confirm raw port 9100 works: `nc <printer-ip> 9100 < /dev/null` should connect.

**Connection timed out.**
Tablet and printer are on different subnets or the router has client isolation
("AP isolation") on. Put both on the same SSID with isolation off.

**Prints twice.**
Two devices are running the bridge with the same token and overlapping stations. That
is safe (claims are atomic) unless both map the same station to the same printer —
give each device a distinct set of stations.

**Non-ASCII characters print as `?`.**
Expected on MVP: bytes are sent as single-byte Latin-1. Keep menu names ASCII-ish, or
extend the code page selection in `apps/print-bridge/lib/src/escpos.dart`.

**Token leaked.**
Owner → Settings → tick **Generate a new token on save**. The old token stops working
immediately; update the app afterwards.

## Migration

`includes/schema_patch.php` creates `print_jobs` and the three `shops` columns
automatically on the next request. For hosts that cannot `ALTER`, import
`database/migrate_print_bridge.sql` in phpMyAdmin.
