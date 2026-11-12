#!/usr/bin/env bash
# Assemble fear-30s ad after Flow clips exist.
# Usage: ./scripts/assemble.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CLIPS="$ROOT/clips"
EXPORT="$ROOT/export"
END="$EXPORT/endcard-cuba-percuma-logo-1080.png"
OUT="$EXPORT/tabletap-fear-30s.mp4"

for f in A B C D; do
  if [[ ! -f "$CLIPS/$f.mp4" ]]; then
    echo "Missing $CLIPS/$f.mp4 — generate in Flow first."
    exit 1
  fi
done

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# Normalize each clip to 1080x1920 @ 30fps
for f in A B C D; do
  ffmpeg -y -i "$CLIPS/$f.mp4" \
    -vf "scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,fps=30,format=yuv420p" \
    -c:v libx264 -preset fast -crf 18 -c:a aac -b:a 192k \
    "$TMP/$f.mp4"
done

# End card hold 2.5s (mute)
ffmpeg -y -loop 1 -i "$END" -t 2.5 \
  -vf "scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,fps=30,format=yuv420p" \
  -c:v libx264 -preset fast -crf 18 -an \
  "$TMP/E.mp4"

# Concat list
cat > "$TMP/list.txt" <<EOF
file '$TMP/A.mp4'
file '$TMP/B.mp4'
file '$TMP/C.mp4'
file '$TMP/D.mp4'
file '$TMP/E.mp4'
EOF

ffmpeg -y -f concat -safe 0 -i "$TMP/list.txt" -c copy "$OUT"
echo "Wrote $OUT"
ffprobe -hide_banner "$OUT" 2>&1 | head -20
