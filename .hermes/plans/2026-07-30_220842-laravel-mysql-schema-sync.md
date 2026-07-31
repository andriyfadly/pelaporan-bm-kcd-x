# Migrasi Laravel + Sinkronisasi MySQL Implementation Plan

> **For Hermes:** Implementasikan task satu per satu dengan skill gates di bawah. Jangan commit tanpa permintaan user. Semua test baru wajib Pest; hentikan penambahan test berbentuk class PHPUnit.

**Goal:** Memindahkan aplikasi PHP aktif ke Laravel secara bertahap, tetap memakai MySQL dan data lama, memakai `public_id` UUIDv7 untuk URL tanpa mengganggu 77 sekolah.

**Architecture:** Laravel monolith baru berjalan berdampingan dengan aplikasi lama. Aplikasi ini memiliki schema `kcd_x_belanja_modal`, membaca `kcd_x_inventaris_sekolah`, dan mengonsumsi data sekolah dari aplikasi dedicated `kcd_x_master`. Aplikasi ini tidak memiliki migration atau write command untuk schema master. Primary/foreign key lama tetap dipertahankan; perubahan schema transaksi pertama bersifat additive. Route publik memakai `public_id`, sedangkan authorization tetap memakai kepemilikan `id_sekolah` dan Policy.

**Tech Stack:** Laravel versi stabil yang kompatibel dengan PHP produksi, PHP-FPM, MySQL 8+, Blade, Tailwind CSS v4 melalui Vite, Eloquent, Laravel Queue berbasis database pada tahap awal, Pest sebagai satu-satunya gaya test aplikasi.

---

## 0. Skill Gates Wajib

Setiap fase harus memuat skill terkait sebelum kode diubah. Skill audit bersifat read-only; jalankan setelah implementasi fase selesai, bukan sambil memodifikasi file.

| Fase | Skill wajib | Fungsi |
|---|---|---|
| Semua perubahan perilaku | `test-driven-development` | Pest RED → GREEN → REFACTOR; satu perilaku per siklus |
| Migrasi aplikasi aktif | `legacy-application-migration` | additive migration, parity, canary, cutover, rollback |
| Schema/query/migration | `database-backend-audit` | cocokkan migration dengan live MySQL, constraint, money, index, lock |
| Model, route, controller, queue | `laravel-backend-audit` | trace route → middleware → request → policy → model/query |
| Tenant/sekolah | `multi-tenant-data-isolation` | actor-derived tenant scope dan test dua sekolah |
| Security gate | `application-security-audit` + referensi Laravel | IDOR, CSRF, mass assignment, auth/session, rate limit, secret/log leakage |
| Diagnosis kegagalan | `systematic-debugging` | cari root cause sebelum patch |
| Final review | `requesting-code-review`, lalu `agent-self-evaluation` | quality gate dan evaluasi hasil |

Aturan eksekusi:

1. Test baru ditulis memakai Pest function syntax: `it(...)`, `test(...)`, `expect(...)`.
2. Jalankan target Pest sampai RED karena behavior belum ada, bukan karena syntax/setup error.
3. Implementasikan diff minimum, lalu buktikan GREEN.
4. Jalankan neighboring tests dan full suite secara serial bila berbagi database.
5. Audit backend/security wajib kembali ke clean/no-new-diff setelah review; finding diperbaiki lewat siklus Pest baru.
6. `composer audit --locked --no-interaction`, `vendor/bin/pint --test`, `php artisan route:list --except-vendor`, dan `php artisan test --compact` menjadi quality gates.
7. Jangan menjalankan migration/seed/test mutating terhadap database produksi atau database lokal berisi data kerja; gunakan database MySQL khusus test.

---

## 1. Hasil Audit Database Lokal

Audit read-only dilakukan 2026-07-30. Kredensial dan nilai data pengguna tidak dicetak.

### Database utama

Database aktif: `kcd_x_belanja_modal`

| Tabel | Baris perkiraan | Primary key | Catatan |
|---|---:|---|---|
| `kode_sekolah` | 77 | `id INT` | `id_sekolah` lama seluruhnya `NULL`; jangan jadikan sumber relasi |
| `users` | 78 | `id INT` | 1 admin, 77 user sekolah; admin tidak punya pasangan sekolah dan itu valid |
| `data_barang_acuan` | 693 | `id INT` | `id_sekolah VARCHAR(50)`, seluruh nilai saat ini numerik dan valid |
| `laporan_realisasi` | 67 | `id INT` | unique `(id_sekolah, bulan)` sudah ada |
| `master_barang_sekolah` | 666 | `id INT` | `id_sekolah VARCHAR(50)`; bulan saat ini 6 dan 7 |
| `realisasi_barang_sekolah` | 612 | `id BIGINT` | satu-satunya FK aktif menuju `master_barang_sekolah.id` |
| `realisasi_lock` | 0 | `id INT` | belum punya unique school/month |
| `status_kirim_berkas` | 0 | `id INT` | tampak tumpang tindih dengan status laporan; jangan migrasikan fungsi sebelum ditelusuri |

Database inventaris sebenarnya bernama `kcd_x_inventaris_sekolah` dan harus dipetakan melalui `DB_INV`. Audit terbaru membuktikan database lokal ini tersedia dengan 11 tabel; `kode_barang` berisi 12.957 row dan `kode_sekolah` berisi 74 row. Beberapa kode masih memakai fallback atau hardcode `db_inventaris`; itu wajib diganti dengan connection `inventory` terkonfigurasi. `kcd_x_master` dimiliki aplikasi dedicated lain; schema dan migration master bukan scope repository ini.

### Temuan kualitas data

- Semua `id_sekolah` terisi pada tabel aktif, numerik, dan cocok ke `kode_sekolah.id`.
- Tidak ada orphan pada `realisasi_barang_sekolah.id_master_barang`.
- Tidak ada perbedaan sekolah antara realisasi dan master terkait.
- Tidak ada duplikat `(id_sekolah, bulan)` pada `laporan_realisasi`.
- Nilai uang `realisasi_barang_sekolah` saat ini tidak memiliki lebih dari dua angka desimal.
- `harga_satuan` dan `nilai_perolehan` pada tabel realisasi masih `DOUBLE`; target akhirnya `DECIMAL(15,2)`.
- `volume` master memakai `DECIMAL(10,2)`, tetapi volume realisasi memakai `INT`; perubahan perlu keputusan domain dan uji data.
- Ada 220 grup SPK berulang. Itu kemungkinan item-item dalam satu SPK, bukan duplikat; jangan pasang unique pada `(id_sekolah, no_spk, bulan_realisasi)`.
- Ada 154 grup acuan dengan business key sederhana berulang; jangan membuat unique constraint tanpa definisi key bisnis.
- Kolasi bercampur `utf8mb4_unicode_ci` dan `utf8mb4_0900_ai_ci`.

## 2. Keputusan dan Batasan

1. Pakai MySQL; jangan pindah ke PostgreSQL bersamaan dengan migrasi Laravel.
2. Pertahankan semua nilai `id` lama agar aplikasi lama dan baru tetap sinkron.
3. Tambahkan `public_id CHAR(36)` berisi UUIDv7 pada record yang dapat muncul di URL/API.
4. Jangan menerima `id`, `public_id`, atau `id_sekolah` dari payload update sebagai sumber kebenaran.
5. Ambil sekolah dari user login; Policy wajib memeriksa kepemilikan untuk `show/edit/update/delete`.
6. Jangan langsung mengubah `id_sekolah VARCHAR(50)` menjadi integer selama legacy masih menulis ke database.
7. Jangan memasang FK baru lintas seluruh tabel sebelum audit produksi, cleanup, backup, dan uji lock duration.
8. Semua perubahan produksi additive pada release pertama; drop/rename/type conversion dilakukan setelah legacy dimatikan.
9. Laravel app ditempatkan sementara di `laravel/` agar aplikasi lama tetap dapat dijalankan dan dibandingkan.
10. Tidak ada microservices, Redis, atau database per sekolah pada fase migrasi.
11. Tiga Laravel connection logis: `mysql` → `kcd_x_belanja_modal`, `master` → `kcd_x_master`, `inventory` → `kcd_x_inventaris_sekolah`.
12. Aplikasi dedicated `kcd_x_master` menjadi source of truth sekolah lintas aplikasi. Repository ini tidak boleh membuat migration, menulis langsung, atau mengimpor data ke schema master.
13. Integrasi master memakai kontrak read-only yang ditentukan aplikasi master (API lebih disukai; koneksi DB read-only hanya bila kontrak API belum tersedia). Data yang dibutuhkan request disimpan sebagai proyeksi lokal agar availability master tidak mematikan transaksi.
14. Pest menjadi test runner dan gaya test tunggal. Test class PHPUnit yang sudah dibuat harus dikonversi sebelum fitur dilanjutkan.

## 3. Acceptance Criteria

- Fresh Laravel test database dapat dibangun dari baseline schema terverifikasi.
- Laravel dapat membaca semua 77 sekolah dan 78 user tanpa mengubah primary key.
- Setiap entitas publik memiliki UUIDv7 unik dan non-null setelah backfill.
- Tidak ada URL Laravel yang menampilkan primary key numerik.
- User sekolah A tidak dapat membaca/mengubah record sekolah B walau mengetahui `public_id`.
- Admin hanya mendapat akses yang secara eksplisit diberikan Policy.
- Total baris, total nominal, status laporan, dan hasil ekspor legacy versus Laravel cocok.
- Legacy tetap berfungsi selama fase parallel run.
- Backup dan restore rehearsal lulus sebelum migration produksi.
- Semua test aplikasi memakai function-style Pest.
- Security audit tidak menemukan IDOR lintas sekolah, mass-assignment ownership, atau mutation route tanpa CSRF/authz.

---

## 4. Rencana Implementasi

### Task 1: Bekukan kontrak dan ambil snapshot produksi

**Objective:** Memastikan schema lokal bukan asumsi untuk produksi.

**Files:**
- Create: `docs/migration/database-audit.md`
- Create: `scripts/audit-database.php`
- Create: `tests/fixtures/schema/legacy-schema.sql` setelah sanitasi

**Steps:**

1. Buat script audit read-only berbasis `information_schema`; output hanya schema, indeks, FK, count, null/orphan/duplicate aggregate.
2. Jalankan pada staging/clone produksi, bukan langsung melakukan DDL di produksi.
3. Audit tiga database: `DB_MAIN`, `MASTER_DB_DATABASE`, dan `DB_INV`/`INVENTORY_DB_DATABASE`.
4. Rekam versi server, charset/collation, storage engine, ukuran tabel, trigger, event, view, procedure, dan grant yang dibutuhkan.
5. Dump schema tanpa data untuk fixture pengujian; pastikan tidak ada credential atau data sekolah.
6. Bandingkan hasil produksi dengan audit lokal dalam dokumen.

**Verify:**

```bash
php scripts/audit-database.php > /tmp/db-audit.txt
```

Expected: exit 0; tiga database tercatat, database yang belum dibuat berstatus `missing`, dan tidak ada password atau row-level personal data.

### Task 2: Buat Laravel berdampingan

**Objective:** Menyiapkan Laravel tanpa memindah atau menghapus aplikasi lama.

**Files:**
- Create: `laravel/` melalui Composer
- Modify: `laravel/.env.example`
- Modify: `laravel/config/database.php`
- Modify: root `.gitignore` bila diperlukan

**Steps:**

1. Periksa versi PHP produksi; pilih Laravel stabil yang mendukung versi tersebut.
2. Scaffold Laravel di `laravel/`; jangan menimpa file PHP root.
3. Set koneksi utama memakai env Laravel standar ke `DB_MAIN`.
4. Tambahkan connection `master` dan `inventory`; nama database berasal dari env, bukan hardcode query.
5. Jangan commit `.env`; hanya isi `.env.example` dengan nama variabel tanpa secret.
6. Set timezone, locale, session, dan trusted proxy sesuai deployment nyata.
7. Load `legacy-application-migration` untuk compatibility boundary dan `laravel-backend-audit` untuk review scaffold.

**Verify:**

```bash
cd laravel
php artisan about
php artisan config:clear
php artisan test
```

Expected: aplikasi boot; test bawaan lulus; tidak ada perubahan pada database produksi.

### Task 2A: Pasang dan standarkan Pest

**Objective:** Menjadikan Pest satu-satunya gaya test sebelum production code bertambah.

**Skills:** `test-driven-development`, lalu `laravel-backend-audit` sebagai gate read-only.

**Files:**
- Modify: `laravel/composer.json`
- Modify: `laravel/composer.lock`
- Create: `laravel/tests/Pest.php`
- Convert: `laravel/tests/Feature/DatabaseConnectionsTest.php`
- Convert: `laravel/tests/Feature/AddPublicIdsMigrationTest.php`
- Convert: `laravel/tests/Unit/Models/HasPublicIdTest.php`
- Remove after parity: class-based test files yang sudah dikonversi

**Steps:**

1. Pasang `pestphp/pest` dan `pestphp/pest-plugin-laravel` versi kompatibel.
2. Buat konfigurasi Pest minimal.
3. Konversi seluruh test Laravel ke `it(...)`, `test(...)`, dan `expect(...)`.
4. Perbaiki assertion UUIDv7 agar memeriksa karakter versi pada posisi UUID yang benar.
5. Pakai SQLite hanya untuk unit/config test tanpa semantics MySQL. Migration, enum, upsert, collation, dan integration test wajib MySQL test database.
6. Hapus example tests yang tidak memberi kontrak bisnis.

**Verify:**

```bash
cd laravel
./vendor/bin/pest --compact
./vendor/bin/pint --test
```

Expected: semua test memakai Pest dan lulus tanpa matcher/setup error.

### Task 3: Buat baseline schema untuk fresh test, bukan recreate produksi

**Objective:** Membuat environment test deterministik tanpa mengklaim Laravel telah membuat tabel legacy di produksi.

**Skills:** `database-backend-audit`, `legacy-application-migration`.

**Files:**
- Create: `laravel/database/schema/mysql-schema.sql`
- Create: `laravel/tests/Feature/LegacySchemaSmokeTest.php`
- Modify: `laravel/phpunit.xml`

**Steps:**

1. Hasilkan baseline dari schema clone yang sudah diaudit dan disanitasi.
2. Baseline harus mencakup tabel, index, enum, default, dan FK `fk_realisasi_master` yang sudah ada.
3. Jangan memasukkan data, account database, DEFINER, trigger tak terverifikasi, atau credential.
4. Konfigurasi test memakai database MySQL khusus test; jangan gunakan SQLite karena enum, `ON DUPLICATE KEY`, join update, dan semantics MySQL berbeda.
5. Tulis Pest smoke test yang memeriksa keberadaan delapan tabel utama dan kolom kunci.

**Verify:**

```bash
mysql "$DB_TEST_DATABASE" < database/schema/mysql-schema.sql
./vendor/bin/pest --filter=LegacySchemaSmoke
```

Expected: schema terbuat di DB test kosong; smoke test lulus.

### Task 4: Tambahkan `public_id` secara additive

**Objective:** Menambah identifier publik tanpa mengganti primary key atau memutus legacy.

**Skills:** `test-driven-development`, `legacy-application-migration`, `database-backend-audit`.

**Files:**
- Create: `laravel/database/migrations/<timestamp>_add_public_ids_to_legacy_tables.php`
- Create: `laravel/tests/Feature/Migrations/AddPublicIdsMigrationTest.php`

**Target tables:**

- `kode_sekolah`
- `users`
- `data_barang_acuan`
- `laporan_realisasi`
- `master_barang_sekolah`
- `realisasi_barang_sekolah`

`realisasi_lock` dan `status_kirim_berkas` belum butuh URL publik; jangan tambahkan sebelum ada route nyata.

**Migration release A:**

- Tambah `public_id CHAR(36) NULL`.
- Tambah unique index bernama eksplisit, misalnya `kode_sekolah_public_id_unique`.
- Jangan membuat default UUID database; generator aplikasi harus UUIDv7 konsisten.
- `down()` hanya drop index dan kolom. Jangan menjalankan rollback ini setelah aplikasi memakai public ID tanpa prosedur khusus.

**Verify:**

```bash
php artisan migrate --pretend
./vendor/bin/pest --filter='adds nullable unique public ids'
```

Expected: hanya enam kolom dan enam unique index ditambahkan; tidak ada perubahan/drop kolom lama.

### Task 5: Backfill UUIDv7 dengan proses resumable

**Objective:** Mengisi `public_id` lama tanpa request timeout dan tanpa mengunci tabel lama terlalu lama.

**Skills:** `test-driven-development`, `database-backend-audit`, `legacy-application-migration`.

**Files:**
- Create: `laravel/app/Console/Commands/BackfillPublicIds.php`
- Create: `laravel/tests/Feature/Commands/BackfillPublicIdsTest.php`

**Steps:**

1. Command memakai `Str::uuid7()` dan chunk berdasarkan primary key, default 500 record.
2. Update hanya row `WHERE public_id IS NULL`.
3. Proses per tabel dalam transaksi kecil; jangan satu transaksi untuk seluruh database.
4. Command harus idempotent dan resumable setelah gagal.
5. Log hanya nama tabel dan jumlah, bukan data pengguna.
6. Setelah run, verifikasi `NULL=0`, duplicate=0, dan row count tidak berubah.

**Verify:**

```bash
php artisan app:backfill-public-ids --dry-run
php artisan app:backfill-public-ids
php artisan app:backfill-public-ids
```

Expected: dry-run tidak mengubah data; run pertama mengisi semua null; run kedua mengubah 0 row.

### Task 6: Enforce `public_id NOT NULL` pada release terpisah

**Objective:** Menutup kemungkinan record baru tanpa public ID setelah semua writer siap.

**Prerequisite:** Legacy writer harus sudah diperbarui untuk menghasilkan UUIDv7, atau semua create operasi untuk tabel terkait sudah dialihkan ke Laravel. Jangan jalankan task ini sebelumnya.

**Files:**
- Create: `laravel/database/migrations/<later_timestamp>_make_public_ids_not_null.php`
- Create: `laravel/tests/Feature/Migrations/PublicIdsNotNullMigrationTest.php`

**Steps:**

1. Tambahkan preflight yang gagal jelas bila masih ada `public_id IS NULL`.
2. Ubah enam kolom menjadi non-null.
3. Uji waktu DDL pada clone produksi; gunakan online schema change bila ukuran produksi jauh di atas lokal dan ALTER menahan lock terlalu lama.
4. Deploy terpisah dari release A agar backfill dapat selesai dan diverifikasi.

**Verify:** setiap kolom `public_id` non-null dan unique; insert tanpa `public_id` gagal; insert via model berhasil.

### Task 7: Petakan model legacy tanpa mengubah schema

**Objective:** Membuat Eloquent membaca schema lama secara tepat.

**Skills:** `test-driven-development`, `laravel-backend-audit`, `database-backend-audit`.

**Files:**
- Create: `laravel/app/Models/Sekolah.php`
- Create: `laravel/app/Models/User.php` atau sesuaikan model bawaan
- Create: `laravel/app/Models/DataBarangAcuan.php`
- Create: `laravel/app/Models/LaporanRealisasi.php`
- Create: `laravel/app/Models/MasterBarangSekolah.php`
- Create: `laravel/app/Models/RealisasiBarangSekolah.php`
- Create: `laravel/tests/Feature/Models/LegacyRelationsTest.php`

**Rules:**

- Tetapkan `$table` saat nama model tidak mengikuti pluralisasi Laravel.
- Tetapkan timestamp behavior sesuai schema; sebagian tabel hanya punya `created_at`, tidak punya `updated_at`.
- Cast uang sebagai decimal string, tanggal sebagai date, status sebagai enum aplikasi setelah nilai terverifikasi.
- Jangan masukkan `id`, `public_id`, `id_sekolah`, role, atau status approval ke `$fillable` mass assignment user biasa.
- Route key semua model publik: `public_id`.
- Selama transisi, relasi sekolah memakai `kode_sekolah.id` ke kolom legacy `id_sekolah`; jangan pakai `kode_sekolah.id_sekolah` karena seluruh nilainya null.

**Verify:** model count cocok dengan audit; relationship menghasilkan sekolah yang benar; tidak ada query yang memakai `kode_sekolah.id_sekolah` sebagai parent key.

### Task 8: Pusatkan pembuatan UUIDv7

**Objective:** Menjamin setiap record Laravel baru mendapat `public_id` sebelum insert.

**Files:**
- Create: `laravel/app/Models/Concerns/HasPublicId.php`
- Create: `laravel/tests/Unit/Models/HasPublicIdTest.php`

**Rules:**

- Trait mengisi `public_id` pada event `creating` hanya jika kosong.
- Gunakan `Str::uuid7()`.
- Jangan menimpa nilai existing saat hydration/update.
- Jangan menerima `public_id` dari request.

**Verify:** dua record baru punya UUID valid, berbeda, time-ordered, dan tidak berubah setelah update.

### Task 9: Terapkan authorization tenant

**Objective:** UUID menyembunyikan ID; Policy tetap menjadi keamanan utama.

**Skills:** `test-driven-development`, `multi-tenant-data-isolation`, `application-security-audit`, `laravel-backend-audit`.

**Files:**
- Create: `laravel/app/Policies/*Policy.php` untuk setiap resource publik
- Create: `laravel/app/Http/Middleware/EnsureSchoolContext.php` bila context terpusat diperlukan
- Create: `laravel/tests/Feature/Authorization/CrossSchoolAccessTest.php`

**Steps:**

1. Policy user sekolah membandingkan `auth()->user()->id_sekolah` dengan `record.id_sekolah` memakai normalisasi tipe yang aman selama legacy masih `VARCHAR`.
2. Admin mendapat izin eksplisit per action, bukan bypass global tersembunyi.
3. Controller memakai route model binding berdasarkan `public_id` dan `authorize()`.
4. Query index selalu scope ke sekolah user; Policy per-record saja tidak cukup untuk daftar.
5. Form request tidak memvalidasi/menerima primary key, `public_id`, atau `id_sekolah` sebagai field editable.
6. Penguncian laporan diperiksa ulang di endpoint update/delete.

**Security tests:**

- User A dapat mengakses record A.
- User A mendapat 404/403 untuk UUID record B.
- UUID acak mendapat 404.
- Mengirim `id_sekolah` palsu tidak memindahkan kepemilikan.
- Mengirim `public_id` palsu dalam body diabaikan/ditolak.
- Record berstatus terkunci tidak dapat diubah lewat request langsung.

### Task 10: Migrasikan modul dengan strangler pattern

**Objective:** Mengalihkan fungsi tanpa big-bang rewrite.

**Skills per modul:** `legacy-application-migration` + `test-driven-development`; tambah `multi-tenant-data-isolation` untuk query sekolah, `database-backend-audit` untuk mutation/query, dan `laravel-backend-audit` pada module gate.

**Order:**

1. Auth/session dan sekolah.
2. Dashboard read-only.
3. Acuan barang.
4. Master barang/SPJ.
5. Realisasi.
6. Submit, lock, approval admin.
7. Rekapan dan ekspor Excel.
8. Katalog `kcd_x_inventaris_sekolah` setelah connection `inventory` terverifikasi.

Untuk setiap modul:

1. Tulis characterization test terhadap perilaku legacy.
2. Buat endpoint Laravel minimal.
3. Bandingkan hasil query dan tampilan pada clone data.
4. Alihkan kelompok sekolah kecil/canary.
5. Pantau error dan rekonsiliasi.
6. Baru pindah ke modul berikutnya.

Setiap modul wajib punya Pest Feature test pada route nyata: auth, validation, policy, tenant scope, lock state, dan response parity.

### Task 11: Rekonsiliasi data otomatis

**Objective:** Membuktikan aplikasi baru tidak mengubah makna laporan.

**Skills:** `legacy-application-migration`, `database-backend-audit`; command dan test tidak boleh mengubah source data.

**Files:**
- Create: `laravel/app/Console/Commands/ReconcileLegacyData.php`
- Create: `laravel/tests/Feature/Commands/ReconcileLegacyDataTest.php`

**Checks per school/month:**

- jumlah acuan;
- total nominal acuan;
- jumlah master barang;
- total nilai perolehan master;
- jumlah realisasi;
- total nilai perolehan realisasi;
- status dan tanggal kirim;
- jumlah SPK berbeda;
- orphan dan school mismatch;
- checksum data terkanonisasi bila diperlukan.

Command exit non-zero bila mismatch dan tidak boleh melakukan koreksi otomatis.

**Verify:** hasil legacy dan Laravel sama untuk 77 sekolah pada staging clone.

### Task 12: Normalisasi schema setelah legacy berhenti

**Objective:** Merapikan tipe dan FK hanya setelah tidak ada dual writer.

**Files:**
- Create migration per perubahan; jangan gabungkan semua ALTER dalam satu migration besar.
- Create test preflight untuk setiap constraint.

**Urutan kandidat:**

1. Rename konsep relasi aplikasi menjadi `sekolah_id` hanya jika seluruh kode telah pindah; dapat mempertahankan nama database `id_sekolah` bila rename tidak memberi nilai cukup.
2. Ubah `id_sekolah VARCHAR(50)` menjadi tipe integer yang cocok dengan `kode_sekolah.id` setelah preflight numerik/orphan lulus.
3. Samakan signedness dan ukuran parent-child sebelum FK.
4. Tambahkan FK bertahap dengan delete behavior eksplisit; default aman `RESTRICT`, bukan cascade.
5. Ubah uang `DOUBLE` ke `DECIMAL(15,2)` setelah rekonsiliasi sebelum/sesudah.
6. Putuskan `volume INT` versus `DECIMAL(10,2)` bersama pemilik domain.
7. Satukan collation setelah uji perbandingan string dan lock duration.
8. Evaluasi/hapus `kode_sekolah.id_sekolah`, `realisasi_lock`, atau `status_kirim_berkas` hanya setelah bukti tidak digunakan.

Jangan membuat unique key SPK atau acuan berdasarkan dugaan; data audit menunjukkan pengulangan yang mungkin valid.

### Task 13: Cutover dan rollback

**Objective:** Pindah produksi dengan downtime kecil dan jalur balik jelas.

**Pre-cutover:**

1. Backup penuh dan point-in-time recovery aktif.
2. Lakukan restore rehearsal ke server terpisah.
3. Jalankan migration `--pretend` dan uji nyata pada clone produksi.
4. Jalankan rekonsiliasi sampai nol mismatch.
5. Siapkan maintenance window untuk perubahan non-additive saja.

**Cutover:**

1. Hentikan writer legacy untuk modul yang dipindah.
2. Jalankan final sync/backfill dan rekonsiliasi.
3. Alihkan traffic ke Laravel.
4. Pantau HTTP 5xx, query lambat, queue, disk, dan mismatch bisnis.
5. Pertahankan legacy read-only selama masa observasi.

**Rollback:**

- Release additive: arahkan traffic kembali ke legacy; jangan drop `public_id`.
- Setelah write schema baru/non-additive: rollback aplikasi saja tidak cukup. Gunakan restore/PITR atau forward-fix yang sudah diuji.
- Jangan menjalankan `migrate:rollback` buta di produksi.

---

## 5. Index Awal yang Perlu Diverifikasi dengan `EXPLAIN`

Sudah ada:

- `laporan_realisasi (id_sekolah, bulan)` unique.
- `master_barang_sekolah (id_sekolah, bulan_realisasi)`.
- `realisasi_barang_sekolah (id_master_barang)`.

Kandidat, bukan otomatis dibuat:

- `master_barang_sekolah (id_sekolah, no_spk, bulan_realisasi)` non-unique.
- `realisasi_barang_sekolah (id_sekolah, bulan_realisasi)`.
- `realisasi_barang_sekolah (id_sekolah, id_master_barang, kodering_belanja)` bila query dedup tetap dipakai.
- `data_barang_acuan (id_sekolah, bulan)`.
- `realisasi_lock (id_sekolah, bulan)` unique bila tabel benar-benar menjadi sumber lock.

Sebelum membuat index: ambil slow query log staging/produksi, jalankan `EXPLAIN ANALYZE`, ukur write overhead, lalu tambahkan hanya index yang terbukti membantu.

## 6. Verification Gate Final

```bash
cd laravel
php artisan migrate:fresh --env=testing
./vendor/bin/pest --compact
./vendor/bin/pint --test
php artisan route:list --except-vendor
composer audit --locked --no-interaction
php artisan app:reconcile-legacy-data
php artisan config:cache
```

Sesudah command gate:

1. Load `laravel-backend-audit`; trace semua mutation route sampai storage.
2. Load `application-security-audit` beserta referensi Laravel; audit auth/session, CSRF, IDOR, mass assignment, upload, export, rate limit, logs, dan dependencies.
3. Load `multi-tenant-data-isolation`; periksa index, detail, export, aggregate, cache key, queue, dan dropdown.
4. Load `requesting-code-review`; review diff branch.
5. Load `agent-self-evaluation`; nilai hasil setelah semua gate lulus.

Lalu verifikasi manual:

- Tidak ada route resource yang memakai `{id}` numerik.
- Tidak ada controller yang lookup record publik hanya berdasarkan `public_id` tanpa Policy/school scope.
- Tidak ada request DTO/FormRequest yang mengizinkan `id_sekolah`, `public_id`, role, atau status approval tanpa use case admin khusus.
- Semua 77 sekolah punya hasil rekonsiliasi identik.
- Export Excel sample sama pada nilai, baris, dan grouping; metadata nondeterministik boleh dikecualikan.
- Backup hasil cutover dapat dipulihkan.

## 7. Risiko dan Open Questions

1. Schema produksi mungkin berbeda dari lokal; Task 1 wajib sebelum migration final ditulis.
2. Database inventaris lokal `kcd_x_inventaris_sekolah` tersedia dan berisi 11 tabel, tetapi kode masih memiliki fallback/hardcode `db_inventaris`. Perlu audit staging/produksi dan penghapusan hardcode lewat connection `inventory`.
3. Versi PHP produksi belum diketahui; versi Laravel tidak boleh dipilih dari PHP lokal saja.
4. Definisi business key untuk acuan, SPK, dan realisasi perlu dikonfirmasi sebelum unique constraint baru.
5. `volume` boleh pecahan atau wajib bulat perlu keputusan domain.
6. Peran admin tanpa sekolah valid; tenant middleware tidak boleh memblokir admin sebelum Policy memutuskan akses.
7. UUIDv7 menyulitkan tebak ID, tetapi bukan authorization. Policy dan scope sekolah tetap gate utama.
8. DDL pada ukuran lokal kecil, tetapi ukuran produksi harus diukur sebelum estimasi downtime.
9. `kcd_x_master` dimiliki aplikasi dedicated lain. Kontrak API/read-only, authentication antar-aplikasi, sync retry, dan stale-data behavior harus disepakati; tidak ada migration master di repository ini.
10. Scaffold lokal memakai Laravel 13. Kompatibilitas PHP produksi wajib dibuktikan sebelum versi dipertahankan.
11. Test class PHPUnit sudah dibuat saat eksekusi awal; satu assertion UUIDv7 gagal karena matcher salah. Task 2A wajib mengonversi semuanya ke Pest sebelum fitur dilanjutkan.

## 8. Scope yang Sengaja Ditunda

- PostgreSQL.
- Penggantian primary key lama menjadi UUID.
- Redis dan microservices.
- Rename/drop kolom legacy.
- FK/cascade massal.
- Unique constraint berdasarkan dugaan business key.
- Reformat seluruh UI selama migrasi perilaku.

Tambahkan hanya setelah parallel run stabil dan ada kebutuhan terukur.

## 9. Goal Sampai Selesai

Branch aktif: `feature/laravel-migration`.

### Goal 1 — Fondasi Laravel

**Status:** selesai.

- Laravel 13 berdampingan dalam `laravel/`.
- Pest 4, Laravel Boost, Fortify, Pint, dan tiga logical connection terpasang.
- Audit read-only membuktikan `kcd_x_belanja_modal` 8 tabel dan `kcd_x_inventaris_sekolah` 11 tabel.
- Baseline schema-only MySQL tersedia untuk integration test.
- `public_id` UUIDv7 additive migration, backfill dry-run/resumable/idempotent, dan model mapping tersedia.
- Fortify memakai `username`; registration/reset/2FA/passkeys belum diaktifkan.
- Login user sekolah memverifikasi keberadaan sekolah; admin boleh tanpa sekolah; session regeneration/logout/throttle diuji.
- Repository ini tidak memiliki migration atau write path menuju aplikasi dedicated `kcd_x_master`.

### Goal 2 — Bekukan Kontrak Perilaku Legacy

**Status:** selesai.

1. Inventaris seluruh endpoint/page/AJAX legacy dan petakan ke route Laravel bernama.
2. Catat role `admin` dan `user`, sumber tenant `users.id_sekolah`, bulan 1–12, serta status `Belum Dikirim`, `Menunggu Approval`, `Disetujui`, `Ditolak`.
3. Buat characterization Pest untuk count, total nominal, grouping SPJ, lock, approval, dan ekspor.
4. Hasilkan parity command read-only; mismatch harus exit non-zero dan tidak boleh auto-fix.

**Gate:** kontrak route/query/status/lock terdokumentasi dalam test executable sebelum controller migrasi ditambah.

### Goal 2A — Fondasi UI Tailwind dan Blade Reusable

**Status:** berjalan. Tailwind CSS v4 dan Vite sudah terpasang; konversi halaman masih dimulai.

1. Tetapkan token visual aplikasi di `resources/css/app.css`: warna status, spacing, typography, focus ring, dan layar responsif. Jangan menambah Bootstrap/CDN baru.
2. Buat layout Blade reusable: `resources/views/layouts/app.blade.php`, `resources/views/layouts/guest.blade.php`, dan partial navigasi berbasis role.
3. Buat Blade components kecil yang dipakai lintas modul, bukan satu komponen besar:
   - `x-ui.page-header`
   - `x-ui.flash-message`
   - `x-ui.status-badge`
   - `x-ui.empty-state`
   - `x-ui.pagination`
   - `x-ui.confirm-button`
   - `x-ui.form-error`
   - `x-ui.currency`
4. Buat komponen domain setelah kontrak stabil: `x-report-lock-banner`, `x-school-context`, dan `x-month-selector`.
5. Gunakan named routes dan Blade escaping. Tidak boleh menaruh query authorization, mutation, atau tenant ownership dalam component/view.
6. Migrasikan halaman satu modul per satu modul. Jangan redesign total; pertahankan label, alur, status, data, dan accessibility parity legacy sebelum polish visual.
7. Setiap komponen harus mendukung keyboard focus, error text terhubung ke input, state disabled/aria, dan responsive table overflow.

**Gate:** `npm run build` lulus; halaman Laravel tidak memakai Bootstrap CDN; setiap modul baru memakai layout/components bersama; browser/feature test membuktikan role, tenant, dan text/status penting tidak berubah.

### Goal 3 — Auth, Tenant Context, dan Dashboard Read-Only

1. Buat middleware role dan tenant context dari user terautentikasi; jangan percaya `id_sekolah` request.
2. Buat Policy dasar dan school-scoped query untuk semua user route.
3. Migrasikan dashboard user/admin read-only lebih dulu memakai `layouts.app`, `x-ui.page-header`, `x-ui.status-badge`, dan `x-ui.currency`.
4. Tambahkan Pest lintas dua sekolah untuk detail, aggregate, filter, dan dropdown.

**Gate:** user sekolah A tidak dapat melihat data sekolah B; admin behavior eksplisit; legacy tetap dapat dipakai.

### Goal 4 — Acuan dan Inventory Read-Only

1. Migrasikan daftar/filter/pagination `data_barang_acuan` menggunakan `x-ui.pagination`, `x-ui.empty-state`, dan `x-ui.currency`.
2. Migrasikan pencarian `kode_barang` melalui connection `inventory` read-only.
3. Hilangkan hardcode `db_inventaris` dari path Laravel.
4. Pertahankan import inventory di aplikasi pemiliknya; aplikasi ini tidak menulis master inventory tanpa keputusan owner.

**Gate:** hasil pencarian dan aggregate sama dengan legacy; query tidak cross-tenant; connection inventory failure ditangani tanpa membocorkan detail.

### Goal 5 — SPJ dan Master Barang

1. Migrasikan create/edit/delete SPJ dan item barang dengan FormRequest serta `x-ui.form-error`, `x-ui.confirm-button`, `x-report-lock-banner`, dan `x-month-selector`.
2. `id`, `public_id`, dan `id_sekolah` tidak boleh mass assignable atau dipercaya dari payload.
3. Gunakan transaction dan cek report lock ulang di endpoint mutation.
4. Pertahankan item berulang dalam satu SPK; jangan membuat unique business key berdasarkan dugaan.

**Gate:** Pest happy/error/cross-tenant/locked-state lulus dan total master sama dengan legacy.

### Goal 6 — Realisasi, Submit, Approval, dan Unlock

1. Migrasikan realisasi barang dan sinkronisasi `is_realisasi` dalam transaction.
2. Submit memakai unique `(id_sekolah, bulan)` dan transisi status eksplisit.
3. Approval/unlock hanya admin; audit log perubahan status ditambahkan bila schema aplikasi menyediakannya.
4. Lock dicek server-side pada setiap update/delete, bukan hanya UI; `x-report-lock-banner` hanya representasi visual dari hasil server.

**Gate:** concurrency/idempotency Pest lulus; tidak ada mutation setelah laporan terkunci; approval parity sama.

### Goal 7 — Rekapan dan Ekspor

1. Migrasikan rekapan admin, detail, filter bulan/tahun, dan progress ekspor memakai `x-ui.status-badge`, `x-ui.empty-state`, dan layout admin bersama.
2. Gunakan PhpSpreadsheet versi terkunci di Laravel.
3. Lindungi formula injection pada cell yang berasal dari input user.
4. Pindahkan ekspor berat ke queue setelah database queue schema dibuat khusus aplikasi.

**Gate:** row count, nominal, grouping, filename, dan workbook sample cocok dengan legacy; admin-only dan rate-limit teruji.

### Goal 8 — Integrasi Aplikasi Dedicated `kcd_x_master`

**Blocker eksternal:** kontrak belum tersedia.

1. Dapatkan kontrak API/read-only resmi: endpoint/schema, auth service-to-service, UUID sekolah, NPSN, status aktif, pagination, versioning, dan error semantics.
2. Prefer API client dengan timeout/retry/circuit behavior. Gunakan DB account read-only hanya bila aplikasi master menetapkannya sebagai kontrak.
3. Sinkronkan ke proyeksi sekolah lokal secara idempotent; request transaksi memakai last-known-good data.
4. Repository ini tetap tidak membuat migration atau write ke schema master.

**Gate:** master unavailable tidak mematikan transaksi; stale state terlihat; sync tidak menghapus histori.

### Goal 9 — Rekonsiliasi, Hardening, dan Cutover

1. Rekonsiliasi seluruh 77 sekolah per bulan: count, sum, distinct SPK, status, orphan, tenant mismatch, dan ekspor.
2. Jalankan `application-security-audit`, `laravel-backend-audit`, `database-backend-audit`, dan `multi-tenant-data-isolation`.
3. Verifikasi index dengan `EXPLAIN`, bukan menambah index berdasarkan asumsi.
4. Audit backup, PITR, restore rehearsal, DDL lock duration, monitoring, dan rollback.
5. Canary modul/sekolah kecil, hentikan legacy writer per modul, final sync, lalu cutover.

**Gate:** nol mismatch tak terjelaskan, restore berhasil, rollback diuji, dan legacy tersedia read-only selama observasi.

### Goal 10 — Definition of Done

```bash
cd laravel
php artisan test --compact
vendor/bin/pint --test --format agent
composer audit --locked --no-interaction
php artisan route:list --except-vendor
php artisan app:reconcile-legacy-data
```

- Semua Pest unit/feature/E2E dan characterization test hijau.
- Tidak ada route mutation tanpa auth, authorization, validation, CSRF, dan tenant scope.
- Tidak ada secret/credential tracked.
- Tidak ada migration dijalankan pada database kerja tanpa clone rehearsal dan backup.
- Data dan ekspor cocok untuk 77 sekolah.
- Deployment, health check, queue, scheduler, backup, restore, monitoring, cutover, dan rollback terdokumentasi serta diuji.
- Final backend/security/code review tidak memiliki finding high/critical terbuka.
- `npm run build` lulus dan tidak ada halaman Laravel yang menambah framework CSS kedua.
- Komponen Blade reusable tidak menerima atau menetapkan `id_sekolah`, `public_id`, role, status approval, atau payload mutation sebagai sumber authorization.
