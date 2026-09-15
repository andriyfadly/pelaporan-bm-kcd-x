# Dokumentasi Sistem Pelaporan Belanja Modal (KCD Wilayah X)

Sistem aplikasi web modern berbasis Laravel 12 dan Inertia.js (React + TypeScript) untuk pengelolaan, pencatatan SPJ, verifikasi, rekapan, dan pelaporan belanja modal sekolah di lingkungan Cabang Dinas Pendidikan Wilayah X.

---

## Daftar Isi Dokumentasi

1. [Product Requirements Document (PRD)](prd.md)
   - Latar belakang, sasaran produk, batasan sistem, personas, dan feature scope F1-F5.
2. [Panduan Penggunaan (User Manual)](user-guide.md)
   - Alur operasional Operator Sekolah (SPJ, alokasi realisasi, cetak) dan Admin KCD (monitoring & kunci).
3. [Panduan Instalasi & Setup](setup.md)
   - Kebutuhan sistem, instalasi lokal, perintah verifikasi kualitas, dan deployment produksi.
4. [Arsitektur & Konfigurasi](architecture.md)
   - Teknologi stack (Laravel 12, Inertia React, TypeScript, Tailwind CSS), struktur folder, dan skema data.
5. [Alur & Modul Per Role](modules.md)
   - Detail alur Dashboard, Data Barang (Buku SPJ), Input Realisasi (Target Acuan Kerja), Data Realisasi, Cetak 26 Kolom, dan Rekapan.
6. [Spesifikasi Endpoint & Kontrak Data](api.md)
   - Ringkasan rute web, controller, middleware, dan payload request/response.
7. [Panduan Developer & Aturan Kerja](guidelines.md)
   - Standar formatting (Pint), pengujian (PHPUnit coverage &ge; 80%), dan aturan ketat relative path.

---

## Ringkasan Peran (Roles)

| Role | Akses Utama | Tanggung Jawab |
|---|---|---|
| **Admin KCD** (`admin_kcd` / tanpa `sekolah_id`) | Dashboard KCD, Master Kode Barang, Acuan Belanja Modal, Rekapan Sekolah, Kelola User, Cetak Laporan | Mengelola master kode barang, upload target acuan sekolah, memantau kepatuhan lapor sekolah, verifikasi / approval, dan mengunci laporan bulanan. |
| **Operator Sekolah** (`[npsn]-admin` / terikat `sekolah_id`) | Dashboard Sekolah, Data Barang (Buku SPJ), Input Realisasi (Target Acuan Belanja), Data Realisasi, Cetak Laporan, Ubah Password | Menginput dokumen SPK & barang belanja, mengalokasikan realisasi ke kodering acuan, memantau kekurangan anggaran, kirim laporan bulanan ke dinas. Wajib ubah password pada login perdana dan berkala tiap 3 bulan. |
