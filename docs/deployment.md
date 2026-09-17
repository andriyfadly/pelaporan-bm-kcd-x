# Deployment Produksi

> Instalasi lokal: `docs/setup.md`. Keamanan: `docs/security.md` (§6 checklist).

## 1. Prasyarat Server

- PHP ≥ 8.4 (`pdo_pgsql`, `mbstring`, `bcmath`, `curl`, `zip`), Composer ≥ 2.8
- Node ≥ 20 + npm ≥ 10 (build), PostgreSQL ≥ 14, Nginx/Apache, HTTPS aktif
- Akun Cloudflare (Turnstile), akses cron (backup/scheduler bila dipakai)

## 2. Deploy Awal

```bash
git clone <url> app && cd app
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# --- isi .env produksi (lihat §3) ---
php artisan migrate --force --seed
# opsional (sekali saja, bila bawa data legacy):
# unggah storage/bm-kcd-x.sql lalu php artisan app:migrasi-data-lama
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link   # bila perlu
```

DocumentRoot → `public/`. Pastikan `storage/` & `bootstrap/cache/` writable.

## 3. `.env` Produksi (wajib)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://bm-kcd10.disdik.jabarprov.go.id
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pelaporan_bm
DB_USERNAME=... 
DB_PASSWORD=...
SESSION_SECURE_COOKIE=true
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=...
TURNSTILE_SECRET_KEY=...
```

## 4. Pasca-Deploy (wajib sekali)

1. Login sebagai `admin_kcd` → ganti password; nonaktifkan/hapus akun
   `developer` bila tak diperlukan.
2. Verifikasi: dashboard admin terbuka, unduh 1 file XLSX, viewer log
   (`/admin/log-aktivitas`) mencatat login.
3. Aktifkan backup DB harian + uji restore (lihat §6).
4. Aktifkan monitor log Laravel (`storage/logs/`) & error web server.

## 5. Update Rilis (tanpa downtime besar)

Cara utama di server:

```bash
./deploy.sh
```

Script menjalankan: `git pull` → `composer install` → pertanyaan migrasi (default
**N**, Enter = lewati). Bila dijawab `y`: situs di-`down`, DB di-backup otomatis
ke `storage/backups/<db>_<tanggal>.dump` (format `pg_dump -Fc`, restore via
`pg_restore`), dump diverifikasi ada & tidak kosong (gagal backup = migrasi
dibatalkan), baru `migrate --force`. Dilanjut `npm ci && npm run build`, rebuild
cache `config`/`route`/`view`, `permission:cache-reset`, dan situs otomatis
kembali online (`php artisan up` via trap) walau ada langkah yang gagal.
Backups lama di `storage/backups/` dibersihkan manual sesuai kebutuhan.

Manual (fallback, urutan sama):

```bash
git pull origin <branch>
composer install --no-dev --optimize-autoloader
php artisan down
pg_dump -Fc <db> > storage/backups/<db>_$(date +%F).dump  # backup dulu
php artisan migrate --force
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan permission:cache-reset
php artisan up
```

Rollback: `git reset --hard <tag>` + `php artisan migrate:rollback --step=N`
(hanya bila migrasi terakhir bermasalah; backup dulu).

## 6. Backup & Restore

```bash
# harian via cron (contoh)
pg_dump -Fc pelaporan_bm > /backup/pelaporan_bm_$(date +%F).dump
# restore
pg_restore -d pelaporan_bm_baru /backup/pelaporan_bm_TANGGAL.dump
```

Simpan juga `storage/app/private/*.xlsx` sementara bila dijadikan arsip
unduhan. Uji restore minimal 1× per kuartal.

## 7. Troubleshooting Cepat

| Gejala | Cek |
|--------|-----|
| 500 setelah deploy | `storage/logs/laravel.log`, permission `storage/`, `config:clear` bila env baru diganti |
| Inertia blank / aset 404 | `npm run build` + `public/build/manifest.json` ada; `APP_URL` benar |
| Login gagal massal | Jam server (2FA), `sessions` table, Turnstile key/domain |
| Unduhan 204 terus | Data filter kosong (normal) atau queue Excel error → cek log |
| Permission error | `php artisan permission:cache-reset` (Spatie) |
