#!/usr/bin/env bash
# gen-photos.sh — generate the demo photo set for the Contact Sheet playground.
#
# Produces gradient + grain JPEGs (with a soft vignette) at varied aspect ratios
# so the theme's Photo Strip block and full-width single-post layout look like a
# real photo blog. Output: playground/assets/photos/*.jpg
#
# Re-runnable; overwrites. Requires ImageMagick (`convert`/`magick`).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$ROOT/assets/photos"
mkdir -p "$OUT"

if command -v magick >/dev/null 2>&1; then
  IM=(magick)
else
  IM=(convert)
fi

# make_photo <file> <WxH> <color1> <color2> [label-color]
make_photo() {
  local file="$1" size="$2" c1="$3" c2="$4"
  "${IM[@]}" -size "$size" \
    gradient:"$c1"-"$c2" \
    -blur 0x6 \
    +noise Gaussian -attenuate 0.1 \
    -vignette 0x40 \
    -quality 72 -sampling-factor 4:2:0 \
    "$OUT/$file"
  echo "  $file ($size)"
}

# Each row: filename, geometry, two gradient colors evoking the scene.
make_photo hello-world-1.jpg   1200x800   '#e8c98a' '#8a6a3a'   # sunlit ochre wall
make_photo hello-world-2.jpg   800x1200   '#c9a777' '#5b4326'   # coffee, marble bar
make_photo hello-world-3.jpg   900x900    '#9a9a95' '#3a3a38'   # cobblestone shadow
make_photo san-telmo-1.jpg     1200x800   '#d9a85a' '#7a5530'   # market stalls, warm
make_photo san-telmo-2.jpg     800x1200   '#2a2a2e' '#0c0c0e'   # bandoneón, dark
make_photo san-telmo-3.jpg     1024x768   '#caa46a' '#5e4628'   # old doorway, ochre
make_photo san-telmo-4.jpg     1200x800   '#e6d24a' '#9a8620'   # yellow tram on cobbles
make_photo san-telmo-5.jpg     900x900    '#b0a090' '#6a5f50'   # courtyard cat
make_photo san-telmo-6.jpg     1280x720   '#caa86a' '#3a2a44'   # dusk plaza
make_photo la-boca-1.jpg       800x1200   '#2a6bd0' '#0d2a6b'   # blue wall
make_photo la-boca-2.jpg       1200x800   '#d23838' '#e6d23a'   # red + yellow facades
make_photo la-boca-3.jpg       864x1152   '#3a2a44' '#caa86a'   # mural, streetlamp
make_photo la-boca-4.jpg       1280x720   '#1c2440' '#070b18'   # bridge at night
make_photo la-boca-5.jpg       900x900    '#7a1f3a' '#1a1a22'   # tango couple
make_photo riachuelo-1.jpg     1200x800   '#8a7a5a' '#3a3024'   # boats, low tide brown
make_photo riachuelo-2.jpg     1024x768   '#d23838' '#2a6bd0'   # conventillos colorful
make_photo riachuelo-3.jpg     1280x720   '#9a9080' '#403830'   # transboder bridge
make_photo riachuelo-4.jpg     1200x800   '#d9b070' '#4a3a2a'   # fisherman at dawn
make_photo studio-1.jpg        864x1152   '#e6dcc8' '#7a6a52'   # window light
make_photo studio-2.jpg        900x900    '#2a2a2e' '#101012'   # leica on a shelf
make_photo studio-3.jpg        1024x768   '#b8a888' '#5a4a38'   # studio chair + cat
make_photo studio-4.jpg        1200x800   '#c8c0a8' '#4a4438'   # contact print on wall
make_photo costanera-1.jpg     1280x720   '#e6c878' '#2a3a2a'   # reeds at sunrise
make_photo costanera-2.jpg     1200x800   '#b8a878' '#3a4a3a'   # jogger on path
make_photo costanera-3.jpg     1280x720   '#3a4a6a' '#0c1428'   # puerto madero silhouette
make_photo costanera-4.jpg     900x900    '#a89a78' '#3a4a44'   # heron in shallows
make_photo costanera-5.jpg     1200x800   '#e6b850' '#3a2a18'   # golden light on dock

echo "✓ generated $(ls "$OUT" | wc -l) photos in $OUT"
