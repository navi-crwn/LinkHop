#!/usr/bin/env bash
# Copy the shared homepage module (assets v3) from a reviewed HEL.ink checkout.
set -euo pipefail
cd "$(dirname "$0")/.."
source_root="${1:?Pass the HEL.ink checkout path}"
mkdir -p public/hop-home/home
for file in hop-home.css hop-home.js hop-hero3d.js hop-motion.js home/linkhop-light.webp home/linkhop-dark.webp; do
  cp "$source_root/public/hop-assets/v3/$file" "public/hop-home/$file"
done
# hop-hero3d.js here also carries a guard (an app with no block map keeps its flat mark); re-apply it after a sync
# until the hub's copy has it: tests/Feature/*Home* check for it.
