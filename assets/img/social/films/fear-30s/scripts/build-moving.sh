#!/usr/bin/env bash
# Assemble REAL moving Flow clips → fear 30s ad (9:16) + Amira VO + subs + end card
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/clips/src"
VO="$ROOT/vo"
EXPORT="$ROOT/export"
END="$EXPORT/endcard-cuba-percuma-logo-1080.png"
OUT="$EXPORT/tabletap-fear-30s-moving.mp4"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

W=1080
H=1920
FPS=30

# Copy extra source if needed
DL="/Users/khalfikri/Downloads"
[[ -f "$SRC/A-busy.mp4" ]] || cp "$DL/Waiter_working_in_restaurant_202608191223.mp4" "$SRC/A-busy.mp4"
[[ -f "$SRC/B-mistake.mp4" ]] || cp "$DL/Waiter_realizing_mistake_in_cafe_202608191322.mp4" "$SRC/B-mistake.mp4"
[[ -f "$SRC/C1-qr.mp4" ]] || cp "$DL/Hands_scanning_QR_tent_202608200106.mp4" "$SRC/C1-qr.mp4"
[[ -f "$SRC/C2-kitchen.mp4" ]] || cp "$DL/Chef_glancing_at_kitchen_tablet_202608200108.mp4" "$SRC/C2-kitchen.mp4"
[[ -f "$SRC/D-cta.mp4" ]] || cp "$DL/Owner_reading_phone_with_CTA_202608200111.mp4" "$SRC/D-cta.mp4"

# Subtitle overlays
python3 - "$TMP" <<'PY'
import sys
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont
tmp = Path(sys.argv[1])
W, H = 1080, 1920
subs = [
    ("subA.png", "Malam ramai… takut order tu salah."),
    ("subB.png", "Takut customer marah… tak mai balik."),
    ("subC.png", "Scan QR · pesan sendiri · dapur nampak"),
    ("subD.png", "Cuba percuma ja · WhatsApp sekarang"),
]
font = None
for c in [
    "/System/Library/Fonts/Supplemental/Arial Bold.ttf",
    "/System/Library/Fonts/Supplemental/Arial.ttf",
    "/System/Library/Fonts/Helvetica.ttc",
]:
    if Path(c).exists():
        try:
            font = ImageFont.truetype(c, 54)
            break
        except Exception:
            pass
if font is None:
    font = ImageFont.load_default()

for name, text in subs:
    img = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    max_w = W - 120
    words = text.split()
    lines, cur = [], ""
    for w in words:
        trial = (cur + " " + w).strip()
        bbox = draw.textbbox((0, 0), trial, font=font)
        if bbox[2] - bbox[0] > max_w and cur:
            lines.append(cur)
            cur = w
        else:
            cur = trial
    if cur:
        lines.append(cur)
    line_h = 70
    block_h = line_h * len(lines) + 36
    y0 = H - 220 - block_h
    widths = [draw.textbbox((0, 0), ln, font=font)[2] for ln in lines]
    bw = min(W - 80, max(widths) + 56)
    bx = (W - bw) // 2
    draw.rounded_rectangle([bx, y0, bx + bw, y0 + block_h], radius=18, fill=(0, 0, 0, 160))
    y = y0 + 18
    for ln in lines:
        bbox = draw.textbbox((0, 0), ln, font=font)
        tw = bbox[2] - bbox[0]
        draw.text(((W - tw) // 2, y), ln, font=font, fill=(255, 255, 255, 255))
        y += line_h
    img.save(tmp / name)
print("subs ok")
PY

norm_clip() {
  # $1 src $2 out $3 dur $4 start $5 subpng $6 vowav
  local src="$1" out="$2" dur="$3" start="$4" sub="$5" vow="$6"
  ffmpeg -y -ss "$start" -i "$src" -loop 1 -i "$sub" -i "$vow" -t "$dur" \
    -filter_complex "[0:v]scale=${W}:${H}:force_original_aspect_ratio=increase,crop=${W}:${H},fps=${FPS},setsar=1,format=yuva420p[v0];[1:v]fps=${FPS},format=yuva420p[s];[v0][s]overlay=0:0:shortest=1,format=yuv420p[v]" \
    -map "[v]" -map 2:a \
    -c:v libx264 -preset fast -crf 18 -c:a aac -b:a 192k -shortest \
    "$out"
}

# Pad VO to segment lengths
pad_vo() { ffmpeg -y -i "$1" -af "apad=whole_dur=$3" -t "$3" -ar 44100 -ac 1 "$2"; }
pad_vo "$VO/01.wav" "$TMP/voA.wav" 7
pad_vo "$VO/02.wav" "$TMP/voB.wav" 7
# C = QR 4s + kitchen 4s, use same VO pad 8s split across both visually
pad_vo "$VO/03.wav" "$TMP/voC.wav" 8
pad_vo "$VO/04.wav" "$TMP/voD.wav" 5
ffmpeg -y -f lavfi -i anullsrc=r=44100:cl=mono -t 3 "$TMP/voE.wav"

# Split VO C into two 4s chunks for C1/C2
ffmpeg -y -i "$TMP/voC.wav" -t 4 "$TMP/voC1.wav"
ffmpeg -y -i "$TMP/voC.wav" -ss 4 -t 4 "$TMP/voC2.wav"

norm_clip "$SRC/A-busy.mp4" "$TMP/A.mp4" 7 0.5 "$TMP/subA.png" "$TMP/voA.wav"
norm_clip "$SRC/B-mistake.mp4" "$TMP/B.mp4" 7 0.3 "$TMP/subB.png" "$TMP/voB.wav"
norm_clip "$SRC/C1-qr.mp4" "$TMP/C1.mp4" 4 1.0 "$TMP/subC.png" "$TMP/voC1.wav"
norm_clip "$SRC/C2-kitchen.mp4" "$TMP/C2.mp4" 4 1.5 "$TMP/subC.png" "$TMP/voC2.wav"
norm_clip "$SRC/D-cta.mp4" "$TMP/D.mp4" 5 1.0 "$TMP/subD.png" "$TMP/voD.wav"

ffmpeg -y -loop 1 -i "$END" -i "$TMP/voE.wav" -t 3 \
  -vf "scale=${W}:${H}:force_original_aspect_ratio=increase,crop=${W}:${H},fps=${FPS},format=yuv420p" \
  -c:v libx264 -preset fast -crf 18 -c:a aac -b:a 192k -shortest \
  "$TMP/E.mp4"

# Re-encode concat to fix DTS (avoid -c copy issues)
cat > "$TMP/list.txt" <<EOF
file '$TMP/A.mp4'
file '$TMP/B.mp4'
file '$TMP/C1.mp4'
file '$TMP/C2.mp4'
file '$TMP/D.mp4'
file '$TMP/E.mp4'
EOF

ffmpeg -y -f concat -safe 0 -i "$TMP/list.txt" \
  -c:v libx264 -preset fast -crf 18 -c:a aac -b:a 192k -movflags +faststart \
  "$OUT"

echo "OK → $OUT"
ffprobe -hide_banner "$OUT" 2>&1 | head -16
ls -lh "$OUT"
