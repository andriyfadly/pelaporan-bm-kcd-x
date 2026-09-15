# Spesifikasi Endpoint & Kontrak Data (API & Routes)

Dokumentasi rute web, controller, middleware, dan format payload data aplikasi.

---

## 1. Middleware & Guard Otentikasi
- `auth`: Wajib sesi aktif (Laravel Fortify).
- `EnsurePasswordNotExpired`: Memeriksa apakah password operator sekolah kedaluwarsa (login pertama / > 90 hari). Memaksa redirect ke `/ubah-password`.
- Pembatasan Role:
  - Admin KCD: `auth()->user()->hasRole('admin_kcd')`
  - Operator Sekolah: `auth()->user()->hasRole('operator_sekolah') && !empty(auth()->user()->sekolah_id)`

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
- **`GET /pelaporan-bm/spj/create`**
  - Parameter: `kategori` (`Peralatan & Mesin`|`Buku`), `bulan` (1-12).
  - Return: Inertia `PelaporanBm/Spj/FormSpk` (form dokumen SPK multi-item). Redirect ke index jika bulan dikunci/dikirim.
- **`GET /pelaporan-bm/spj/edit-spk/{no_spk}`**
  - Parameter: `no_spk` (wildcard, boleh mengandung `/`), `bulan` (1-12).
  - Return: Inertia `PelaporanBm/Spj/FormSpk` berisi seluruh item SPK terkait. Redirect ke index jika SPK tidak ditemukan atau bulan terkunci.
- **`POST /pelaporan-bm/spj/store-spk`**
  - Payload: `no_spk`, `no_sp2d`, `sumber_perolehan`, `bulan_realisasi`, `kategori`, `items: [{kode_barang, nama_barang, jenis_aset, merk_tipe, no_sertifikat, ukuran_bangunan, satuan, volume, harga_satuan}]`.
  - Result: Membuat satu dokumen SPK dengan beberapa item barang (satu row `pelaporan_bm_spj` per item) dalam satu transaksi DB.
- **`POST /pelaporan-bm/spj`**
  - Payload: `no_spk`, `no_sp2d`, `sumber_perolehan`, `bulan_realisasi`, `kategori`, `items: [{nama_barang, kode_barang, jenis_aset, volume, satuan, harga_satuan, acuan_id}]`.
- **`PUT /pelaporan-bm/spj/{id}`**: Update item barang SPJ.
- **`DELETE /pelaporan-bm/spj/{id}`**: Hapus item barang SPJ.
- **`DELETE /pelaporan-bm/spj/spk/{no_spk}`**: Hapus seluruh item satu dokumen SPK (`no_spk` wildcard).
- **`POST /pelaporan-bm/spj/toggle-realisasi`**
  - Payload: `id` (integer)
  - Return: JSON status `is_realisasi` (0 atau 1).
- **`POST /pelaporan-bm/spj/kirim-laporan`**
  - Payload: `bulan` (1-12), `tahun` (YYYY)
  - Result: Mengubah status `pelaporan_bm_kunci_laporan` menjadi `menunggu_approval`.

### B2. Input Realisasi (`app/Http/Controllers/PelaporanBm/InputRealisasiController.php`)
- **`GET /pelaporan-bm/input-realisasi`**
  - Parameter: `bulan_realisasi` (1-12, default bulan berjalan).
  - Return: Inertia `PelaporanBm/InputRealisasi/Index` — rekap acuan per kodering (nominal acuan, realisasi, kekurangan) + status kunci/kirim.
- **`GET /pelaporan-bm/input-realisasi/tambah`**
  - Parameter: `kodering`, `bulan_realisasi`.
  - Return: Inertia `PelaporanBm/InputRealisasi/Tambah` — daftar item SPJ bulan tersebut yang belum dialokasikan ke kodering.
- **`POST /pelaporan-bm/input-realisasi/simpan`**
  - Payload: `kodering`, `bulan_realisasi`, `item_ids` (array id `pelaporan_bm_spj`).
  - Result: Membuat row `pelaporan_bm_realisasi` per item + menandai SPJ asal `is_realisasi = true` (transaksi DB). Diblokir jika bulan dikunci/`menunggu_approval`/`disetujui`.
- **`GET /pelaporan-bm/input-realisasi/edit`**
  - Parameter: `kodering`, `bulan_realisasi`.
  - Return: Inertia `PelaporanBm/InputRealisasi/Edit` — item yang sudah dialokasikan ke kodering tersebut.
- **`POST /pelaporan-bm/input-realisasi/update`**
  - Payload: `kodering`, `bulan_realisasi`, `uncheck_ids` (array id `pelaporan_bm_realisasi` yang dilepas).
  - Result: Menghapus realisasi terpilih dan mengembalikan `is_realisasi = false` pada SPJ asal (transaksi DB).

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

### F. Otentikasi & Rotasi Password (`app/Http/Controllers/Auth/PasswordExpiredController.php`)
- **`GET /ubah-password`**
  - Return: Inertia `Auth/ChangePassword`.
- **`POST /ubah-password`**
  - Payload: `current_password` (string), `password` (string, min. 8 karakter, huruf besar/kecil, angka, simbol, beda dari password lama), `password_confirmation` (string).
  - Result: Mengubah password akun dan memperbarui `password_changed_at = now()`.
