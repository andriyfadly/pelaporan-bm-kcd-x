# Alur & Modul Fungsional Per Role

Sistem Pelaporan Belanja Modal KCD Wilayah X membagi akses fungsional ke dalam tiga kelompok: **Super Admin**, **Admin KCD**, dan **Operator/Bendahara Sekolah**.

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

## 7. Modul Distribusi BHP (`/distribusi-bhp`)

Domain **Barang Habis Pakai** yang berdiri sendiri, terpisah dari Pelaporan BM
(tidak berbagi tabel transaksi/status/stok). Menu BHP muncul sebagai section
terpisah di navigasi (`AppLayout`).

**Sumber dana.** Setiap data terikat `BOS` atau `BOPD`. BOS dan BOPD tidak
pernah tercampur pada stok maupun laporan; stok dihitung per sumber dana.
Middleware `SetSumberDanaBhp` bersifat **fail-closed**: `sumber_dana` invalid
ditolak 422, default `BOS` hanya bila parameter tidak dikirim. State sumber dana
dipulihkan setelah request agar tidak bocor antar-request.

**Stok 4-tier.** `Tersedia = Fisik − Terpakai − Reservasi` (di-`max(0,…)`).
`Fisik` dari penerimaan, `Terpakai` dari pengajuan `approved`, `Reservasi` dari
pengajuan `submitted`. Semua mutasi lewat `StokService` dengan `lockForUpdate`;
laporan stok memakai agregasi grouped (`rekapPerBarang`, 3 query untuk semua
barang).

**Tanpa pool pusat.** Stok selalu **milik satu sekolah** — tidak ada penerimaan
"bersama" tanpa sekolah. `bhp_{bos,bopd}_penerimaan.sekolah_id` **NOT NULL**,
dan semua query stok/laporan di-scope **tepat** ke sekolah pemakainya (bukan
`orWhereNull`). Konsekuensinya: sekolah A tidak pernah melihat/mengonsumsi
penerimaan sekolah B, dan dua sekolah tidak bisa meng-oversell stok yang sama.

**Dashboard BHP.** Statistik pengajuan difilter `divisi_id` untuk operator
divisi (hanya divisinya sendiri); operator sekolah/admin melihat seluruh divisi
di sekolahnya. `totalPenerimaan` tetap scope sekolah karena stok bukan milik
divisi.

### 7.1 Master Data

- **Kode Rekening (`/distribusi-bhp/master/kode-rekening`)**: unik per
  `(kode, sumber_dana)`.
- **Barang (`/distribusi-bhp/master/barang`)**: kode, nama, spesifikasi,
  satuan, kode rekening, sumber dana; riwayat harga (harga terakhir tampil).
- **Penyedia (`/distribusi-bhp/master/penyedia`)**: nama unik, alamat/telepon/email.
- Permission: `kelola-master-bhp`.

### 7.2 Penerimaan

- **`/distribusi-bhp/penerimaan`**: catat penerimaan barang (nota/invoice,
  BAST, kwitansi, penyedia, titimangsa) + detail barang & jumlah. Barang masuk
  menambah stok fisik. Mendukung penandaan **saldo awal** (`is_saldo_awal`,
  `periode_tahun`).
- Permission: `kelola-penerimaan-bhp`.
- **Saldo awal** punya **halaman terpisah** `/distribusi-bhp/saldo-awal`
  (pola sistem-manajemen-aset): filter sumber dana + periode, kartu statistik
  (jumlah barang, total unit, total nilai saldo — diagregasi di database via
  `count()`/`sum()`, bukan dihitung di PHP), tabel per barang, tambah
  manual, hapus per barang dengan konfirmasi, serta **import Excel** +
  **unduh template** (all-or-nothing, maksimal 2000 baris, throttle 10/menit).
  Daftar periode yang tersedia (`availablePeriods`) difilter ke sumber dana
  aktif — periode yang hanya memiliki data dana lain tidak muncul.
- **Saldo awal** adalah flow khusus (`SaldoAwalService`), bukan sekadar flag:
  - kombinasi `is_saldo_awal × periode_tahun` wajib konsisten — saldo awal
    wajib menyebut periode (2000–2100), penerimaan biasa dilarang mengisinya;
  - saldo awal **wajib terikat satu sekolah** (tidak ada pool pusat). Satu
    header saldo awal menampung **seluruh barang** untuk periode tersebut;
    keunikan (sekolah + periode, per sumber dana) dijaga aplikasi **dan** partial
    unique index `bhp_{dana}_penerimaan_saldo_awal_unique`
    `ON bhp_{dana}_penerimaan (sekolah_id, periode_tahun) WHERE is_saldo_awal`
    sebagai jaring terakhir terhadap race concurrent. Duplikat barang dalam satu
    request tetap ditolak;
  - **`DELETE /distribusi-bhp/saldo-awal/{detail}`** menghapus **satu barang**
    saja (`deleteDetail`); barang lain pada header yang sama tetap utuh. Header
    ikut terhapus hanya bila detailnya habis, agar tidak meninggalkan saldo awal
    kosong;
  - **`DELETE /distribusi-bhp/penerimaan/{penerimaan}`** menghapus **seluruh**
    saldo awal (header + semua detail). Keduanya hanya untuk saldo awal (bukan
    penerimaan biasa), milik sekolah sendiri, dan ditolak bila barangnya sudah
    dipakai pengajuan `submitted`/`approved` — dicek per sekolah **dan** sumber
    dana, sehingga pengajuan dana lain tidak mengunci. Atomic + activity log
    `kelola-saldo-awal` / `hapus-saldo-awal`;
  - **Import** memvalidasi seluruh baris sebelum menyentuh DB (all-or-nothing):
    kode kosong, kode tak dikenal, sumber dana salah, barang ganda, saldo awal
    sudah ada, jumlah bukan bilangan bulat (`1.5` ditolak, bukan dianggap `15`),
    harga bukan bilangan bulat/negatif, dan batas 2000 baris. Pemisah ribuan
    (`1.500`) diterima; baris kosong (trailing) diabaikan; berkas tak terbaca
    atau bukan `.xlsx`/`.xls` ditolak dengan pesan, bukan error server.

### 7.3 Pengajuan

- **`/distribusi-bhp/pengajuan`**: halaman satu-atap dengan form buat draft
  (sumber dana, tanggal, daftar barang & jumlah diminta; kolom barang
  menampilkan **stok tersedia** per sekolah), tabel pengajuan, modal **Detail**,
  aksi **Edit** draft, **Hapus** draft, dan **Submit** yang memvalidasi stok
  tersedia dan membuat **reservasi**.
- **`PUT /distribusi-bhp/pengajuan/{pengajuan}`** mengganti tanggal + seluruh
  detail draft; **`DELETE`** menghapus draft. Keduanya **hanya untuk draft** —
  pengajuan `submitted`/`approved`/`rejected` ditolak (reservasi sudah terbentuk;
  perubahan harus lewat reject lalu pengajuan baru). Keduanya atomic dengan
  `lockForUpdate` agar edit bersamaan tidak saling menimpa.
- **Validasi divisi**: `divisi_id` selalu diambil dari user login, dan usernya
  wajib berdivisi **milik sekolah pengajuan** (ditolak bila divisi lintas
  sekolah). Barang pada satu pengajuan wajib se-sumber dana dengan pengajuannya.
- Permission: lihat `lihat-distribusi-bhp`; buat/edit/hapus/submit
  `kelola-pengajuan-bhp`.
- Role `operator_divisi` hanya sampai di sini (tanpa approve).

### 7.4 Review & Approval

- **`/distribusi-bhp/review-pengajuan`**: admin membuka pengajuan `submitted`,
  lalu **Approve** (boleh mengoreksi `jumlah_disetujui`) atau **Reject**
  (wajib alasan).
- **Gate pejabat**: approval ditolak (status tetap `submitted`, reservasi utuh,
  tanpa dokumen) bila salah satu dari kepala sekolah, wakasek sarpras, atau
  pembantu pengurus barang belum terisi/berlaku pada tanggal pengajuan. Halaman
  review menampilkan peringatan pejabat yang belum lengkap.
- Approve dan pembuatan dokumen berjalan dalam **satu transaction**: reservasi
  dikonversi ke pemakaian, status `approved`, dan dokumen wajib (nota
  permintaan, surat permintaan, surat penyaluran) digenerate dengan nomor atomic
  plus **snapshot penandatangan** di payload (immutable terhadap pergantian
  pejabat).
- **Tanpa self-approval**: `operator_sekolah` tidak diberi `review-pengajuan-bhp`
  /`approve-pengajuan-bhp`; `ReviewPengajuanController` juga memasang guard
  `pastikanBukanPembuat()` yang menolak approve/reject bila reviewer adalah
  pembuat pengajuan (403).
- Permission: lihat `review-pengajuan-bhp`; approve/reject `approve-pengajuan-bhp`
  (super_admin/admin_kcd saja).
- **Hardening approval**: sumber dana barang direvalidasi saat approve (bukan
  hanya saat submit), semua barang terlibat dikunci `lockForUpdate` dengan
  urutan ID deterministik (anti-deadlock, anti-oversell concurrent), payload
  approval ditolak bila memuat `detail_id` asing atau quantity non-integer/
  melebihi diminta.

### 7.5 Pejabat Sekolah (`/master-data/pejabat`)

- Master penandatangan dokumen per sekolah: kepala sekolah, wakasek sarana
  prasarana, pembantu pengurus barang — nama, pangkat/golongan, NIP, jenis
  (definitif/Plt./Plh.), dan masa jabatan.
- Riwayat pejabat terdahulu tetap tersimpan; pejabat aktif tunggal per jabatan
  (partial unique index). Menambah pejabat aktif otomatis menutup masa jabatan
  pejabat lama (H-1); periode tumpang tindih ditolak.
- Indikator kelengkapan tiga jabatan; approval pengajuan menuntut kelengkapan
  ini pada tanggal pengajuan (mendukung approval backdate via riwayat masa
  jabatan).

### 7.6 Dokumen & PDF (`/distribusi-bhp/pengajuan/{id}/dokumen`)

- Daftar dokumen per pengajuan dengan nomor resmi. Format nomor:
  `{urut:3}/BHP/{kode}/{sumber_dana}/{bulan_romawi}/{tahun}`, counter atomic
  per (jenis, sumber dana, tahun, bulan) — aman untuk request bersamaan
  termasuk saat row sequence pertama kali dibuat (unique constraint + retry).
- **Unduh PDF** per dokumen, atau **Batch PDF** (semua dokumen dalam satu file;
  histori generasi tersimpan di `bhp_{bos,bopd}_batch_dokumen` dengan status
  selesai/sebagian/gagal).
- **Dokumen tambahan**: BAST, BAP, Stok Opname, Buku Pengeluaran, Buku
  Penyaluran — idempotent per jenis (nomor tidak berubah saat dibuat ulang),
  dengan snapshot laporan pada payload. Snapshot **ter-scope sekolah
  pengajuan**: stok opname/buku pengeluaran sekolah A tidak pernah memuat data
  sekolah lain pada sumber dana yang sama.
- Akses dokumen dua lapis: cek `sekolah_id` (lintas tenant 403) dan
  `divisi_id` (operator divisi hanya untuk pengajuan divisinya sendiri, 403
  lintas divisi).
- Permission: lihat `lihat-distribusi-bhp`; cetak/export `cetak-dokumen-bhp`.

### 7.7 Laporan (`/distribusi-bhp/laporan`)

- **Laporan Stok**: tabel 4-tier per barang untuk sumber dana terpilih
  (agregasi grouped `rekapPerBarang` — tetap cepat untuk katalog besar).
- **Buku Pengeluaran**: item yang sudah approved beserta keperluan.
- **Kartu Barang**: profil barang + mutasi masuk/keluar (tanpa saldo berjalan).
- **Kartu Persediaan (`/distribusi-bhp/laporan/kartu-persediaan`)**: mutasi
  masuk/keluar kronologis dengan **saldo berjalan** per barang; pada tanggal
  sama, masuk didahulukan; hanya mutasi sumber dana terkait yang dihitung
  (filter sumber dana + sekolah di level query). `barang_id` divalidasi
  `uuid` + `exists`; barang lintas sumber dana pada export menghasilkan
  redirect + flash error, bukan error server.
- **Export Excel**: jenis `stok`, `pengeluaran`, atau `kartu_persediaan`
  (wajib `barang_id`).
- Permission: lihat `lihat-distribusi-bhp`; export `cetak-dokumen-bhp`.

### 7.8 Settings BHP (`/distribusi-bhp/settings`)

- Format nomor dokumen + preferensi PDF (ukuran kertas & orientasi), **satu
  baris per sumber dana** (tabel `bhp_{bos,bopd}_settings` dengan partial unique
  index `is_singleton`). Settings BOS dan BOPD sepenuhnya independen.
- Validasi format: wajib memuat placeholder `{urut:N}` (agar nomor tidak
  duplikat), hanya placeholder yang dikenal diterima, dan kurung kurawal
  berpasangan.
- Permission: `kelola-master-bhp`.

### 7.9 Impor Data Legacy

- `DistribusiBhpImportSeeder` mengimpor master (kode rekening, penyedia,
  barang, harga) + penerimaan/detail dari DB SQLite aplikasi sumber. Melewati
  baris soft-deleted dan detail keranjang (`cart_status != 'saved'`), dan
  idempotent. Karena tidak ada pool pusat, penerimaan hasil impor diikatkan ke
  satu sekolah tujuan (`setSekolahId()` atau sekolah pertama); bila tidak ada
  sekolah, impor penerimaan dilewati dengan peringatan. Detail: `docs/api.md` §I.
