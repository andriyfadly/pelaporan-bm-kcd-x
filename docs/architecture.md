# Arsitektur & Teknologi

## 1. Tech Stack

- **Backend**: Laravel 12 (PHP 8.4)
- **Frontend SPA**: Inertia.js (React 19 + TypeScript)
- **Styling**: Tailwind CSS
- **Database**: MySQL / MariaDB (Driver PDO)
- **Auth**: Laravel Fortify + Session Authentication (Bcrypt password hash)
- **Testing**: PHPUnit 13 dengan target code coverage &ge; 80%

---

## 2. Struktur Direktori Utama

```text
app/
  Http/
    Controllers/
      Admin/
        KodeBarangController.php    # Master kode barang & import
        UserController.php          # Kelola user & hak akses
      PelaporanBm/
        AcuanController.php         # Master target kodering acuan
        CetakController.php         # Pre-flight check & export CSV 26 kolom
        DashboardController.php     # Logic dashboard Admin KCD vs Sekolah
        KunciLaporanController.php  # Toggle kunci & status approval
        RealisasiController.php     # Data realisasi (is_realisasi = 1)
        RekapanController.php       # Rekapan kodering per sekolah
        SpjController.php           # Buku SPJ dokumen & item barang
  Models/
    Master/
      KodeBarang.php
      MasterDataSekolah.php
    PelaporanBm/
      Acuan.php
      KunciLaporan.php
      Realisasi.php
      Spj.php
    User.php
docs/                               # Dokumentasi teknis & panduan
resources/
  js/
    Layouts/
      AppLayout.tsx                 # Sidebar responsif & role-based menu
    Pages/
      Admin/
        KodeBarang/Index.tsx
        User/Index.tsx
      PelaporanBm/
        Acuan/Index.tsx
        Cetak/Index.tsx
        Dashboard.tsx
        Realisasi/Index.tsx
        Rekapan/Index.tsx
        Spj/Index.tsx
routes/
  web.php                           # Route definitions & middleware
```

---

## 3. Skema Data & Relasi Penting

1. **`User`**:
   - Autentikasi berbasis `username` (Operator Sekolah: `[npsn]-admin`, Admin: `admin_kcd`).
   - `password_changed_at`: Timestamp perubahan password (wajib ganti saat pertama kali login dan tiap 3 bulan / 90 hari).
   - `sekolah_id`: Foreign key ke `master_data_sekolah.id`.
2. **`Sekolah` (`master_data_sekolah`)**:
   - Master data identitas unit sekolah.
   - Kolom: `nama_sekolah`, `npsn`, `kota_kab`, `kode_sub_pengguna`, `kode_wilayah`.
3. **`Spj` (`pelaporan_bm_spj`)**:
   - Menyimpan transaksi dokumen SPK dan rincian belanja modal fisik.
   - Kolom: `no_spk`, `no_sp2d`, `sumber_perolehan`, `bulan_realisasi`, `kategori`, `ba_no`, `ba_tgl`, `kode_barang`, `nama_barang`, `jenis_aset`, `volume`, `harga_satuan`, `nilai_perolehan`, `is_realisasi`, `acuan_id`, `sekolah_id`.
4. **`Acuan` (`pelaporan_bm_acuan`)**:
   - Target pagu anggaran kodering acuan yang diupload admin KCD (ternormalisasi via relasi sekolah).
   - Kolom: `sekolah_id`, `tanggal`, `kodering`, `bku`, `uraian`, `nominal`, `bulan`.
5. **`KunciLaporan` (`pelaporan_bm_kunci_laporan`)**:
   - Menyimpan status kunci laporan dan tahapan approval bulanan (`draft`, `menunggu_approval`, `disetujui`).
