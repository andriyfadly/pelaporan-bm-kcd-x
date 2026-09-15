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
- **Strict Typing**: Deklarasikan tipe eksplisit untuk props, state, dan return values. Dilarang menggunakan `any`; gunakan generics, discriminating union, atau `unknown`.
- **Shared Contracts**: Definisikan interface bersama untuk data entitas (misal: `SpjItem`, `AcuanItem`, `Sekolah`) dan ekspor untuk konsistensi antar-komponen.

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

## 3. Standar Pengujian (Testing & Code Coverage)

- **Target Coverage**: Kode baru atau perubahan pada modul inti Pelaporan BM wajib mempertahankan line coverage &ge; 80%.
- **Menjalankan Tes**:
  ```bash
  php artisan test --compact
  ```
  atau menjalankan direktori spesifik:
  ```bash
  vendor/bin/phpunit tests/Feature/PelaporanBm
  ```
- **Prinsip**: Gunakan database SQLite in-memory atau database testing khusus, dan manfaatkan Model Factory untuk pembuatan fixture data.

---

## 4. Konvensi Alur Modul & Kontrol Akses (RBAC)

1. **Pemisahan Role**:
   - Operator Sekolah (`user` terikat `sekolah_id`): Hanya dapat melihat dan memanipulasi data sekolahnya sendiri. Tidak diizinkan mengakses data sekolah lain.
   - Admin KCD (`admin` tanpa `sekolah_id`): Memiliki akses pemantauan wilayah, rekapitulasi, penguncian/approval laporan, dan manajemen master data.
2. **Integritas Belanja Modal**:
   - Item barang SPJ yang dimasukkan ke laporan cetak dan rekapitulasi realisasi adalah yang ditandai `is_realisasi = 1`.
   - Cetak laporan harus melalui validasi pre-flight (`/pelaporan-bm/cetak/check`) untuk memastikan tidak mencetak data kosong.
   - Perubahan data pada bulan yang telah berstatus `disetujui` atau dikunci oleh KCD harus diblokir demi akuntabilitas audit.
3. **Standar Keamanan Autentikasi**:
   - Autentikasi menggunakan `username` unik tanpa dependensi email.
   - Password baru wajib rumit (`Password::min(8)->letters()->mixedCase()->numbers()->symbols()`).
   - Rotasi password wajib dilakukan tiap 90 hari melalui dedicated page `/ubah-password` yang diproteksi middleware `EnsurePasswordNotExpired`.
