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
| **Operator Sekolah** | `user` (terikat `sekolah_id`) | Input SPK/barang, alokasi realisasi ke kodering acuan, pantau progres & kekurangan target kodering, ajukan approval bulanan ke KCD. |
| **Admin KCD** | `admin` (tanpa `sekolah_id`) | Unggah pagu acuan sekolah, pantau kepatuhan lapor seluruh sekolah, verifikasi / kunci laporan, kelola master kode barang & pengguna. |

---

## 4. Ruang Lingkup Fitur (Feature Scope)

### F1: Dashboard Peran
- **Admin**: Metrik total target wilayah, total realisasi, sisa anggaran, daftar sekolah selesai vs belum lapor, dan tombol kunci instan.
- **Sekolah**: Banner status laporan bulan sebelumnya, 4 kartu ringkasan belanja, dan tabel riwayat status lapor 12 bulan.

### F2: Manajemen SPJ & Input Realisasi
- **Katalog SPJ**: Pengelompokan dokumen berdasarkan nomor SPK, urut terbaru dulu, dengan pencarian instan.
- **Form SPK Multi-Item**: Seksi dokumen (SP2D, SPK, BA) + accordion item barang dengan pencarian katalog pagu, draft autosave, dan datalist history.
- **Input Realisasi (Target Acuan Kerja)**: Ringkasan per kodering (acuan vs realisasi vs kekurangan), tombol `+` alokasi item SPJ ke kodering (dibatasi sisa anggaran), dan tombol `Kirim Laporan` dengan validasi balance + kunci otomatis.

### F3: Data Realisasi & Cetak Laporan
- **Data Realisasi**: Tabel 19 kolom seluruh baris alokasi realisasi (sumber `pelaporan_bm_realisasi`) dengan filter barang/bulan/tahun dan ekspor CSV.
- **Cetak 26 Kolom**: Pre-flight validation (mencegah unduh data kosong), modal progres simulasi unduh, dan ekspor CSV 26 kolom UTF-8 BOM.

### F4: Pengawasan & Penguncian (Admin KCD)
- **Rekapan Kodering**: Komparasi acuan vs realisasi seluruh sekolah per bulan.
- **Gembok Laporan**: Kunci (`disetujui`) atau buka kunci (`draft`) per sekolah per bulan.

### F5: Master Data
- Master Kode Barang (standar aset pemda, live search, import).
- Master Target Kodering Acuan (input manual & import massal).
- Master Data Sekolah (identitas unit sekolah dan NPSN resmi).
- Kelola Pengguna (manajemen akses akun operator & admin).

### F6: Autentikasi & Keamanan Akun
- **Username-based Login**: Autentikasi murni berbasis `username` (tanpa kolom/fitur email).
- **Akun Standar Operator**: Format username `[npsn]-admin` dengan password default `#SidiptaKCD10`.
- **Rotasi Password & Dedicated Page**: Intersep otomatis via `EnsurePasswordNotExpired` mengarahkan akun ke dedicated page `/ubah-password` saat login perdana (`password_changed_at = null`) dan setiap 90 hari (3 bulan).
- **Kompleksitas Password**: Validasi password baru wajib rumit (min. 8 karakter, kombinasi huruf besar/kecil, angka, dan simbol).

---

## 5. Kriteria Penerimaan Non-Fungsional
- **Keamanan & Isolasi Data**: Sekolah dilarang keras mengakses atau memodifikasi data sekolah lain (enforced via scope `sekolah_id`). Rotasi password wajib dipatuhi setiap 90 hari dengan standar password rumit.
- **Integritas Data Kunci**: Data pada bulan yang terkunci (`disetujui`) tidak dapat diubah oleh operator.
- **Kualitas Kode**: Formatter Pint lolos, TypeScript 0 error, dan PHPUnit line coverage &ge; 80%.
- **Aturan Dokumentasi**: Seluruh dokumentasi dan tautan repo wajib menggunakan relative path.
