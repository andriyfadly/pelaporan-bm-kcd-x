# Panduan Penggunaan (User Manual)

Panduan operasional sistem Pelaporan Belanja Modal Cabang Dinas Pendidikan Wilayah X.

---

## 1. Peran & Hak Akses

| Peran | Akses Halaman | Kredensial & Scope |
|---|---|---|
| **Operator Sekolah** | Dashboard Sekolah, Data Barang (Buku SPJ), Input Realisasi (Target Acuan), Data Realisasi, Cetak Laporan, Ubah Password, **Distribusi BHP (master, penerimaan, pengajuan, review, dokumen, laporan)**, **Pencatatan BM (inventaris, penyaluran, mutasi, pengembalian, dokumen)** | Username `[npsn]-admin`, default password `#SidiptaKCD10`. Terikat satu sekolah (`sekolah_id`). Wajib ganti password saat login pertama dan berkala tiap 3 bulan. |
| **Admin KCD** | Dashboard Wilayah, Master Kode Barang, Acuan Belanja Modal, Rekapan Sekolah, Kelola Pengguna, Cetak Laporan, **Distribusi BHP** | Username `admin_kcd`. Akun `admin` tanpa `sekolah_id`. Memiliki wewenang lintas sekolah dan kunci laporan. |
| **Operator Divisi** | **Distribusi BHP: Pengajuan saja** (buat & submit) | Role khusus BHP. Tanpa akses master, penerimaan, approval, atau cetak. |
| **Bendahara Sekolah** | Dashboard Sekolah, Data Barang (Buku SPJ), Input Realisasi, Data Realisasi, Cetak Laporan, **Distribusi BHP (lihat + pengajuan)**, **Pencatatan BM (inventaris, penyaluran, mutasi, pengembalian)** | Terikat satu sekolah; tanpa approval/cetak BHP. |

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

Menu BHP di sidebar menampilkan dua grup terpisah: **Distribusi BHP BOS** dan
**Distribusi BHP BOPD**, masing-masing dengan sub-grup Distribusi Barang,
Penyaluran, dan Laporan — mirror struktur asli aplikasi sistem-manajemen-aset.

### 6.1 Istilah Stok

| Angka | Arti |
|---|---|
| **Fisik** | Total barang yang sudah diterima |
| **Terpakai** | Jumlah yang sudah disetujui/disalurkan |
| **Reservasi** | Jumlah pada pengajuan yang sudah disubmit tapi belum diputus |
| **Tersedia** | Fisik − Terpakai − Reservasi (tidak pernah negatif) |

### 6.2 Menyiapkan Master Data (Admin/Operator Sekolah)

1. **Kode Rekening** (`/distribusi-bhp/{bos|bopd}/master/kode-rekening`) — satu kode per
   sumber dana.
2. **Barang** (`/distribusi-bhp/{bos|bopd}/master/barang`) — pilih kode rekening, isi nama,
   satuan, dan harga satuan. **Kode Barang/NUSP di-generate otomatis** dari kode barang kode
   rekening (format `{kode_barang}.{urut:3}`, mis. `1.1.7.01.01.001`) — kode rekening wajib
   sudah memiliki Kode Barang. Tabel menampilkan NUSP, satuan, harga terakhir (jumlah varian
   harga), dan kode rekening. Tombol **Kelola Harga** membuka halaman untuk menambah/mengubah/
   menghapus varian harga; **Edit** dan **Hapus** mengelola data barangnya (barang yang masih
   dipakai penerimaan/pengajuan tidak dapat dihapus). Halaman ini juga punya kartu statistik
   dan export CSV.
   **Import Excel**: unduh template (kolom: Kode Rekening, Nama Barang, Satuan, Harga Satuan),
   isi, lalu unggah. Kode Barang/NUSP di-generate otomatis; nama barang sama dengan harga
   berbeda menambah varian harga, nama & harga sama persis ditolak (duplikat). Import bersifat
   **all-or-nothing** (satu baris invalid = batal semua; maksimal 1000 baris).
3. **Penyedia** (`/distribusi-bhp/penyedia`) — data rekanan/supplier (berlaku lintas sumber dana).
4. **Penugasan Pegawai** (`/master-data/penugasan`, menu Master Data sekolah — di luar menu BHP) — data penandatangan
   dokumen: **Kepala Sekolah**, **Wakasek Sarpras**, dan **Pembantu
   Pengurus Barang**. Isi nama, pangkat/golongan, NIP, jenis jabatan
   (definitif/Plt./Plh.), dan masa jabatan. **Wajib lengkap ketiga jabatan
   sebelum pengajuan dapat disetujui.** Riwayat pejabat lama tetap tersimpan;
   menambah pejabat aktif otomatis menutup masa jabatan pejabat sebelumnya.
5. **Divisi & Operator** (`/distribusi-bhp/divisi`) — buat divisi sekaligus akun
   operatornya (username otomatis `{npsn}-{suffix}`). Tiap kartu divisi punya
   tombol **Edit** (ubah nama divisi via modal) dan **Hapus** (modal konfirmasi;
   menghapus divisi sekaligus seluruh akun operatornya — ditolak bila divisi
   masih memiliki pengajuan). Reset password & aktif/nonaktif operator juga dari
   halaman ini.

### 6.3 Mencatat Penerimaan Barang

1. Buka **Buku Penerimaan** (`/distribusi-bhp/{bos|bopd}/penerimaan`), pilih sumber dana (lewat navigasi ke grup BOS/BOPD).
2. Isi dokumen (nota/invoice, BAST, penyedia, tanggal) lalu tambahkan detail
   barang dan jumlahnya. Barang masuk akan menambah **stok fisik**.
   **Kosongkan No. Kwitansi bila belum dibayar** — penerimaan itu masuk daftar
   **Lunas Bayar** dan diberi keterangan “Lunas Bayar” pada PDF/Excel Buku
   Penerimaan bulan berjalan. Bila No. Kwitansi diisi, **Tanggal Pembayaran
   wajib ikut diisi**.
3. Untuk saldo awal tahun, buka **Saldo Awal** (`/distribusi-bhp/{bos|bopd}/saldo-awal`),
   tandai sebagai **saldo awal** dan isi periode tahun.
4. **Lunas Bayar** (`/distribusi-bhp/{bos|bopd}/penerimaan/lunas-bayar`) menampilkan
   penerimaan yang **barangnya sudah datang tetapi transaksi keuangannya belum
   terjadi** (kwitansi masih kosong). Tombol **Tandai Bayar** mengisi No. Kwitansi
   dan Tanggal Pembayaran; setelah itu baris keluar dari daftar.
   Pembayaran yang jatuh setelah bulan penerimaan tetap membuat bulan
   penerimaannya bertanda “Lunas Bayar”, dan barisnya tidak berpindah bulan.

> **Stok selalu milik satu sekolah.** Tidak ada stok bersama antar sekolah —
> penerimaan yang Anda catat hanya mengurangi stok sekolah Anda sendiri.

**Saldo awal** juga punya halaman khusus **Saldo Awal BHP**
(`/distribusi-bhp/saldo-awal`): pilih sumber dana (BOS/BOPD) dan periode,
tambah manual, atau **import Excel** (unduh template dulu; satu baris salah
membatalkan seluruh import). Tombol **Hapus** pada satu baris hanya menghapus
barang itu — barang lain pada periode yang sama tetap ada. Barang yang sudah
dipakai pengajuan aktif tidak dapat dihapus.

### 6.4 Membuat & Mengajukan Pengajuan

1. Buka **Pengajuan / Nota Permintaan** (`/distribusi-bhp/{bos|bopd}/pengajuan`, menu sidebar
   di sub-grup **Distribusi Barang**). Sumber dana sudah ditentukan oleh grup
   BOS/BOPD yang dipilih.
2. Isi tanggal, lalu tambahkan baris barang + jumlah diminta lewat **+ Tambah
   Baris**. Kolom barang menampilkan **stok tersedia** sekolah Anda, dan
   peringatan muncul bila jumlah melebihi stok.
3. Klik **Ajukan Pengajuan** (satu-satunya tombol). Tidak ada langkah draft:
   sistem langsung memeriksa stok **Tersedia** dan, bila cukup, membuat
   pengajuan sekaligus **mereservasi** jumlah tersebut (belum mengurangi stok
   terpakai). Bila stok tidak cukup, seluruh pengajuan dibatalkan dan pesan
   sisa stok ditampilkan — tidak ada data setengah jadi.
4. Isian yang belum terkirim **tersimpan otomatis di perangkat** (per sumber
   dana), jadi menutup atau me-refresh halaman tidak menghilangkan pekerjaan.
   Isian baru hilang setelah pengajuan berhasil dikirim, atau bila Anda
   mengubahnya sendiri.
5. Riwayat menampilkan pengajuan yang sudah diajukan beserta statusnya:
   **Menunggu Review**, **Disetujui**, atau **Ditolak**. Pengajuan yang sudah
   diajukan tidak bisa diedit — perubahan dilakukan lewat Reject lalu buat
   pengajuan baru.

### 6.5 Review & Approval (Admin)

1. Buka **Review Pengajuan** (`/distribusi-bhp/{bos|bopd}/review-pengajuan`),
   **menu sidebar terpisah** dari Pengajuan/Nota Permintaan. Pilih pengajuan
   berstatus `submitted`.
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
   dokumen sekaligus. Batch menghasilkan **berkas ZIP** berisi satu PDF per
   dokumen — bukan satu PDF gabungan — supaya tiap dokumen tetap pada kertas dan
   orientasinya sendiri (Surat Permintaan landscape, Stok Opname F4).
   Kop surat hanya ada pada dokumen yang memang berkop: Nota Permintaan,
   Surat Penyaluran, BAST, BAP, dan Stok Opname. Surat Permintaan dan tiga buku
   besar (Pengeluaran, Penerimaan, Penyaluran) sengaja tanpa kop, mengikuti
   template baku.
3. Tombol **+ BAST / BAP / Stok Opname** membuat dokumen tambahan (sekali saja
   per jenis; nomornya tetap bila diklik ulang). Snapshot pada dokumen stok
   opname/buku hanya berisi data sekolah pengajuan.
   Stok Opname dibatasi **satu per bulan per sekolah** dan wajib bertanggal
   **hari kerja**: Sabtu, Minggu, dan libur nasional ditolak dengan pesan yang
   menyebutkan hari kerja terakhir bulan itu. Cuti bersama tetap dianggap hari
   kerja.
4. Operator divisi hanya dapat membuka dokumen pengajuan divisinya sendiri;
   dokumen divisi lain ditolak (403).

> **Format tanggal.** Seluruh tanggal pada dokumen cetak memakai bahasa
> Indonesia (`30 Juni 2026`, `Selasa`), sedangkan kolom tanggal pada tabel buku
> memakai `dd-mm-yyyy` (`30-06-2026`) mengikuti template baku.

### 6.7 Melihat Laporan

1. Buka **Laporan Stok** (`/distribusi-bhp/{bos|bopd}/laporan`), pilih sumber dana BOS/BOPD (lewat grup sidebar).
2. Sub-grup **Laporan** di sidebar menyediakan halaman read-only per jenis:
   - **BAST Internal**: dokumen BAST (`jenis=bast`)
   - **Berita Acara Pemeriksaan**: dokumen BAP
   - **Buku Pengeluaran**: item yang sudah disetujui
   - **Buku Penerimaan**: rekap barang masuk per bulan (tanggal, BAST,
     nota/invoice, penyedia, barang, jumlah, harga, nilai) + tombol cetak PDF
   - **Buku Penyaluran**: dokumen penyaluran
   - **Kartu Persediaan**: mutasi masuk/keluar dengan saldo berjalan
   - **Stok Opname**: dokumen stok opname
3. **Export Excel** tersedia untuk stok, buku pengeluaran, buku penerimaan, dan
   kartu persediaan. Kartu persediaan wajib memilih barang yang sealiran sumber
   dana laporan; jika tidak cocok, sistem menampilkan pesan error (bukan
   halaman rusak).

> Catatan peran: `operator_divisi` hanya dapat membuat dan mensubmit pengajuan
> divisinya; review, approval, master, dan cetak dikerjakan role sekolah/admin
> yang berwenang (`docs/security.md` §1). Dashboard operator divisi menampilkan
> statistik pengajuan divisinya saja.

---

## 7. Panduan Pencatatan BM (Inventaris Aset)

Modul ini mencatat aset yang sudah dibelanjakan menjadi unit inventaris per
register, lalu menelusuri ke mana barang itu disalurkan.

### 7.1 Dari mana unit inventaris berasal

Ada dua jalur, keduanya bermuara ke daftar **Inventaris**:

1. **Sinkronisasi realisasi** — setelah laporan bulanan disetujui Admin KCD,
   admin menjalankan **Sinkronisasi** (`/pencatatan-bm/sinkronisasi`) untuk
   periode tersebut. Satu baris realisasi dengan volume N menjadi N unit.
2. **Tambah/impor sendiri** — di halaman **Inventaris** klik **Tambah Barang**
   untuk satu barang, atau unggah berkas Excel/CSV pada form yang sama.

### 7.2 Menambah barang manual

1. Buka **Pencatatan BM → Inventaris**, klik **Tambah Barang**.
2. Isi **Kode Barang** (bebas; kalau ada di master kode barang, nama/jenis/
   satuan terisi otomatis), **Nama Barang**, **Merek/Tipe**, **Tanggal
   Perolehan** (wajib), **Volume**, **Satuan**, **Harga Satuan**, **Asal
   Perolehan**, dan **Kondisi**.
3. Klik **Simpan**. Volume 3 menghasilkan tiga unit dengan register berurutan.

### 7.3 Impor Excel/CSV

1. Pada form yang sama, klik **Unduh template** untuk mendapat berkas acuan.
2. Isi kolom: Kode Barang, Nama Barang, Merek/Tipe, Tanggal, Bulan, Tahun,
   Satuan, Volume, Harga Satuan, Asal Perolehan. Tahun perolehan **wajib**.
3. Pilih berkas lalu klik **Impor**. Impor bersifat all-or-nothing: satu baris
   gagal membatalkan seluruh berkas, jadi tidak ada register yang terbit untuk
   data yang batal.

### 7.4 Nomor register

- Format tiga digit: `001`, `002`, dst.
- **Diulang dari 001 setiap tahun perolehan.** Barang tahun 2025 dan 2026
  masing-masing mulai dari `001`, jadi register selalu dibaca bersama tahunnya.
- Nomor yang sudah terbit tidak pernah dipakai ulang.

### 7.5 Penyaluran — penempatan pertama

1. Buka **Pencatatan BM → Penyaluran**, klik **Salurkan Barang**.
2. Pilih unit (hanya unit yang belum punya lokasi yang muncul), **Ruang
   Tujuan** (wajib), **Penanggung Jawab** (opsional), kondisi saat disalurkan,
   tanggal, dan keterangan.
3. Setelah tersimpan, unit hilang dari daftar penyaluran dan pindah ke daftar
   mutasi.

### 7.6 Mutasi — pindah dari lokasi pertama

1. Buka **Pencatatan BM → Mutasi**, klik **Mutasi Unit**.
2. Pilih unit yang **sudah disalurkan**, lalu isi ruang tujuan **atau**
   penanggung jawab tujuan (minimal salah satu).
3. Untuk mengoreksi kondisi saja tanpa memindahkan barang, gunakan halaman
   **detail unit** — bukan mutasi.

### 7.7 Pengembalian

1. Pastikan unit sudah punya **BAST bernomor**. Selama nomor BA belum diisi di
   **Berita Acara**, tombol kembalikan terkunci.
2. Buka **Pencatatan BM → Pengembalian**, pilih unit, tentukan kondisi akhir.
3. Unit kembali ke gudang: lokasinya dikosongkan dan statusnya `dikembalikan`.
   Barang itu muncul lagi di menu **Penyaluran** bila nanti akan ditempatkan.

### 7.8 Dokumen, label, dan BAST

- **Detail unit** menyediakan Label PDF, QR, tombol **Buat BAST**, dan **Fakta
  Integritas** (tombol fakta integritas aktif setelah unit punya penanggung
  jawab).
- **Berita Acara** (`/pencatatan-bm/berita-acara`) untuk mengoreksi atau
  membatalkan nomor BAST. Nomor yang masih aktif tidak boleh duplikat; nomor
  yang sudah dibatalkan boleh dipakai lagi.
- **Dokumen** (`/pencatatan-bm/dokumen`) mendaftar dokumen tersimpan dan
  menyediakan cetak langsung: KIR per ruang, Buku Inventaris per tahun, dan
  Sensus Aset per tahun.
- Tanggal dokumen bisa diisi mundur; penandatangan mengikuti pejabat yang
  menjabat pada tanggal tersebut.

### 7.9 Ekspor

Tombol **Ekspor Excel** di halaman Inventaris mengunduh daftar unit sesuai
filter kondisi yang aktif, termasuk kolom asal perolehan dan tahun perolehan.
