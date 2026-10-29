# Panduan Instalasi & Setup Lingkungan (Setup)

Petunjuk instalasi dan konfigurasi environment lokal. Deployment produksi dan
staging ada di `docs/deployment.md`.

---

## 1. Kebutuhan Sistem (Prerequisites)

- **PHP**: &ge; 8.4 (ekstensi: `pdo`, `pdo_pgsql`, `mbstring`, `bcmath`, `curl`, `zip`)
- **Node.js**: &ge; 20.19 (Vite 8; 22.12+ disarankan) & npm &ge; 10.x
- **Composer**: &ge; 2.8
- **Database**: PostgreSQL &ge; 14 (dev & prod; test memakai SQLite in-memory)
- **Web Server**: Nginx, Apache, atau Laravel Valet/Herd

---

## 2. Instalasi Lokal (Local Development)

```bash
# 1. Clone repositori
git clone <url-repo> pelaporan-bm
cd pelaporan-bm

# 2. Pasang dependensi PHP & Node.js
composer install
npm install

# 3. Konfigurasi berkas environment
cp .env.example .env
php artisan key:generate

# 4. Sesuaikan konfigurasi database pada .env
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=pelaporan_bm
# DB_USERNAME=postgres
# DB_PASSWORD=

# 5. Letakkan file SQL dump legacy di storage/
cp /path/to/bm-kcd-x.sql storage/

# 6. Jalankan migrasi & seeder
php artisan migrate --seed
# Seeder otomatis menghasilkan:
# - Akun Developer:  username `developer`  (password: `#SidiptaBeuKCD10`, role: `super_admin`)
# - Akun Admin KCD:  username `admin_kcd`  (password: `#SidiptaBeuKCD10`, role: `admin_kcd`)
# - Akun Operator:   username `{npsn}-admin`  (password: `#SidiptaKCD10`, role: `operator_sekolah`, wajib ubah password perdana)
# - Permission & role (32 permission, 5 role termasuk `operator_divisi`)
#
# Catatan: KodeSekolahSeeder mengisi NPSN secara otomatis dari tabel
# `data_barang_acuan` di file bm-kcd-x.sql. Tanpa file tersebut NPSN tetap
# null dan user operator tidak dibuat.
#
# Seeder juga memanggil DistribusiBhpImportSeeder: mengimpor master + penerimaan
# BHP dari DB SQLite sumber. Default path:
#   ../sistem-manajemen-aset/prisma/dev.db.backup.db
# Bila file sumber tidak ada, impor dilewati dengan peringatan (tidak error).
# Arahkan ke sumber lain / jalankan manual:
#   php artisan db:seed --class=DistribusiBhpImportSeeder
# atau via tinker:
#   (new Database\Seeders\DistribusiBhpImportSeeder)->setSourcePath('/path/db')->run();

# 5b. (Opsional) Impor data legacy BM: dump transaksional + katalog kode barang
# Letakkan file storage/bm-kcd-x.sql dan storage/db_inventaris.sql, lalu:
php artisan app:migrasi-data-lama

# 6. Jalankan build aset atau dev server
npm run build
# atau dev mode:
npm run dev
php artisan serve
```

---

## 3. Perintah Verifikasi Kualitas

```bash
# Format kode PHP sesuai standar proyek
vendor/bin/pint --format agent

# Type-check TypeScript
npx tsc --noEmit

# Pengujian unit & fitur (cepat, tanpa coverage)
composer test

# Gate coverage penuh: gagal bila line coverage total < 80%
# atau ada satu file < 80% (lihat tests/coverage-per-file.php)
composer test-coverage
```

> **Jangan jalankan `composer update` untuk sekadar memperbaiki test yang merah.**
> `vendor/` harus cocok dengan `composer.lock`. Bila `vendor/` menyimpang (mis.
> paket ada di lock tetapi tidak terpasang), test seperti
> `BackupNotificationConfigTest` gagal dengan pesan "Class not found" padahal
> kodenya benar. Pulihkan dengan `composer install`.

---

## 4. Konfigurasi Produksi (Production Deployment)

1. Set environment produksi:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://bm-kcd10.disdik.jabarprov.go.id
   ```
2. Aktifkan Cloudflare Turnstile (proteksi login):
   ```env
   TURNSTILE_SITE_KEY=xxx
   TURNSTILE_SECRET_KEY=xxx
   TURNSTILE_ENABLED=true
   ```
   Ambil Site Key + Secret Key dari dashboard Cloudflare → Turnstile, daftarkan domain produksi (dan lokal bila perlu; `127.0.0.1` harus masuk daftar hostname widget). Tanpa key / `TURNSTILE_ENABLED=false`, widget login disembunyikan dan verifikasi dilewati di `local`/testing.
3. Optimasi cache:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   npm run build
   ```
3. Konfigurasi Web Server: Arahkan DocumentRoot ke direktori `public/`.

---

## 5. Data Demo Distribusi BHP

Untuk menguji domain BHP tanpa data legacy, sediakan satu sekolah
(NPSN `20246369`) lalu jalankan seeder demo:

```bash
php artisan db:seed --class=DemoDistribusiBhpSmkn4KuninganSeeder
```

Hasil: 10 barang, 19 penerimaan, dan 26 pengajuan **per sumber dana** (BOS dan
BOPD), beberapa divisi + akun operator divisi, tiga pejabat sekolah, dan 82
dokumen. Baris demo ditandai prefix `DEMO-SMKN4-2026`, jadi seeder aman
dijalankan ulang (idempotent).

`KodeSekolahSeeder` hanya mengisi sekolah dari `storage/bm-kcd-x.sql`. Pada
clone bersih file itu tidak ada dan seeder **diam tanpa melakukan apa pun**,
sehingga seeder demo gagal dengan "Sekolah NPSN 20246369 tidak ditemukan".
Buat sekolahnya dulu:

```bash
php artisan tinker --execute 'App\Models\Master\Sekolah::create([
    "nama_sekolah" => "SMK NEGERI 4 KUNINGAN",
    "kota_kab" => "KABUPATEN KUNINGAN",
    "npsn" => "20246369",
    "kode_sub_pengguna" => "1.01.01",
    "kode_wilayah" => "32.08",
]);'
```

Akun yang dihasilkan seeder demo memakai password yang sama untuk semua akun
demo; lihat konstanta `PASSWORD` di file seeder. Jangan pakai akun itu di
produksi.

---

## 6. Staging

QA dilakukan di staging, bukan di mesin lokal maintainer. Prosedur inisialisasi,
konfigurasi `.env`, worker, dan troubleshooting ada di `docs/deployment.md` §8.

Ringkas: staging memakai direktori, database, user DB, queue worker, dan cron
terpisah dari produksi, serta masuk lewat Cloudflare Tunnel (port 80/443 server
tidak terbuka ke internet). Dua nilai `.env` yang wajib diisi dan mudah
terlewat:

```env
BACKUP_NOTIFICATION_EMAIL=admin@example.com
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=1x00000000000000000000AA
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA
```

`BACKUP_NOTIFICATION_EMAIL` kosong membuat seluruh aplikasi 500 saat boot.
`TURNSTILE_ENABLED=false` di luar `local` menolak semua login. Key di atas adalah
Cloudflare **test key** (selalu lolos) dan hanya boleh dipakai untuk pengujian.
