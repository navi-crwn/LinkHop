#!/usr/bin/env bash
# Copy the shared homepage module from a reviewed HEL.ink checkout, at the hop-assets version its manifest names
# (public/hop-assets/apps.json "version"), so this script never pins an old version by itself.
set -euo pipefail
cd "$(dirname "$0")/.."
source_root="${1:?Pass the HEL.ink checkout path}"
manifest="$source_root/public/hop-assets/apps.json"
version="$(sed -n 's/^[[:space:]]*"version":[[:space:]]*"\(v[0-9][0-9]*\)".*/\1/p' "$manifest" | head -n 1)"
[ -n "$version" ] || { echo "No version found in $manifest" >&2; exit 1; }
src="$source_root/public/hop-assets/$version"
echo "Syncing hop-assets $version"
mkdir -p public/hop-home/home
for file in hop-home.css hop-home.js hop-hero3d.js hop-motion.js hop-bento.css; do
  cp "$src/$file" "public/hop-home/$file"
done
for app in hub linkhop biohop pixelhop notehop filehop; do
  for look in light dark; do
    cp "$src/home/$app-$look.webp" "public/hop-home/home/$app-$look.webp"
  done
done
# hop-hero3d.js here may carry a guard (an app with no block map keeps its flat mark); tests/Feature/*Home* check for it.
