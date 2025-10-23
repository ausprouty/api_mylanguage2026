#!/bin/bash
set -e

ts=$(date -u +%Y%m%d_%H%M%S)

# 1) Create base zip from git (honors .gitattributes; use working tree attrs)
git archive --format=zip \
  --worktree-attributes \
  --output "release_${ts}.zip" \
  --prefix=release/ \
  HEAD

# 2) Add VERSION.txt
hash=$(git rev-parse --short HEAD)
printf "commit=%s\ndate=%s\n" "$hash" "$(date -u +%F_%T)" > VERSION.txt
zip -j "release_${ts}.zip" VERSION.txt
rm VERSION.txt

# 3) Sanity check
zip -T "release_${ts}.zip"

echo "✅ Created release_${ts}.zip"

# Optional: verify that /data is absent
# unzip -l "release_${ts}.zip" | grep -E '^release/data/' && echo "⚠️ data present" || echo "👍 data excluded"
