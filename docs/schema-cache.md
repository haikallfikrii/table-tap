# Schema cache (performance)

`includes/db.php` menulis snapshot kolom ke `storage/cache/schema-*.json` selepas `ensureAppSchema`.

## Kenapa

Elak puluhan `SHOW COLUMNS` pada setiap request (poll kasir/dapur + order) di shared hosting.

## Ops

- Cache key ikut `filemtime(schema_patch.php)` + db name  
- Patch gagal → retry selepas ~10 minit (`ok: false`)  
- Jangan commit isi `storage/cache/` (gitignored)  
- Selepas migrate manual besar: hapus cache server atau touch `schema_patch.php`  

Lihat juga `docs/troubleshooting-order-fail.md`.
