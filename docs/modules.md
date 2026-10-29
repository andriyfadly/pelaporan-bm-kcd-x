# Alur & Modul Fungsional Per Role

Sistem Pelaporan Belanja Modal KCD Wilayah X membagi akses fungsional ke dalam
tiga kelompok: **Super Admin**, **Admin KCD**, dan **Operator/Bendahara
Sekolah**, ditambah role khusus **Operator Divisi** untuk Distribusi BHP.

---

## 1. Modul Dashboard (`/dashboard`)

- **Admin KCD**:
  - Filter bulan (1-12) dan tahun.
  - Target = sekolah yang memiliki data acuan di bulan+tahun terpilih (paritas `legacy/index_admin.php`). Bulan tanpa acuan → target 0, **tanpa fallback** ke semua sekolah.
  - "Sudah Realisasi" hanya dihitung dari `Realisasi` final (bukan `Spj` katalog draft), difilter bulan+tahun (`YEAR(ba_tgl/created_at)`).
  - Tabel Sekolah Sudah Realisasi vs Sekolah Belum Realisasi.
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
   - **Import Excel SPJ** (tombol "Import Excel" + modal unggah, hanya tampil untuk sekolah dengan NPSN di daftar allowlist): unggah template `/templates/template_import_spj.xlsx` berisi 13 kolom (No. SP2D, Sumber Perolehan\*, No. SPK/Kwitansi\*, BA NO\*, BA TGL\*, Kode Barang, Merk/Tipe\*, No. Sertifikat/Pabrik/Penerbit, Ukuran/Dimensi Bangunan, Satuan\*, Volume\*, Harga Satuan\*, Jenis\*). Nilai Jenis wajib `Peralatan & Mesin` atau `Buku` dan disimpan ke `kategori`; `nama_barang`/`jenis_aset` tetap di-lookup dari katalog via `kode_barang`. Parsing via `maatwebsite/excel`; **all-or-nothing** (satu baris invalid → seluruh impor dibatalkan dengan pesan no. baris + alasan); baris `no_spk` sama digabung jadi 1 SPK multi-item; limit 5.000 baris; diblokir saat laporan terkunci.
   - Hapus item/SPK ikut menghapus data `pelaporan_bm_realisasi` terkait (transaksi DB) agar tidak ada realisasi yatim.
   - Seluruh mutasi diblokir ketika laporan `status_kunci` aktif atau `status_kirim` `menunggu_approval`/`disetujui` (readonly + gembok).
3. **Form Dokumen SPK Multi-Item (`/pelaporan-bm/spj/create` & `/pelaporan-bm/spj/edit-spk/{no_spk}`)**:
   - Tampilan identik `legacy/data_barang_input.php`: sticky category badge (Peralatan & Mesin / Buku), seksi I Dokumen & Administrasi Keuangan (SP2D, Sumber Dana, SPK, BAST No & Tgl), seksi II & III Detail Item Barang dengan accordion dinamis.
   - Pencarian kode barang live dari katalog pagu (endpoint `/pelaporan-bm/cari-barang`) yang mereplikasi `legacy/ajax_cari_barang.php`: pencarian tidak peka huruf besar/kecil (`ILIKE` via `whereLike`), escape wildcard LIKE, hanya kode leaf (bukan prefix parent), limit 100.
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
   - Setelah `status_kirim` menjadi `menunggu_approval` atau `disetujui`, halaman menjadi read-only untuk Admin KCD dan akun sekolah.
   - Role `super_admin` tetap dapat membuka halaman edit dan melepas alokasi item pada laporan `disetujui`, termasuk ketika `status_kunci = true`. Perubahan hanya menghapus baris realisasi yang dipilih dalam scope bulan+kodering; status laporan tetap `disetujui`.

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
- **Format 26 Kolom**: File Excel (.xlsx) mencakup identitas sekolah, SP2D, SPK, BA Penerimaan, kodering, spesifikasi, volume, dan harga satuan.

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
  - Nonaktifkan/aktifkan user (kolom `users.is_active`): user nonaktif ditolak saat login, semua sesi aktifnya langsung diputus, dan tidak bisa menonaktifkan akun sendiri.
- **Rekapan Sekolah (`/pelaporan-bm/rekapan`)**:
  - Rekapitulasi laporan seluruh sekolah per bulan, perbandingan acuan vs realisasi per kodering, serta toggle gembok laporan.
  - Paritas `legacy/rekapan_admin.php`: baris tabel = sekolah dengan acuan pada bulan terpilih (tanpa fallback ke semua sekolah); realisasi & log fisik dihitung dari baris `pelaporan_bm_realisasi` yang dialokasikan ke acuan (`acuan_id`), bukan dari seluruh SPJ bulan tersebut.
  - Detail drill-down dua tab: REKENING ACUAN (badge SESUAI/BELUM SESUAI ditentukan `kekurangan <= 0`) dan INPUT REALISASI (log fisik dengan kolom bulan realisasi).
  - Aksi admin: ACC Laporan (status → Disetujui) dan Buka Kunci Edit (status → draft) via `/pelaporan-bm/kunci-laporan/status`.
  - `super_admin` memiliki jalur koreksi langsung untuk edit alokasi realisasi yang sudah disetujui; Admin KCD tetap menggunakan alur Buka Kunci Edit sebelum koreksi.

---

## 6. Modul Autentikasi & Rotasi Password (`/ubah-password`)

- **Autentikasi Username**: Login menggunakan `username` (Operator: `[npsn]-admin`, Admin: `admin_kcd`), tanpa verifikasi email.
- **Middleware Interseptor (`EnsurePasswordNotExpired`)**: Mencegah akses ke menu lain jika password operator berstatus kedaluwarsa (`password_changed_at = null` pada login awal, atau berusia &ge; 90 hari).
- **Dedicated Page (`/ubah-password`)**: Halaman khusus mandiri untuk pembaruan password dengan validasi password rumit (min. 8 karakter, huruf besar/kecil, angka, simbol).
- **Update Timestamp**: Menyimpan waktu perubahan password (`password_changed_at = now()`) dan mengembalikan hak akses penuh ke dashboard.
---

## 7. Modul Pencatatan BM (`/pencatatan-bm`)

Alur pembentukan: **SPJ → realisasi → kirim laporan → Admin KCD setujui →
sinkronisasi → unit inventaris**. SPJ/realisasi yang belum disetujui hanya
sumber potensial, bukan inventaris aktif.

- **Dashboard** (`/pencatatan-bm`): total unit, nilai perolehan, sebaran
  kondisi (B/RR/RB), jumlah jenis barang, ruang & pegawai, 10 kode barang
  terbanyak, dan status sinkronisasi terakhir.
- **Inventaris** (`/pencatatan-bm/inventaris`): daftar unit per register,
  filter kondisi, pencarian nama/kode/register/merek, ekspor Excel, tambah
  barang manual, impor Excel/CSV, unduh template, dan halaman detail (ubah
  kondisi, unggah foto, label PDF, QR, BAST, riwayat mutasi).
- **Penyaluran** (`/pencatatan-bm/penyaluran`): penempatan **pertama** barang ke
  ruang/penerima. Hanya unit yang belum punya lokasi yang muncul; ruang wajib,
  penanggung jawab opsional. Barang yang sudah dikembalikan masuk lagi daftar
  ini karena sudah kembali ke gudang.
- **Mutasi** (`/pencatatan-bm/mutasi`): perpindahan **setelah** barang berada di
  lokasi. Tujuan wajib (minimal ruang atau pegawai); mengosongkan lokasi bukan
  mutasi, melainkan pengembalian. Setiap perubahan state meninggalkan satu baris
  histori; `sekolah_id` tidak berubah.
- **Pengembalian** (`/pencatatan-bm/pengembalian`): menutup penyaluran aktif,
  kondisi akhir wajib diisi, histori tetap tersimpan. Unit tanpa BAST bernomor
  tidak bisa dikembalikan (tombol terkunci, backend menolak).
- **Berita Acara** (`/pencatatan-bm/berita-acara`): rekap BAST; nomor bisa
  dikoreksi atau dibatalkan (dengan alasan) tanpa menghapus dokumen. Nomor yang
  masih aktif tidak boleh duplikat; nomor yang sudah dibatalkan boleh dipakai
  ulang.
- **Dokumen** (`/pencatatan-bm/dokumen`): daftar dokumen tersimpan + unduh PDF.
  Cetak langsung: KIR per ruang, buku inventaris dan sensus per tahun.
- **Sinkronisasi** (`/pencatatan-bm/sinkronisasi`, khusus `super_admin`): jalankan
  ulang per periode, lihat log, dan daftar unit yang perlu tinjau manual.

### Sumber unit inventaris

Ada dua jalur masuk yang bermuara ke tabel `inventaris_unit`:

1. **Sinkronisasi realisasi** — satu baris realisasi dengan volume N melahirkan N
   unit. Hanya periode yang sudah `disetujui` Admin KCD yang diproses.
2. **Pendaftaran manual/impor** — operator mengisi barang langsung (atau impor
   Excel/CSV). Kolom mengikuti `template_import_inventaris.xlsx` aplikasi acuan:
   Kode Barang, Nama, Merek/Tipe, Tanggal, Bulan, Tahun, Satuan, Volume, Harga
   Satuan, Asal Perolehan. Impor bersifat all-or-nothing.

### Nomor register

- Format 3 digit: `001`, `002`, …
- **Diulang dari `001` setiap tahun perolehan** — tahun berbeda = urutan baru.
- Unik per `(sekolah_id, kode_barang, tahun_perolehan)`.
- Dialokasikan atomik lewat tabel `inventaris_register_sequences` (dikunci saat
  transaksi), jadi dua proses bersamaan tidak mendapat nomor yang sama.
- Nomor yang sudah terbit tidak pernah dipakai ulang.

### Riwayat perubahan unit

`inventaris_mutasi` menyimpan semua perubahan state dengan `jenis`:

| Jenis | Arti |
|---|---|
| `penyaluran` | penempatan pertama ke ruang/penerima |
| `mutasi` | perpindahan dari satu lokasi ke lokasi lain |
| `pengembalian` | barang kembali ke gudang (lokasi dikosongkan) |
| `koreksi` | perubahan kondisi/keterangan tanpa perpindahan |

Baris histori tidak pernah dihapus dan tidak pernah ditimpa.

Master pendukung ada di **Master Data** (sidebar): `Ruang`, `Pegawai`,
`Jabatan`, dan `Penugasan Pegawai`, terpisah dari `Profil Sekolah`. Menu
`Pejabat Sekolah` dihapus — jabatan dan pemegangnya dikelola lewat dua menu
terakhir.

Hak akses:

| Aksi | Operator/Bendahara | Admin KCD | Super Admin |
|---|---|---|---|
| Lihat dashboard/inventaris/mutasi/pengembalian | ✔ (sekolahnya) | ✔ (lintas sekolah, baca) | ✔ |
| Kelola unit, mutasi, pengembalian | ✔ (sekolahnya) | – | ✔ |
| Kelola master ruang, pegawai, jabatan, penugasan | ✔ (sekolahnya) | – | ✔ |
| Cetak dokumen/label/ekspor | ✔ | ✔ | ✔ |
| Sinkronisasi ulang | – | – | ✔ |

Dokumen memakai kop dari `master_data_sekolah` dan penandatangan dari
`master_data_penugasan_pegawai` yang berlaku pada tanggal dokumen, bukan kop
legacy. Master `master_data_pegawai` hanya identitas; jabatan tidak lagi
disimpan di sana. Data historis legacy tidak dimigrasikan.

---

## 8. Modul Distribusi BHP (`/distribusi-bhp/{bos|bopd}/…`)

Domain terpisah dari Pelaporan BM. Setiap halaman BHP selalu berada di bawah
prefix sumber dana (`bos` atau `bopd`); stok, dokumen, dan laporan tidak pernah
tercampur antar sumber dana. Flat route (tanpa prefix) tetap dilayani untuk
backward compatibility dan default ke `BOS`.

Alur: **penerimaan/saldo awal → stok fisik → pengajuan (reservasi) → submit →
review/approve → dokumen + buku → laporan**.

- **Dashboard** (`/dashboard`): ringkasan pengajuan per status, stok kritis,
  dan aktivitas terakhir. Operator divisi hanya melihat divisinya sendiri.
- **Penerimaan** (`/penerimaan`): catat nota/invoice, BAST, penyedia, titimangsa
  + detail barang. Menambah **stok fisik**. Kwitansi boleh kosong → baris masuk
  daftar **Lunas Bayar**.
- **Lunas Bayar** (`/penerimaan/lunas-bayar`): penerimaan yang barangnya sudah
  datang tetapi transaksi keuangannya belum terjadi. Aksi **Tandai Bayar**
  mengisi No. Kwitansi + Tanggal Pembayaran.
- **Saldo Awal** (`/saldo-awal`): halaman terpisah untuk stok awal tahun;
  tambah manual atau import Excel; hapus per barang (bukan seluruh header).
- **Pengajuan** (`/pengajuan`): form isian dengan autosave di perangkat
  (`localStorage`, per sumber dana). Tombol **Ajukan Pengajuan** membuat record
  dan mereservasi stok dalam satu transaksi; tidak ada tombol/langkah draft
  terpisah dan draft lama tidak ditampilkan di riwayat.
- **Review Pengajuan** (`/review-pengajuan`): approve dengan koreksi
  `jumlah_disetujui`, atau reject dengan alasan. Approve + generate dokumen
  berjalan dalam satu transaksi.
- **Dokumen** (`/pengajuan/{id}/dokumen`): delapan jenis dokumen, PDF satuan atau
  **batch ZIP**. Format per jenis mengikuti template baku (lihat §8.1).
- **Laporan** (`/laporan`): laporan stok 4-tier, buku pengeluaran, buku
  penerimaan, buku penyaluran, kartu persediaan (saldo berjalan), rekonsiliasi
  bulanan, stok opname, dan halaman read-only per jenis dokumen.
- **Master**: kode rekening (termasuk `batch-assign` kode barang), barang +
  riwayat harga + import Excel, penyedia.
- **Divisi** (`/divisi`): buat divisi + akun `operator_divisi` per divisi
  (username `{npsn}-{suffix}`), reset password, aktif/nonaktif, hapus.
- **Settings** (`/settings`): hanya `format_nomor`; ukuran/orientasi kertas
  hardcode per jenis di `DokumenController::PAPER`.

### 8.1 Format cetak (mengikuti `sistem-manajemen-aset`)

| Jenis | Kertas | Orientasi | Kop |
|---|---|---|---|
| Nota Permintaan | A4 | portrait | ✅ |
| Surat Permintaan | A4 | landscape | ❌ |
| Surat Penyaluran (SPPB) | A4 | portrait | ✅ |
| BAST | A4 | portrait | ✅ |
| BAP | A4 | portrait | ✅ |
| Stok Opname | **F4** (210 × 330 mm) | portrait | ✅ |
| Buku Pengeluaran | A4 | landscape | ❌ |
| Buku Penyaluran | A4 | landscape | ❌ |
| Buku Penerimaan | A4 | landscape | ❌ |

Perbedaan ini **disengaja** — legacy memang tidak menyeragamkan kop. Dikunci
`BhpPdfFormatTest` lewat `MediaBox` PDF jadi, bukan lewat konfigurasi.

Tanggal pada dokumen selalu bahasa Indonesia (`30 Juni 2026`, `Selasa`) karena
`Carbon::setLocale('id')` dipasang global; kolom tanggal pada tabel buku memakai
`dd-mm-yyyy`.

Hak akses: lihat `docs/security.md` §1 (11 permission BHP).
