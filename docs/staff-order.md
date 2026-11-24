# Staff order (key-in)

Dari kasir/waiter: `admin/staff_order.php` (popup dari kasir supaya printer BT kekal).

## Guna untuk

- Grab / Foodpanda / telefon order  
- Pelanggan tak mahu scan  
- Meja tanpa HP  

## Nota penting

- `sumber_order = staf` — **tidak** kena burst limit pelanggan  
- Client retry jika rangkaian goyah  
- Selepas submit berjaya: redirect confirmation; popup boleh tutup dan bawa opener  

Jangan guna QR pelanggan untuk key-in staf jika nak elak double-submit dari meja yang sama.
