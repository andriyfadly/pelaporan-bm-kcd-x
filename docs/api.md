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
- **`GET /pelaporan-bm/spj/pilih-bulan`**
  - Return: Inertia `PelaporanBm/Spj/PilihBulan` (Menu pilih bulan data barang identik legacy).
- **`GET /pelaporan-bm/spj`**
  - Parameter: `bulan` (1-12).
  - Return: Inertia `PelaporanBm/Spj/Index` (Katalog dokumen SPJ belanja modal identik legacy; urut terbaru dulu).
- **`GET /pelaporan-bm/spj/create`**
  - Parameter: `kategori` (`Peralatan & Mesin`|`Buku`), `bulan` (1-12).
  - Return: Inertia `PelaporanBm/Spj/FormSpk` (Form dokumen SPK multi-item identik legacy `data_barang_input.php`). Redirect jika bulan dikunci/dikirim.
- **`GET /pelaporan-bm/spj/edit-spk/{no_spk}`**
  - Parameter: `no_spk` (wildcard), `bulan` (1-12).
  - Return: Inertia `PelaporanBm/Spj/FormSpk` berisi seluruh item SPK terkait.
- **`POST /pelaporan-bm/spj/store-spk`**
  - Payload: `no_spk`, `no_sp2d`, `sumber_perolehan`, `bulan_realisasi`, `kategori`, `ba_no`, `ba_tgl`, `items: [{id?, kode_barang, nama_barang, jenis_aset, merk_tipe, no_sertifikat, ukuran_bangunan, satuan, volume, harga_satuan}]`.
  - Result: Transaksi atomik simpan/update SPK beserta multi-item; item yang dilepas saat edit ikut menghapus realisasi terkait.
- **`DELETE /pelaporan-bm/spj/{id}`**: Hapus item barang SPJ (beserta realisasi terkait, transaksi DB).
- **`DELETE /pelaporan-bm/spj/spk/{no_spk}`**: Hapus seluruh item satu dokumen SPK + realisasi terkait (`no_spk` wildcard).
- **`GET /pelaporan-bm/cari-barang`**
  - Parameter: `q` (keyword), `kategori`.
  - Return JSON: daftar kode barang leaf (bukan prefix parent) dari `master_data_kode_barang` — matching lowercase + escape wildcard LIKE, limit 100 (replika `legacy/ajax_cari_barang.php`); fallback pencarian riwayat SPJ sekolah (limit 10) bila katalog kosong.

### B2. Input Realisasi (`app/Http/Controllers/PelaporanBm/InputRealisasiController.php`)
- **`GET /pelaporan-bm/input-realisasi/pilih-bulan`**
  - Return: Inertia `PelaporanBm/InputRealisasi/PilihBulan` (Pilih bulan input realisasi identik legacy).
- **`GET /pelaporan-bm/input-realisasi`**
  - Parameter: `bulan_realisasi` (1-12, default bulan berjalan).
  - Return: Inertia `PelaporanBm/InputRealisasi/Index` — rekap acuan per kodering (nominal acuan, realisasi, kekurangan) + status kunci/kirim.
- **`GET /pelaporan-bm/input-realisasi/tambah`**
  - Parameter: `kodering`, `bulan_realisasi`.
  - Return: Inertia `PelaporanBm/InputRealisasi/Tambah` — daftar item SPJ bulan tersebut yang belum dialokasikan ke kodering.
- **`POST /pelaporan-bm/input-realisasi/simpan`**
  - Payload: `kodering`, `bulan_realisasi`, `item_ids` (array id `pelaporan_bm_spj`).
  - Result: Membuat row `pelaporan_bm_realisasi` per item + menandai SPJ asal `is_realisasi = true` (transaksi DB). Diblokir jika bulan dikunci/`menunggu_approval`/`disetujui`, atau bila total nilai item terpilih melebihi sisa anggaran kodering (acuan - realisasi berjalan).
- **`GET /pelaporan-bm/input-realisasi/edit`**
  - Parameter: `kodering`, `bulan_realisasi`.
  - Return: Inertia `PelaporanBm/InputRealisasi/Edit` — item yang sudah dialokasikan ke kodering tersebut.
- **`POST /pelaporan-bm/input-realisasi/update`**
  - Payload: `kodering`, `bulan_realisasi`, `uncheck_ids` (array id `pelaporan_bm_realisasi` yang dilepas).
  - Result: Menghapus realisasi terpilih dan mengembalikan `is_realisasi = false` pada SPJ asal (transaksi DB).
- **`POST /pelaporan-bm/input-realisasi/kirim-laporan`**
  - Payload: `bulan_realisasi` (1-12).
  - Result: Validasi server-side total realisasi &ge; total acuan (balance), lalu mengunci status laporan menjadi `menunggu_approval` + `status_kunci = true` (upsert `pelaporan_bm_kunci_laporan` per sekolah+bulan).

### C. Data Realisasi (`app/Http/Controllers/PelaporanBm/RealisasiController.php`)
- **`GET /pelaporan-bm/realisasi`**
  - Parameter: `filter_barang`, `filter_bulan` (1-12), `filter_tahun`.
  - Sumber data: tabel `pelaporan_bm_realisasi` (identik legacy `realisasi_barang_sekolah`), urut `ba_tgl DESC, id DESC`, pagination 25.
  - Return: Inertia `PelaporanBm/Realisasi/Index` (19 kolom) + `totalNilaiPerolehan` tersaring + `availableYears` dinamis.
- **`GET /pelaporan-bm/realisasi/unduh`**
  - Parameter: `filter_barang`, `filter_bulan`, `filter_tahun`.
  - Return: Stream unduhan CSV UTF-8 BOM (20 kolom, urut ASC) — laporan realisasi sesuai filter.

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

### G. Migrasi Data Legacy (console command)
- **`php artisan app:migrasi-data-lama`** (`app/Console/Commands/MigrasiDataLamaCommand.php`)
  - Membaca dump SQL `storage/bm-kcd-x.sql` (DB transaksional belanja modal) dan `storage/db_inventaris.sql` (katalog `kode_barang`, 27 batch INSERT) lalu mengisinya ke tabel aplikasi via `upsert` (chunk 500, dedupe per `kode_barang`).
  - Wajib dijalankan sekali setelah `migrate --seed` pada instalasi baru yang menginginkan data legacy.
