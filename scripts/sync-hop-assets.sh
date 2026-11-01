#!/usr/bin/env bash
# Copy the shared homepage module (assets v2) from a reviewed HEL.ink checkout.
set -euo pipefail
cd "$(dirname "$0")/.."
source_root="${1:?Pass the HEL.ink checkout path}"
mkdir -p public/hop-home/home
for file in hop-home.css hop-home.js hop-hero3d.js hop-motion.js home/linkhop-light.webp home/linkhop-dark.webp; do
  cp "$source_root/public/hop-assets/v2/$file" "public/hop-home/$file"
done
