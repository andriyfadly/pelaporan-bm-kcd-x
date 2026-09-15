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
        InputRealisasiController.php # Alokasi item SPJ ke kodering realisasi (simpan/edit/uncheck)
        KunciLaporanController.php  # Toggle kunci & status approval
        RealisasiController.php     # Data realisasi (tabel pelaporan_bm_realisasi) + ekspor CSV
        RekapanController.php       # Rekapan kodering per sekolah
        SpjController.php           # Buku SPJ dokumen, form SPK multi-item, & item barang
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
        InputRealisasi/Index.tsx
        InputRealisasi/Tambah.tsx
        InputRealisasi/Edit.tsx
        Realisasi/Index.tsx
        Rekapan/Index.tsx
        Spj/Index.tsx
        Spj/FormSpk.tsx
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
   - `is_realisasi = true` + `acuan_id` terisi ketika item dialokasikan ke suatu kodering via Input Realisasi.
4. **`Realisasi` (`pelaporan_bm_realisasi`)**:
   - Snapshot baris realisasi per alokasi kodering (sumber data menu `/pelaporan-bm/realisasi`, identik legacy `realisasi_barang_sekolah`).
   - Kolom: `spj_id`, `sekolah_id`, `acuan_id`, `kodering_belanja`, `bulan_realisasi`, salinan field dokumen & barang (SP2D, SPK, BA, kode/nama barang, merk, sertifikat, ukuran, satuan, volume, harga, nilai perolehan), `is_realisasi`, `id_realisasi_lama`.
   - Baris dihapus otomatis (transaksi DB) ketika SPJ asalnya dihapus dari Buku SPJ.
5. **`Acuan` (`pelaporan_bm_acuan`)**:
   - Target pagu anggaran kodering acuan yang diupload admin KCD (ternormalisasi via relasi sekolah).
   - Kolom: `sekolah_id`, `tanggal`, `kodering`, `bku`, `uraian`, `nominal`, `bulan`.
6. **`KodeBarang` (`master_data_kode_barang`)**:
   - Katalog kode barang standar pemerintah daerah (sumber pencarian form SPK).
   - Kolom: `kode_barang` (unique), `uraian`, `kodering_aset`, `jenis_aset`, `umur_ekonomis`, `satuan`.
   - Pencarian hanya mengembalikan kode leaf (kode tanpa turunan prefix), mereplikasi perilaku legacy.
7. **`KunciLaporan` (`pelaporan_bm_kunci_laporan`)**:
   - Menyimpan status kunci laporan dan tahapan approval bulanan (`draft`, `menunggu_approval`, `disetujui`).
   - `status_kunci = true` ATAU `status_kirim` ∈ {`menunggu_approval`, `disetujui`} ⇒ seluruh mutasi SPJ/realisasi bulan tsb diblokir (readonly).
