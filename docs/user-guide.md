# Panduan Penggunaan (User Manual)

Panduan operasional sistem Pelaporan Belanja Modal Cabang Dinas Pendidikan Wilayah X.

---

## 1. Peran & Hak Akses

| Peran | Akses Halaman | Kredensial & Scope |
|---|---|---|
| **Operator Sekolah** | Dashboard Sekolah, Data Barang (Buku SPJ), Input Realisasi (Target Acuan), Data Realisasi, Cetak Laporan, Ubah Password, **Distribusi BHP (master, penerimaan, pengajuan, review, dokumen, laporan)** | Username `[npsn]-admin`, default password `#SidiptaKCD10`. Terikat satu sekolah (`sekolah_id`). Wajib ganti password saat login pertama dan berkala tiap 3 bulan. |
| **Admin KCD** | Dashboard Wilayah, Master Kode Barang, Acuan Belanja Modal, Rekapan Sekolah, Kelola Pengguna, Cetak Laporan, **Distribusi BHP** | Username `admin_kcd`. Akun `admin` tanpa `sekolah_id`. Memiliki wewenang lintas sekolah dan kunci laporan. |
| **Operator Divisi** | **Distribusi BHP: Pengajuan saja** (buat & submit) | Role khusus BHP. Tanpa akses master, penerimaan, approval, atau cetak. |
| **Bendahara Sekolah** | Dashboard Sekolah, Data Barang (Buku SPJ), Input Realisasi, Data Realisasi, Cetak Laporan, **Distribusi BHP (lihat + pengajuan)** | Terikat satu sekolah; tanpa approval/cetak BHP. |

---

## 2. Alur Login & Keamanan Password

1. **Login Perdana**:
   - Masuk menggunakan username `[npsn]-admin` dan password default `#SidiptaKCD10`.
   - Sistem secara otomatis mendeteksi akun baru (`password_changed_at = null`) dan langsung mengarahkan pengguna ke **Dedicated Page Ubah Password** (`/ubah-password`).
2. **Aturan Password Baru**:
   - Wajib memenuhi kriteria rumit: minimal 8 karakter, kombinasi huruf besar, huruf kecil, angka, dan karakter simbol/tanda baca.
   - Tidak boleh sama dengan password saat ini.
3. **Masa Berlaku Berkala**:
   - Password kedaluwarsa setelah 90 hari (3 bulan).
   - Pengguna wajib mengganti password sebelum dapat mengakses kembali modul operasional.

---

## 3. Alur Kerja Operator Sekolah

```text
[1. Buat Dokumen SPK] ──> [2. Alokasikan Realisasi ke Kodering] ──> [3. Pantau Acuan] ──> [4. Kirim Laporan] ──> [5. Cetak 26 Kolom]
```

### Langkah 1: Input Dokumen SPK & Item Barang
1. Masuk ke menu **Buku SPJ / Data Barang** (`/pelaporan-bm/spj`), pilih bulan.
2. Klik tombol **+ Tambah SPJ**, lalu pilih kategori (**Peralatan & Mesin** atau **Buku**).
3. Isi informasi kontrak: No SPK, No SP2D, Sumber Perolehan (BOS Reguler / Kinerja), No Berita Acara (BA) dan Tanggal BA (wajib).
4. Masukkan rincian barang per accordion: cari dari **Katalog Pagu** (mengisi otomatis Kode Barang, Nama Barang, Jenis Aset), lalu lengkapi Merk/Tipe, Satuan, Volume, dan Harga Satuan. Untuk kategori **Buku**, No Sertifikat/Penerbit wajib diisi.
5. Klik **Simpan Realisasi**. Isi form tersimpan otomatis sebagai draft di browser — aman jika halaman ter-refresh, draft hilang setelah tersimpan.

**Alternatif: Import Excel** (hanya sekolah tertentu yang memiliki tombol ini). Klik **Import Excel** di halaman Data Barang, unduh **Template**, isi 12 kolom (No. SP2D, Sumber Perolehan\*, No. SPK/Kwitansi\*, BA NO\*, BA TGL\*, Kode Barang, Merk/Tipe\*, No. Sertifikat/Pabrik/Penerbit, Ukuran/Dimensi Bangunan, Satuan\*, Volume\*, Harga Satuan\* — `*` = wajib; Kode Barang harus ada di katalog), lalu unggah. Import bersifat **all-or-nothing**: bila ada satu baris tidak valid, seluruh impor dibatalkan dan sistem menampilkan nomor baris + alasannya. Baris dengan No. SPK sama otomatis digabung menjadi satu dokumen SPK multi-item.

### Langkah 2: Alokasikan Realisasi ke Kodering
1. Buka menu **Input Realisasi** (`/pelaporan-bm/input-realisasi`), pilih bulan.
2. Pada kodering yang belum terpenuhi, klik tombol **+** lalu centang item SPJ yang masuk kodering tersebut.
3. Simpan — item tercatat di tabel realisasi dan tampil di menu **Data Realisasi** (`/pelaporan-bm/realisasi`). Total alokasi tidak boleh melebihi sisa anggaran kodering.

### Langkah 3: Pantau Target Acuan Kodering
1. Pada halaman **Input Realisasi**, periksa kolom **Realisasi** dan **Kekurangan** per kodering rekening belanja modal (klik kode rekening untuk melihat daftar uraiannya).
2. Kekurangan berwarna hijau berarti kodering sudah balance; gunakan tombol **Edit** untuk mengubah alokasi.

### Langkah 4: Kirim & Cetak Laporan Bulanan
1. Ketika total kekurangan = Rp 0, klik **Kirim Laporan** di panel bawah Input Realisasi (konfirmasi lalu status berubah `menunggu_approval` dan data terkunci).
2. Setelah disetujui Admin KCD, buka menu **Cetak Laporan** (`/pelaporan-bm/cetak`), pilih bulan, lalu unduh format resmi Excel 26 kolom.
3. Menu **Data Realisasi** juga menyediakan unduhan **Laporan XLSX** sesuai filter dan **Template Isian** Excel.

---

## 4. Alur Kerja Admin KCD

### Langkah 1: Unggah Target Acuan Anggaran
1. Buka menu **Acuan Belanja Modal** (`/pelaporan-bm/acuan`).
2. Masukkan pagu per kodering per sekolah secara manual atau gunakan tombol **Import Excel**.

### Langkah 2: Monitoring Wilayah di Dashboard
1. Buka **Dashboard** (`/dashboard`).
2. Filter periode bulan dan tahun anggaran.
3. Pantau sekolah yang **Selesai Lapor** vs **Belum Lapor**.

### Langkah 3: Verifikasi & Gembok Laporan Sekolah
1. Buka menu **Rekapan Sekolah** (`/pelaporan-bm/rekapan`).
2. Pilih bulan; daftar menampilkan sekolah yang memiliki target acuan pada bulan tersebut, diurutkan TUNTAS dulu lalu alfabetis. Gunakan kotak SUDAH SELESAI / BELUM SELESAI atau pencarian nama/NPSN untuk menyaring.
3. Klik **Lihat** untuk membuka rincian dua tab: REKENING ACUAN (acuan vs realisasi per kodering, badge SESUAI/BELUM SESUAI) dan INPUT REALISASI (log fisik barang yang dialokasikan operator ke rekening acuan).
4. Pada laporan berstatus **Wait ACC**, klik **ACC** untuk menyetujui (terkunci otomatis), atau **Buka Edit** untuk mengembalikan status ke draft agar operator dapat mengisi ulang (tanpa menghapus data).

---

## 5. Log Aktivitas (Super Admin & Admin KCD)
Menu **Log Aktivitas** (`/admin/log-aktivitas`) terlihat untuk role `super_admin`
dan `admin_kcd`. Berisi jejak audit sistem: pembuatan/ubah/hapus data
(SPJ, realisasi, acuan, kode barang, user, kunci laporan), login/logout,
ganti password, kirim & verifikasi laporan, import, dan unduhan.
Aktivitas yang dilakukan `super_admin` hanya terlihat oleh super_admin sendiri.

1. Buka menu **Log Aktivitas** di grup Master Data.
2. Saring dengan filter: aksi (event), entitas, sekolah, rentang tanggal,
   atau kata kunci ringkasan.
3. Klik **Lihat perubahan** pada baris untuk membuka diff sebelum/sesudah
   (before/after) perubahan data.

---

## 6. Panduan Distribusi BHP

Modul ini mengelola **Barang Habis Pakai** dengan sumber dana **BOS** atau
**BOPD**. Aturan utama: pilih sumber dana lebih dulu, karena stok dan laporan
BOS dan BOPD **tidak pernah tercampur**.

### 6.1 Istilah Stok

| Angka | Arti |
|---|---|
| **Fisik** | Total barang yang sudah diterima |
| **Terpakai** | Jumlah yang sudah disetujui/disalurkan |
| **Reservasi** | Jumlah pada pengajuan yang sudah disubmit tapi belum diputus |
| **Tersedia** | Fisik − Terpakai − Reservasi (tidak pernah negatif) |

### 6.2 Menyiapkan Master Data (Admin/Operator Sekolah)

1. **Kode Rekening** (`/distribusi-bhp/master/kode-rekening`) — satu kode per
   sumber dana.
2. **Barang** (`/distribusi-bhp/master/barang`) — isi kode, nama, satuan, kode
   rekening, dan harga satuan.
3. **Penyedia** (`/distribusi-bhp/master/penyedia`) — data rekanan/supplier.
4. **Pejabat Sekolah** (`/master-data/pejabat`, menu Master Data sekolah — di luar menu BHP) — data penandatangan
   dokumen: **Kepala Sekolah**, **Wakasek Sarana Prasarana**, dan **Pembantu
   Pengurus Barang**. Isi nama, pangkat/golongan, NIP, jenis jabatan
   (definitif/Plt./Plh.), dan masa jabatan. **Wajib lengkap ketiga jabatan
   sebelum pengajuan dapat disetujui.** Riwayat pejabat lama tetap tersimpan;
   menambah pejabat aktif otomatis menutup masa jabatan pejabat sebelumnya.

### 6.3 Mencatat Penerimaan Barang

1. Buka **Penerimaan** (`/distribusi-bhp/penerimaan`), pilih sumber dana.
2. Isi dokumen (nota/invoice, BAST, kwitansi, penyedia, tanggal) lalu tambahkan
   detail barang dan jumlahnya. Barang masuk akan menambah **stok fisik**.
3. Untuk saldo awal tahun, tandai sebagai **saldo awal** dan isi periode tahun.

### 6.4 Membuat & Mensubmit Pengajuan

1. Buka **Pengajuan** (`/distribusi-bhp/pengajuan`), buat draft: pilih sumber
   dana, tanggal, lalu daftar barang + jumlah diminta.
2. Klik **Submit**. Sistem memeriksa stok **Tersedia**; bila cukup, jumlah
   tersebut **direservasi** (belum mengurangi stok terpakai). Bila tidak cukup,
   submit ditolak dengan pesan sisa stok.

### 6.5 Review & Approval (Admin)

1. Buka **Review Pengajuan** (`/distribusi-bhp/review-pengajuan`), pilih
   pengajuan berstatus `submitted`.
2. **Approve** — boleh mengoreksi jumlah yang disetujui (tidak boleh melebihi
   yang diminta atau stok tersedia), atau **Reject** dengan alasan wajib.
3. Approval ditolak bila pejabat penandatangan (kepala sekolah, wakasek
   sarpras, pembantu pengurus barang) belum lengkap pada tanggal pengajuan;
   halaman review menampilkan peringatannya.
4. Saat approve, sistem otomatis membuat dokumen wajib (Nota Permintaan, Surat
   Permintaan, Surat Perintah Penyaluran) lengkap dengan nomor resmi dan
   snapshot nama pejabat saat itu — dokumen lama tidak berubah meski pejabat
   berganti kemudian.

### 6.6 Mencetak Dokumen

1. Dari halaman pengajuan, buka **Dokumen**.
2. **PDF** untuk mengunduh satu dokumen, atau **Unduh Batch PDF** untuk semua
   dokumen sekaligus.
3. Tombol **+ BAST / BAP / Stok Opname** membuat dokumen tambahan (sekali saja
   per jenis; nomornya tetap bila diklik ulang). Snapshot pada dokumen stok
   opname/buku hanya berisi data sekolah pengajuan.
4. Operator divisi hanya dapat membuka dokumen pengajuan divisinya sendiri;
   dokumen divisi lain ditolak (403).

### 6.7 Melihat Laporan

1. Buka **Laporan** (`/distribusi-bhp/laporan`), pilih sumber dana BOS/BOPD.
2. Tab tersedia: **Laporan Stok** (4-tier), **Buku Pengeluaran** (item yang
   sudah disetujui).
3. Buka **Kartu Persediaan** (`/distribusi-bhp/laporan/kartu-persediaan`),
   pilih barang untuk melihat mutasi masuk/keluar dengan **saldo berjalan**.
4. **Export Excel** tersedia untuk stok, buku pengeluaran, dan kartu
   persediaan. Kartu persediaan wajib memilih barang yang sealiran sumber
   dana laporan; jika tidak cocok, sistem menampilkan pesan error (bukan
   halaman rusak).

> Catatan peran: `operator_divisi` hanya dapat membuat dan mensubmit pengajuan
> divisinya; review, approval, master, dan cetak dikerjakan role sekolah/admin
> yang berwenang (`docs/security.md` §1). Dashboard operator divisi menampilkan
> statistik pengajuan divisinya saja.
