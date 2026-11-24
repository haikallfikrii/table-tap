# Bunyi dashboard (dapur / waiter / kasir)

## Mode (`sound_mode`)

| Mode | Makna |
|------|--------|
| `until_cleared` | Ulang sampai staf proses / clear |
| `count` | Ulang N kali (`sound_repeat_count`) |
| `duration` | Ulang selama N saat (`sound_duration_sec`) |

Interval: `sound_interval_ms` · Volume: `sound_volume`.

## Browser

- Staf mesti tekan **Aktifkan suara** sekali (autoplay policy)  
- Biarkan tab dashboard terbuka  
- Pref printer beep berasingan (`printer_beep_kasir` / `printer_beep_kitchen`)  

## Tip

Waiter-mode: bunyi pendek pada perubahan status (kurang agresif dari self-pickup ready loop).
