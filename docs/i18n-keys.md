# i18n keys

Fail: `includes/lang/my.php` + `en.php` (sentiasa berpasangan).

## Amalan

1. Tambah key di **kedua-dua** bahasa  
2. Page PHP pass subset ke JS via `data-i18n` JSON  
3. Elak hardcode string BM di JS kasir/order  

## Kumpulan kerap

- Order: `order_failed`, `order_network_error`, `order_rate_limited`  
- Bayar: `mark_paid`, `cash_*`, `cod_*`, `proof_*`  
- Landing: `lp_*`  
- Printer: `printer_*`, `autoprint_*`  

Selepas tambah key kasir/order: soft refresh tablet (asset `?v=filemtime`).
