#!/usr/bin/env bash
# Deploy produksi: pull, build, (opsional) backup + migrasi DB, rebuild cache.
# Lihat docs/deployment.md §5 untuk detail dan rollback.
set -euo pipefail
cd "$(dirname "$0")"

# Situs selalu kembali online di akhir script, apa pun yang terjadi.
trap 'php artisan up' EXIT

git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction

read -rp "Jalankan migrasi database? [y/N] " jawab
if [[ "$jawab" == "y" || "$jawab" == "Y" ]]; then
    php artisan down

    # Kredensial DB dari .env (tanpa source .env — aman utk nilai dgn spasi/khusus).
    env_val() { sed -n "s/^$1=//p" .env | tr -d "\"'"; }
    DB_HOST=$(env_val DB_HOST)
    DB_PORT=$(env_val DB_PORT)
    DB_NAME=$(env_val DB_DATABASE)
    DB_USER=$(env_val DB_USERNAME)
    DB_PASS=$(env_val DB_PASSWORD)

    mkdir -p storage/backups
    DUMP="storage/backups/${DB_NAME}_$(date +%Y%m%d_%H%M%S).dump"
    echo "Backup DB → $DUMP"
    PGPASSWORD="$DB_PASS" pg_dump -h "$DB_HOST" -p "${DB_PORT:-5432}" -U "$DB_USER" -Fc "$DB_NAME" -f "$DUMP"

    # Dump wajib ada & tidak kosong; gagal backup = migrasi dibatalkan.
    [[ -s "$DUMP" ]] || { echo "Backup gagal: $DUMP kosong/tidak ada. Deploy dihentikan sebelum migrasi. Restore manual: php artisan up" >&2; exit 1; }

    php artisan migrate --force
else
    echo "Migrasi dilewati."
fi

npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan permission:cache-reset
echo "Deploy selesai."
