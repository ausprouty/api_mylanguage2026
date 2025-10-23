#!/usr/bin/env bash
set -euo pipefail

ts="$(date -u +%Y%m%d_%H%M%S)"
STAGE=".build/release_${ts}"
ARTIFACT="release_${ts}.zip"

echo "• staging: ${STAGE}"
# Aggressive clean to avoid stale case-mismatched dirs on Windows
rm -rf .build
mkdir -p "${STAGE}"
# 1) Export tracked files via git archive (honors .gitattributes export-ignore)
  git archive --format=tar --worktree-attributes HEAD \
   | tar -x -C "${STAGE}"


# 2) Write a VERSION.txt inside the stage
  hash="$(git rev-parse --short HEAD)"
  printf "commit=%s\ndate=%s\n" "$hash" "$(date -u +%F_%T)" > "${STAGE}/VERSION.txt"

# 3) Verify composer is available and composer.json exists in the STAGE
  composer --version >/dev/null || { echo "ERROR: Composer not found in PATH"; exit 1; }

  if [[ ! -f "${STAGE}/composer.json" ]]; then
    echo "ERROR: composer.json is missing from ${STAGE}"
    echo "Diagnostics:"
    echo "  • Is composer.json marked export-ignore?"
    git check-attr export-ignore -- composer.json || true
    echo "  • Is the file tracked?"
    git ls-files --error-unmatch composer.json && echo "    -> tracked" || echo "    -> NOT tracked"
    echo "  • .gitattributes (top-level lines that mention composer.json):"
    { grep -n -i 'composer.json' .gitattributes || true; } | sed 's/^/    /'
    echo "Fix: ensure composer.json is tracked and NOT export-ignored, then re-run."
    exit 1
  fi


# 4) Install prod deps into the stage (no dev, optimized autoload), then strict autoload
  ( cd "${STAGE}" && \
    composer install --no-dev --prefer-dist --no-interaction \
      --optimize-autoloader --classmap-authoritative && \
    composer dump-autoload -o -a --strict-psr -vvv )

# 5) Autoload smoke test for BundleRepositoryFs
  php -r "require '${STAGE}/vendor/autoload.php'; \
    echo class_exists('App\\\\Infra\\\\Filesystem\\\\BundleRepositoryFs') ? 'Autoload OK' : 'Autoload MISS';" \
    | grep -q 'Autoload OK' || { echo 'ERROR: class App\\Infra\\Filesystem\\BundleRepositoryFs not found in stage'; exit 1; }

# 6) Final cleanup (optional): remove dev/ tests/ etc if they slipped in
 rm -rf "${STAGE}/tests" "${STAGE}/.github" || true


# 7) Pack artifact as tar.gz using built-in tar.exe (Windows has it)
( cd "${STAGE}/.." && tar -czf "../${ARTIFACT%.zip}.tar.gz" "release_${ts}" )
echo "• wrote ${ARTIFACT%.zip}.tar.gz"
 
