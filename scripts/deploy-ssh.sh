#!/usr/bin/env bash
set -euo pipefail

# Usage: scripts/deploy-ssh.sh release_YYYYmmdd_HHMMSS.zip user@host:/path/to/app
ARTIFACT="${1:?zip path required}"
TARGET="${2:?user@host:/var/www/myapp}"

APP_DIR="$(echo "$TARGET" | sed 's|^[^:]*:||')"   # remote /path/to/app
SSH_HOST="$(echo "$TARGET" | sed 's|:.*$||')"     # user@host

BASENAME="$(basename "$ARTIFACT" .zip)"
REL_DIR="${APP_DIR}/releases/${BASENAME}"

echo "• Uploading artifact"
scp -q "$ARTIFACT" "$SSH_HOST:${APP_DIR}/"

echo "• Remote install to ${REL_DIR}"
ssh -T "$SSH_HOST" bash <<EOF
set -euo pipefail
cd "${APP_DIR}"

# 1) Prepare release dir and unpack
mkdir -p "${REL_DIR}"
unzip -q "${BASENAME}.zip" -d releases/
# If the zip contains release_*/ (as in our build), move/rename into ${REL_DIR}
if [ -d "releases/${BASENAME}" ] && [ "${REL_DIR}" != "${APP_DIR}/releases/${BASENAME}" ]; then
  rm -rf "${REL_DIR}"
  mv "releases/${BASENAME}" "${REL_DIR}"
fi

# 2) Link shared stuff (create if missing)
mkdir -p shared/{.env,logs,cache,uploads}
[ -f shared/.env ] || touch shared/.env
ln -sfn "${APP_DIR}/shared/.env"         "${REL_DIR}/.env"  || true
mkdir -p "${REL_DIR}/storage"            || true
ln -sfn "${APP_DIR}/shared/logs"         "${REL_DIR}/storage/logs"   || true
ln -sfn "${APP_DIR}/shared/cache"        "${REL_DIR}/storage/cache"  || true
mkdir -p "${REL_DIR}/public"             || true
ln -sfn "${APP_DIR}/shared/uploads"      "${REL_DIR}/public/uploads" || true

# 3) Post-deploy hooks
php -r 'function_exists("opcache_reset") && opcache_reset();' || true

# Optional: health check BEFORE switching (adjust path/URL if needed)
if command -v curl >/dev/null 2>&1; then
  curl -fsS "http://localhost/health" >/dev/null || { echo "Health check failed"; exit 1; }
fi

# 4) Atomic switch
ln -sfn "${REL_DIR}" "${APP_DIR}/current"

# 5) Prune old releases (keep last 5)
ls -1dt "${APP_DIR}/releases/"* | tail -n +6 | xargs -r rm -rf

echo "OK"
EOF

echo "✅ Deployed ${BASENAME}"
