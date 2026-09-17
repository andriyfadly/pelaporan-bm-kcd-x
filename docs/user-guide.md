# Panduan Penggunaan (User Manual)

Panduan operasional sistem Pelaporan Belanja Modal Cabang Dinas Pendidikan Wilayah X.

---

## 1. Peran & Hak Akses

| Peran | Akses Halaman | Kredensial & Scope |
|---|---|---|
| **Operator Sekolah** | Dashboard Sekolah, Data Barang (Buku SPJ), Input Realisasi (Target Acuan), Data Realisasi, Cetak Laporan, Ubah Password | Username `[npsn]-admin`, default password `#SidiptaKCD10`. Terikat satu sekolah (`sekolah_id`). Wajib ganti password saat login pertama dan berkala tiap 3 bulan. |
| **Admin KCD** | Dashboard Wilayah, Master Kode Barang, Acuan Belanja Modal, Rekapan Sekolah, Kelola Pengguna, Cetak Laporan | Username `admin_kcd`. Akun `admin` tanpa `sekolah_id`. Memiliki wewenang lintas sekolah dan kunci laporan. |

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

## 3. Alur Kerja Admin KCD

### Langkah 1: Unggah Target Acuan Anggaran
1. Buka menu **Acuan Belanja Modal** (`/pelaporan-bm/acuan`).
2. Masukkan pagu per kodering per sekolah secara manual atau gunakan tombol **Import Excel**.

### Langkah 2: Monitoring Wilayah di Dashboard
1. Buka **Dashboard** (`/dashboard`).
2. Filter periode bulan dan tahun anggaran.
3. Pantau sekolah yang **Selesai Lapor** vs **Belum Lapor**.

### Langkah 3: Verifikasi & Gembok Laporan Sekolah
1. Buka menu **Rekapan Sekolah** (`/pelaporan-bm/rekapan`).
2. Teliti perbandingan acuan belanja vs realisasi item SPJ masing-masing sekolah.
3. Klik tombol **Kunci Laporan** untuk mengesahkan data bulan tersebut dan mencegah perubahan data susulan dari pihak sekolah.

---

## 4. Log Aktivitas (Super Admin & Admin KCD)

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
