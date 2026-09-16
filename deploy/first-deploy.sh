#!/usr/bin/env bash
# RUN-ITC: adopsi folder server yang sudah ada menjadi checkout git.
# Dipakai SEKALI saja, menggantikan cara copy folder manual.
#   bash deploy/first-deploy.sh
set -euo pipefail

APP_DIR="${1:-/www/wwwroot/runitc.toeic.or.id}"
REPO_URL="${REPO_URL:-https://github.com/nasiuduk27-hub/runitc.git}"
BRANCH="${BRANCH:-main}"
BACKUP_DIR="${BACKUP_DIR:-/root}"

cd "$APP_DIR"

echo "== 1/6 Backup .env dan storage =="
STAMP="$(date +%F-%H%M%S)"
if [ -f .env ] || [ -d storage ]; then
    tar czf "$BACKUP_DIR/runitc-backup-$STAMP.tar.gz" .env storage 2>/dev/null || true
    [ -f .env ] && cp .env "$BACKUP_DIR/runitc-$STAMP.env" || true
    echo "Backup: $BACKUP_DIR/runitc-backup-$STAMP.tar.gz"
else
    echo "Tidak ada .env/storage untuk dibackup (instalasi baru)."
fi

echo "== 2/6 Siapkan repo git =="
if [ -d .git ]; then
    echo "Repo git sudah ada, lewati init."
else
    git init -q
fi
if git remote get-url origin >/dev/null 2>&1; then
    git remote set-url origin "$REPO_URL"
else
    git remote add origin "$REPO_URL"
fi

echo "== 3/6 Ambil kode terbaru dari GitHub =="
git fetch origin --prune

echo "== 4/6 Samakan file tracked dengan origin/$BRANCH =="
# Hanya menimpa file yang di-track git.
# .env, vendor, dan storage runtime TIDAK tersentuh karena ada di .gitignore.
# Jangan tambahkan "git clean" di sini: itu akan menghapus .env dan vendor.
git checkout -f -B "$BRANCH" "origin/$BRANCH"

echo "== 5/6 Verifikasi file server-only masih utuh =="
if [ -f .env ]; then echo "OK  .env ada"; else echo "!!  .env TIDAK ada - buat dari .env.example lalu php artisan key:generate"; fi
if [ -d storage/logs ]; then echo "OK  storage/logs ada"; else echo "!!  storage/logs hilang"; fi

echo "== 6/6 Jalankan update (composer, migrate, cache, permission) =="
bash deploy/update.sh "$APP_DIR"

echo
echo "SELESAI. Verifikasi:"
echo "  git -C $APP_DIR log --oneline -1"
echo "  git -C $APP_DIR status -sb"
