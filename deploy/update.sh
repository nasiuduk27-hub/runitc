#!/usr/bin/env bash
# RUN-ITC: update kode + dependency di server aaPanel.
# Jalankan dari root project:  bash deploy/update.sh
#
# Aman dijalankan berulang:
#   - backup .env + storage tiap kali
#   - simpan perubahan lokal ke git stash sebelum update
#   - tarik kode dengan fast-forward saja (tidak pernah memaksa / menimpa commit lokal)
set -euo pipefail

# Jalankan dari salinan sementara supaya aman bila update ini mengubah file ini sendiri.
if [ "${RUNITC_REEXEC:-0}" != "1" ]; then
    _self="$(mktemp /tmp/runitc-update-XXXXXX.sh)"
    cp "$0" "$_self"
    RUNITC_REEXEC=1 exec bash "$_self" "$@"
fi

APP_DIR="${1:-/www/wwwroot/runitc.toeic.or.id}"
BRANCH="${BRANCH:-main}"
BACKUP_DIR="${BACKUP_DIR:-/root/runitc-backups}"
OWNER="${OWNER:-www:www}"

cd "$APP_DIR"
STAMP="$(date +%F-%H%M%S)"
mkdir -p "$BACKUP_DIR"

echo "== 1/7 Backup .env + storage =="
tar czf "$BACKUP_DIR/app-$STAMP.tar.gz" .env storage 2>/dev/null || true
echo "  $BACKUP_DIR/app-$STAMP.tar.gz"

echo "== 2/7 Simpan perubahan lokal (tracked) ke stash =="
if git diff --quiet && git diff --cached --quiet; then
    echo "  Tidak ada perubahan lokal."
else
    git diff > "$BACKUP_DIR/local-$STAMP.patch" || true
    git stash push -m "server-local-$STAMP"
    echo "  Disimpan di git stash + $BACKUP_DIR/local-$STAMP.patch"
fi

echo "== 3/7 Tarik kode terbaru =="
git fetch origin --prune

echo "== 4/7 Fast-forward ke origin/$BRANCH =="
if ! git merge --ff-only "origin/$BRANCH"; then
    echo "!!  Tidak bisa fast-forward. Update DIHENTIKAN, tidak ada file yang ditimpa."
    echo "    Server punya commit lokal yang berbeda dari origin."
    echo "    Periksa: git log --oneline --graph --all -15"
    exit 1
fi
echo "  HEAD -> $(git log --oneline -1)"

echo "== 5/7 Composer install =="
if command -v composer >/dev/null 2>&1; then
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
elif [ -x /www/server/php/83/bin/composer ]; then
    COMPOSER_ALLOW_SUPERUSER=1 /www/server/php/83/bin/composer install --no-dev --optimize-autoloader --no-interaction
elif [ -x /www/server/php/82/bin/composer ]; then
    COMPOSER_ALLOW_SUPERUSER=1 /www/server/php/82/bin/composer install --no-dev --optimize-autoloader --no-interaction
else
    echo "composer tidak ditemukan. Install composer di aaPanel (App Store), lalu ulangi."
    exit 1
fi

echo "== 6/7 Migrasi DB (idempotent, aman dijalankan ulang) + bersihkan cache =="
php artisan migrate --force --no-interaction
php artisan optimize:clear

echo "== 7/7 Permission storage =="
chown -R "$OWNER" storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo
echo "SELESAI. Verifikasi halaman:"
echo "  https://runitc.toeic.or.id/credit-union/manual-savings/create"
echo "  https://runitc.toeic.or.id/credit-union/bank-transactions"
echo "  https://runitc.toeic.or.id/credit-union/loan-calculation/detail"
