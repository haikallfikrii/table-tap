#!/usr/bin/env bash
# Image-to-video via fal Kling — NEW v3 angles
# Requires: FAL_KEY in assets/.../fear-30s/.env
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SHOTS="$ROOT/shots/v3"
OUT="$ROOT/clips/v3"
mkdir -p "$OUT"

if [[ -f "$ROOT/.env" ]]; then
  set -a; # shellcheck disable=SC1091
  source "$ROOT/.env"; set +a
fi
if [[ -z "${FAL_KEY:-}" ]]; then
  echo "Missing FAL_KEY"
  echo "1) Buka https://fal.ai/dashboard/keys  → Create key"
  echo "2) echo 'FAL_KEY=fal_xxx' > $ROOT/.env"
  echo "3) Jalankan semula: $0"
  exit 1
fi

PY="$ROOT/.venv/bin/python"
[[ -x "$PY" ]] || python3 -m venv "$ROOT/.venv"
"$ROOT/.venv/bin/pip" install -q fal-client

export ROOT FAL_KEY
"$PY" <<'PY'
import pathlib, urllib.request, fal_client, os

root = pathlib.Path(os.environ["ROOT"])
shots = root / "shots" / "v3"
out = root / "clips" / "v3"
out.mkdir(parents=True, exist_ok=True)

jobs = [
  ("v3-G-exterior-wide.png", "Slow cinematic push-in toward Malay cafe doorway at rainy night, wet street reflections shimmer, subtle handheld, photorealistic"),
  ("v3-A-lowangle-owner.png", "Low angle: stressed Malay restaurant owner breathes heavily, paper order slips flutter in hands, staff blur behind, slow rise, photorealistic"),
  ("v3-B-overshoulder-leave.png", "Over-shoulder: frustrated customer stands and walks to exit, chair scrapes, apologetic waiter gestures, warm cafe night, photorealistic"),
  ("v3-E-macro-qr.png", "Macro: thumb moves, phone focuses on table QR tent card, soft orange UI glow, shallow depth, photorealistic"),
  ("v3-F-profile-kitchen.png", "Side profile: Malay owner checks kitchen tablet, slight nod of relief, steam drifts, warm light, photorealistic"),
]

MODEL = "fal-ai/kling-video/v2.1/pro/image-to-video"

for fname, prompt in jobs:
    img = shots / fname
    print("→", fname)
    url = fal_client.upload_file(str(img))
    result = fal_client.subscribe(
        MODEL,
        arguments={
            "prompt": prompt,
            "image_url": url,
            "duration": "5",
            "aspect_ratio": "9:16",
            "negative_prompt": "blurry, text overlay, logo, watermark, cartoon, deformed hands",
        },
        with_logs=True,
    )
    video_url = result["video"]["url"]
    dest = out / fname.replace(".png", ".mp4")
    urllib.request.urlretrieve(video_url, dest)
    print("  saved", dest)

print("DONE — reply 'clips v3 ready' untuk saya edit final")
PY
