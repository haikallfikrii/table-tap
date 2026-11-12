#!/usr/bin/env bash
# Fear ad v2 — UNUSED clips + dense northern VO (no dead air)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/clips/src/v2"
VO="$ROOT/vo"
EXPORT="$ROOT/export"
END="$EXPORT/endcard-cuba-percuma-logo-1080.png"
OUT="$EXPORT/tabletap-fear-30s-v2.mp4"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

W=1080; H=1920; FPS=30

# Exact VO durations — video trimmed to VO (NO silence pad)
D1=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$VO/01.wav")
D2=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$VO/02.wav")
D3=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$VO/03.wav")
D4=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$VO/04.wav")
DE=2.5
echo "durs: $D1 $D2 $D3 $D4 +$DE end"

python3 - "$TMP" <<'PY'
import sys
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont
tmp = Path(sys.argv[1])
W, H = 1080, 1920
subs = [
    ("subA.png", "Peak malam · order atas kertas · customer marah"),
    ("subB.png", "Review 1★ · customer hilang · duit hilang"),
    ("subC.png", "Scan QR · pesan sendiri · dapur nampak"),
    ("subD.png", "Cuba percuma · WhatsApp tabletap.my"),
]
font = None
for c in ["/System/Library/Fonts/Supplemental/Arial Bold.ttf",
          "/System/Library/Fonts/Supplemental/Arial.ttf",
          "/System/Library/Fonts/Helvetica.ttc"]:
    if Path(c).exists():
        try:
            font = ImageFont.truetype(c, 50); break
        except Exception:
            pass
if font is None:
    font = ImageFont.load_default()
for name, text in subs:
    img = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    max_w = W - 100
    words = text.split(); lines=[]; cur=""
    for w in words:
        trial = (cur+" "+w).strip()
        if draw.textbbox((0,0), trial, font=font)[2] > max_w and cur:
            lines.append(cur); cur=w
        else:
            cur=trial
    if cur: lines.append(cur)
    line_h=66; block_h=line_h*len(lines)+32
    y0=H-200-block_h
    bw=min(W-60, max(draw.textbbox((0,0), ln, font=font)[2] for ln in lines)+48)
    bx=(W-bw)//2
    draw.rounded_rectangle([bx,y0,bx+bw,y0+block_h], radius=16, fill=(0,0,0,165))
    y=y0+16
    for ln in lines:
        tw=draw.textbbox((0,0), ln, font=font)[2]
        draw.text(((W-tw)//2, y), ln, font=font, fill=(255,255,255,255)); y+=line_h
    img.save(tmp/name)
print("subs ok")
PY

# Copy wavs as-is (already dense, no pad)
cp "$VO/01.wav" "$TMP/voA.wav"
cp "$VO/02.wav" "$TMP/voB.wav"
cp "$VO/03.wav" "$TMP/voC.wav"
cp "$VO/04.wav" "$TMP/voD.wav"
ffmpeg -y -f lavfi -i anullsrc=r=44100:cl=mono -t "$DE" "$TMP/voE.wav" 2>/dev/null

norm() {
  local src="$1" out="$2" dur="$3" start="$4" sub="$5" vow="$6"
  # If source shorter than dur, tpad freeze last frame
  local srcdur
  srcdur=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$src")
  local vf="scale=${W}:${H}:force_original_aspect_ratio=increase,crop=${W}:${H},fps=${FPS},setsar=1"
  if python3 -c "import sys; sys.exit(0 if float('$srcdur')+0.05 < float('$dur') else 1)"; then
    # freeze-pad video to match VO
    ffmpeg -y -ss "$start" -i "$src" -loop 1 -i "$sub" -i "$vow" \
      -filter_complex "[0:v]${vf},tpad=stop_mode=clone:stop_duration=$(python3 -c "print(max(0,float('$dur')-float('$srcdur')+float('$start')))"),format=yuva420p[v0];[1:v]fps=${FPS},format=yuva420p[s];[v0][s]overlay=0:0,format=yuv420p[v]" \
      -map "[v]" -map 2:a -t "$dur" \
      -c:v libx264 -preset fast -crf 18 -c:a aac -b:a 192k \
      "$out"
  else
    ffmpeg -y -ss "$start" -i "$src" -loop 1 -i "$sub" -i "$vow" -t "$dur" \
      -filter_complex "[0:v]${vf},format=yuva420p[v0];[1:v]fps=${FPS},format=yuva420p[s];[v0][s]overlay=0:0:shortest=1,format=yuv420p[v]" \
      -map "[v]" -map 2:a \
      -c:v libx264 -preset fast -crf 18 -c:a aac -b:a 192k -shortest \
      "$out"
  fi
}

# NEW clips (not used in previous moving cut)
norm "$SRC/A-handle.mp4" "$TMP/A.mp4" "$D1" 0.2 "$TMP/subA.png" "$TMP/voA.wav"
norm "$SRC/B-leave.mp4"  "$TMP/B.mp4" "$D2" 0.2 "$TMP/subB.png" "$TMP/voB.wav"
norm "$SRC/D1-plate.mp4" "$TMP/C.mp4" "$D3" 0.5 "$TMP/subC.png" "$TMP/voC.wav"
norm "$SRC/D2-phone.mp4" "$TMP/D.mp4" "$D4" 1.0 "$TMP/subD.png" "$TMP/voD.wav"

ffmpeg -y -loop 1 -i "$END" -i "$TMP/voE.wav" -t "$DE" \
  -vf "scale=${W}:${H}:force_original_aspect_ratio=increase,crop=${W}:${H},fps=${FPS},format=yuv420p" \
  -c:v libx264 -preset fast -crf 18 -c:a aac -b:a 192k -shortest \
  "$TMP/E.mp4" 2>/dev/null

cat > "$TMP/list.txt" <<EOF
file '$TMP/A.mp4'
file '$TMP/B.mp4'
file '$TMP/C.mp4'
file '$TMP/D.mp4'
file '$TMP/E.mp4'
EOF

ffmpeg -y -f concat -safe 0 -i "$TMP/list.txt" \
  -c:v libx264 -preset fast -crf 18 -c:a aac -b:a 192k -movflags +faststart \
  "$OUT"

echo "OK → $OUT"
ffprobe -hide_banner "$OUT" 2>&1 | head -14
ls -lh "$OUT"
