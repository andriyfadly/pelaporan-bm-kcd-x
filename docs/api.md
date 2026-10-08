# Spesifikasi Endpoint & Kontrak Data (API & Routes)

Dokumentasi rute web, controller, middleware, dan format payload data aplikasi.

---

## 1. Middleware & Guard Otentikasi
- `auth`: Wajib sesi aktif (Laravel Fortify).
- **Cloudflare Turnstile**: Login (`POST /login`) wajib token `cf-turnstile-response` yang diverifikasi server-side via `app/Services/TurnstileService.php`. Aktif bila `TURNSTILE_ENABLED=true` + site/secret key terisi. Bila nonaktif, verifikasi dilewati di `local`/testing saja. Hasil siteverify di-memo per-request (token sekali pakai; pipeline Fortify memanggil `authenticateUsing` dua kali per login). Parameter `remoteip` sengaja tidak dikirim — di balik proxy/serve lokal IP server tak cocok dengan IP browser sehingga Cloudflare menolak token valid dengan `timeout-or-duplicate`.
  - **UX klien** (`resources/js/Pages/Auth/Login.tsx`): tombol "Masuk" tetap nonaktif (`disabled`) selama Turnstile aktif dan widget belum lolos verifikasi; label berubah jadi "SELESAIKAN VERIFIKASI" + hint. State `turnstileVerified` di-set oleh `callback` (nama opsi resmi Turnstile untuk sukses; `success-callback` bukan opsi valid), dan direset oleh `expired-callback`/`timeout-callback`/`error-callback` serta `onFinish` submit (token sekali pakai). Bila `turnstileSiteKey` kosong (Turnstile nonaktif), tombol langsung aktif.
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
  - Return: Inertia `PelaporanBm/Spj/Index` (Katalog dokumen SPJ belanja modal identik legacy; urut terbaru dulu) + prop `canImportSpj` (NPSN sekolah ada di allowlist import).
- **`GET /pelaporan-bm/spj/create`**
  - Parameter: `kategori` (`Peralatan & Mesin`|`Buku`), `bulan` (1-12).
  - Return: Inertia `PelaporanBm/Spj/FormSpk` (Form dokumen SPK multi-item identik legacy `data_barang_input.php`). Redirect jika bulan dikunci/dikirim.
- **`GET /pelaporan-bm/spj/edit-spk/{no_spk}`**
  - Parameter: `no_spk` (wildcard), `bulan` (1-12).
  - Return: Inertia `PelaporanBm/Spj/FormSpk` berisi seluruh item SPK terkait.
- **`POST /pelaporan-bm/spj/store-spk`**
  - Payload: `no_spk`, `no_sp2d`, `sumber_perolehan`, `bulan_realisasi`, `kategori`, `ba_no`, `ba_tgl`, `items: [{id?, kode_barang, nama_barang, jenis_aset, merk_tipe, no_sertifikat, ukuran_bangunan, satuan, volume, harga_satuan}]`.
  - Result: Transaksi atomik simpan/update SPK beserta multi-item; item yang dilepas saat edit ikut menghapus realisasi terkait.
- **`POST /pelaporan-bm/spj/import`** (throttle 10/menit, **restricted NPSN**)
  - Dibatasi sekolah dengan NPSN di daftar allowlist `SpjController::NPSN_IMPORT_SPJ`
    (saat ini `20246369`, `20246370`, `20252161`, `20254692`); sekolah lain
    ditolak dengan flash error. Halaman index mengirim flag `canImportSpj`
    untuk menampilkan/menyembunyikan tombol Import Excel.
  - Payload: `file` (xlsx/xls, max 10MB, template `/templates/template_import_spj.xlsx`), `bulan` (1-12).
  - Kolom template (urutan tetap): No. SP2D, Sumber Perolehan\*, No. SPK/Kwitansi\*, BA NO\*, BA TGL\*, Kode Barang, Merk/Tipe\*, No. Sertifikat/Pabrik/Penerbit, Ukuran/Dimensi Bangunan, Satuan\*, Volume\*, Harga Satuan\*.
  - Result: **All-or-nothing** — pre-validasi seluruh baris (kolom wajib, BA TGL valid/serial Excel, `kode_barang` harus ada di master katalog → `nama_barang`+`jenis_aset` di-lookup, volume &gt; 0, harga ≥ 0); satu baris invalid = batal semua + flash error per baris. Baris `no_spk` sama = 1 dokumen SPK multi-item (sama seperti UI). Limit 5.000 baris. Diblokir saat laporan terkunci. Tercatat di log `sistem` (event `import-spj`) **dengan flag `sembunyi_dari_admin_kcd`** — log hanya terlihat oleh super_admin.
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
  - Return: Inertia `PelaporanBm/InputRealisasi/Index` — rekap acuan per kodering (nominal acuan, realisasi, kekurangan) + status kunci/kirim dan `canEditApproved`.
  - `canEditApproved = true` hanya untuk user dengan role `super_admin` ketika `status_kirim = disetujui`. Prop ini mengatur affordance UI; otorisasi mutasi tetap diperiksa di controller.
- **`GET /pelaporan-bm/input-realisasi/tambah`**
  - Parameter: `kodering`, `bulan_realisasi`.
  - Return: Inertia `PelaporanBm/InputRealisasi/Tambah` — daftar item SPJ bulan tersebut yang belum dialokasikan ke kodering.
- **`POST /pelaporan-bm/input-realisasi/simpan`**
  - Payload: `kodering`, `bulan_realisasi`, `item_ids` (array id `pelaporan_bm_spj`).
  - Result: Membuat row `pelaporan_bm_realisasi` per item (transaksi DB; status realisasi di-derive dari row ini). Diblokir jika bulan dikunci/`menunggu_approval`/`disetujui`, atau bila total nilai item terpilih melebihi sisa anggaran kodering (acuan - realisasi berjalan).
- **`GET /pelaporan-bm/input-realisasi/edit`**
  - Parameter: `kodering`, `bulan_realisasi`.
  - Return: Inertia `PelaporanBm/InputRealisasi/Edit` — item yang sudah dialokasikan ke kodering tersebut, `isReadOnly`, dan `canEditApproved`.
  - Laporan terkunci atau berstatus `menunggu_approval`/`disetujui` read-only untuk role selain `super_admin`. `super_admin` boleh mengedit hanya status `disetujui`, termasuk saat `status_kunci = true`.
- **`POST /pelaporan-bm/input-realisasi/update`**
  - Payload: `kodering`, `bulan_realisasi`, `uncheck_ids` (array id `pelaporan_bm_realisasi` yang dilepas).
  - Result: Menghapus row realisasi terpilih (status realisasi SPJ asal otomatis kembali belum-teralisasi karena derive).
  - Guard: request biasa ditolak untuk laporan terkunci atau berstatus `menunggu_approval`/`disetujui`. Role `super_admin` boleh menghapus alokasi pada laporan `disetujui`; `uncheck_ids` tetap dibatasi oleh sekolah, bulan, dan kodering. Status `KunciLaporan.status_kirim` dan `status_kunci` tidak diubah.
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
  - Return: Unduhan `.xlsx` via `maatwebsite/excel` (25 kolom A–Y + sheet `KODE BARANG`, urut ASC) — laporan realisasi sesuai filter.

### D. Cetak Laporan 26 Kolom (`app/Http/Controllers/PelaporanBm/CetakController.php`)
- **`GET /pelaporan-bm/cetak`**
  - Parameter: `bulan`, `sekolah_id` (admin only)
  - Return: Inertia `PelaporanBm/Cetak/Index`.
- **`POST /pelaporan-bm/cetak/check`**
  - Payload: `bulan`, `sekolah_id` (opsional jika role user)
  - Return JSON: `{ status: 'ok'|'empty', count: number, message: string }`.
- **`GET /pelaporan-bm/cetak/export`**
  - Parameter: `bulan`, `sekolah_id`
  - Return: Unduhan `.xlsx` via `maatwebsite/excel` (26 Kolom format KCD Wilayah X).

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
  - Membaca dump SQL `storage/bm_kcd_x.sql` (DB transaksional belanja modal) dan `storage/db_inventaris.sql` (katalog `kode_barang`, 27 batch INSERT).
   - Cakupan (terverifikasi test `MigrasiLegacyTest`): 77 `kode_sekolah` → `Sekolah`, 717 `data_barang_acuan` (3 batch) → `Acuan` + backfill `npsn`, 490 `master_barang_sekolah` (4 batch) → `Spj`, 42 `realisasi_barang_sekolah` → `Realisasi`, 13 `laporan_realisasi` → `KunciLaporan` (status `disetujui` + `tanggal_kirim` terbawa), katalog kode barang via `upsert` chunk 500. Users legacy **tidak** dimigrasi (dilewati; akun dikelola `UserSeeder`).
  - Idempoten (`updateOrCreate` by id lama) — aman dijalankan ulang.
  - Wajib dijalankan sekali setelah `migrate --seed` pada instalasi baru yang menginginkan data legacy.

### H. Distribusi BHP (`app/Http/Controllers/DistribusiBhp/*`)

Semua rute ber-prefix `/distribusi-bhp`, name `distribusi-bhp.*`, dan dijaga
permission BHP. Prefix ini tidak menyentuh `/pelaporan-bm` (lihat
`docs/requirements/domain-boundaries.md`).

| Method | Path | Permission | Controller::method |
|---|---|---|---|
| GET | `/distribusi-bhp/dashboard` | `lihat-distribusi-bhp` | `DashboardController::index` |
| GET | `/distribusi-bhp/pengajuan` | `lihat-distribusi-bhp` | `PengajuanController::index` |
| POST | `/distribusi-bhp/pengajuan` | `kelola-pengajuan-bhp` | `PengajuanController::store` |
| POST | `/distribusi-bhp/pengajuan/{pengajuan}/submit` | `kelola-pengajuan-bhp` | `PengajuanController::submit` |
| GET | `/distribusi-bhp/review-pengajuan` | `review-pengajuan-bhp` | `ReviewPengajuanController::index` |
| POST | `/distribusi-bhp/review-pengajuan/{pengajuan}/approve` | `approve-pengajuan-bhp` | `ReviewPengajuanController::approve` |
| POST | `/distribusi-bhp/review-pengajuan/{pengajuan}/reject` | `approve-pengajuan-bhp` | `ReviewPengajuanController::reject` |
| GET/POST | `/distribusi-bhp/master/kode-rekening` | `kelola-master-bhp` | `KodeRekeningController` |
| GET/POST | `/distribusi-bhp/master/barang` | `kelola-master-bhp` | `BarangController` |
| POST | `/distribusi-bhp/master/barang/{barang}/harga` | `kelola-master-bhp` | `BarangController::storeHarga` |
| GET/POST | `/distribusi-bhp/master/penyedia` | `kelola-master-bhp` | `PenyediaController` |
| GET/POST | `/distribusi-bhp/penerimaan` | `kelola-penerimaan-bhp` | `PenerimaanController` |
| GET | `/distribusi-bhp/pengajuan/{pengajuan}/dokumen` | `lihat-distribusi-bhp` | `DokumenController::show` |
| GET | `/distribusi-bhp/pengajuan/{pengajuan}/dokumen/{dokumen}/pdf` | `cetak-dokumen-bhp` | `DokumenController::pdf` |
| POST | `/distribusi-bhp/pengajuan/{pengajuan}/dokumen/batch` | `cetak-dokumen-bhp` | `DokumenController::batch` |
| POST | `/distribusi-bhp/pengajuan/{pengajuan}/dokumen/tambahan` | `cetak-dokumen-bhp` | `DokumenController::tambahan` |
| GET | `/distribusi-bhp/laporan` | `lihat-distribusi-bhp` | `LaporanController::index` |
| GET | `/distribusi-bhp/laporan/kartu-persediaan` | `lihat-distribusi-bhp` | `LaporanController::kartuPersediaan` |
| GET | `/distribusi-bhp/laporan/export` | `cetak-dokumen-bhp` | `LaporanController::export` |

Total: **23 rute**.

Payload penting:

- `POST pengajuan`: `sumber_dana` (`BOS`/`BOPD`), `tanggal`, `details[]`
  (`barang_id`, `jumlah_diminta`, `keperluan`).
- `POST dokumen/tambahan`: `jenis` ∈ {`bast`, `bap`, `stok_opname`,
  `buku_pengeluaran`, `buku_penyaluran`}, `tanggal` (opsional). Idempotent per
  jenis.
- `GET laporan/export`: `sumber_dana`, `jenis` ∈ {`stok`, `pengeluaran`,
  `kartu_persediaan`}; `barang_id` **wajib** bila `jenis=kartu_persediaan`.
- `GET laporan/kartu-persediaan`: `barang_id`, `sumber_dana` (opsional;
  default `BOS`). Mengembalikan `kartu` berisi `barang` (saldo awal/masuk/
  keluar/akhir) + `mutasi[]` dengan saldo berjalan.

### I. Migrasi Data Legacy BHP (seeder)

- **`php artisan db:seed --class=DistribusiBhpImportSeeder`**
  (`database/seeders/DistribusiBhpImportSeeder.php`)
  - Membaca DB SQLite sumber (Next/Prisma). Default:
    `../sistem-manajemen-aset/prisma/dev.db.backup.db`; dapat diarahkan via
    `setSourcePath()`.
  - Cakupan: `bhp_kode_rekening`, `bhp_penyedia`, `bhp_barang`,
    `bhp_harga_barang`, `bhp_penerimaan` + `bhp_penerimaan_detail`.
  - Melewati baris `deleted_at` (bila kolom ada) dan detail
    `cart_status != 'saved'`.
  - Idempotent (upsert berbasis `source_id` untuk penerimaan, natural key untuk
    master). Terdaftar di `DatabaseSeeder`, jadi ikut `migrate --seed`; bila
    sumber tidak ada, seeder hanya memberi peringatan dan dilewati.
  - Terverifikasi `BhpImportSeederTest`.
