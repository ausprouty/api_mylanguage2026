#!/usr/bin/env bash
set -euo pipefail

ts="$(date -u +%Y%m%d_%H%M%S)"
STAGE=".build/release_${ts}"
ARTIFACT="release_${ts}.zip"

echo "• staging: ${STAGE}"
rm -rf .build && mkdir -p "${STAGE}"

# 1) Export tracked files via git archive (honors .gitattributes export-ignore)
git archive --format=tar --worktree-attributes HEAD \
  | tar -x -C "${STAGE}"

# 2) Write a VERSION.txt inside the stage
hash="$(git rev-parse --short HEAD)"
printf "commit=%s\ndate=%s\n" "$hash" "$(date -u +%F_%T)" > "${STAGE}/VERSION.txt"

# 3) Install prod deps into the stage (no dev, optimized autoload)
#    IMPORTANT: use the PHP/composer versions that match prod
composer --version >/dev/null || { echo "Composer not found in PATH"; exit 1; }

# If composer.json is export-ignored, this will no-op. If you keep it ignored,
# skip this step and rely on vendor committed elsewhere. Prefer NOT ignored.
if [[ -f "${STAGE}/composer.json" ]]; then
  (cd "${STAGE}" && composer install --no-dev --prefer-dist \
     --optimize-autoloader --classmap-authoritative)
fi

# 4) Final cleanup (optional): remove dev/ tests/ etc if they slipped in
rm -rf "${STAGE}/tests" "${STAGE}/.github" || true

# 5) Pack zip
( cd "${STAGE}/.." && zip -r "../${ARTIFACT}" "release_${ts}" )
# zip -T "${ARTIFACT}"
powershell -NoProfile -Command "Compress-Archive -Path '${STAGE}\*' -DestinationPath '${ARTIFACT}' -Force"


echo "✅ Built ${ARTIFACT}"
