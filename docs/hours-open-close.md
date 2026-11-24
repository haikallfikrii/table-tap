# Jam buka / tutup kedai

Field shop: `hours_enabled`, `open_time`, `close_time`.

## Perilaku

- Jika hours off → sentiasa terima order (selagi shop aktif)  
- Overnight (cth 16:00–02:00): `close <= open` → window merentas tengah malam  
- Di luar jam → `shop_closed_hours` / 403 pada submit  

## Laporan

`shopBusinessDayBounds` dipakai closing harian supaya “hari perniagaan” ikut shift malam, bukan kalendar 00:00 sahaja.
