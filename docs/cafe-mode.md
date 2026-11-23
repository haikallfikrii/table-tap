# Cafe mode (QR kaunter)

Untuk kedai tanpa meja bertoken tetap — customer ambil QR/session di kaunter.

## Ciri

- `ordering_mode = cafe`  
- Session token + optional OTP e-mel/telefon (`cafe_verify`)  
- Order terikat `session_id`  
- Rate limit per session (bukan meja fizikal)  

## Ops

- Pastikan e-mel/SMTP OK jika verify = email  
- `verify = none` hanya untuk ujian — risiko spam  
- Track URL selepas order: confirmation / session track  

Lihat juga migrasi `database/migrate_cafe_mode.sql`.
