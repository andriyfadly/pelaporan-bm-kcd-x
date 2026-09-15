# Panduan Instalasi & Setup Lingkungan (Setup & Deployment)

Petunjuk instalasi, konfigurasi environment lokal, dan deployment aplikasi Pelaporan Belanja Modal KCD Wilayah X.

---

## 1. Kebutuhan Sistem (Prerequisites)

- **PHP**: &ge; 8.4 (ekstensi: `pdo`, `pdo_mysql`, `mbstring`, `bcmath`, `curl`, `zip`)
- **Node.js**: &ge; 20.x & npm &ge; 10.x
- **Composer**: &ge; 2.8
- **Database**: MySQL &ge; 8.0 atau MariaDB &ge; 10.6
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
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=pelaporan_bm
# DB_USERNAME=root
# DB_PASSWORD=

# 5. Jalankan migrasi & seeder
php artisan migrate --seed
# Seeder otomatis menghasilkan:
# - Akun Developer: username `developer` (password: `#SidiptaBeuKCD10`, role: `super_admin`)
# - Akun Admin KCD: username `admin_kcd` (password: `#SidiptaBeuKCD10`, role: `admin_kcd`)
# - Akun Operator Sekolah: username `[npsn]-admin` (password: `#SidiptaKCD10`, role: `operator_sekolah`, wajib ubah password perdana)

# 5b. (Opsional) Impor data legacy: dump transaksional + katalog kode barang
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

# Gate coverage penuh: gagal bila line coverage < 80%
composer test-coverage
```

---

## 4. Konfigurasi Produksi (Production Deployment)

1. Set environment produksi:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://bm-kcd10.disdik.jabarprov.go.id
   ```
2. Optimasi cache:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   npm run build
   ```
3. Konfigurasi Web Server: Arahkan DocumentRoot ke direktori `public/`.
