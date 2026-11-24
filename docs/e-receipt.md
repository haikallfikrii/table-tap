# E-resit

## Alur

1. Order lunas  
2. Owner/kasir hantar e-resit (e-mel) jika SMTP configured  
3. Atau pelanggan buka resit browser / thermal print  

## Ops

- Semak SMTP di `config.php` (server sahaja)  
- Gagal hantar → mesej `receipt_send_failed` — jangan anggap order tak lunas  
- Split bill: resit ikut order pecahan (`split_from` pada thermal)  

API berkaitan: `admin/api/send_receipt.php`, `admin/receipt.php`.
