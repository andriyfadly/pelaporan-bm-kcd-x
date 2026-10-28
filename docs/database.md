# Skema Database

> Engine: **PostgreSQL**. Seluruh id entitas bisnis memakai UUID
> (`HasUuids`); tabel bawaan Spatie memakai bigint. Sumber kebenaran:
> `database/migrations/`. Di bawah ini ringkasan per 2026-10-27
> (48 migrasi + `activity_log`; tabel BHP dipisah fisik per sumber dana).

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

> **Penting:** tabel transaksi & master barang BHP **dipisah fisik per sumber
> dana** menjadi `bhp_bos_*` dan `bhp_bopd_*` (migrasi
> `2026_10_12_000001_split_bhp_tables_per_sumber_dana`). Sumber dana **implisit
> dari nama tabel** — tidak ada lagi kolom `sumber_dana`. Hanya `bhp_penyedia`
> dan `bhp_divisi` yang tetap satu tabel bersama lintas sumber dana. Pemetaan
> suffix → tabel fisik terpusat di `App\Support\DistribusiBhp\SumberDana`
> (dipakai model via trait `HasSumberDanaTable` dan query builder mentah).

```text
master_data_sekolah
  ├── 1──N bhp_{bos,bopd}_penerimaan            [FK restrict]
  └── 1──N bhp_{bos,bopd}_pengajuan             [FK restrict]

bhp_divisi [unique(sekolah_id, nama)]
  ├── 1──N bhp_{bos,bopd}_pengajuan             [FK restrict]
  └── 1──N users (divisi_id)                    [FK set null saat divisi dihapus]

bhp_{bos,bopd}_kode_rekening (unique kode per tabel)
  └── 1──N bhp_{bos,bopd}_barang

bhp_{bos,bopd}_barang
  ├── 1──N bhp_{bos,bopd}_harga_barang          [FK cascade]
  ├── 1──N bhp_{bos,bopd}_penerimaan_detail     [FK restrict]
  └── 1──N bhp_{bos,bopd}_pengajuan_detail      [FK restrict]

bhp_penyedia ──1──N bhp_{bos,bopd}_penerimaan (penyedia_id)

bhp_{bos,bopd}_penerimaan ──1──N bhp_{bos,bopd}_penerimaan_detail [FK cascade]
  └── 1──N bhp_{bos,bopd}_pengajuan ──1──N bhp_{bos,bopd}_pengajuan_detail [FK cascade]
        ├── 1──N bhp_{bos,bopd}_dokumen         [FK cascade; unique(pengajuan, jenis)]
        └── 1──N bhp_{bos,bopd}_batch_dokumen   [FK set null; histori generasi batch; files json]

bhp_{bos,bopd}_settings: satu baris per tabel (partial unique index pada
              is_singleton)

master_data_pegawai ──N──1 master_data_sekolah
master_data_jabatan ──N──1 master_data_sekolah
master_data_penugasan_pegawai ──N──1 master_data_sekolah
        (riwayat penugasan pegawai pada jabatan; satu pemegang efektif per
         (sekolah, jabatan) via partial unique index; tabel bersama, non-BHP)

bhp_{bos,bopd}_nomor_sequence: counter atomic per (jenis, tahun, bulan),
              unique(jenis, tahun, bulan), tanpa FK
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
`kode_sub_pengguna` (20) · `kode_wilayah` (10) · `npsn` (20) · `id_sekolah_lama` int (jejak migrasi legacy) ·
profil kontak: `alamat` (500), `telepon` (30), `website` (255), `email` (255) —
diisi operator sekolah sendiri via `/master-data/profil-sekolah`.

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
| `2026_10_08_151827_create_bhp_settings_table` | `bhp_settings` (format nomor/PDF) |
| `2026_10_08_153505_create_bhp_divisi_table` | `bhp_divisi` + `users.divisi_id` |
| `2026_10_08_160849_add_singleton_key_to_bhp_settings_table` | `bhp_settings.is_singleton` + partial unique index (anti-race singleton) |
| `2026_10_08_161500_add_foreign_keys_to_bhp_tables` | Foreign key relasi BHP |
| `2026_10_08_163932_create_pejabat_sekolah_table` | Master pejabat penandatangan + partial unique index satu-aktif (**tabel dihapus** oleh `2026_10_27_000006_drop_pejabat_sekolah_table`) |
| `2026_10_08_163940_drop_penyetuju_from_bhp_settings_table` | Hapus `nama_penyetuju`/`jabatan_penyetuju` global (diganti pejabat sekolah) |
| `2026_10_08_170000_add_profil_kontak_to_master_data_sekolah_table` | `master_data_sekolah.alamat/telepon/website/email` (profil kontak, diisi operator) |
| `2026_10_10_000001_add_saldo_awal_unique_key_to_bhp_penerimaan` | Kolom bantu `saldo_awal_key` + partial unique (jaring race saldo awal) |
| `2026_10_11_000001_drop_pool_pusat_saldo_awal` | `sekolah_id` **NOT NULL** (hilangkan pool pusat); ganti unique ke `(sekolah_id, sumber_dana, periode_tahun)` |
| `2026_10_13_000001_drop_paper_from_bhp_settings` | **Hapus** kolom `paper_size` & `orientation` dari `bhp_{bos,bopd}_settings` — PDF hardcode per jenis dokumen |

### Master

Semua suffix di bawah punya **dua tabel fisik** (`bhp_bos_*` dan
`bhp_bopd_*`). Kolom `sumber_dana` **tidak ada** — nilainya implisit dari nama
tabel.

| Tabel | Kolom penting |
|---|---|---|
| `bhp_{dana}_kode_rekening` | `kode` (100) · `nama` · `kode_barang` (100, nullable) · `uraian_kode_barang` (255, nullable) · unique `kode` per tabel. `kode_barang` diisi dari 5 opsi statis (`1.1.7.01.01`–`1.1.7.01.07`, lihat `App\Support\DistribusiBhp\KodeBarang::OPSI`) — bukan FK. `KodeRekeningBhpSeeder` mengisinya otomatis dari nama rekening, sehingga database baru di-seed langsung bisa dipakai Tambah Barang; pemegang `assign-kode-rekening-bhp` dapat mengoreksi lewat dropdown per baris. |
| `bhp_{dana}_barang` | `kode_barang` · `nama_barang` · `spesifikasi_barang_nusp` · `satuan` · `kode_rekening_id` (FK nullable) |
| `bhp_{dana}_harga_barang` | `barang_id` FK · `harga_satuan` bigint · `created_by` (uuid nullable) |
| `bhp_penyedia` *(bersama)* | `nama` (unique) · `alamat` · `telepon` · `email` |
| `bhp_divisi` *(bersama)* | `sekolah_id` FK · `nama` · `kode` — `users.divisi_id` FK set null |

Catatan: `bhp_{dana}_barang` **tidak** menyimpan `stok_tereservasi`; angka stok
selalu dihitung (`StokService`).

### Transaksi

| Tabel | Kolom penting |
|---|---|
| `bhp_{dana}_penerimaan` | `source_id` (unique, nullable) · `sekolah_id` FK **NOT NULL** (tanpa pool pusat) · `no_nota_invoice`/`no_bast`/`no_kwitansi` · `tanggal_bast`/`tanggal_titimangsa`/`tanggal_pembayaran` date · `penyedia_id` FK nullable · `is_saldo_awal` bool · `periode_tahun` int · partial unique `bhp_{dana}_penerimaan_saldo_awal_unique` `(sekolah_id, periode_tahun) WHERE is_saldo_awal` |
| `bhp_{dana}_penerimaan_detail` | `bhp_penerimaan_id` FK · `barang_id` FK nullable · `harga_barang_id` nullable · `jumlah` int · `harga_satuan`/`nilai_total` bigint nullable · `merek_type` · `keterangan` |
| `bhp_{dana}_pengajuan` | `sekolah_id` FK nullable · `divisi_id`/`user_id` uuid nullable · `nomor_pengajuan` · `tanggal` date · `status` enum `draft`/`submitted`/`approved`/`rejected` · `review_notes`/`rejection_reason` · `reviewed_by`/`reviewed_at` |
| `bhp_{dana}_pengajuan_detail` | `bhp_pengajuan_id` FK · `barang_id` FK nullable · `jumlah_diminta` int · `jumlah_disetujui` int nullable · `harga_satuan` nullable · `keperluan`/`keterangan` · `status_reservasi` (30) · `jumlah_direservasi` int |

### Dokumen & penomoran

| Tabel | Kolom penting |
|---|---|
| `bhp_{dana}_dokumen` | `bhp_pengajuan_id` FK cascade · `jenis` (nota_permintaan, surat_permintaan, surat_penyaluran, bast, bap, stok_opname, buku_pengeluaran, buku_penyaluran) · `nomor` · `tanggal_titimangsa` date · `payload` json (snapshot, **ter-scope sekolah pengajuan**) — unique `(bhp_pengajuan_id, jenis)` |
| `bhp_{dana}_nomor_sequence` | `jenis` · `tahun` · `bulan` · `counter` — dikunci `lockForUpdate` saat generate; insert row pertama aman terhadap race (catch `UniqueConstraintViolationException` lalu lock row pemenang) |
| `bhp_{dana}_batch_dokumen` | `jenis` · `bhp_pengajuan_id` FK set null · `status` (`pending`/`selesai`/`sebagian`/`gagal`) · `total`/`berhasil`/`gagal` · `files` json · `created_by` · `error_message` |
| `bhp_{dana}_settings` | `format_nomor` · `is_singleton` (unique: maks satu baris per tabel dana). **`paper_size` & `orientation` dihapus** (2026-10-13) — ukuran/orientasi PDF hardcode per jenis dokumen di `DokumenController::PAPER`. Penandatangan TIDAK lagi di sini — lihat `master_data_penugasan_pegawai` |
| `master_data_jabatan` *(generic, non-BHP)* | `sekolah_id` FK · `kode` · `nama` · `kategori` · `aktif` · soft deletes · unique `(sekolah_id, kode)`. Tiga kode wajib untuk penandatangan: `kepala_sekolah`, `wakasek_sarpras`, `pembantu_pengurus_barang`. Dibuat otomatis (`Jabatan::pastikanWajib`, dibuka saat halaman dibuka + `JabatanWajibSeeder`); kode & namanya dikunci, hanya `kategori` yang dapat diubah, dan tidak dapat dihapus |
| `master_data_penugasan_pegawai` *(generic, non-BHP)* | `sekolah_id` FK · `pegawai_id` FK · `jabatan_id` FK · `jenis_penugasan` (`definitif`/`plt`/`plh`) · `mulai_menjabat`/`selesai_menjabat` date · `created_by`/`updated_by` · unique `(sekolah_id, jabatan_id, mulai_menjabat)` + partial unique satu-pemegang `(sekolah_id, jabatan_id) WHERE selesai_menjabat IS NULL` |

## 6. Konvensi & Batasan

- Uang BM: `numeric(15,2)`; volume `numeric(10,2)`; bulan int 1–12 (bukan date).
- Uang BHP: bigint rupiah (harga satuan, nilai total).
- `nilai_perolehan = volume × harga_satuan` dihitung server-side (BM).
- **Stok BHP 4-tier:** `Tersedia = Fisik − Terpakai − Reservasi`, di-`max(0,…)`.
  `Fisik` = Σ detail penerimaan; `Terpakai` = Σ `jumlah_disetujui` pengajuan
  `approved`; `Reservasi` = Σ `jumlah_direservasi` pengajuan `submitted`.
- Semua mutasi stok lewat `StokService` dengan `lockForUpdate`. Untuk laporan
  stok massal, `StokService::rekapPerBarang()` mengagregasi seluruh barang
  dalam 3 query grouped (fisik/terpakai/reservasi) — tanpa N+1.
- Sumber dana BHP (`BOS`/`BOPD`) **implisit dari nama tabel fisik**
  (`bhp_bos_*`/`bhp_bopd_*`); tidak ada kolom `sumber_dana`. Akses model
  di-resolve runtime via `SumberDana::tabel()` + trait `HasSumberDanaTable`;
  BOS dan BOPD tidak boleh tercampur pada stok/laporan.
- **Foreign key BHP** (migrasi 2026-10-08, diterapkan ulang per tabel saat
  split 2026-10-12): detail & dokumen cascade mengikuti
  parent; `barang_id` pada detail transaksi dan `divisi_id` pada pengajuan
  `restrict` (histori tidak boleh yatim); `bhp_{dana}_pengajuan.user_id` dan
  `users.divisi_id` `set null` saat referensi dihapus.
- Snapshot payload dokumen (`bhp_{dana}_dokumen.payload`) selalu ter-scope
  `sekolah_id` milik pengajuan — tidak pernah memuat data sekolah lain.
- **Penandatangan dokumen BHP**: approval mensyaratkan ketiga jabatan
  (`kepala_sekolah`, `wakasek_sarpras`, `pembantu_pengurus_barang`) terisi dan
  berlaku pada tanggal pengajuan
  (`App\Services\Master\PenugasanPegawaiService::snapshotPenandatangan`).
  Sumbernya `master_data_pegawai` + `master_data_jabatan` +
  `master_data_penugasan_pegawai`; snapshot tersimpan di
  `payload.penandatangan` tiap dokumen sehingga reprint tidak berubah meski
  pejabat berganti. Riwayat penugasan tidak dihapus saat mutasi — periode lama
  ditutup (`selesai_menjabat`), bukan ditimpa.
- Hapus SPJ/SPK BM: cascade manual (`realisasi` by `spj_id` dulu) dalam transaksi.
- Unique: `kode_barang`, `username`, `(sekolah_id, bulan)` di kunci,
  `bhp_penyedia.nama`, `bhp_{dana}_kode_rekening(kode)`,
  `bhp_{dana}_penerimaan.source_id`, `(bhp_pengajuan_id, jenis)` di dokumen,
  `(jenis, tahun, bulan)` di nomor sequence,
  `bhp_{dana}_settings.is_singleton` (partial),
  partial unique saldo awal `(sekolah_id, periode_tahun) WHERE is_saldo_awal`,
  `master_data_penugasan_pegawai` satu pemegang efektif per
  `(sekolah_id, jabatan_id)` (partial).
- `bhp_{dana}_kode_rekening.kode_barang` diisi dari 5 opsi statis (mirror sistem
  manajemen aset): `1.1.7.01.01` (Bahan), `1.1.7.01.02` (Suku Cadang),
  `1.1.7.01.03` (Alat/Bahan Untuk Kegiatan Kantor), `1.1.7.01.04` (Obat-Obatan),
  `1.1.7.01.07` (Naturan dan Pakan). Nilai hardcoded di
  `KodeRekeningController::KODE_BARANG_OPTIONS` — bukan FK ke tabel manapun.
  Role sekolah (operator/bendahara) dapat batch-assign via dropdown per baris;
  admin KCD mengisi langsung saat tambah kode rekening.
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

---

## 8. Tabel Pencatatan BM (inventaris aset)

Migrasi: `2026_10_27_000001_create_inventaris_master_tables.php` (ruang,
pegawai — kemudian di-rename), `2026_10_27_000002_create_inventaris_tables.php`
(unit, sequence, mutasi, dokumen, foto, sync log),
`2026_10_27_000003_add_pembatalan_nomor_to_inventaris_dokumen.php`,
`2026_10_27_000004_promote_inventaris_master_tables.php` (rename ke
`master_data_ruang`/`master_data_pegawai` + kolom `aktif`),
`2026_10_27_000005_create_master_data_jabatan_and_penugasan_tables.php`,
`2026_10_27_000006_drop_pejabat_sekolah_table.php`,
`2026_10_27_000007_drop_jabatan_ruang_from_master_data_pegawai.php`.

```text
master_data_sekolah 1─┬─* master_data_ruang  1─* inventaris_unit ─1─* inventaris_mutasi
                      ├─* master_data_pegawai 1─* inventaris_unit  ─1─* inventaris_foto
                      ├─* master_data_jabatan 1─* master_data_penugasan_pegawai
                      │                              └─* master_data_pegawai
                      ├─* inventaris_register_sequences
                      ├─* inventaris_dokumen
                      └─* inventaris_sync_logs
pelaporan_bm_realisasi 1─* inventaris_unit   (restrict; sumber pembentukan unit)
master_data_ruang / master_data_pegawai ─* inventaris_mutasi (asal/tujuan, nullOnDelete)
```

### `inventaris_unit`

Satu baris = satu unit fisik. `volume = N` pada realisasi menghasilkan N unit
(`unit_index` 1..N).

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | uuid PK | |
| `sekolah_id` | uuid FK `master_data_sekolah` | restrict; **immutable** |
| `realisasi_id` | uuid FK `pelaporan_bm_realisasi` | restrict; **immutable** |
| `unit_index` | int | unique bersama `realisasi_id` |
| `kode_barang`, `nama_barang`, `jenis_aset`, `merk_tipe`, `no_sertifikat`, `ukuran_bangunan`, `satuan` | | snapshot sumber |
| `nomor_register` | varchar(10) | `001`, `002`, … unique per `(sekolah_id, kode_barang)` |
| `tanggal_perolehan`, `bulan_perolehan`, `tahun_perolehan` | | dari BA/realisasi |
| `harga_satuan`, `nilai_perolehan_unit` | numeric(15,2) | 1 unit = 1 harga satuan |
| `kondisi` | varchar(2) | `B`, `RR`, `RB` |
| `status` | varchar(20) | `aktif`, `dikembalikan`, `dihapus_koreksi` |
| `ruang_id`, `pegawai_id` | uuid nullable | FK `nullOnDelete` |
| `foto_path`, `keterangan` | | |
| timestamps | | tanpa soft delete: unit surplus ditandai `dihapus_koreksi` |

Index: `(sekolah_id, kode_barang)`, `(sekolah_id, status)`,
`(sekolah_id, ruang_id)`, `(sekolah_id, pegawai_id)`.

### `inventaris_register_sequences`

`(sekolah_id, kode_barang)` unique, `next_number` int. Nomor dialokasikan di
dalam transaksi dengan `lockForUpdate`; nomor yang sudah terbit **tidak pernah
dipakai ulang**, termasuk setelah unit dipensiunkan atau volume dikoreksi.

### `master_data_ruang` / `master_data_pegawai`

Master milik sekolah, soft delete, kolom `aktif`. Nama ruang unik
case-insensitive per sekolah (unique index `lower(nama_ruang)`).
`master_data_pegawai` hanya menyimpan identitas: `nama_lengkap`, `nip`,
`pangkat_golongan`. **Tidak ada kolom jabatan maupun ruang** — jabatan berasal
dari `master_data_penugasan_pegawai`, penempatan aset dari `inventaris_unit.ruang_id`.

### `master_data_jabatan` / `master_data_penugasan_pegawai`

`master_data_jabatan` mendefinisikan jabatan per sekolah (kode stabil, nama
bebas diubah — kecuali tiga jabatan wajib penandatangan yang kode dan namanya
dikunci). `master_data_penugasan_pegawai` menyimpan satu periode per baris:
`definitif`/`plt`/`plh`. Menambah penugasan aktif menutup periode sebelumnya
(H-1), jadi satu jabatan hanya punya satu pemegang efektif dan tidak ada
periode beririsan. Pemilihan pejabat untuk dokumen bertanggal `T` memakai
`mulai_menjabat <= T AND (selesai_menjabat IS NULL OR selesai_menjabat >= T)`
dengan prioritas `definitif` > `plt` > `plh` lalu `mulai_menjabat` terbaru.

### `inventaris_mutasi`

Histori penempatan/mutasi/pengembalian/koreksi. Menyimpan asal & tujuan
ruang/pegawai/kondisi, `tanggal`, `keterangan`, `user_id`. Tidak pernah dihapus;
FK `inventaris_unit_id` restrict.

### `inventaris_dokumen`

Dokumen cetak (`bast`, `fakta_integritas`, `surat_pengembalian`). `payload` JSON
berisi **snapshot** sekolah/unit/pegawai/pejabat/mutasi saat dokumen dibuat,
sehingga cetak ulang tidak berubah setelah koreksi. `nomor_dibatalkan_pada`
menandai nomor BA yang dibatalkan (dokumen tetap ada).

### `inventaris_foto`

`path`, `mime`, `size`, `checksum` (SHA-256), `uploaded_by`. Disk `public`,
path relatif `inventaris/foto/{sekolah_id}/…`.

### `inventaris_sync_logs`

Observability saja: `status` (`berjalan`/`sukses`/`gagal`), `dibuat`, `tetap`,
`surplus`, `perlu_review`, `error`, `actor_id`, `started_at`, `finished_at`.

### Aturan koreksi sumber

- Nama/merk/harga sumber yang berubah **menyegarkan** unit yang belum pernah
  dimutasi; unit bermutasi mempertahankan snapshot lamanya.
- Volume turun: unit surplus **tanpa** histori ditandai `dihapus_koreksi`
  (tidak di-hard-delete); unit yang sudah bermutasi tetap `aktif` dan masuk
  daftar "perlu tinjau manual".
- `sekolah_id` dan `realisasi_id` unit tidak pernah berubah, termasuk saat
  mutasi/pengembalian.
