# Sistem Pelaporan Belanja Modal (KCD Wilayah X)

Aplikasi web terpadu pelaporan, rekonsiliasi SPJ, dan pencetakan Berita Acara Belanja Modal (Format 26 Kolom) untuk sekolah negeri di lingkungan Cabang Dinas Pendidikan Wilayah X Provinsi Jawa Barat.

---

## 🚀 Tech Stack

- **Backend**: Laravel 13 (PHP 8.4)
- **Frontend**: Inertia.js (React 19 + TypeScript)
- **Styling**: Tailwind CSS
- **Database**: PostgreSQL
- **Testing**: PHPUnit (Coverage &ge; 80%) & Laravel Pint

---

## 📚 Dokumentasi Proyek (`docs/`)

Dokumentasi lengkap dan spesifikasi teknis tersedia di direktori `docs/`:

- [docs/index.md](docs/index.md): Ringkasan sistem dan indeks utama dokumentasi.
- [docs/prd.md](docs/prd.md): Product Requirements Document (latar belakang, scope fitur F1-F9, kriteria penerimaan).
- [docs/architecture.md](docs/architecture.md): Arsitektur teknis, stack, pola kunci, dan keputusan desain.
- [docs/design.md](docs/design.md): Design system & panduan UI/UX (token, komponen, pola UX).
- [docs/database.md](docs/database.md): Diagram relasi, kamus 13 tabel, dan konvensi skema.
- [docs/api.md](docs/api.md): Spesifikasi rute, controller, middleware, dan kontrak data.
- [docs/modules.md](docs/modules.md): Alur kerja per modul (Dashboard, SPJ, Realisasi, Cetak 26 Kolom, Rekapan).
- [docs/security.md](docs/security.md): Matriks RBAC, isolasi tenant, audit trail, checklist rilis.
- [docs/testing.md](docs/testing.md): Strategi, perintah, dan matriks cakupan test.
- [docs/setup.md](docs/setup.md): Petunjuk instalasi lokal dan verifikasi kualitas.
- [docs/deployment.md](docs/deployment.md): Deployment produksi, backup, dan troubleshooting.
- [docs/user-guide.md](docs/user-guide.md): Panduan operasional untuk Admin KCD dan Operator Sekolah.
- [docs/guidelines.md](docs/guidelines.md): Panduan developer, standar pengujian, dan aturan penulisan path.
- [docs/glossary.md](docs/glossary.md): Istilah BM, SPJ, kunci laporan, dan log aktivitas.

---

## 🎨 Branding & Ikon

Favicon, ikon PWA, dan Apple touch icon diturunkan dari satu master
`public/images/logolog.jpeg` via ImageMagick dan dimuat di
`resources/views/app.blade.php`. Manifest: `public/site.webmanifest`.
Detail & perintah regenerate: [docs/design.md](docs/design.md) (§5a).

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
composer test          # test cepat tanpa coverage
composer test-coverage # gate coverage (gagal bila < 80%)
```
