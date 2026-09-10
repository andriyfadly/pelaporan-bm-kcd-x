# Sistem Pelaporan Belanja Modal (KCD Wilayah X)

Aplikasi web terpadu pelaporan, rekonsiliasi SPJ, dan pencetakan Berita Acara Belanja Modal (Format 26 Kolom) untuk sekolah negeri di lingkungan Cabang Dinas Pendidikan Wilayah X Provinsi Jawa Barat.

---

## 🚀 Tech Stack

- **Backend**: Laravel 12 (PHP 8.4)
- **Frontend**: Inertia.js (React 19 + TypeScript)
- **Styling**: Tailwind CSS
- **Database**: MySQL / MariaDB
- **Testing**: PHPUnit (Coverage &ge; 80%) & Laravel Pint

---

## 📚 Dokumentasi Proyek (`docs/`)

Dokumentasi lengkap dan spesifikasi teknis tersedia di direktori `docs/`:

- [docs/index.md](docs/index.md): Ringkasan sistem dan indeks utama dokumentasi.
- [docs/prd.md](docs/prd.md): Product Requirements Document (latar belakang, scope fitur F1-F5, kriteria penerimaan).
- [docs/user-guide.md](docs/user-guide.md): Panduan operasional untuk Admin KCD dan Operator Sekolah.
- [docs/setup.md](docs/setup.md): Petunjuk instalasi lokal, verifikasi kualitas, dan deployment produksi.
- [docs/architecture.md](docs/architecture.md): Arsitektur teknis, struktur direktori, dan skema basis data.
- [docs/modules.md](docs/modules.md): Alur kerja per modul (Dashboard, SPJ, Realisasi, Cetak 26 Kolom, Rekapan).
- [docs/api.md](docs/api.md): Spesifikasi rute, controller, middleware, dan kontrak data.
- [docs/guidelines.md](docs/guidelines.md): Panduan developer, standar pengujian, dan aturan penulisan path.

---

## ⚡ Quick Start

```bash
# 1. Pasang dependensi
composer install
npm install

# 2. Setup env & generate key
cp .env.example .env
php artisan key:generate

# 3. Migrasi & Seeding
php artisan migrate --seed

# 4. Jalankan dev server
npm run dev
php artisan serve
```

### Verifikasi Kualitas
```bash
vendor/bin/pint --format agent
npx tsc --noEmit
php artisan test --compact
```
