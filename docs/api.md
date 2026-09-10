# Spesifikasi Endpoint & Kontrak Data (API & Routes)

Dokumentasi rute web, controller, middleware, dan format payload data aplikasi.

---

## 1. Middleware & Guard Otentikasi
- `auth`: Wajib sesi aktif (Laravel Fortify).
- `verified`: Email terverifikasi (opsional/default aktif).
- Pembatasan Role:
  - Admin: `auth()->user()->role === 'admin'`
  - Operator: `auth()->user()->role === 'user' && !empty(auth()->user()->sekolah_id)`

---

## 2. Ringkasan Endpoint

### A. Dashboard (`app/Http/Controllers/PelaporanBm/DashboardController.php`)
- **`GET /dashboard`**
  - Parameter: `bulan` (integer 1-12), `tahun` (integer)
  - Return: Inertia `PelaporanBm/Dashboard`
  - Props Admin: `is_admin: true`, `rekap_wilayah`, `sekolah_selesai`, `sekolah_belum`.
  - Props Operator: `is_admin: false`, `status_bulan_lalu`, `summary`, `rekap_tahunan`.

### B. Buku SPJ & Realisasi (`app/Http/Controllers/PelaporanBm/SpjController.php`)
- **`GET /pelaporan-bm/spj`**
  - Parameter: `mode` (`'realisasi'` untuk tabel kodering acuan; default `'katalog'` untuk dokumen SPK), `q` (search string), `bulan` (1-12).
  - Return: Inertia `PelaporanBm/Spj/Index`.
- **`POST /pelaporan-bm/spj`**
  - Payload: `no_spk`, `no_sp2d`, `sumber_perolehan`, `bulan_realisasi`, `kategori`, `items: [{nama_barang, kode_barang, jenis_aset, volume, satuan, harga_satuan, acuan_id}]`.
- **`PUT /pelaporan-bm/spj/{id}`**: Update item barang SPJ.
- **`DELETE /pelaporan-bm/spj/{id}`**: Hapus item barang SPJ.
- **`POST /pelaporan-bm/spj/toggle-realisasi`**
  - Payload: `id` (integer)
  - Return: JSON status `is_realisasi` (0 atau 1).
- **`POST /pelaporan-bm/spj/kirim-laporan`**
  - Payload: `bulan` (1-12), `tahun` (YYYY)
  - Result: Mengubah status `pelaporan_bm_kunci_laporan` menjadi `menunggu_approval`.

### C. Data Realisasi (`app/Http/Controllers/PelaporanBm/RealisasiController.php`)
- **`GET /pelaporan-bm/realisasi`**
  - Parameter: `q`, `bulan`, `tahun`
  - Return: Inertia `PelaporanBm/Realisasi/Index`.

### D. Cetak Laporan 26 Kolom (`app/Http/Controllers/PelaporanBm/CetakController.php`)
- **`GET /pelaporan-bm/cetak`**
  - Parameter: `bulan`, `sekolah_id` (admin only)
  - Return: Inertia `PelaporanBm/Cetak/Index`.
- **`POST /pelaporan-bm/cetak/check`**
  - Payload: `bulan`, `sekolah_id` (opsional jika role user)
  - Return JSON: `{ status: 'ok'|'empty', count: number, message: string }`.
- **`GET /pelaporan-bm/cetak/export`**
  - Parameter: `bulan`, `sekolah_id`
  - Return: Stream unduhan file `.csv` (26 Kolom format KCD Wilayah X).

### E. Administrasi & Kunci Laporan (`app/Http/Controllers/PelaporanBm/KunciLaporanController.php`)
- **`POST /pelaporan-bm/kunci-laporan/toggle`** (Admin only)
  - Payload: `sekolah_id`, `bulan`, `tahun`
  - Return JSON: `{ status: 'disetujui'|'draft' }`.
