# Deployment Produksi & Staging

> Instalasi lokal: `docs/setup.md`. Keamanan: `docs/security.md` (§6 checklist).
> Staging QA: §8 di dokumen ini.

## 1. Prasyarat Server

- PHP ≥ 8.4 (`pdo_pgsql`, `mbstring`, `bcmath`, `curl`, `zip`), Composer ≥ 2.8
- Node ≥ 20.19 (Vite 8) + npm ≥ 10 (build), PostgreSQL ≥ 14, Nginx/Apache, HTTPS aktif
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
3. Verifikasi domain BHP: buka `/distribusi-bhp/dashboard`, cek master &
   penerimaan terisi (hasil impor), dan coba buka satu laporan stok.
   - Bila seeder impor dilewati (sumber tidak ada), jalankan manual:
     `php artisan db:seed --class=DistribusiBhpImportSeeder`
     dengan path sumber yang benar.
   - Periksa tidak ada error rendering PDF:
     buka satu dokumen pengajuan → unduh PDF.
4. Aktifkan backup DB harian + uji restore (lihat §6).
5. Aktifkan monitor log Laravel (`storage/logs/`) & error web server.

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

> **Paket wajib benar-benar terpasang.** `spatie/laravel-backup` beserta
> `spatie/db-dumper`, `spatie/laravel-signal-aware-command`, dan
> `spatie/temporary-directory` harus ada di `vendor/`. Bila `vendor/` menyimpang
> dari `composer.lock`, `backup:run` gagal dengan
> `Class "Spatie\Backup\Config\Config" not found` dan test
> `BackupNotificationConfigTest` ikut merah. Verifikasi cepat:
>
> ```bash
> composer install                 # pasang sesuai lock (direkomendasikan di server)
> php -r '$l=json_decode(file_get_contents("composer.lock"),true);
>   $i=json_decode(file_get_contents("vendor/composer/installed.json"),true);
>   $a=array_column($l["packages"],"version","name");
>   $b=array_column($i["packages"],"version","name");
>   echo "hilang: ", implode(", ", array_diff(array_keys($a), array_keys($b))), "\n";
>   echo "beda versi: ", count(array_filter($a, fn($v,$k)=>isset($b[$k]) && $b[$k]!==$v, ARRAY_FILTER_USE_BOTH)), "\n";'
> ```

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
| Unduhan Excel 204 terus | Data filter kosong (normal) | Periksa periode/filter; tidak ada worker yang terlibat |
| Permission error | `php artisan permission:cache-reset` (Spatie) |

## 8. Staging (QA)

Staging dipakai QA untuk mencoba perubahan sebelum rilis. Letaknya di server yang
sama dengan produksi, tetapi **semua komponen terpisah**: direktori, database,
user DB, dan cron. Tidak ada data produksi yang tersentuh.

| Komponen | Staging | Produksi |
|---|---|---|
| Direktori | `/var/www/pelaporan-bm-staging` | `/var/www/aset-kcd-x-superapps` |
| Domain | `staging.sidipta-kcd10.my.id` | `sidipta-kcd10.my.id` |
| `APP_ENV` | `staging` | `production` |
| Database | `pelaporan_bm_staging` | `aset_kcd_x_superapps_db` |
| User DB | `pelaporan_bm_staging_user` | `aset_kcd_x_user` |
| Nginx | `/etc/nginx/sites-available/staging.sidipta-kcd10.my.id` | `.../sidipta-kcd10.my.id` |
| Queue | `pelaporan-bm-staging-queue.service` | belum ada worker (aplikasi belum mengirim job async) |
| Scheduler | `/etc/cron.d/pelaporan-bm-staging` | entri cron produksi |

### 8.1 Trafik masuk lewat Cloudflare Tunnel

Port 80/443 server **tidak terbuka ke internet**; trafik publik masuk lewat
tunnel `cloudflared`. Karena itu Certbot tidak dipakai untuk staging — HTTPS
diterminasi di edge Cloudflare.

Menambah site baru berarti menambah entri di `/etc/cloudflared/config.yml`, lalu:

```bash
cloudflared tunnel --config /etc/cloudflared/config.yml ingress validate
systemctl restart cloudflared
```

Tanpa langkah ini Cloudflare akan membalas **404** untuk hostname tersebut,
walaupun Nginx lokal sudah benar (`curl -H "Host: <domain>" http://127.0.0.1/`
mengembalikan 200). Ini penyebab kegagalan Certbot HTTP-01 yang paling sering
terjadi di server ini.

### 8.2 Inisialisasi dari nol

```bash
# 1. Kode
git clone <url> /var/www/pelaporan-bm-staging
cd /var/www/pelaporan-bm-staging && git checkout <branch>
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 2. Database (sebagai root)
sudo -u postgres psql -c "CREATE ROLE pelaporan_bm_staging_user LOGIN PASSWORD '<pw>'"
sudo -u postgres createdb -O pelaporan_bm_staging_user pelaporan_bm_staging

# 3. .env — salin .env.example lalu sesuaikan (lihat §8.3)

# 4. Migrasi + seed
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force --class=DatabaseSeeder
php artisan db:seed --force --class=DemoDistribusiBhpSmkn4KuninganSeeder

# 5. Cache
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan permission:cache-reset
```

`DemoDistribusiBhpSmkn4KuninganSeeder` membutuhkan sekolah NPSN `20246369` yang
sudah ada. `KodeSekolahSeeder` hanya mengisi data itu bila `storage/bm-kcd-x.sql`
tersedia — pada clone bersih file itu tidak ada, dan seeder **diam tanpa
melakukan apa pun**. Buat sekolahnya lebih dulu:

```bash
php artisan tinker --execute 'App\Models\Master\Sekolah::create([
    "nama_sekolah" => "SMK NEGERI 4 KUNINGAN",
    "kota_kab" => "KABUPATEN KUNINGAN",
    "npsn" => "20246369",
    "kode_sub_pengguna" => "1.01.01",
    "kode_wilayah" => "32.08",
]);'
```

### 8.3 `.env` staging (wajib)

```env
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging.sidipta-kcd10.my.id
APP_NAME="ASET KCD 10"
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=pelaporan_bm_staging
DB_USERNAME=pelaporan_bm_staging_user
DB_PASSWORD=...
SESSION_SECURE_COOKIE=true
BACKUP_NOTIFICATION_EMAIL=admin@example.com
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=1x00000000000000000000AA
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA
```

Dua nilai yang mudah terlewat dan langsung mematikan aplikasi:

- `BACKUP_NOTIFICATION_EMAIL` **tidak boleh kosong**. `config/backup.php`
  memvalidasinya saat service provider boot, jadi nilai kosong membuat semua
  request 500 (`InvalidConfig: is not a valid email address`).
- `TURNSTILE_ENABLED=false` di luar `local` menolak **semua** login
  (`TurnstileService::verify()` hanya meloloskan `local` dan unit test). Untuk
  staging gunakan Cloudflare **test key** di atas yang selalu lolos. Key itu
  publik dan hanya untuk pengujian — jangan pernah dipakai di produksi.

### 8.4 Worker & scheduler

Aplikasi saat ini **tidak mengirim job ke queue** (ekspor XLSX dan cetak PDF
dikerjakan sinkron di request), jadi worker hanya disiapkan untuk antisipasi job
baru di masa depan. Bila belum dibutuhkan, langkah ini boleh dilewati.

Buat `/etc/systemd/system/pelaporan-bm-staging-queue.service`:

```ini
[Unit]
Description=Laravel Queue Worker - Pelaporan BM Staging
After=network.target postgresql.service

[Service]
User=pplgsmk4
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/pelaporan-bm-staging
ExecStart=/usr/bin/php artisan queue:work database --sleep=3 --tries=3 --timeout=300 --max-time=3600
StandardOutput=append:/var/www/pelaporan-bm-staging/storage/logs/queue-worker.log
StandardError=append:/var/www/pelaporan-bm-staging/storage/logs/queue-worker.log

[Install]
WantedBy=multi-user.target
```

Aktifkan:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now pelaporan-bm-staging-queue.service
```

Isi `/etc/cron.d/pelaporan-bm-staging`:

```cron
* * * * * pplgsmk4 cd /var/www/pelaporan-bm-staging && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

### 8.5 Update staging

```bash
cd /var/www/pelaporan-bm-staging
git pull origin <branch>
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan permission:cache-reset
sudo systemctl restart pelaporan-bm-staging-queue.service
```

`migrate --force` di staging aman karena databasenya terpisah. Jangan menyalin
perintah ini ke produksi tanpa backup (lihat §5).

### 8.6 Reset data QA

Untuk mengembalikan dataset demo tanpa menyentuh skema:

```bash
php artisan db:seed --force --class=DemoDistribusiBhpSmkn4KuninganSeeder
```

Seeder ini idempotent: baris demo ditandai prefix `DEMO-SMKN4-2026`, jadi
menjalankannya ulang tidak menggandakan data. Jangan pakai `migrate:fresh` —
perintah itu menghapus seluruh isi database. Bila benar-benar perlu skema
bersih, buat database baru lalu `migrate --force` di sana.

### 8.7 Verifikasi pasca-setup

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://staging.sidipta-kcd10.my.id/login   # 200
curl -s https://staging.sidipta-kcd10.my.id/login | grep -o '<title[^>]*>[^<]*'      # ASET KCD 10
systemctl is-active pelaporan-bm-staging-queue.service                              # active (bila worker dipasang)
php artisan queue:failed                                                            # kosong
```

Login dengan akun demo, lalu periksa: dashboard, pengajuan/penerimaan BOS & BOPD,
laporan stok, kartu persediaan, dan unduh satu PDF dokumen
(`content-type: application/pdf`, diawali `%PDF-`).

### 8.8 Troubleshooting staging

| Gejala | Cek |
|---|---|
| Hostname staging balas 404 dari internet, tapi `curl -H "Host: ..." http://127.0.0.1/` = 200 | Entri `ingress` belum ada di `/etc/cloudflared/config.yml`; restart `cloudflared` |
| Semua login ditolak "Verifikasi keamanan gagal" | `TURNSTILE_*` kosong/`false`; pakai test key |
| Seluruh halaman 500 setelah `.env` diubah | `BACKUP_NOTIFICATION_EMAIL` kosong, atau `config:cache` belum dijalankan |
| Jam/tanggal form mundur sehari | `tanggalHariIni()` memakai zona waktu lokal; cek `date.timezone` PHP |
| 500 hanya di halaman yang menulis file | `storage/` & `bootstrap/cache/` harus milik user PHP-FPM (`chown -R pplgsmk4:www-data`) |
| Aset lama masih muncul setelah `npm run build` | `php artisan view:cache` + hard refresh; cek `public/build/manifest.json` |

