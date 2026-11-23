# Guest token & privasi order

## Table / dine-in

- `guest_token` pada order (jika kolom wujud)  
- Confirmation URL boleh bawa `gt=` supaya pelanggan nampak order sendiri  

## Delivery

- Isolasi kuat: filter `guest_token` dan/atau `customer_email`  
- Jangan senaraikan semua order meja “Delivery” kepada setiap customer  

## Amalan

- Jangan kongsi URL confirmation tanpa token di channel awam  
- Staff dashboard kekal auth session — berbeza dari link pelanggan  
