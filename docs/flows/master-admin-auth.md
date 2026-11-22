# Flow Master Data & Admin (Auth & Rotasi Password)

> **Status: Fixed** Cakupan master data bersama, pengelolaan user/admin, dan autentikasi yang terimplementasi sesuai kode. Fondasi RBAC & tenant-isolation untuk seluruh aplikasi (role check di `docs/security.md`, resolver `ResolvesSekolah`, dan permission middleware di `routes/web.php`).

## 1. Aktor, role, dan permission (ringkas)

Sumber: `routes/web.php`, `docs/security.md` §1.

| Role | Catatan |
|---|---|
| `super_admin` | Akses penuh, bypass semua ability (`Gate::before`) |
| `admin_kcd` | Admin kantor (umumnya lintas sekolah pada modul yang diizinkan) |
| `operator_sekolah` | 1 sekolah, `sekolah_id` wajib |
| `bendahara_sekolah` | 1 sekolah, `sekolah_id` wajib |
| `operator_divisi` | BHP-only (pemohon lintas sekolah sesuai hak divisi), tanpa akses master/approval Pelaporan/Pencatatan |

Permission penting (contoh): `kelola-master-data-sekolah`, `kelola-ruang-bm`, `kelola-pegawai-bm`, `kelola-jabatan-bm`, `kelola-penugasan-pegawai-bm`, `kelola-user`, `lihat-log-aktivitas`, `ubah-password` (via rotasi wajib), serta permission domain lain di `docs/security.md`.

## 2. Invariant tenant & sekolah

1. `sekolah_id` **selalu** dari `$request->user()->sekolah_id` lewat `ResolvesSekolah`/`resolveSekolahIdWajib`, **bukan** dari input (anti cross-tenant write).
2. Controller master aset sekolah (Ruang/Pegawai/Jabatan/Penugasan) menolak akses lintas sekolah dengan **404** (`pastikanSekolahSendiri`) agar UUID tidak menjadi oracle.
3. `UserController`: role `operator_sekolah`/`bendahara_sekolah` wajib punya `sekolah_id`; role `admin_kcd` tidak boleh punya `sekolah_id` (`validateRoleSchoolPair`). `super_admin` tidak bisa dihapus/diubah lewat endpoint ini.
4. Import SPJ allowlist NPSN (`SpjController::NPSN_IMPORT_SPJ`) membatasi siapa yang boleh import di UI/backend.

## 3. Master data bersama (domain sekolah)

### 3.1 Profil sekolah — `Master\ProfilSekolahController`

- Routes: `GET|PUT /master-data/profil-sekolah` (`master-data.profil-sekolah.edit|update`), permission `kelola-master-data-sekolah` (grup `master-data` pertama di `routes/web.php`).
- `sekolah_id` dipaksa dari user: bila user tidak punya sekolah (admin tanpa tenant), halaman edit menampilkan pilihan sekolah (opsional) untuk konteks UI; update **wajib** punya `sekolah_id` atau gagal validasi (“Pilih sekolah terlebih dahulu…”).
- Field yang bisa diubah: `alamat`, `telepon`, `website`, `email` (nama/NPSN/kota tidak diubah lewat halaman ini).
- Log: event `kelola-profil-sekolah`.

### 3.2 Ruang — `Master\RuangController`

- Routes: `/master-data/ruang` (index/store/update/destroy), middleware `permission:kelola-ruang-bm` per grup prefix.
- `store`: `resolveSekolahIdWajib` + validasi nama unik (case-insensitive) per `sekolah_id`.
- `update`/`destroy`: pastikan `sekolah_id` ruang milik user (`pastikanSekolahSendiri`) + policy/gate terkait ruang (`Gate::authorize('kelolaRuang', $ruang)`) lalu ubah/hapus (histori mutasi yang memakai ruang bisa `nullOnDelete` sesuai skema).

### 3.3 Pegawai — `Master\PegawaiController`

- Routes: `/master-data/pegawai`, permission `kelola-pegawai-bm`.
- `sekolah_id` dari user; update/destroy cek tenant + gate `kelolaPegawai`. Soft delete (`destroy`).
- Field: `nama_lengkap`, `nip`, `pangkat_golongan` (validasi di controller).

### 3.4 Jabatan — `Master\JabatanController`

- Routes: `/master-data/jabatan`, permission `kelola-jabatan-bm`.
- `Jabatan::pastikanWajib($sekolahId)` membuat tiga jabatan penandatangan jika belum ada saat index.
- `kode` format `^[a-z0-9_]+$`, unik per `sekolah_id`; kategori dari `Jabatan::KATEGORI`.
- Jabatan wajib (`isWajib()`): hanya `kategori` yang boleh diubah (kode/nama ditolak bila diubah).
- Jika jabatan pernah dipakai di penugasan (`penugasan()->exists()`), `destroy` men-set `aktif = false` (bukan hapus fisik); jika belum pernah dipakai, dihapus.

### 3.5 Penugasan pegawai — `Master\PenugasanPegawaiController`

- Routes: `/master-data/penugasan`, permission `kelola-penugasan-pegawai-bm`.
- `index`: `resolveSekolahIdWajib`, pastikan jabatan wajib ada, muat `penugasan` + daftar jabatan tersedia + opsi jenis.
- `store`: `PenugasanPegawaiService::simpan`; gagal bisnis → flash error.
- `update`: wajib tenant sama (`abort 404` jika beda) lalu service simpan.
- `akhiri`: set `selesai_menjabat` via service (`RuntimeException` → flash error).
- `mutasi`: ubah ke jabatan/jenis/tanggal via service (`mutasi`).
- Validasi `kodeJabatanRule`: kode boleh dari jabatan sekolah atau kode penandatangan bawaan (`Jabatan::KODE_PENANDATANGAN`).

## 4. Admin

### 4.1 Kelola user — `Admin\UserController`

- Routes (grup `admin`, middleware `role:super_admin|admin_kcd`): `GET|POST|PUT|DELETE /admin/user`, permission `kelola-user`.
- `index`: daftar user **kecuali** yang punya role `super_admin`, eager `sekolah` & `roles`, urut nama; serta daftar `Sekolah` untuk form.
- `store` (`DB::transaction`): validasi + `validateRoleSchoolPair`; buat user tanpa log otomatis (`withoutLogging`), `assignRole`, log event `tambah-user` (log `sistem`).
- `update`: tolak jika target `super_admin` (403). Validasi role/sekolah pair terhadap hasil akhir (`finalRole`, `finalSekolahId`). Tidak boleh menonaktifkan diri sendiri (`is_active=false` → error). Jika dinonaktifkan, hapus semua `sessions` user. Role disinkron lewat `syncRoles` bila dikirim. Log event `ubah-user` (termasuk `is_active`).
- `destroy`: tolak `super_admin` dan diri sendiri; dalam transaksi hapus session lalu hapus user, log `hapus-user`.
- Validasi pasangan role–sekolah: `admin_kcd` tidak boleh punya `sekolah_id`; `operator_sekolah`/`bendahara_sekolah` wajib punya `sekolah_id` (lempar `ValidationException`).

### 4.2 Kode barang (katalog) — `Admin\KodeBarangController`

- Routes: `GET|POST /admin/kode-barang`, `POST /admin/kode-barang/import`, `DELETE /admin/kode-barang/{kodeBarang}` (grup `admin` `super_admin|admin_kcd`). `import` throttle `10,1`.
- `index`: mendukung JSON/`ajax` (limit 50, filter `search`/`q` via `SearchesText`) untuk pemilihan cepat; selain itu render Inertia `Admin/KodeBarang/Index` dengan pagination 25.
- `store`: `updateOrCreate` by `kode_barang` (validasi field sesuai controller).
- `import`: upload file (xls/xlsx/csv), parse (helper di controller), validasi batas baris (<=20.000 data baris per impor di implementasi saat ini), `DB::transaction`, `updateOrCreate` per baris. Log event `import-kode-barang` (jumlah).
- `destroy`: hapus record.

### 4.3 Log aktivitas — `Admin\ActivityLogController`

- Route: `GET /admin/log-aktivitas` (`admin.log-aktivitas.index`), permission `lihat-log-aktivitas`.
- Log sumber: `Activity::inLog('sistem')`, eager `causer` & `subject`, urut terbaru.
- Filter (validated): `event`, `subject_type`, `q` (cari di `description`/`properties->ringkasan` via `SearchesText`), `sekolah_id`, `dari`, `sampai`.
- Aturan visibilitas (server-side):
  - `admin_kcd` **tidak** melihat aktivitas yang di-cause `super_admin` (`causer` tanpa role super_admin atau `causer_id` null pada kondisi yang dikodekan di query).
  - `admin_kcd` **tidak** melihat log ber-flag `properties->sembunyi_dari_admin_kcd = true` (mis. import SPJ tertentu) — hanya `super_admin`.
- Props: `items` (paginate 50 + query string), `filters`, daftar `events`, `subjectTypes`, `sekolahs` (id,nama) untuk filter.

> Catatan viewer log error (opcodesio/log-viewer) di route `/admin/log-error` dibatasi `role:super_admin` + gate `viewLogViewer` (lihat `routes/web.php` / `docs/security.md`).

## 5. Autentikasi & rotasi password

### 5.1 Login / sesi (ringkas)

- Fortify session; login username-only (lihat `config/auth.php`, `docs/security.md`).
- Middleware/aksi rotasi password: `EnsurePasswordNotExpired` + route `password.change`/`password.update` (terdaftar di grup `auth`).

### 5.2 Rotasi password wajib — `Auth\PasswordExpiredController`

- Routes: `GET /ubah-password` (`password.change`), `POST /ubah-password` (`password.update`), middleware `auth`.
- Validasi update: `current_password` (`current_password:web`), `password` min 8 + huruf + huruf besar/kecil + angka + simbol (`Password::min(8)->letters()->mixedCase()->numbers()->symbols()`), `confirmed`, `different:current_password` (pesan kustom di controller).
- Setelah berhasil: `password_changed_at = now()`, log aktivitas `ganti-password` (event `ganti-password`, log `sistem`, `causedBy` user, `performedOn` user, `ringkasan` “Ganti password: {username}”, `sekolah_id`).
- Redirect ke `dashboard` dengan flash `success`.

## 6. Route map (referensi cepat)

| Area | Prefix / name | Controller utama |
|---|---|---|
| Profil sekolah | `/master-data/profil-sekolah` `master-data.profil-sekolah.*` | `Master\ProfilSekolahController` |
| Ruang | `/master-data/ruang` `master-data.ruang.*` | `Master\RuangController` |
| Pegawai | `/master-data/pegawai` `master-data.pegawai.*` | `Master\PegawaiController` |
| Jabatan | `/master-data/jabatan` `master-data.jabatan.*` | `Master\JabatanController` |
| Penugasan | `/master-data/penugasan` `master-data.penugasan.*` | `Master\PenugasanPegawaiController` |
| User admin | `/admin/user` `admin.user.*` | `Admin\UserController` |
| Kode barang | `/admin/kode-barang` `admin.kode-barang.*` | `Admin\KodeBarangController` |
| Log aktivitas | `/admin/log-aktivitas` `admin.log-aktivitas.index` | `Admin\ActivityLogController` |
| Ubah password | `/ubah-password` `password.change|update` | `Auth\PasswordExpiredController` |

## 7. File terkait (cepat)

- Routes: `routes/web.php` (grup `master-data`, `admin`, dan route password).
- Controllers: `app/Http/Controllers/Master/*`, `app/Http/Controllers/Admin/{UserController,ActivityLogController,KodeBarangController}.php`, `app/Http/Controllers/Auth/PasswordExpiredController.php`.
- Docs: `docs/security.md` (RBAC/tenant), `docs/modules.md` (ringkasan modul).
