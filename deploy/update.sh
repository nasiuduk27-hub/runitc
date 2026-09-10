#!/usr/bin/env bash
# RUN-ITC: update kode di server aaPanel.
# Jalankan setelah paket update di-extract di /www/wwwroot/runitc.toeic.or.id
#   bash deploy/update.sh
set -euo pipefail

APP_DIR="${1:-/www/wwwroot/runitc.toeic.or.id}"
cd "$APP_DIR"

echo "== 1/5 Cek kode sudah versi terbaru =="
if ! grep -q "manual-savings/create" routes/cooperative.php; then
    echo "routes/cooperative.php masih versi lama."
    echo "Upload & extract dulu paket update, lalu jalankan ulang script ini."
    exit 1
fi

echo "== 2/5 Composer install =="
if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --no-interaction
elif [ -x /www/server/php/82/bin/composer ]; then
    /www/server/php/82/bin/composer install --no-dev --optimize-autoloader --no-interaction
else
    echo "composer tidak ditemukan. Install composer di aaPanel (App Store), lalu ulangi."
    exit 1
fi

echo "== 3/5 Migrasi DB (idempotent, aman dijalankan ulang) =="
php artisan migrate --force --no-interaction

echo "== 4/5 Bersihkan cache Laravel (config, route, view) =="
php artisan optimize:clear

echo "== 5/5 Permission storage =="
chown -R www:www storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo
echo "SELESAI. Verifikasi halaman:"
echo "  https://runitc.toeic.or.id/cooperative/manual-savings/create"
echo "  https://runitc.toeic.or.id/cooperative/bank-transactions"
echo "  https://runitc.toeic.or.id/cooperative/loan-calculation/detail"
