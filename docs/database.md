# Skema Database

> Engine: **PostgreSQL**. Seluruh id entitas bisnis memakai UUID
> (`HasUuids`); tabel bawaan Spatie memakai bigint. Sumber kebenaran:
> `database/migrations/`. Di bawah ini ringkasan per 2026-09-16
> (19 migrasi + `activity_log`).

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

## 3. Konvensi & Batasan

- Uang: `numeric(15,2)`; volume `numeric(10,2)`; bulan int 1–12 (bukan date).
- `nilai_perolehan = volume × harga_satuan` dihitung server-side.
- Hapus SPJ/SPK: cascade manual (`realisasi` by `spj_id` dulu) dalam transaksi.
- Unique: `kode_barang`, `username`, `(sekolah_id, bulan)` di kunci.
- Migrasi legacy: kolom `*_lama` (`id_sekolah_lama`, `id_acuan_lama`,
  `id_spj_lama`, `id_realisasi_lama`) + `updateOrCreate` idempoten.

## 4. Perintah Terkait

```bash
php artisan migrate --seed        # instalasi baru
php artisan app:migrasi-data-lama # impor dump legacy (idempoten, tanpa log)
php artisan migrate:fresh --seed  # reset total (DEV SAJA)
```
