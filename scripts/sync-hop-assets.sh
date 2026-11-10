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
# The apps come from the manifest (every app with "public": true), so a new family member needs no edit here.
apps="$(awk '/"slug":/ { gsub(/[",]/, "", $2); slug = $2 } /"public": *true/ { print slug }' "$manifest")"
[ -n "$apps" ] || { echo "No public apps found in $manifest" >&2; exit 1; }
for app in $apps; do
  for look in light dark; do
    cp "$src/home/$app-$look.webp" "public/hop-home/home/$app-$look.webp"
  done
done
# The app names in their own typefaces (hop-brand.css and the Unbounded licence it points to) and the fonts this app uses.
mkdir -p public/hop-brand/fonts/brand
cp "$src/hop-brand.css" public/hop-brand/hop-brand.css
cp "$src/fonts/LICENSE-unbounded.txt" public/hop-brand/fonts/LICENSE-unbounded.txt
for app in linkhop biohop payhop; do
  cp "$src/fonts/brand/$app-name.woff2" "public/hop-brand/fonts/brand/$app-name.woff2"
done
# hop-hero3d.js here used to carry a guard (an app with no block map keeps its flat mark). The copy above replaces it with
# the hub's file; if tests/Feature/*Home* fail after a sync, re-apply the guard until the hub's copy has it.
