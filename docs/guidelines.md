# Panduan Pengembang & Aturan Kerja (Developer Guidelines)

Dokumen ini memuat standar kerja teknis, konvensi kode, penulisan dokumentasi, serta tata cara pengujian untuk proyek Sistem Pelaporan Belanja Modal KCD Wilayah X.

---

## 1. Aturan Penulisan Path & Dokumentasi Markdown (PENTING)

> [!CAUTION]
> **DILARANG KERAS MENGGUNAKAN ABSOLUTE PATH**
> Dalam membuat, mengubah, atau menyusun file dokumentasi (`docs/`) maupun file markdown lainnya (`.md` seperti `AGENTS.md`, `README.md`, dsb.), developer dan AI Agent **DILARANG KERAS** menyisipkan absolute path sistem lokal (contoh terlarang: `/Users/username/...`, `/home/user/...`, atau `C:\...`).
> 
> **Wajib Menggunakan Relative Path:**
> Selalu gunakan relative path yang dihitung dari root direktori proyek atau relative terhadap file dokumen saat ini:
> - Tepat: `docs/index.md`, `app/Http/Controllers/PelaporanBm/DashboardController.php`, `resources/js/Pages/PelaporanBm/Spj/Index.tsx`
> - Salah: `/Users/username/pelaporan-bm/docs/index.md`

---

## 2. Standar Format Kode & Linting

### PHP (Laravel Pint)
- Setiap kali mengubah file PHP, jalankan formatter Laravel Pint:
  ```bash
  vendor/bin/pint --format agent
  ```
- Selalu patuhi standar PSR-12, deklarasikan strict return types, serta manfaatkan constructor property promotion PHP 8.4.

### TypeScript & React Best Practices
- Selalu periksa type correctness sebelum commit:
  ```bash
  npx tsc --noEmit
  ```
  dan build bundel:
  ```bash
  npx vite build
  ```
- **Strict Typing**: Deklarasikan tipe eksplisit untuk props, state, dan return values. Dilarang menggunakan `any`; gunakan generics, discriminating union, atau `unknown` (contoh: `usePage<{ auth?: {...} }>()`, bukan `usePage<any>()`).
- **Shared Contracts**: Definisikan interface bersama untuk data entitas (misal: `SpjItem`, `AcuanItem`, `Sekolah`) dan ekspor untuk konsistensi antar-komponen.
- **Helper bersama**: Nama bulan (`BULAN_LIST`, `getNamaBulan`) dan format rupiah (`formatRupiah`) dari `resources/js/Utils/format.ts` — dilarang mendefinisikan ulang `bulanNames` lokal di halaman.

---

## 3. Prinsip SOLID & Clean Architecture

- **Single Responsibility (SRP)**: Setiap controller, model, dan komponen React hanya bertanggung jawab atas satu fungsi logis. Hindari komponen raksasa dengan memecah UI ke sub-komponen terfokus.
- **Open/Closed (OCP)**: Utamakan komposisi daripada modifikasi kode inti yang sudah stabil.
- **Liskov Substitution (LSP)**: Pastikan setiap variasi komponen atau turunan kelas mematuhi kontrak antarmuka dasarnya.
- **Interface Segregation (ISP)**: Buat interface props yang ramping dan spesifik untuk kebutuhan komponen pemanggil.
- **Dependency Inversion (DIP)**: Manfaatkan dependency injection via Laravel service container dan React component props.

---

## 4. Clean Code & DRY (Don't Repeat Yourself)

- **DRY (Don't Repeat Yourself)**: Sentralisasi logika kalkulasi (penjumlahan nominal, periksa status gembok, format rupiah) ke dalam helper, trait, atau custom hook. Hindari duplikasi kode antar-halaman.
- **Early Returns & Guard Clauses**: Gunakan guard clauses di awal fungsi/method untuk mereduksi nested if-else yang dalam.
- **Penamaan Deskriptif**: Gunakan nama variabel dan fungsi yang mencerminkan intensi (`isReportLocked`, `calculateShortage()`). Hindari singkatan ambigu.

---

## 5. Standar UI Halaman (Konsistensi Antar Modul)

Standar ini hasil audit UI seluruh halaman (Dashboard, Acuan, Rekapan, Cetak, Kode Barang, User, Log Aktivitas). Detail token & pola: `docs/design.md`.

1. **Komponen bersama wajib**: `Modal`, `ConfirmDialog`, `SearchInput`, `EmptyState`, `Pagination` dari `resources/js/Components/`. Dilarang modal ad-hoc `fixed inset-0`, `window.confirm()`/`alert()`, input search manual, atau sel kosong italic.
2. **Konfirmasi destruktif**: hapus/kunci/buka kunci/ACC selalu `ConfirmDialog`; aksi konstruktif (ACC) pakai `isDestructive={false}`.
3. **Guard diri-sendiri di UI**: tombol hapus/nonaktifkan disembunyikan untuk akun yang sedang login (validasi backend tetap ada).
4. **Lebar konten penuh**: wrapper halaman tanpa `max-w-* mx-auto`; padding hanya dari `main`.
5. **Aksesibilitas**: widget filter clickable = `<button aria-pressed>`; toggle expand-baris = `aria-expanded`; kontrol filter tanpa label visual = `aria-label`; tombol ikon = `aria-label`; glyph redup minimal `slate-400`.
6. **Tabel responsif**: `overflow-x-auto` + `min-w` skala sesuai jumlah kolom (skala di `docs/design.md` §4).

---

## 6. Standar Pengujian (Testing & Code Coverage)

- **Target Coverage**: Line coverage &ge; 80% per file dan total, di-enforce via gate `composer test-coverage` (gagal bila di bawah minimum). Status saat ini: **100% total, semua file 100%**.
- **Menjalankan Tes**:
  ```bash
  # Cepat (tanpa coverage) — pakai ini saat iterasi harian
  composer test

  # Gate coverage penuh (Xdebug coverage + --min=80) — wajib lulus sebelum merge
  composer test-coverage
  ```
  atau menjalankan file spesifik:
  ```bash
  php artisan test tests/Feature/PelaporanBmTest.php
  php artisan test --filter=test_multi_item_spk_and_input_realisasi_workflow
  ```
- **Struktur Test**:
  - `tests/Feature/`: alur HTTP end-to-end per modul (SPJ & Input Realisasi, Kode Barang + import CSV/xlsx, User Management, Password Expiry, Rekapan & Kunci, isolasi tenant, filter log aktivitas, edge-case index/export).
  - `tests/Unit/`: Fortify actions, relasi model, Gate super_admin & rate limiter.
- **Prinsip**: Gunakan database SQLite in-memory (sudah disetel di `phpunit.xml`). Untuk test feature, seed `PeranDanHakAksesSeeder` lalu buat user via `User::create` + `assignRole` mengikuti pola test existing. Gunakan `UploadedFile::fake()->createWithContent()` untuk import CSV; xlsx dibuat in-test via `ZipArchive` (tanpa paket tambahan).
- **Logika bergantung waktu**: jangan mengandalkan `date('n')`/jam sistem di test. Ekstrak perhitungan ke helper kecil ber-argumen opsional (contoh: `AcuanController::defaultBulan(?int)`, `DashboardController::bulanLapor(?int)`) lalu uji deterministik via reflection. Untuk cabang murni privat (parser, formatter), uji memakai `ReflectionMethod` alih-alih memaksa jalur HTTP.

---

## 7. Konvensi Alur Modul & Kontrol Akses (RBAC)

1. **Pemisahan Role**:
   - Operator Sekolah (`user` terikat `sekolah_id`): Hanya dapat melihat dan memanipulasi data sekolahnya sendiri. Tidak diizinkan mengakses data sekolah lain.
   - Admin KCD (`admin` tanpa `sekolah_id`): Memiliki akses pemantauan wilayah, rekapitulasi, penguncian/approval laporan, dan manajemen master data.
2. **Integritas Belanja Modal**:
    - Item barang SPJ yang dimasukkan ke laporan cetak dan rekapitulasi realisasi adalah yang memiliki row di `pelaporan_bm_realisasi` (status realisasi di-derive, tanpa kolom flag).
   - Cetak laporan harus melalui validasi pre-flight (`/pelaporan-bm/cetak/check`) untuk memastikan tidak mencetak data kosong.
   - Perubahan data pada bulan yang telah berstatus `disetujui` atau dikunci oleh KCD harus diblokir demi akuntabilitas audit.
3. **Standar Keamanan Autentikasi**:
   - Autentikasi menggunakan `username` unik tanpa dependensi email.
   - Password baru wajib rumit (`Password::min(8)->letters()->mixedCase()->numbers()->symbols()`).
   - Rotasi password wajib dilakukan tiap 90 hari melalui dedicated page `/ubah-password` yang diproteksi middleware `EnsurePasswordNotExpired`.
