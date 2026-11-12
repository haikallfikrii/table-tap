# Fear ad v3 — NEW angles (image → video)

Stills di folder ini. **Jangan recycle** clip Downloads lama.

| File | Angle | Motion prompt (EN for Kling/Flow) |
|------|-------|-----------------------------------|
| `v3-G-exterior-wide.png` | Wide exterior night | Slow push-in toward the doorway silhouette, light rain shimmer on wet street, subtle handheld, 5s |
| `v3-A-lowangle-owner.png` | Low angle owner stress | Camera slowly rises, owner breathes heavy, paper slips flutter, staff blur behind, 5s |
| `v3-D-dutch-rush.png` | Dutch rush waitress | Fast slight pan following waitress rush, plates wobble, background motion blur, 5s |
| `v3-B-overshoulder-leave.png` | OTS customer leave | Customer stands and walks toward door, chair scrapes, waiter apologetic gesture, 5s |
| `v3-C-topdown-chaos.png` | Top-down papers/cash | Slow orbit/rotate over messy receipts and Ringgit, papers shift slightly, 5s |
| `v3-E-macro-qr.png` | Macro QR scan | Thumb taps, phone focus racks to QR, soft UI glow, 5s |
| `v3-F-profile-kitchen.png` | Profile kitchen tablet | Owner nods slightly, tablet scroll glow, steam drifts, relieved breath, 5s |

Suggested edit order (≈30s): **G → A → B → E → F** (+ end card)  
VO: reuse dense northern script in `vo/script-utara.txt`

## Flow (Veo)
Upload still as Ingredient / first frame → Ingredients to Video → paste STYLE LOCK + motion line above + AUDIO lock from `docs/marketing/flow-ad-fear-30s.md`

## Kling via fal (saya generate bila ada key)
```bash
# di .env:
FAL_KEY=fal_xxxxxxxx

./scripts/generate-kling-v3.sh
```
