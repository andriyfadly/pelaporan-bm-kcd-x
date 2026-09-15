# Panduan Penggunaan (User Manual)

Panduan operasional sistem Pelaporan Belanja Modal Cabang Dinas Pendidikan Wilayah X.

---

## 1. Peran & Hak Akses

| Peran | Akses Halaman | Kredensial & Scope |
|---|---|---|
| **Operator Sekolah** | Dashboard Sekolah, Data Barang (Buku SPJ), Target Acuan Kerja (`?mode=realisasi`), Data Realisasi, Cetak Laporan, Ubah Password | Username `[npsn]-admin`, default password `#SidiptaKCD10`. Terikat satu sekolah (`sekolah_id`). Wajib ganti password saat login pertama dan berkala tiap 3 bulan. |
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
[1. Buat Dokumen SPK] ──> [2. Tambah Item Barang] ──> [3. Checklist Realisasi] ──> [4. Pantau Acuan] ──> [5. Kirim Laporan] ──> [6. Cetak 26 Kolom]
```

### Langkah 1: Input Dokumen SPK & Item Barang
1. Masuk ke menu **Buku SPJ / Data Barang** (`/pelaporan-bm/spj`).
2. Klik tombol **+ Tambah SPJ**, lalu pilih kategori (**Peralatan & Mesin** atau **Buku**).
3. Isi informasi kontrak: No SPK, Sumber Dana (BOS Reguler / Kinerja), No Berita Acara (BA), dan Tanggal BA.
4. Masukkan rincian barang: Nama Barang, Kode Barang (pilih dari master), Merk/Tipe, Volume, Satuan, dan Harga Satuan.
5. Simpan data.

### Langkah 2: Tandai Realisasi Fisik
1. Pada daftar dokumen SPK, periksa item barang yang telah diterima fisik.
2. Beri centang pada kotak **Realisasi**. Item yang dicentang otomatis tercatat ke dalam laporan resmi dinas dan menu **Data Realisasi** (`/pelaporan-bm/realisasi`).

### Langkah 3: Pantau Target Acuan Kodering
1. Buka tab **Target Acuan Belanja** atau menu **Input Realisasi** (`/pelaporan-bm/spj?mode=realisasi`).
2. Periksa kolom **Realisasi** dan **Kekurangan** pada masing-masing kodering rekening belanja modal.
3. Gunakan tombol **+ Input** di sebelah kodering yang belum terpenuhi untuk langsung membuka form SPJ dengan kodering terkait.

### Langkah 4: Kirim & Cetak Laporan Bulanan
1. Klik tombol **Kirim Laporan** pada halaman SPJ untuk mengajukan verifikasi ke Admin KCD (status berubah menjadi `menunggu_approval`).
2. Setelah disetujui, buka menu **Cetak Laporan** (`/pelaporan-bm/cetak`), pilih bulan, lalu unduh format resmi CSV 26 kolom.

---

## 3. Alur Kerja Admin KCD

### Langkah 1: Unggah Target Acuan Anggaran
1. Buka menu **Acuan Belanja Modal** (`/pelaporan-bm/acuan`).
2. Masukkan pagu per kodering per sekolah secara manual atau gunakan tombol **Import Excel/CSV**.

### Langkah 2: Monitoring Wilayah di Dashboard
1. Buka **Dashboard** (`/dashboard`).
2. Filter periode bulan dan tahun anggaran.
3. Pantau sekolah yang **Selesai Lapor** vs **Belum Lapor**.

### Langkah 3: Verifikasi & Gembok Laporan Sekolah
1. Buka menu **Rekapan Sekolah** (`/pelaporan-bm/rekapan`).
2. Teliti perbandingan acuan belanja vs realisasi item SPJ masing-masing sekolah.
3. Klik tombol **Kunci Laporan** untuk mengesahkan data bulan tersebut dan mencegah perubahan data susulan dari pihak sekolah.
