# Printer kasir — tip pantas

## Bluetooth (Chrome)

1. Buka kasir di **Chrome/Edge** (bukan IG in-app browser)  
2. Connect printer → test print  
3. Biarkan tab kasir hidup; staff order buka **popup** supaya BT tak putus  
4. Auto-reconnect jalan bila tab focus balik  

## Masalah biasa

| Isu | Cuba |
|-----|------|
| Connect gagal | Reset BT telefon/tablet, pairing printer semula |
| Putus lepas staff order | Pastikan staff order popup, bukan navigate full page |
| Font pelik / kosong | Elak emoji pelik di nama menu; test item ASCII |
| Drawer tak buka | Pref `kasir_open_drawer` + printer support kick |

## Wi‑Fi Print Bridge

Lihat [`print-bridge.md`](print-bridge.md) + app `apps/print-bridge/`.  
Boleh jalan bersama Bluetooth.
