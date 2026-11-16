# Troubleshooting — “Gagal hantar pesanan”

Pesan UI: `Gagal hantar pesanan. Cuba lagi.` / network error.

## Semak pantas (kedai)

1. Customer **refresh** atau scan QR semula  
2. Wi‑Fi / data telefon customer OK?  
3. Cuba meja lain — satu meja sahaja?  
4. Staff order dari kasir berjaya?

## Semak server (anda)

1. `admin/dbcheck.php?key=…` — DB connect + `now_php` vs `now_mysql`  
2. `storage/logs/order_fail.log` via dbcheck `log=1` (jika di-deploy)  
3. Resource Hostinger (entry processes / CPU) waktu peak  
4. Branch **production** sudah pull commit retry/schema-cache?

## Punca biasa

| Punca | Gejala | Tindakan |
|-------|--------|----------|
| Shared hosting penuh | Gagal ramai meja serentak | Schema cache + client retry; naik plan jika perlu |
| Rate limit meja | Mesej rate limited | Bayar/clear unpaid atau naik burst shop |
| Item habis / tidak aktif | Error item unavailable | Semak menu stok |
| QR/token salah | 403 invalid table | Cetak semula QR |
| Telefon wajib | phone_required | Isi nombor atau longgarkan setting shop |

## Jangan

- Jangan test POST order production dengan token rawak tanpa owner  
- Jangan commit `config.php` / cron secret  
