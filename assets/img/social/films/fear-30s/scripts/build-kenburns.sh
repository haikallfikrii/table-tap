#!/usr/bin/env bash
# Build fear-30s Ken Burns draft from stills + Amira VO + end card + subtitle overlays.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SHOTS="$ROOT/shots"
VO="$ROOT/vo"
EXPORT="$ROOT/export"
END="$EXPORT/endcard-cuba-percuma-logo-1080.png"
OUT="$EXPORT/tabletap-fear-30s.mp4"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

W=1080
H=1920
FPS=30
DUR_A=7
DUR_B=7
DUR_C=8
DUR_D=5
DUR_E=3

# Make subtitle PNGs into TMP via python
python3 - "$TMP" <<'PY'
import sys
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

tmp = Path(sys.argv[1])
W, H = 1080, 1920
subs = [
    ("subA.png", "Peak malam… takut order salah."),
    ("subB.png", "Takut customer tak datang balik."),
    ("subC.png", "Scan QR · pesan sendiri · dapur nampak"),
    ("subD.png", "Cuba percuma · WhatsApp sekarang"),
]
# Prefer system fonts that exist on macOS
candidates = [
    "/System/Library/Fonts/Supplemental/Arial Bold.ttf",
    "/System/Library/Fonts/Supplemental/Arial.ttf",
    "/Library/Fonts/Arial.ttf",
    "/System/Library/Fonts/Helvetica.ttc",
]
font = None
for c in candidates:
    p = Path(c)
    if p.exists():
        try:
            font = ImageFont.truetype(str(p), 54)
            break
        except Exception:
            continue
if font is None:
    font = ImageFont.load_default()

for name, text in subs:
    img = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    # wrap roughly
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
    # dark translucent bar
    pad = 28
    # measure block width
    widths = [draw.textbbox((0, 0), ln, font=font)[2] for ln in lines]
    bw = min(W - 80, max(widths) + pad * 2)
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

pad_vo() {
  local in="$1" out="$2" dur="$3"
  ffmpeg -y -i "$in" -af "apad=whole_dur=${dur}" -t "$dur" -ar 44100 -ac 1 "$out"
}

pad_vo "$VO/01.wav" "$TMP/voA.wav" "$DUR_A"
pad_vo "$VO/02.wav" "$TMP/voB.wav" "$DUR_B"
pad_vo "$VO/03.wav" "$TMP/voC.wav" "$DUR_C"
pad_vo "$VO/04.wav" "$TMP/voD.wav" "$DUR_D"
ffmpeg -y -f lavfi -i anullsrc=r=44100:cl=mono -t "$DUR_E" "$TMP/voE.wav"

make_kb() {
  local img="$1" out="$2" dur="$3" vo="$4" zoom_dir="$5" sub="$6"
  local frames=$((dur * FPS))
  local zexpr
  if [[ "$zoom_dir" == "in" ]]; then
    zexpr="min(1.12,1+0.12*on/${frames})"
  else
    zexpr="max(1.0,1.12-0.12*on/${frames})"
  fi
  ffmpeg -y -loop 1 -i "$img" -loop 1 -i "$sub" -i "$vo" -t "$dur" \
    -filter_complex "[0:v]scale=$((W*2)):$((H*2)):force_original_aspect_ratio=increase,crop=$((W*2)):$((H*2)),zoompan=z='${zexpr}':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=${frames}:s=${W}x${H}:fps=${FPS},format=yuva420p[base];[1:v]fps=${FPS},format=yuva420p[sub];[base][sub]overlay=0:0:shortest=1,format=yuv420p[v]" \
    -map "[v]" -map 2:a -c:v libx264 -preset fast -crf 18 -c:a aac -b:a 192k -shortest \
    "$out"
}

make_kb "$SHOTS/A.png" "$TMP/A.mp4" "$DUR_A" "$TMP/voA.wav" "in" "$TMP/subA.png"
make_kb "$SHOTS/B.png" "$TMP/B.mp4" "$DUR_B" "$TMP/voB.wav" "in" "$TMP/subB.png"
make_kb "$SHOTS/C.png" "$TMP/C.mp4" "$DUR_C" "$TMP/voC.wav" "in" "$TMP/subC.png"
make_kb "$SHOTS/D.png" "$TMP/D.mp4" "$DUR_D" "$TMP/voD.wav" "out" "$TMP/subD.png"

ffmpeg -y -loop 1 -i "$END" -i "$TMP/voE.wav" -t "$DUR_E" \
  -vf "scale=${W}:${H}:force_original_aspect_ratio=increase,crop=${W}:${H},fps=${FPS},format=yuv420p" \
  -c:v libx264 -preset fast -crf 18 -c:a aac -b:a 192k -shortest \
  "$TMP/E.mp4"

cat > "$TMP/list.txt" <<EOF
file '$TMP/A.mp4'
file '$TMP/B.mp4'
file '$TMP/C.mp4'
file '$TMP/D.mp4'
file '$TMP/E.mp4'
EOF

ffmpeg -y -f concat -safe 0 -i "$TMP/list.txt" -c copy "$OUT"
echo "OK → $OUT"
ffprobe -hide_banner "$OUT" 2>&1 | head -16
ls -lh "$OUT"
