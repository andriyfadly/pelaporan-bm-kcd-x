# Arsitektur & Teknologi

> Implementasi berjalan (branch `feat/migrasi-laravel-12`). Skema lengkap:
> `docs/database.md`. Endpoint: `docs/api.md`.

## 1. Tech Stack (terverifikasi)

| Lapisan | Teknologi | Versi |
|---------|-----------|-------|
| Backend | Laravel | 13.31 (PHP 8.4) |
| Frontend | Inertia.js React + TypeScript | Inertia 3, React 19, TS 7 |
| Styling / build | Tailwind CSS 4, Vite 7 | — |
| Database | **PostgreSQL** (dev & prod) | PDO pgsql |
| Auth | Fortify 1.39 (session) + 2FA TOTP + passkeys (webauthn) | — |
| RBAC | spatie/laravel-permission | 8.3 |
| Activity log | spatie/laravel-activitylog | 5.1.1 (log `sistem`) |
| Export Excel | maatwebsite/excel 4 + PhpSpreadsheet 5 | `.xlsx` langsung |
| Captcha login | Cloudflare Turnstile (`TurnstileService`) | opsional via env |
| Test | PHPUnit 13, Pint, `tsc --noEmit` | coverage gate 80% |

Roles: `super_admin`, `admin_kcd`, `operator_sekolah`, `bendahara_sekolah`.
10 permission; `lihat-log-aktivitas` dimiliki super_admin & admin_kcd
(aktivitas super_admin disembunyikan dari admin_kcd).

## 2. Alur Request

```text
Browser (React SPA via Inertia)
  → routes/web.php (auth → role/permission → throttle)
  → Controller (validasi → cek kunci → transaksi DB → activity log)
  → Inertia page (props) | redirect flash | file .xlsx | 204 empty
```

- Inertia: 1 layout `AppLayout.tsx` (sidebar role-based), 17 halaman di
  `resources/js/Pages/` (Auth 2, Admin 3, PelaporanBm 11 + Dashboard).
- Shared props (`HandleInertiaRequests`): `auth.user` (id, name, username,
  roles, permissions, sekolah), `flash`, `turnstileSiteKey`.
- Route URL tersedia di frontend via Ziggy.

## 3. Struktur Direktori (ringkas, aktual)

```text
app/
  Actions/Fortify/            # Create/Reset/Update password & profil
  Console/Commands/MigrasiDataLamaCommand.php   # migrasi legacy (logging dimatikan)
  Exports/                    # SpjRekapExport, RealisasiBmExport(+Sheet),
                              # CetakBmExport(+Sheet), KodeBarangSheet, SpjRekapExport
  Http/Controllers/
    Admin/                    # ActivityLog, KodeBarang, User
    Auth/PasswordExpiredController.php
    Concerns/ResolvesSekolah.php   # resolusi tenant sekolah_id
    PelaporanBm/              # Acuan, Cetak, Dashboard, InputRealisasi,
                              # KunciLaporan, Realisasi, Rekapan, Spj
  Listeners/CatatLoginLogout.php   # log login/logout (Event::listen di provider)
  Models/ (User + Master/{Sekolah,KodeBarang} + PelaporanBm/{Spj,Realisasi,Acuan,KunciLaporan})
  Providers/ (AppServiceProvider: Gate super_admin + event auth; FortifyServiceProvider)
  Services/TurnstileService.php
bootstrap/app.php             # middleware alias role/permission, web group
routes/web.php                # ~40 rute (lihat docs/api.md)
config/activitylog.php        # log default `default`, auth driver default
public/
  favicon.ico                 # favicon multi-res (16/32/48/64)
  favicon-16x16.png, favicon-32x32.png
  site.webmanifest            # manifest PWA (nama, theme_color, icons)
  icons/                      # apple-touch, PWA 72–512, mstile-150
  images/logolog.jpeg         # master logo (sumber semua ikon)
resources/views/app.blade.php # <head> global: favicon, manifest, @inertia
```

## 4. Pola Kunci Implementasi

### 4.1 Isolasi tenant (`sekolah_id`)
- `ResolvesSekolah`: operator/bendahara terkunci ke `sekolah_id` miliknya;
  admin tanpa `sekolah_id` (lintas sekolah).
- Guard ganda: query selalu `where sekolah_id` + cek kepemilikan per-record
  (403 lintas tenant). Test: `SecurityHardeningTest`.

### 4.2 Penguncian laporan
- `KunciLaporan` unique `(sekolah_id, bulan)`; status
  `draft`/`menunggu_approval`/`disetujui` + `status_kunci`.
- Terkunci = `status_kunci` ATAU status kirim final → semua mutasi SPJ/
  realisasi bulan itu ditolak (blokir di tiap aksi tulis).

### 4.3 Status realisasi ter-derive (tanpa flag)
- `Spj::is_realisasi` dihitung dari keberadaan row `Realisasi`
  (`realisasiIds`/`teralokasiIds` di props). Hapus SPJ → realisasi terkait
  ikut terhapus dalam transaksi (tanpa yatim).

### 4.4 Export XLSX identik legacy
- Semua unduhan via `maatwebsite/excel ^4.0`, file `.xlsx` langsung.
- Pola sheet: `Export + WithEvents(AfterSheet) + WithTitle`; judul/header
  ditulis eksplisit `setCellValue` (bukan `FromCollection`, yang me-skip
  baris array kosong dan menggeser data), data via `fromArray` dari `A10`.
- 25 kolom A–Y (realisasi) / 26 kolom A–Z + Kab/Kota (cetak),
  sheet `KODE BARANG`, formula VLOOKUP range dinamis
  `$A$2:$E$<batasMaster>`, SUM, nilai & penyusutan; `pre_calculate_formulas=false`.
- Data kosong → `204 No Content` + cookie `download_status=empty`;
  sukses → cookie `download_status=complete`. Frontend: guard + pre-flight
  `cetak/check` + modal progres.
- Util: `safeCell` (anti formula-injection), `formatKotaKab`
  (`KABUPATEN/KOTA X`), gridlines on, auto-width `maxLen+4`, urut
  `nama_sekolah, ba_tgl, id`.

### 4.5 Import
- Acuan: XLSX-only (`mimes:xlsx,xls`), parser native ZipArchive + SimpleXML
  (shared/inline strings, serial tanggal), batas 5.000 baris, transaksi.
- Kode Barang: xlsx/xls/csv, batas 20.000 baris, `updateOrCreate` per kode.

### 4.6 Logging aktivitas (sistem-wide)
- Trait `LogsActivity` di 6 model (Spj, Realisasi, Acuan, KunciLaporan,
  KodeBarang, User) → auto `created/updated/deleted` + diff
  (`attribute_changes.attributes/old`), `logOnlyDirty + dontLogEmptyChanges`.
- User: `logExcept` password, remember_token, secret 2FA.
- Manual `activity('sistem')`: login/logout (listener), ganti/reset password,
  kirim/verifikasi/kunci, import & hapus-massal, 3 unduhan, hapus
  SPK/prune/sinkron realisasi (query-builder bypass trait).
- Anti-duplikat: aksi user & reset password dibungkus `withoutLogging` +
  1 ringkasan manual. Migrasi legacy mematikan logging.
- Tenant: via `causer.sekolah` (`User belongsTo Sekolah`) + `properties.sekolah_id`.
- Viewer: `Admin/ActivityLogController` + page filter + permission
  `lihat-log-aktivitas`; aktivitas super_admin disembunyikan dari non-super_admin.
  Tanpa purge (retensi permanen).
- Migrasi bawaan Spatie diubah ke `nullableUuidMorphs` (model ber-UUID).

### 4.7 Auth & keamanan akun
- Fortify session + username-only (tanpa email). `EnsurePasswordNotExpired`:
  paksa `/ubah-password` saat `password_changed_at` null / ≥ 90 hari (khusus
  operator & bendahara). Password: min 8 + huruf besar/kecil + angka + simbol.
- Turnstile: `TURNSTILE_ENABLED=false` lokal; share `null` bila disabled.
- Throttle: unduhan 30/menit, import/destroy-all 10/menit, cari-barang 120/menit.

## 5. Model & Relasi (ringkas; detail di `docs/database.md`)

```text
Sekolah 1──N User (sekolah_id, nullable utk admin)
Sekolah 1──N Acuan | Spj | Realisasi | KunciLaporan
Acuan 1──N Spj | Realisasi (acuan_id)
Spj 1──N Realisasi (spj_id, cascade manual dlm transaksi)
KunciLaporan unique(sekolah_id, bulan); dikunci_oleh → User
Activity: morph subject/causer (UUID), log_name `sistem`
```

Semua id entitas UUID (`HasUuids`); permission/role id bigint (bawaan Spatie).

## 6. Frontend

- `AppLayout`: sidebar collapsible + mobile drawer, menu admin vs sekolah,
  link Log Aktivitas hanya super_admin; header status sekolah; logout confirm.
- Komponen reuse: `Pagination`, `SearchInput`, `EmptyState`, `Modal`,
  `ConfirmDialog`, `StatusBadge`, `CardStat`.
- Form SPK: accordion item, live search katalog, draft autosave
  (`draft_spj_barang_{sekolah}_{bulan}`), datalist history merk/sertifikat/satuan.
- Cetak: pre-flight check, cegah unduh kosong, modal progres (cookie status).
- Ikon: lucide-react. Tanpa `any` (aturan `docs/guidelines.md`).

## 7. Testing & Kualitas

- 179 test / 930 assertions (line coverage 100%, semua file 100%): Feature per
  modul + Unit (Fortify, relasi, gate, rate limit). DB SQLite in-memory
  (`phpunit.xml`), seed `PeranDanHakAksesSeeder` per test.
- Gate: `composer test` (cepat), `composer test-coverage` (Xdebug; total ≥ 80%
  via `--min` **dan** tiap file ≥ 80% via `tests/coverage-per-file.php`).
- `vendor/bin/pint`, `npx tsc --noEmit` wajib hijau sebelum merge.

## 8. Keputusan Arsitektur Penting (ADL ringkas)

| Keputusan | Alasan |
|-----------|--------|
| Inertia SPA, bukan Blade/API terpisah | 1 codebase, validasi server + UX reaktif |
| Status realisasi di-derive | Hindari flag basi; konsisten otomatis |
| Header Excel via `setCellValue` | `FromCollection` skip baris kosong → data geser |
| Import Acuan XLSX-only | Samakan template legacy `input_acuan.php` |
| Log via Spatie v5, bukan custom | Auto-diff, causer/subject morph, viewer siap |
| Morph activity UUID | Seluruh model ber-UUID |
| Tanpa purge log | Keputusan produk: simpan permanen |
| Ikon brand statis di `public/` + `site.webmanifest` | Tanpa pipeline build; disajikan langsung, cacheable, PWA-ready |
