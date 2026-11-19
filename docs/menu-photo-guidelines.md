# Garis panduan foto menu

## Ambil gambar

- Cahaya cukup (elak bayang gelap di atas makanan)  
- 1 hidangan utama per frame  
- Sudut 45° atau top-down konsisten dalam satu kedai  
- Latarbelakang meja/kedai bersih  

## Untuk TableTap

- Prefer JPG &lt; 1.5MB selepas compress  
- Nama fail: `slug-menu-pendek.jpg`  
- Simpan curated di `reco/` atau upload owner panel — **jangan** commit dump WhatsApp Mentah  
- Elak watermark / logo orang lain  

## Seed / refresh

Big Tree: `cron/seed_big_tree_western.php` (secret) — re-run idempotent untuk sync katalog + foto curated.
