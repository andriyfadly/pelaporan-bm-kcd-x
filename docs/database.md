# Skema Database

> Engine: **PostgreSQL**. Seluruh id entitas bisnis memakai UUID
> (`HasUuids`); tabel bawaan Spatie memakai bigint. Sumber kebenaran:
> `database/migrations/`. Di bawah ini ringkasan per 2026-10-01
> (24 migrasi + `activity_log`; 20 migrasi BM/shared + 4 migrasi Distribusi BHP).

## 1. Diagram Relasi (tekstual)

```text
master_data_sekolah (Sekolah)
  ├── 1──N users (sekolah_id NULLABLE: NULL = admin/KCD)
  ├── 1──N pelaporan_bm_acuan
  ├── 1──N pelaporan_bm_spj
  ├── 1──N pelaporan_bm_realisasi
  └── 1──N pelaporan_bm_kunci_laporan  [unique(sekolah_id, bulan)]

pelaporan_bm_acuan (Acuan)
  ├── 1──N pelaporan_bm_spj (acuan_id)
  └── 1──N pelaporan_bm_realisasi (acuan_id)

pelaporan_bm_spj (Spj)
  └── 1──N pelaporan_bm_realisasi (spj_id; dihapus manual dlm transaksi)

pelaporan_bm_kunci_laporan ──N──1 users (dikunci_oleh)
master_data_kode_barang: katalog mandiri (tanpa FK)
activity_log: morph subject/causer (UUID), tanpa FK database

users N──N roles / permissions (tabel Spatie: roles, permissions,
model_has_roles, model_has_permissions, role_has_permissions)
```

### Distribusi BHP (domain terpisah — lihat §5)

```text
master_data_sekolah
  ├── 1──N bhp_penerimaan
  └── 1──N bhp_pengajuan

bhp_kode_rekening (unique kode+sumber_dana)
  └── 1──N bhp_barang

bhp_barang
  ├── 1──N bhp_harga_barang
  ├── 1──N bhp_penerimaan_detail
  └── 1──N bhp_pengajuan_detail

bhp_penyedia ──1──N bhp_penerimaan (penyedia_id)

bhp_penerimaan ──1──N bhp_penerimaan_detail

bhp_pengajuan ──1──N bhp_pengajuan_detail
  └── 1──N bhp_dokumen            (jenis: nota/surat/BAST/BAP/buku/stok opname)
        └── 1──N bhp_batch_dokumen (histori generasi batch; files json)

bhp_nomor_sequence: counter atomic per (jenis, sumber_dana, tahun, bulan), tanpa FK
```

## 2. Kamus Tabel

### `users`
| Kolom | Tipe | Ket |
|-------|------|-----|
| id | uuid PK | — |
| name / username | varchar (unique username) | Login username-only, tanpa email |
| password | varchar (bcrypt) | — |
| sekolah_id | uuid NULLABLE FK → sekolah | NULL = admin/KCD |
| is_active | boolean | — |
| two_factor_* | secret/codes/confirmed_at | 2FA TOTP (Fortify) |
| password_changed_at | timestamptz NULLABLE | NULL = wajib ganti perdana; rotasi 90 hari |

Catatan: migrasi `drop_email_and_email_verified_at` menghapus kolom email.

### `master_data_sekolah`
`id` uuid PK · `no_urut` int · `nama_sekolah` (150) · `kota_kab` (100) ·
`kode_sub_pengguna` (20) · `kode_wilayah` (10) · `npsn` (20) · `id_sekolah_lama` int (jejak migrasi legacy).

### `master_data_kode_barang`
`id` uuid PK · `kode_barang` (50, unique) · `uraian` (255) ·
`kodering_aset` (100) · `jenis_aset` (100) · `umur_ekonomis` int ·
`satuan` (50) · `harga_standar` numeric(15,2).

### `pelaporan_bm_acuan`
`id` uuid PK · `sekolah_id` FK · `tanggal` date · `kodering` (255) ·
`bku` (100) · `uraian` text · `nominal` numeric(15,2) · `bulan` int 1–12 ·
`id_acuan_lama` int (jejak legacy).

### `pelaporan_bm_spj`
Dokumen + item fisik per baris. `sekolah_id` FK · `acuan_id` NULLABLE FK ·
`kategori` (50) · `no_sp2d` (100) · `sumber_perolehan` (100) ·
`bulan_realisasi` int · `no_spk` (150) · `ba_no` (150), `ba_tgl` date ·
barang: `kode_barang` (50), `nama_barang` (255), `jenis_aset` (100),
`merk_tipe` (255), `no_sertifikat` (150), `ukuran_bangunan` (150),
`satuan` (50), `volume` numeric(10,2), `harga_satuan`/`nilai_perolehan`
numeric(15,2) · `id_spj_lama` int.

### `pelaporan_bm_realisasi`
Snapshot alokasi per kodering: `spj_id` FK, `sekolah_id` FK, `acuan_id` FK,
`kodering_belanja` (255), `bulan_realisasi` int + salinan seluruh field
dokumen & barang SPJ · `id_realisasi_lama` bigint.

### `pelaporan_bm_kunci_laporan`
`sekolah_id` FK · `bulan` int · `tahun` int · `status_kunci` boolean ·
`dikunci_pada` · `dikunci_oleh` (uuid → users) · `status_kirim`
(`draft`/`menunggu_approval`/`disetujui`) · `dikirim_pada`.
Unique `(sekolah_id, bulan)`.

### `activity_log` (Spatie v5, morph UUID)
`id` bigint PK · `log_name` (app memakai `sistem`) · `description` text ·
`subject_type`/`subject_id` (uuid) · `event` · `causer_type`/`causer_id` (uuid) ·
`attribute_changes` json (`attributes` + `old`) · `properties` json
(`ringkasan`, `sekolah_id`, konteks) · timestamps.
Tanpa purge (keputusan produk).

### Tabel pendukung
Spatie permission (`roles`, `permissions`, `model_has_*`, `role_has_permissions`),
Fortify (`password_reset_tokens`, 2FA di users, `passkeys`), `sessions`,
`cache`, `jobs`/`job_batches`/`failed_jobs`.

## 5. Tabel Distribusi BHP

Domain BHP terpisah total dari `pelaporan_bm_*` (lihat
`docs/requirements/domain-boundaries.md`). Semua memakai UUID.

### Migrasi

| File | Isi |
|---|---|
| `2026_10_01_000001_create_distribusi_bhp_master_tables` | Master + transaksi |
| `2026_10_01_000002_create_distribusi_bhp_dokumen_tables` | `bhp_dokumen`, `bhp_nomor_sequence` |
| `2026_10_01_000003_create_distribusi_bhp_batch_tables` | `bhp_batch_dokumen` |
| `2026_10_01_000004_add_source_id_to_bhp_penerimaan` | `source_id` unik (jejak impor) |

### Master

| Tabel | Kolom penting |
|---|---|
| `bhp_kode_rekening` | `kode` (100) · `nama` · `kode_barang` · `uraian_kode_barang` · `sumber_dana` enum `BOS`/`BOPD` · unique `(kode, sumber_dana)` |
| `bhp_barang` | `kode_barang` · `nama_barang` · `spesifikasi_barang_nusp` · `satuan` · `kode_rekening_id` (FK nullable) · `sumber_dana` |
| `bhp_harga_barang` | `barang_id` FK · `harga_satuan` bigint · `created_by` (uuid nullable) |
| `bhp_penyedia` | `nama` (unique) · `alamat` · `telepon` · `email` |

Catatan: `bhp_barang` **tidak** menyimpan `stok_tereservasi`; angka stok selalu
dihitung (`StokService`).

### Transaksi

| Tabel | Kolom penting |
|---|---|
| `bhp_penerimaan` | `source_id` (unique, nullable) · `sekolah_id` FK nullable · `no_nota_invoice`/`no_bast`/`no_kwitansi` · `tanggal_bast`/`tanggal_titimangsa` date · `penyedia_id` FK nullable · `is_saldo_awal` bool · `periode_tahun` int · `sumber_dana` |
| `bhp_penerimaan_detail` | `bhp_penerimaan_id` FK · `barang_id` FK nullable · `harga_barang_id` nullable · `jumlah` int · `harga_satuan`/`nilai_total` bigint nullable · `merek_type` · `keterangan` |
| `bhp_pengajuan` | `sekolah_id` FK nullable · `divisi_id`/`user_id` uuid nullable · `nomor_pengajuan` · `tanggal` date · `sumber_dana` · `status` enum `draft`/`submitted`/`approved`/`rejected` · `review_notes`/`rejection_reason` · `reviewed_by`/`reviewed_at` |
| `bhp_pengajuan_detail` | `bhp_pengajuan_id` FK · `barang_id` FK nullable · `jumlah_diminta` int · `jumlah_disetujui` int nullable · `harga_satuan` nullable · `keperluan`/`keterangan` · `status_reservasi` (30) · `jumlah_direservasi` int |

### Dokumen & penomoran

| Tabel | Kolom penting |
|---|---|
| `bhp_dokumen` | `bhp_pengajuan_id` FK · `jenis` (nota_permintaan, surat_permintaan, surat_penyaluran, bast, bap, stok_opname, buku_pengeluaran, buku_penyaluran) · `nomor` · `tanggal_titimangsa` date · `payload` json (snapshot) |
| `bhp_nomor_sequence` | `jenis` · `sumber_dana` · `tahun` · `bulan` · `counter` — dikunci `lockForUpdate` saat generate |
| `bhp_batch_dokumen` | `jenis` · `sumber_dana` · `bhp_pengajuan_id` FK · `status` (`pending`/`selesai`/`sebagian`/`gagal`) · `total`/`berhasil`/`gagal` · `files` json · `created_by` · `error_message` |

## 6. Konvensi & Batasan

- Uang BM: `numeric(15,2)`; volume `numeric(10,2)`; bulan int 1–12 (bukan date).
- Uang BHP: bigint rupiah (harga satuan, nilai total).
- `nilai_perolehan = volume × harga_satuan` dihitung server-side (BM).
- **Stok BHP 4-tier:** `Tersedia = Fisik − Terpakai − Reservasi`, di-`max(0,…)`.
  `Fisik` = Σ detail penerimaan; `Terpakai` = Σ `jumlah_disetujui` pengajuan
  `approved`; `Reservasi` = Σ `jumlah_direservasi` pengajuan `submitted`.
- Semua mutasi stok lewat `StokService` dengan `lockForUpdate`.
- Sumber dana (`BOS`/`BOPD`) selalu menjadi filter wajib; BOS dan BOPD tidak
  boleh tercampur pada stok/laporan.
- Hapus SPJ/SPK BM: cascade manual (`realisasi` by `spj_id` dulu) dalam transaksi.
- Unique: `kode_barang`, `username`, `(sekolah_id, bulan)` di kunci,
  `bhp_penyedia.nama`, `bhp_kode_rekening(kode, sumber_dana)`,
  `bhp_penerimaan.source_id`.
- Migrasi legacy: kolom `*_lama` (BM) dan `source_id` (BHP) + `updateOrCreate`
  idempoten.

## 7. Perintah Terkait

```bash
php artisan migrate --seed        # instalasi baru (sekalian impor BHP legacy)
php artisan app:migrasi-data-lama # impor dump legacy BM (idempoten, tanpa log)
php artisan migrate:fresh --seed  # reset total (DEV SAJA)
```

Import BHP dapat diarahkan ke file sumber lain:

```php
(new Database\Seeders\DistribusiBhpImportSeeder)
    ->setSourcePath('/path/ke/sumber.db')
    ->run();
```

Default sumber: `../sistem-manajemen-aset/prisma/dev.db.backup.db`. Seeder
melewati baris soft-deleted, detail `cart_status != 'saved'`, dan aman
diulang (idempotent).
