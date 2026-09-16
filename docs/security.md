# Keamanan (Security)

> Cakupan: RBAC, isolasi tenant, auth, proteksi input, logging audit.
> Operasional produksi: `docs/deployment.md`.

## 1. Matriks RBAC (aktual, 10 permission)

| Kemampuan | super_admin | admin_kcd | operator/bendahara |
|-----------|:-----------:|:---------:|:------------------:|
| Lihat dashboard + modul sekolah | ✅ | ✅ | ✅ (sekolah sendiri) |
| Input/edit/hapus SPJ (`input/edit/hapus-spj-bm`) | ✅ | ✅ | ✅ (sekolah sendiri) |
| Unduh & cetak (`unduh-rekap-bm`, `cetak-laporan-bm`) | ✅ | ✅ | ✅ (sekolah sendiri) |
| Kunci laporan (`kunci-laporan-bm`) | ✅ | ✅ | ❌ |
| Verifikasi (`verifikasi-laporan-bm`) | ✅ | ✅ | ❌ |
| Kelola user (`kelola-user`) | ✅ | ✅* | ❌ |
| Lihat log aktivitas (`lihat-log-aktivitas`) | ✅ | ❌ | ❌ |

\* Grup rute `/admin` memakai `role:super_admin|admin_kcd`; halaman user
menyembunyikan super_admin dari daftar dan menolak hapus diri. `Gate::before`
memberi super_admin bypass semua ability.

Rute log (`/admin/log-aktivitas` + `/admin/log-error` via opcodesio/log-viewer):
`role:super_admin` + gate `viewLogViewer` = permission `lihat-log-aktivitas`
(terverifikasi test: tamu/operator → 403, super_admin → 200).

## 2. Isolasi Tenant

- Operator/bendahara terkunci `sekolah_id` via `ResolvesSekolah`; tiap query
  difilter + cek kepemilikan per-record (403 lintas tenant).
- `resolveSekolahId($request)` **wajib** di tiap aksi lintas-sekolah
  (index/unduh/check): operator tanpa `sekolah_id` akan `abort 403` — bukan
  lolos ke jalur admin "lihat semua sekolah". Berlaku di
  `Acuan`, `Realisasi`, `Rekapan`, `Cetak`, `InputRealisasi`, `Spj`.
- `AcuanController::store` memaksa `sekolah_id` ke sekolah user, mengabaikan
  kiriman request (anti cross-tenant write).
- `InputRealisasi::update` menghapus `uncheck_ids` **hanya** dalam scope
  `bulan_realisasi` + `kodering_belanja` (anti hapus realisasi periode lain
  via ID tebakan).
- `acuan_id` lintas sekolah ditolak ("Acuan tidak valid untuk sekolah ini").
- `like` search di-escape (`addcslashes %_\`) anti wildcard-injection — termasuk
  `search_satuan` (`AcuanController::index`).
- Terverifikasi `SecurityHardeningTest` + `AcuanImportGapTest` + `CetakControllerEdgeTest`
  (cross-tenant 403, kunci diblokir, operator tanpa sekolah ditolak).

## 3. Autentikasi & Akun

- Fortify session + bcrypt; login username-only (tanpa email).
- Rotasi password 90 hari (`EnsurePasswordNotExpired`, khusus
  operator/bendahara) → paksa `/ubah-password`; kompleksitas min 8 +
  besar/kecil + angka + simbol; `different:current_password`.
- 2FA TOTP + recovery codes + passkeys (WebAuthn) tersedia.
- Turnstile di login (arda proteksi bot); dilewati bila `TURNSTILE_ENABLED=false`
  (lokal/testing).
- Reset password via Fortify tercatat sebagai `reset-password` (causer anonim).

## 4. Proteksi Aplikasi

| Ancaman | Kontrol |
|---------|---------|
| Brute force login | Fortify rate-limit + Turnstile; throttle rute sensitif |
| Spam unduh/import | Throttle: unduh 30/mnt, import & destroy-all 10/mnt, cari-barang 120/mnt |
| CSRF | Middleware web Laravel + token Inertia |
| XSS / formula injection | Blade escape default; React escape default; unduhan teks via `safeCell` di `CetakBmSheet` **dan** `RealisasiBmSheet` (prefix anti formula-injection `=+-@\t\r`), terverifikasi `CetakBmSheetTest` + `ExportSafeCellTest` |
| Mass assignment | `$fillable` eksplisit; validasi per-controller |
| Data terkunci diubah | Guard status di tiap aksi tulis (kunci/menunggu/disetujui) |
| Secret bocor ke log | `logExcept` password/token di model User; test `password_tidak_bocor_di_log` |
| DB error bocor ke user | Handler `QueryException` di `bootstrap/app.php`: Inertia `Error` 500 (XHR) / flash ramah (web biasa) + `Log::error` konteks; tanpa SQL ke respons |
| Error page default Laravel | Handler `Throwable` 4xx → Inertia `Error` (navigasi) / `errors.page` Blade (load awal); tanpa stack trace ke user |
| Session hijack | `SESSION_SECURE_COOKIE`, regenerasi saat login (Fortify) |

## 5. Audit Trail

- `activity_log` (log `sistem`): auto-diff model + manual login/logout,
  password, kirim/verifikasi/kunci, import/hapus-massal, unduhan, hapus SPK.
- Viewer super_admin: filter event/entitas/sekolah/tanggal/pencarian.
- `Log::` file untuk Turnstile & error sistem termasuk `db-error` (bukan activity log — DB ikut mati saat down, dan jejak audit bebas noise infra).
- Tanpa purge — retensi permanen (keputusan produk, tinjau tiap evaluasi tahunan).

## 6. Checklist Aman Sebelum Rilis

- [ ] `APP_DEBUG=false`, `APP_ENV=production` (lihat `docs/deployment.md`)
- [ ] Kredensial default diganti; akun `developer` dinonaktifkan/dihapus bila tak perlu
- [ ] `TURNSTILE_ENABLED=true` + key valid; HTTPS + cookie secure
- [ ] `composer audit` bersih; `npm audit` ditinjau
- [ ] Backup DB terjadwal & pernah diuji restore
- [ ] Test hijau: `composer test-coverage`, `tsc --noEmit`, Pint
