# Alur & Modul Fungsional Per Role

Sistem Pelaporan Belanja Modal KCD Wilayah X membagi akses ke dalam 2 peran utama: **Admin KCD** dan **Operator Sekolah**.

---

## 1. Modul Dashboard (`/dashboard`)

- **Admin KCD**:
  - Filter bulan (1-12) dan tahun.
  - Kartu ringkasan wilayah: Total Target Acuan, Total Realisasi, Sisa Anggaran.
  - Tabel Sekolah Selesai Lapor vs Sekolah Belum Selesai Lapor.
  - Aksi langsung Kunci / Buka Gembok Laporan sekolah.
- **Operator Sekolah**:
  - Banner Status Laporan Bulan Lalu (SELESAI / BELUM SELESAI).
  - 4 Kartu Metrik: Target Acuan Bulan Berjalan, Total Realisasi, Total Aset Fisik, Jumlah Berkas SPK.
  - Tabel Rekapitulasi Tahunan (Bulan 1 s/d 12) dengan status approval per bulan.

---

## 2. Modul Data Barang & Buku SPJ (`/pelaporan-bm/spj`)

Khusus role Operator Sekolah dengan dua tab terintegrasi:

1. **Katalog Dokumen SPJ (`/pelaporan-bm/spj`)**:
   - Grup dokumen berdasarkan Nomor SPK.
   - Menampilkan No SPK, Sumber Dana (BOS Reguler/Kinerja), No BA & Tanggal BA.
   - Rincian item barang: Nama, Kode Barang, Merk/Tipe, Volume, Satuan, Harga Satuan, Sub Perolehan.
   - Checkbox realisasi (`is_realisasi`) untuk memasukkan barang ke laporan resmi.
   - Fitur Live Search instan (SPK, Nama Barang, Merk/Tipe).
   - Aksi Tambah SPJ (modal pilihan kategori: Peralatan & Mesin vs Buku), Edit, dan Hapus Item/SPK.
   - Tombol Kirim Laporan ke KCD Wilayah X (mengubah status ke `menunggu_approval`).
2. **Target Acuan Kerja Realisasi (`/pelaporan-bm/spj?mode=realisasi`)**:
   - Tabel ringkasan kodering anggaran belanja modal periode bulan aktif.
   - Menampilkan Kode Rekening (accordion daftar uraian pekerjaan), Nilai Acuan, Realisasi, dan Kekurangan.
   - Indikator status: Badge `Selesai` jika target terpenuhi (&le; 0), atau tombol `+ Input` yang otomatis membuka form input SPJ dengan kodering acuan tersebut terpilih.

---

## 3. Modul Data Realisasi (`/pelaporan-bm/realisasi`)

- Menampilkan seluruh item barang fisik yang sudah berstatus realisasi (`is_realisasi = 1`).
- Filter komprehensif: nama/kode barang, filter bulan, filter tahun anggaran.
- Ekspor CSV data realisasi sekolah.

---

## 4. Modul Cetak Laporan (`/pelaporan-bm/cetak`)

- Format cetak Berita Acara Rekapitulasi Belanja Modal standar KCD Wilayah X.
- **Pre-Flight Validation**: Sistem mengecek kelayakan data via endpoint `POST /pelaporan-bm/cetak/check`. Jika tidak ada data realisasi pada bulan/sekolah yang dipilih, muncul modal alert dan unduhan dicegah.
- **Progress Unduh**: Modal simulasi progress unduh melingkar (0-100%).
- **Format 26 Kolom**: File CSV UTF-8 dengan BOM kompatibel Microsoft Excel mencakup identitas sekolah, SP2D, SPK, BA Penerimaan, kodering, spesifikasi, volume, dan harga satuan.

---

## 5. Modul Master & Administrasi (Admin KCD)

- **Target Acuan Belanja Modal (`/pelaporan-bm/acuan`)**:
  - Input manual kodering acuan atau import massal template Excel/CSV.
  - Filter bulan dan filter sekolah.
- **Master Kode Barang (`/admin/kode-barang`)**:
  - Database master kode barang standar pemerintah daerah.
  - Import massal dan pencarian live search.
- **Kelola User (`/admin/user`)**:
  - Manajemen akun operator sekolah dan administrator.
- **Rekapan Sekolah (`/pelaporan-bm/rekapan`)**:
  - Rekapitulasi laporan seluruh sekolah per bulan, perbandingan acuan vs realisasi per kodering, serta toggle gembok laporan.

---

## 6. Modul Autentikasi & Rotasi Password (`/ubah-password`)

- **Autentikasi Username**: Login menggunakan `username` (Operator: `[npsn]-admin`, Admin: `admin_kcd`), tanpa verifikasi email.
- **Middleware Interseptor (`EnsurePasswordNotExpired`)**: Mencegah akses ke menu lain jika password operator berstatus kedaluwarsa (`password_changed_at = null` pada login awal, atau berusia &ge; 90 hari).
- **Dedicated Page (`/ubah-password`)**: Halaman khusus mandiri untuk pembaruan password dengan validasi password rumit (min. 8 karakter, huruf besar/kecil, angka, simbol).
- **Update Timestamp**: Menyimpan waktu perubahan password (`password_changed_at = now()`) dan mengembalikan hak akses penuh ke dashboard.
