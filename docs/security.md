# Keamanan (Security)

> Cakupan: RBAC, isolasi tenant, auth, proteksi input, logging audit.
> Operasional produksi: `docs/deployment.md`.

## 1. Matriks RBAC (aktual, 32 permission + 5 role)

### Pelaporan BM (10 permission)

| Kemampuan | super_admin | admin_kcd | operator/bendahara |
|-----------|:-----------:|:---------:|:------------------:|
| Lihat dashboard + modul sekolah | ✅ | ✅ | ✅ (sekolah sendiri) |
| Input/edit/hapus SPJ (`input/edit/hapus-spj-bm`) | ✅ | ✅ | ✅ (sekolah sendiri) |
| Unduh & cetak (`unduh-rekap-bm`, `cetak-laporan-bm`) | ✅ | ✅ | ✅ (sekolah sendiri) |
| Kunci laporan (`kunci-laporan-bm`) | ✅ | ✅ | ❌ |
| Verifikasi (`verifikasi-laporan-bm`) | ✅ | ✅ | ❌ |
| Edit alokasi realisasi setelah disetujui | ✅ | ❌ | ❌ |
| Kelola user (`kelola-user`) | ✅ | ✅* | ❌ |
| Lihat log aktivitas (`lihat-log-aktivitas`) | ✅ | ✅\*\* | ❌ |

### Distribusi BHP (11 permission)

| Kemampuan | super_admin | admin_kcd | operator_sekolah | bendahara_sekolah | operator_divisi |
|-----------|:-----------:|:---------:|:----------------:|:----------------:|:---------------:|
| Lihat modul BHP (`lihat-distribusi-bhp`) | ✅ | ✅ | ✅ | ✅ | ✅ |
| Kelola master (`kelola-master-bhp`) | ✅ | ✅ | ❌ | ❌ | ❌ |
| Assign kode rekening (`assign-kode-rekening-bhp`) | ✅ | ✅ | ❌ | ❌ | ❌ |
| Kelola penerimaan (`kelola-penerimaan-bhp`) | ✅ | ✅ | ✅ | ❌ | ❌ |
| Buat/submit pengajuan (`kelola-pengajuan-bhp`) | ✅ | ✅ | ✅ | ✅ | ✅ |
| Review (`review-pengajuan-bhp`) | ✅ | ✅ | ❌ | ❌ | ❌ |
| Approve/reject (`approve-pengajuan-bhp`) | ✅ | ✅ | ❌ | ❌ | ❌ |
| Cetak/export (`cetak-dokumen-bhp`) | ✅ | ✅ | ✅ | ❌ | ❌ |
| Kelola divisi (`kelola-divisi-bhp`) | ✅ | ✅ | ✅ | ❌ | ❌ |
| Kelola penyedia (`kelola-penyedia-bhp`) | ✅ | ✅ | ✅ | ❌ | ❌ |
| Kelola barang (`kelola-barang-bhp`) | ✅ | ✅ | ✅ | ❌ | ❌ |

Kolom "assign" pada Master Kode Rekening hanya dirender untuk pemegang
`assign-kode-rekening-bhp` (admin KCD), karena endpoint
`POST /master/kode-rekening/batch-assign` dijaga izin itu.

Role `operator_divisi` adalah role baru khusus BHP (pemohon lintas sekolah,
tanpa akses master/approval). Wewenang dihitung server-side via middleware
`permission:*`, bukan dari UI.

`operator_sekolah` **tidak** memiliki `review-pengajuan-bhp`/
`approve-pengajuan-bhp` — operator pembuat pengajuan tidak boleh
menyetujui/menolaknya. Sebagai jaring kedua, `ReviewPengajuanController`
memasang guard `pastikanBukanPembuat()` yang menolak approve/reject bila
reviewer adalah pembuat pengajuan (defense-in-depth selain RBAC). Terverifikasi
`BhpHttpFlowTest::test_operator_tidak_dapat_approve_pengajuan_sendiri`.

### Pencatatan BM (8 permission)

| Kemampuan | super_admin | admin_kcd | operator/bendahara |
|-----------|:-----------:|:---------:|:------------------:|
| Lihat modul (`lihat-pencatatan-bm`) | ✅ | ✅ (lintas sekolah) | ✅ (sekolah sendiri) |
| Kelola unit inventaris (`kelola-inventaris-bm`) | ✅ | ❌ | ✅ (sekolah sendiri) |
| Tambah/impor barang manual | ✅ | ❌ | ✅ (sekolah sendiri) — gate `kelola-inventaris-bm` |
| Penyaluran pertama | ✅ | ❌ | ✅ (sekolah sendiri) — gate `mutasi-inventaris-bm` |
| Kelola ruang / pegawai (`kelola-ruang-bm`, `kelola-pegawai-bm`) | ✅ | ❌ | ✅ (sekolah sendiri) |
| Kelola jabatan / penugasan (`kelola-jabatan-bm`, `kelola-penugasan-pegawai-bm`) | ✅ | ❌ | ✅ (sekolah sendiri) |
| Mutasi (`mutasi-inventaris-bm`) | ✅ | ❌ | ✅ (sekolah sendiri) |
| Pengembalian (`proses-pengembalian-bm`) | ✅ | ❌ | ✅ (sekolah sendiri) |
| Cetak/ekspor (`cetak-inventaris-bm`) | ✅ | ✅ | ✅ |
| Sinkronisasi ulang (`sinkronisasi-inventaris-bm`) | ✅ | ❌ | ❌ |

Unit inventaris terbentuk dari dua jalur: sinkronisasi realisasi yang
periodenya `disetujui`, atau pendaftaran manual/impor oleh pemegang
`kelola-inventaris-bm`. Keduanya memakai layanan penomoran register yang sama.
Pengembalian mensyaratkan BAST bernomor (backend menolak, bukan hanya UI).
Data sekolah lain dijawab **404** (bukan 403) agar UUID sekolah lain tidak
membocorkan keberadaan data. Terverifikasi `InventarisTenantIsolationTest`,
`InventarisPermissionTest`, dan `InventarisPendaftaranTest`.

\* Grup rute `/admin` memakai `role:super_admin|admin_kcd`; halaman user
menyembunyikan super_admin dari daftar dan menolak hapus diri. `Gate::before`
memberi super_admin bypass semua ability.

\*\* Aktivitas yang di-cause super_admin disembunyikan dari admin_kcd;
super_admin melihat semua. Log ber-flag `sembunyi_dari_admin_kcd` (saat ini:
event `import-spj`) juga hanya terlihat oleh super_admin.

Rute log aktivitas (`/admin/log-aktivitas`): `permission:lihat-log-aktivitas`
(terverifikasi test: tamu/operator → 403, super_admin & admin_kcd → 200).
Rute log error (`/admin/log-error` via opcodesio/log-viewer): `role:super_admin`
+ gate `viewLogViewer`.

## 2. Isolasi Tenant

- Operator/bendahara terkunci `sekolah_id` via `ResolvesSekolah`; tiap query
  difilter + cek kepemilikan per-record (403 lintas tenant).
- `resolveSekolahId($request)` **wajib** di tiap aksi lintas-sekolah
  (index/unduh/check): operator tanpa `sekolah_id` akan `abort 403` — bukan
  lolos ke jalur admin "lihat semua sekolah". Berlaku di
  `Acuan`, `Realisasi`, `Rekapan`, `Cetak`, `InputRealisasi`, `Spj`.
- `AcuanController::store` memaksa `sekolah_id` ke sekolah user, mengabaikan
  kiriman request (anti cross-tenant write).
- User dengan role `operator_sekolah` atau `bendahara_sekolah` wajib memiliki
  `sekolah_id`; akun sekolah tanpa tenant ditolak 403 dan tidak masuk jalur
  admin. Manajemen user juga menolak role admin yang terhubung ke sekolah atau
  role sekolah tanpa sekolah.
- `InputRealisasi::update` menghapus `uncheck_ids` **hanya** dalam scope
  `bulan_realisasi` + `kodering_belanja` (anti hapus realisasi periode lain
  via ID tebakan).
- Laporan dengan `status_kirim` `menunggu_approval` atau `disetujui` tetap
  read-only untuk `admin_kcd`, operator sekolah, dan bendahara sekolah.
  Role `super_admin` adalah pengecualian untuk laporan `disetujui`: boleh
  membuka halaman edit dan melepas alokasi realisasi melalui
  `InputRealisasiController::update`. Status tetap `disetujui`; perubahan
  tidak otomatis mengembalikan laporan ke `draft`.
- Izin tersebut dihitung server-side melalui `hasRole('super_admin')`, bukan
  username. Prop Inertia `canEditApproved` hanya mengendalikan tampilan UI;
  guard backend tetap wajib memvalidasi setiap request mutasi.
- `acuan_id` lintas sekolah ditolak ("Acuan tidak valid untuk sekolah ini").
- Import Excel SPJ dibatasi allowlist NPSN (`SpjController::NPSN_IMPORT_SPJ`):
  sekolah di luar daftar ditolak di backend (flash error) dan tombol Import
  tidak dirender di UI (prop `canImportSpj`). Verifikasi: `SpjImportTest`.
- `like` search di-escape (`addcslashes %_\`) anti wildcard-injection — termasuk
  `search_satuan` (`AcuanController::index`).
- Alokasi realisasi menolak `spj_id` yang sudah dialokasikan. Pemeriksaan item,
  pagu, dan realisasi berjalan di dalam transaction dengan `lockForUpdate()`
  untuk mencegah duplicate submit dan overspending akibat request bersamaan.
- Pengiriman laporan yang sudah `menunggu_approval` atau `disetujui` ditolak;
  pengembalian ke `draft` juga membersihkan metadata penguncian.
- Terverifikasi `SecurityHardeningTest` + `AcuanImportGapTest` + `CetakControllerEdgeTest`
  (cross-tenant 403, kunci diblokir, operator tanpa sekolah ditolak).

### Isolasi tenant Distribusi BHP

- Halaman/aksi BHP memakai `resolveSekolahId` yang sama; pengajuan dan
  penerimaan difilter `sekolah_id`.
- Dokumen (`DokumenController::authorizeSekolah`) menolak 403 jika
  `pengajuan.sekolah_id` bukan milik user, dan `authorizeDokumen` menolak 404
  bila dokumen bukan milik pengajuan tersebut (anti IDOR). Controller yang sama
  juga memeriksa `divisi_id`: operator divisi hanya dapat melihat/mencetak/
  membuat dokumen untuk pengajuan divisinya sendiri (403 lintas divisi) —
  terverifikasi `BhpDivisiTest`.
- Render PDF menggunakan template Blade **per jenis dokumen** (8 file
  di `resources/views/distribusi-bhp/`); paper/orientasi hardcode di
  `DokumenController::PAPER`, bukan konfigurasi user.
- Halaman read-only per jenis dokumen (`{bos|bopd}/laporan/bast-internal`, dll.)
  menggunakan permission `lihat-distribusi-bhp` — semua role dengan akses BHP
  dapat melihat daftar dokumen; unduh PDF membutuhkan `cetak-dokumen-bhp`.
- Snapshot payload dokumen tambahan (`DokumenService::payloadFor`) selalu
  mengirim `sekolah_id` milik pengajuan ke `laporanStok`/`bukuPengeluaran`,
  sehingga stok opname dan buku pengeluaran tidak pernah memuat data sekolah
  lain pada sumber dana yang sama — terverifikasi
  `BhpDokumenTambahanTest::test_payload_snapshot_tidak_mencampur_data_sekolah_lain`.
- Dashboard BHP menghitung statistik pengajuan per `divisi_id` untuk operator
  divisi (divisi lain tidak terlihat di angka draft/submitted/approved);
  penerimaan tetap scope sekolah karena stok bukan milik divisi.
- Validasi sumber dana wajib: `sumber_dana` ∈ {BOS, BOPD}; kode rekening dan
  barang harus sealiran sumber dana dengan pengajuan/penerimaan — BOS dan BOPD
  tidak boleh tercampur. Karena tabel dipisah fisik per dana, `barang_id`
  lintas dana ditolak sejak validasi `exists` (`SumberDana::existsRule`), dan
  `LaporanService::kartuPersediaan` melempar `RuntimeException` bila tidak
  cocok (`LaporanController` mengubahnya menjadi redirect + flash error,
  bukan 500).
- **Middleware `SetSumberDanaBhp` fail-closed**: `sumber_dana` yang dikirim
  tetapi tidak valid (mis. `APBN`) ditolak **422**, bukan diam-diam jatuh ke
  BOS. Default BOS hanya berlaku bila parameter tidak dikirim sama sekali —
  sehingga typo/manipulasi parameter tidak pernah membaca atau menulis data
  sumber dana lain. Terverifikasi `BhpDomainBoundaryTest`.
- **State sumber dana aktif tidak bocor antar-request**: middleware menyimpan
  nilai sebelumnya dan memulihkannya di `finally`; `SettingsService::update()`
  memakai `SumberDana::dengan()` (bukan `setAktif()` permanen). Aman untuk
  worker jangka panjang (Octane/queue) dan pemanggilan service bersarang.
- **Opsi periode saldo awal ter-scope sumber dana**: `availablePeriods` pada
  halaman saldo awal dihitung di dalam konteks `SumberDana::dengan()` sehingga
  periode yang hanya memiliki data sumber dana lain tidak muncul.
- `barang_id` pada kartu persediaan divalidasi `uuid` + `exists` ke tabel barang
  **sesuai sumber dana** laporan (`SumberDana::existsRule('barang', $sumberDana)`)
  — UUID asing/tak dikenal → validation error, bukan error server.
- Stok tidak bisa dipakai melebihi `tersedia`; submit approve dan reject
  berjalan dalam satu transaction dengan `lockForUpdate` pada pengajuan,
  detail, dan barang (`StokService`) sehingga request bersamaan tidak
  menghasilkan stok negatif.
- Race condition penomoran dokumen tertangani: insert row sequence pertama
  menangkap `UniqueConstraintViolationException` lalu lock row pemenang
  (`NomorDokumenService`) — nomor tetap unik dan berurutan.
- `bhp_{dana}_settings` satu baris per sumber dana, dilindungi partial unique
  index (`is_singleton`); `get`/`create` bersamaan tidak menghasilkan dua baris.
- **Pejabat sekolah ter-isolasi tenant**: `sekolah_id` selalu dari user login
  (`resolveSekolahId`), update/akhiri pejabat sekolah lain → 403. Satu pejabat
  aktif per `(sekolah_id, jabatan)` dijaga partial unique index di level DB;
  periode overlap ditolak di service (`lockForUpdate`).
- **Gate pejabat pada approval**: approval pengajuan ditolak (rollback penuh)
  bila pejabat penandatangan belum lengkap; snapshot pejabat tersimpan di
  payload dokumen sehingga dokumen lama tidak berubah saat master diubah
  (immutability historis).
- Foreign key database (10 relasi BHP) mencegah orphan record pada level DB.
- Terverifikasi `BhpDomainBoundaryTest`, `BhpStokDanPengajuanTest`,
  `BhpHttpFlowTest`, `BhpLaporanTest`, `BhpKartuPersediaanTest`,
  `BhpDivisiTest`, `BhpDokumenTambahanTest`, `BhpPejabatSekolahTest`.

## 3. Autentikasi & Akun

- Fortify session + bcrypt; login username-only (tanpa email).
- Rotasi password 90 hari (`EnsurePasswordNotExpired`, khusus
  operator/bendahara) → paksa `/ubah-password`; kompleksitas min 8 +
  besar/kecil + angka + simbol; `different:current_password`.
- 2FA TOTP + recovery codes + passkeys (WebAuthn) tersedia.
- Turnstile di login (arda proteksi bot); dilewati bila `TURNSTILE_ENABLED=false`
  (lokal/testing). Di klien, tombol "Masuk" baru aktif setelah widget Turnstile
  lolos verifikasi (`success-callback`), sehingga submit tak mengirim token kosong.
- Reset password via Fortify tercatat sebagai `reset-password` (causer anonim).

## 4. Proteksi Aplikasi

| Ancaman | Kontrol |
|---------|---------|
| Brute force login | Fortify rate-limit + Turnstile; throttle rute sensitif |
| Spam unduh/import | Throttle: unduh 30/mnt, import (acuan & SPJ) & destroy-all 10/mnt, cari-barang 120/mnt |
| CSRF | Middleware web Laravel + token Inertia |
| XSS / formula injection | Blade escape default; React escape default; unduhan teks via `safeCell` di `CetakBmSheet` **dan** `RealisasiBmSheet` (prefix anti formula-injection `=+-@\t\r`), terverifikasi `CetakBmSheetTest` + `ExportSafeCellTest` |
| Mass assignment | `$fillable` eksplisit; validasi per-controller |
| Data terkunci diubah | Guard status di tiap aksi tulis (kunci/menunggu/disetujui); metadata lock dibersihkan saat kembali ke draft |
| Duplicate allocation / overspending | Guard `spj_id` + transaction dan `lockForUpdate()` pada item, pagu acuan, dan realisasi |
| Secret bocor ke log | `logExcept` password/token di model User; test `password_tidak_bocor_di_log` |
| DB error bocor ke user | Handler `QueryException` di `bootstrap/app.php`: Inertia `Error` 500 (XHR) / flash ramah (web biasa) + `Log::error` konteks; tanpa SQL ke respons |
| Error page default Laravel | Handler `Throwable` 4xx → Inertia `Error` (navigasi) / `errors.page` Blade (load awal); tanpa stack trace ke user |
| Session hijack | `SESSION_SECURE_COOKIE`, regenerasi saat login (Fortify) |

## 5. Audit Trail

- `activity_log` (log `sistem`): auto-diff model + manual login/logout,
  password, kirim/verifikasi/kunci, import/hapus-massal (import SPJ ber-flag
  `sembunyi_dari_admin_kcd` → hanya super_admin yang melihat), unduhan, hapus SPK.
- Viewer super_admin: filter event/entitas/sekolah/tanggal/pencarian; menyembunyikan
  aktivitas super_admin **dan** log ber-flag `sembunyi_dari_admin_kcd` dari
  admin_kcd (query utama + daftar dropdown events/subjectTypes).
- `Log::` file untuk Turnstile & error sistem termasuk `db-error` (bukan activity log — DB ikut mati saat down, dan jejak audit bebas noise infra).
- Tanpa purge — retensi permanen (keputusan produk, tinjau tiap evaluasi tahunan).

## 6. Checklist Aman Sebelum Rilis

- [ ] `APP_DEBUG=false`, `APP_ENV=production` (lihat `docs/deployment.md`)
- [ ] Kredensial default diganti; akun `developer` dinonaktifkan/dihapus bila tak perlu
- [ ] `TURNSTILE_ENABLED=true` + key valid; HTTPS + cookie secure
- [ ] `composer audit` bersih; `npm audit` ditinjau
- [ ] Backup DB terjadwal & pernah diuji restore
- [x] Test regresi: `php artisan test --compact` (877 test, 6007 assertion), `npx tsc --noEmit`, Pint
- [ ] `composer test-coverage` tetap wajib dijalankan sebagai gate coverage sebelum merge
