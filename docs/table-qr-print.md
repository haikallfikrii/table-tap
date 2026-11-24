# Cetak QR meja

## Sumber

- Token per meja dari DB (`tables.token_akses`)  
- URL: `public/order.php?meja=N&token=...`  
- Kit client: `clients/<slug>/` (design HTML + PNG QR)  

## Checklist cetak

- [ ] Saiz akrilik / tent betul (cth Big Tree 10×14.8 cm)  
- [ ] Duplex / potong A4 ikut fail print helper  
- [ ] Uji scan 1 meja sebelum mass print  
- [ ] Meja bonus / ekstra: generate token baharu, jangan copy QR lama  

Jangan commit dump foto WhatsApp mentah; PNG QR curated OK.
