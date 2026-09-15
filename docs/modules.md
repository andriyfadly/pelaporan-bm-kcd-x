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

## 2. Modul Data Barang & SPJ (`/pelaporan-bm/spj`)

Khusus role Operator Sekolah dengan alur yang identik dengan legacy:

1. **Pilih Bulan Data Barang (`/pelaporan-bm/spj/pilih-bulan`)**:
   - Menu awal pemilihan bulan realisasi sebelum masuk katalog belanja (identik `legacy/pilih_bulan_data_barang.php`).
2. **Katalog Dokumen SPJ (`/pelaporan-bm/spj?bulan={n}`)**:
   - Grup dokumen berdasarkan Nomor SPK (identik `legacy/data_barang.php`).
   - Menampilkan No SPK, Sumber Dana (BOS Reguler/Kinerja), No BA & Tanggal BA.
   - Rincian item barang: Nama, Kode Barang, Merk/Tipe, Volume, Satuan, Harga Satuan, Sub Perolehan.
   - Fitur Live Search instan (SPK, Nama Barang, Merk/Tipe).
   - Aksi Tambah SPJ (modal pilihan kategori: Peralatan & Mesin vs Buku), Edit SPK, dan Hapus Item/SPK.
3. **Form Dokumen SPK Multi-Item (`/pelaporan-bm/spj/create` & `/pelaporan-bm/spj/edit-spk/{no_spk}`)**:
   - Tampilan identik `legacy/data_barang_input.php`: sticky category badge (Peralatan & Mesin / Buku), seksi I Dokumen & Administrasi Keuangan (SP2D, Sumber Dana, SPK, BAST No & Tgl), seksi II & III Detail Item Barang dengan accordion dinamis.
   - Pencarian kode barang live dari katalog pagu yang mengisi otomatis field readonly (Kode Barang, Nama Barang, Jenis Aset).
   - Validasi ketat BAST, nomor sertifikat untuk kategori buku, dan total akumulasi realisasi.
4. **Pilih Bulan Input Realisasi (`/pelaporan-bm/input-realisasi/pilih-bulan`)**:
   - Pemilihan bulan untuk alokasi realisasi (identik `legacy/pilih_bulan.php`).
5. **Input Realisasi Target Acuan (`/pelaporan-bm/input-realisasi?bulan_realisasi={n}`)**:
   - Rekapitulasi target acuan vs realisasi per kode rekening (identik `legacy/input_realisasi.php`).
   - Accordion daftar uraian belanja per kodering, nominal acuan, realisasi, dan kekurangan.
   - Tombol alokasi SPJ ke kodering dan edit alokasi.
   - Panel status pengajuan laporan di bagian bawah: tombol Kirim Laporan ke KCD Wilayah X yang hanya aktif jika seluruh kekurangan anggaran terpenuhi (= 0). Mengubah status menjadi `menunggu_approval`.

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
