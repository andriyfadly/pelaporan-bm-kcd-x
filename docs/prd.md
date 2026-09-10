# Product Requirements Document (PRD): Sistem Pelaporan Belanja Modal KCD Wilayah X

## 1. Latar Belakang & Masalah
Sekolah negeri (SMKN, SMAN, SLBN) di lingkungan Cabang Dinas Pendidikan Wilayah X memiliki target belanja modal tahunan berbasis kodering rekening. Pengelolaan manual atau parsial berisiko menyebabkan selisih antara target acuan belanja dan realisasi aset fisik, keterlambatan pelaporan bulanan, serta format pelaporan yang tidak seragam saat rekapitulasi dinas.

---

## 2. Tujuan Produk (Product Goals)
1. **Sentralisasi SPJ & Fisik Barang**: Dokumentasi digital terpusat untuk dokumen kontrak (SPK/SP2D) dan rincian item barang fisik.
2. **Kesesuaian Target Acuan vs Realisasi**: Memastikan belanja modal per kodering rekening termonitor secara real-time (Target, Realisasi, Kekurangan).
3. **Standarisasi Cetak Laporan**: Menghasilkan Berita Acara Rekapitulasi Belanja Modal format resmi 26 Kolom (CSV/Excel).
4. **Akuntabilitas & Kontrol**: Penguncian laporan bulanan oleh KCD untuk menjamin validitas data audit.

---

## 3. Persona & Peran Pengguna

| Role | Identifikasi | Kebutuhan Utama |
|---|---|---|
| **Operator Sekolah** | `user` (terikat `sekolah_id`) | Input SPK/barang, checklist barang realisasi, pantau progres target kodering acuan, ajukan approval bulanan ke KCD. |
| **Admin KCD** | `admin` (tanpa `sekolah_id`) | Unggah pagu acuan sekolah, pantau kepatuhan lapor seluruh sekolah, verifikasi / kunci laporan, kelola master kode barang & pengguna. |

---

## 4. Ruang Lingkup Fitur (Feature Scope)

### F1: Dashboard Peran
- **Admin**: Metrik total target wilayah, total realisasi, sisa anggaran, daftar sekolah selesai vs belum lapor, dan tombol kunci instan.
- **Sekolah**: Banner status laporan bulan sebelumnya, 4 kartu ringkasan belanja, dan tabel riwayat status lapor 12 bulan.

### F2: Manajemen SPJ & Input Realisasi
- **Katalog SPJ**: Pengelompokan dokumen berdasarkan nomor SPK. Input item belanja fisik, penanda `is_realisasi`, dan tombol `Kirim Laporan`.
- **Target Acuan Kerja (`?mode=realisasi`)**: Ringkasan per kodering, status selesai/kurang, dan tombol cepat `+ Input` yang otomatis memuat kodering terpilih.

### F3: Data Realisasi & Cetak Laporan
- **Data Realisasi**: Tabel agregasi seluruh barang dengan status `is_realisasi = 1`.
- **Cetak 26 Kolom**: Pre-flight validation (mencegah unduh data kosong), modal progres simulasi unduh, dan ekspor CSV 26 kolom UTF-8 BOM.

### F4: Pengawasan & Penguncian (Admin KCD)
- **Rekapan Kodering**: Komparasi acuan vs realisasi seluruh sekolah per bulan.
- **Gembok Laporan**: Kunci (`disetujui`) atau buka kunci (`draft`) per sekolah per bulan.

### F5: Master Data
- Master Kode Barang (standar aset pemda, live search, import).
- Master Target Kodering Acuan (input manual & import massal).
- Kelola Pengguna (manajemen akses akun operator & admin).

---

## 5. Kriteria Penerimaan Non-Fungsional
- **Keamanan & Isolasi Data**: Sekolah dilarang keras mengakses atau memodifikasi data sekolah lain (enforced via scope `sekolah_id`).
- **Integritas Data Kunci**: Data pada bulan yang terkunci (`disetujui`) tidak dapat diubah oleh operator.
- **Kualitas Kode**: Formatter Pint lolos, TypeScript 0 error, dan PHPUnit line coverage &ge; 80%.
- **Aturan Dokumentasi**: Seluruh dokumentasi dan tautan repo wajib menggunakan relative path.
