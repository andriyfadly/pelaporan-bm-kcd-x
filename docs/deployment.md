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

### 6.1 Backup otomatis (spatie/laravel-backup)

Backup aplikasi + database dijadwalkan **tiap akhir bulan pukul 23:00**
(`routes/console.php`), diikuti `backup:clean` pukul 23:30 dan `backup:monitor`
harian pukul 04:00. Hasil zip disimpan di `storage/app/private/<BACKUP_NAME>/`
pada disk `local`.

Scheduler Laravel butuh satu entri cron di server:

```bash
* * * * * cd /var/www/aset-kcd-x-superapps && php artisan schedule:run >> /dev/null 2>&1
```

Perintah manual dan verifikasi:

```bash
php artisan backup:run          # buat backup sekarang
php artisan backup:list         # daftar backup + ukuran
php artisan backup:monitor      # cek umur/ukuran backup
php artisan backup:clean        # terapkan retensi
php artisan schedule:list       # pastikan jadwal terbaca
```

Konfigurasi di `config/backup.php`; nilai per-environment lewat `.env`:
`BACKUP_NAME`, `BACKUP_NOTIFICATION_EMAIL`, opsional `BACKUP_ARCHIVE_PASSWORD`
(bila diisi, zip terenkripsi AES-256 dan password wajib disimpan di luar server).

Retensi default: 7 hari simpan semua, 16 hari harian, 8 minggu mingguan,
4 bulan bulanan, 2 tahun tahunan, dan total maksimum 5000 MB.

Restore (v10 tidak menyediakan `backup:restore`, jadi lakukan manual):

```bash
# lihat isi zip lalu ekstrak
php artisan backup:list
unzip -l storage/app/private/<BACKUP_NAME>/<nama-zip>.zip | grep db-dumps
unzip -o storage/app/private/<BACKUP_NAME>/<nama-zip>.zip 'db-dumps/*' -d /tmp/restore
gunzip -c /tmp/restore/db-dumps/*.sql.gz | psql -U <user> -d <db>
# file aplikasi (opsional, hati-hati menimpa .env):
unzip -o storage/app/private/<BACKUP_NAME>/<nama-zip>.zip -d /tmp/restore/files
```

### 6.2 Backup manual saat migrasi/deploy

`deploy.sh` sudah mem-backup DB ke `storage/backups/<db>_<tanggal>.dump`
(`pg_dump -Fc`) sebelum migrasi, dan membatalkan deploy bila dump kosong.

```bash
# manual
pg_dump -Fc pelaporan_bm > storage/backups/pelaporan_bm_$(date +%F).dump
# restore
pg_restore -d pelaporan_bm_baru storage/backups/pelaporan_bm_TANGGAL.dump
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
