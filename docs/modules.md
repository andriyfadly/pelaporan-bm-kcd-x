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
   - Menampilkan No SPK, Sumber Dana (BOS Reguler/Kinerja), No BA & Tanggal BA, plus label `SPK:` dan `Tgl: d/m/Y` pada sel dokumen.
   - Rincian item barang: Nama, Kode Barang, Merk/Tipe, Volume, Satuan, Harga Satuan, Sub Perolehan.
   - Badge ringkasan: Total SPJ Bulan (berkas) dan Total Barang (item).
   - Urutan dokumen terbaru lebih dulu (identik legacy `ORDER BY id DESC`).
   - Fitur Live Search instan (SPK, Nama Barang, Merk/Tipe).
   - Aksi Tambah SPJ (modal pilihan kategori: Peralatan & Mesin vs Buku), Edit SPK, dan Hapus Item/SPK.
   - Hapus item/SPK ikut menghapus data `pelaporan_bm_realisasi` terkait (transaksi DB) agar tidak ada realisasi yatim.
   - Seluruh mutasi diblokir ketika laporan `status_kunci` aktif atau `status_kirim` `menunggu_approval`/`disetujui` (readonly + gembok).
3. **Form Dokumen SPK Multi-Item (`/pelaporan-bm/spj/create` & `/pelaporan-bm/spj/edit-spk/{no_spk}`)**:
   - Tampilan identik `legacy/data_barang_input.php`: sticky category badge (Peralatan & Mesin / Buku), seksi I Dokumen & Administrasi Keuangan (SP2D, Sumber Dana, SPK, BAST No & Tgl), seksi II & III Detail Item Barang dengan accordion dinamis.
   - Pencarian kode barang live dari katalog pagu (endpoint `/pelaporan-bm/cari-barang`) yang mereplikasi `legacy/ajax_cari_barang.php`: matching lowercase, escape wildcard LIKE, hanya kode leaf (bukan prefix parent), limit 100.
   - Draft autosave localStorage per sekolah+bulan (`draft_spj_barang_{sekolah_id}_{bulan}` + suffix no_spk saat edit): pulih otomatis saat halaman dimuat, terhapus saat simpan sukses.
   - Datalist history autocomplete untuk Merk/Tipe, No Sertifikat, dan Satuan dari nilai unik yang sudah diketik (identik legacy `history_merk`/`history_sertifikat`/`history_satuan`).
   - Mode kategori **Buku**: default Jenis Aset = `Buku`, No Sertifikat wajib diisi (validasi client & server).
   - Validasi ketat BA No/BA Tgl, merk/tipe, satuan, volume &gt; 0, harga &gt; 0, dan total akumulasi realisasi.
   - **Sync snapshot alokasi**: edit SPJ ikut memperbarui baris realisasi yang teralokasi dari item tersebut (nama, merk, volume, harga, nilai — identik `legacy/proses_simpan_barang.php`). Kolom milik alokasi (`kodering_belanja`, `acuan_id`, `bulan_realisasi`) tidak disentuh.
4. **Pilih Bulan Input Realisasi (`/pelaporan-bm/input-realisasi/pilih-bulan`)**:
   - Pemilihan bulan untuk alokasi realisasi (identik `legacy/pilih_bulan.php`); pilihan terakhir diingat via localStorage.
5. **Input Realisasi Target Acuan (`/pelaporan-bm/input-realisasi?bulan_realisasi={n}`)**:
   - Rekapitulasi target acuan vs realisasi per kode rekening (identik `legacy/input_realisasi.php`).
   - Accordion daftar uraian belanja per kodering, nominal acuan, realisasi, dan kekurangan.
   - Tombol alokasi SPJ ke kodering dan edit alokasi; alokasi dibatasi sisa anggaran kodering (diblokir bila melebihi).
   - Panel status pengajuan laporan di bagian bawah: tombol Kirim Laporan ke KCD Wilayah X yang hanya aktif jika total kekurangan anggaran terpenuhi (= 0), dikonfirmasi via ConfirmDialog. Mengubah status menjadi `menunggu_approval` + mengunci laporan.

---

## 3. Modul Data Realisasi (`/pelaporan-bm/realisasi`)

Sumber data tabel `pelaporan_bm_realisasi` (identik `legacy/data_realisasi.php` yang membaca `realisasi_barang_sekolah`):

- Tabel 19 kolom: No, SP2D, Sumber Perolehan, Kodering Belanja, No SPK, BA Penerimaan (No/Tgl/Bln/Thn), Bulan Realisasi, Kode Barang, dan Rincian Barang (Nama, Merk/Tipe, No Sertifikat, Ukuran, Satuan, Volume, Harga Satuan) + Nilai Perolehan.
- Urutan terbaru dulu (`ORDER BY ba_tgl DESC, id DESC`); pagination 25 baris.
- Filter komprehensif: nama/kode barang, filter bulan, filter tahun anggaran (tahun dinamis dari data `ba_tgl`).
- Kartu Total Nilai Perolehan (tersaring) beserta jumlah baris.
- Ekspor XLSX via `maatwebsite/excel` (`RealisasiBmExport`): 25 kolom A–Y + judul + sheet `KODE BARANG` + formula VLOOKUP/Nilai Perolehan/Penyusutan, samakan `legacy/data_realisasi.php`.
- Unduh template import Excel statis (`/templates/template_import_inventaris.xlsx`).

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
