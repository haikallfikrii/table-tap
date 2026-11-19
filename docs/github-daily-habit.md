# Habit harian supaya GitHub aktif (cara betul)

Kotak hijau = **commit nyata** ke default branch (`main`) dengan email yang sama dengan akaun GitHub.

## Setiap hari (pilih ≥1)

- Kemas 1 FAQ / tip support  
- Caption Reel atau pre-filled WA  
- Nota bug kedai → fail di `docs/`  
- Update matrix Ads / log keputusan minggu  
- Betul typo docs  

Lalu:

```bash
git add -A   # hanya fail yang dimaksud
git commit -m "Ringkas: kenapa perubahan ini."
git push origin main
```

## Jangan

- Commit kosong / `--allow-empty` hanya untuk grafik  
- Backdate commit palsu  
- Push secrets / `leads/` telefon  

## Automation yang OK

- Minta agent Cursor **setiap hari**: “push beberapa commit docs/ops hari ini”  
- Checklist: [`daily-ops-checklist.md`](daily-ops-checklist.md)  

Production hanya di-update bila anda sengaja merge/ff `production`.
