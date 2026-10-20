# Arsitektur & Teknologi

> Implementasi berjalan (branch `feat/migrasi-laravel-12`). Skema lengkap:
> `docs/database.md`. Endpoint: `docs/api.md`.

## 1. Tech Stack (terverifikasi)

| Lapisan | Teknologi | Versi |
|---------|-----------|-------|
| Backend | Laravel | 13.37 (PHP 8.4) |
| Frontend | Inertia.js React + TypeScript | Inertia 3, React 19, TS 7 |
| Styling / build | Tailwind CSS 4, Vite 8 | Node >= 22.12 |
| Database | **PostgreSQL** (dev & prod) | PDO pgsql |
| Auth | Fortify 1.41 (session) + 2FA TOTP + passkeys (webauthn) | — |
| RBAC | spatie/laravel-permission | 8.3.2 |
| Activity log | spatie/laravel-activitylog | 5.1.3 (log `sistem`) |
| Export Excel | maatwebsite/excel 4 + PhpSpreadsheet 5 | `.xlsx` langsung |
| Captcha login | Cloudflare Turnstile (`TurnstileService`) | opsional via env |
| Test | PHPUnit 13, Pint, `tsc --noEmit` | coverage gate 80% |

Roles: `super_admin`, `admin_kcd`, `operator_sekolah`, `bendahara_sekolah`,
`operator_divisi`. 19 permission (10 BM + 8 BHP + 1 master-data-sekolah);
`lihat-log-aktivitas` dimiliki super_admin & admin_kcd (aktivitas super_admin
disembunyikan dari admin_kcd).

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
- Flash `success`/`error` ditampilkan sebagai toast Sonner oleh listener
  `router.on('success')` di `resources/js/app.tsx` (sekali per kunjungan).
- Route URL tersedia di frontend via Ziggy.

## 3. Struktur Direktori (ringkas, aktual)

```text
app/
  Actions/Fortify/            # Create/Reset/Update password & profil
  Console/Commands/MigrasiDataLamaCommand.php   # migrasi legacy (logging dimatikan)
  Exports/                    # SpjRekapExport, RealisasiBmExport(+Sheet),
                              # CetakBmExport(+Sheet), KodeBarangSheet
    DistribusiBhp/            # BhpStokExport, BhpPengeluaranExport,
                              # BhpKartuPersediaanExport
  Http/Controllers/
    Admin/                    # ActivityLog, KodeBarang, User
    Auth/PasswordExpiredController.php
    Concerns/ResolvesSekolah.php   # resolusi tenant sekolah_id
    PelaporanBm/              # Acuan, Cetak, Dashboard, InputRealisasi,
                              # KunciLaporan, Realisasi, Rekapan, Spj
    DistribusiBhp/            # Dashboard, Pengajuan, ReviewPengajuan, Barang,
                              # KodeRekening, Penyedia, Penerimaan, SaldoAwal,
                              # Dokumen, Laporan, Settings, Divisi
  Listeners/CatatLoginLogout.php   # log login/logout (Event::listen di provider)
  Models/
    Master/{Sekolah,KodeBarang,PejabatSekolah}
    PelaporanBm/{Spj,Realisasi,Acuan,KunciLaporan}
    DistribusiBhp/            # Barang, KodeRekening, HargaBarang, Penyedia,
                              # Penerimaan(+Detail), Pengajuan(+Detail),
                              # Dokumen, BatchDokumen, NomorSequence,
                              # Settings, Divisi
      Concerns/HasSumberDanaTable.php  # resolve tabel fisik per sumber dana
  Providers/ (AppServiceProvider: Gate super_admin + event auth; FortifyServiceProvider)
  Services/
    TurnstileService.php
    DistribusiBhp/            # StokService, NomorDokumenService,
                              # DokumenService, LaporanService,
                              # SaldoAwalService, SettingsService
  Support/DistribusiBhp/SumberDana.php  # pemetaan suffix → tabel bhp_{bos,bopd}_*
  Http/Middleware/SetSumberDanaBhp.php  # set/restore sumber dana aktif (fail-closed)
bootstrap/app.php             # middleware alias role/permission, web group
routes/web.php                # ~63 rute (lihat docs/api.md)
config/activitylog.php        # log default `default`, auth driver default
public/
  favicon.ico                 # favicon multi-res (16/32/48/64)
  favicon-16x16.png, favicon-32x32.png
  site.webmanifest            # manifest PWA (nama, theme_color, icons)
  icons/                      # apple-touch, PWA 72–512, mstile-150
  images/logolog.jpeg         # master logo (sumber semua ikon)
resources/views/app.blade.php # <head> global: favicon, manifest, @inertia
resources/views/distribusi-bhp/
  partials/kop-surat.blade.php           # kop surat reusable (logo, PEMDA JABAR, sekolah, kontak)
  nota-permintaan.blade.php              # PDF — portrait, 6 kolom (nama, jumlah, satuan, keperluan, ket)
  surat-permintaan.blade.php             # PDF — landscape, 13 kolom (kode, nama, NUSP, spesifikasi, diminta, sisa, disetujui, keperluan, ket)
  surat-penyaluran.blade.php             # PDF — portrait, 7 kolom (kode, nama, spesifikasi, jumlah, satuan, ket)
  bast.blade.php                         # PDF — portrait, 7 kolom, 2 pihak signature (Pihak I=Wakasek, Pihak II=PPB)
  bap.blade.php                          # PDF — portrait, 5 kolom (nama, jumlah, satuan, ket)
  stok-opname.blade.php                  # PDF — portrait, 6 kolom + total nilai, 3 kolom signature (Wakasek, PPB, Kepsek)
  buku-pengeluaran.blade.php             # PDF — landscape, 13 kolom (dokumen, kode, nama, spesifikasi, satuan, harga, total, ket)
  buku-penyaluran.blade.php              # PDF — landscape, 14 kolom (BAST, kode, nama, spesifikasi, jumlah, harga, total, SPPB, penerima, ket)
  dokumen-kompilasi.blade.php            # PDF — batch, render per jenis di page terpisah
database/seeders/
  PeranDanHakAksesSeeder.php  # 19 permission, 5 role (termasuk operator_divisi)
  DistribusiBhpImportSeeder.php  # impor legacy BHP (idempotent)
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
- SPJ: `maatwebsite/excel` (`Excel::toCollection`), xlsx/xls max 10MB, batas
  5.000 baris, **all-or-nothing** (pre-validasi semua baris → transaksi bila
  lolos), lookup `nama_barang`/`jenis_aset` dari katalog via `kode_barang`,
  BA TGL serial Excel via `PhpSpreadsheet\Shared\Date`. **Restricted NPSN**:
  allowlist `SpjController::NPSN_IMPORT_SPJ` (backend tolak + flag
  `canImportSpj` untuk UI). Log `import-spj` ber-flag `sembunyi_dari_admin_kcd`.

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
  `lihat-log-aktivitas`; aktivitas super_admin disembunyikan dari non-super_admin,
  demikian juga log ber-flag `sembunyi_dari_admin_kcd` (query utama + dropdown
  events/subjectTypes). Tanpa purge (retensi permanen).
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

### Distribusi BHP (domain terpisah)

Tabel transaksi & master dipisah fisik per sumber dana. Suffix di bawah ADA di
`bhp_bos_*` **dan** `bhp_bopd_*`; `penyedia`/`divisi` satu tabel bersama.

```text
KodeRekening 1──N Barang          (per dana; unique kode)
Barang 1──N HargaBarang | PenerimaanDetail | PengajuanDetail
Penyedia 1──N Penerimaan          (tabel bersama)
Penerimaan 1──N PenerimaanDetail   [FK cascade]
Pengajuan 1──N PengajuanDetail     [FK cascade]
Pengajuan 1──N Dokumen 1──N BatchDokumen  [Dokumen FK cascade; Batch set null]
Divisi 1──N Pengajuan | User.divisi_id  [pengajuan restrict, user set null]
NomorSequence: counter per (jenis, tahun, bulan) per tabel dana, tanpa FK
Settings: satu baris per tabel dana (partial unique is_singleton)
```

Semua id entitas UUID (`HasUuids`); permission/role id bigint (bawaan Spatie).
Model BHP **tidak** memiliki relasi ke model BM. Relasi antar tabel BHP kini
dijaga foreign key di level database (lihat `docs/database.md` §6).

## 5a. Service Domain BHP

| Service | Tanggung jawab |
|---|---|
| `StokService` | Hitung stok 4-tier; `submit` (buat reservasi), `applyApprove` (tanpa transaction sendiri), `approve`, `reject`, `rekapPerBarang` (agregasi 4-tier semua barang dalam 3 query grouped untuk laporan, anti N+1) |
| `NomorDokumenService` | Generate nomor atomic `{urut:3}/BHP/{kode}/{dana}/{romawi}/{tahun}` dengan `lockForUpdate`; insert row sequence pertama anti-race (catch `UniqueConstraintViolationException` → lock row pemenang) |
| `DokumenService` | `approveDanGenerate` (validasi pejabat + approve + dokumen wajib dalam satu transaction), `generateTambahan` (idempotent per jenis, hanya untuk pengajuan `approved`); snapshot payload selalu ter-scope `sekolah_id` pengajuan dan menyertakan snapshot `penandatangan` |
| `LaporanService` | `laporanStok` (via `rekapPerBarang`), `bukuPengeluaran`, `kartuBarang`, `kartuPersediaan` (saldo berjalan; filter sumber dana + sekolah di query DB, bukan collection PHP) |
| `SettingsService` | Satu baris `bhp_{dana}_settings` per sumber dana; `get`/`update` selalu di dalam konteks `SumberDana::dengan()` (tidak menetapkan `$aktif` secara permanen); format nomor, preferensi PDF **hardcode a4/portrait** (ukuran & orientasi tetap per jenis dokumen, bukan user-configurable) |
| `SaldoAwalService` | Lifecycle saldo awal: `create` (header tunggal per sekolah+dana+periode, partial unique index sebagai jaring race), `deleteDetail` (hapus satu barang, bersihkan header yatim), `delete` (hapus seluruh saldo awal); menolak hapus bila barang dipakai pengajuan `submitted`/`approved` (scope per sekolah + sumber dana) |

**Pemetaan sumber dana.** `App\Support\DistribusiBhp\SumberDana` adalah sumber
kebenaran tunggal untuk pemisahan tabel per dana: `tabel($suffix, $dana)`
memetakan suffix logis → `bhp_{bos,bopd}_{suffix}`; `existsRule()` menghasilkan
aturan validasi `exists` yang menunjuk tabel fisik dana terkait (cegah
`barang_id` asing lintas dana). Model BHP memakai trait
`HasSumberDanaTable`: tabel di-resolve runtime dari `SumberDana::aktif()`,
`scopeSumberDana()` menyetel konteks, dan `resolveRouteBinding()` mencari di
kedua tabel (UUID unik) sambil menyetel dana aktif. `SumberDana::dengan()`
(callback + restore) dipakai untuk operasi yang perlu konteks dana eksplisit.

Service generic (lintas domain): `App\Services\PejabatSekolahService` +
`App\Models\Master\PejabatSekolah` (tabel `pejabat_sekolah`) — master pejabat
penandatangan: `snapshotPenandatangan` (gate approval — tiga jabatan wajib
berlaku pada tanggal pengajuan), `simpan` (auto-close pejabat aktif lama H-1,
guard overlap periode), `akhiriMasaJabatan`; snapshot pejabat tersimpan
immutable di payload dokumen pemanggil.

Aturan: semua mutasi stok lewat `StokService`; `applyApprove` tidak membuka
transaction sendiri agar caller (mis. `DokumenService`) dapat membungkus approve
+ generate dokumen dalam satu transaction.

## 6. Frontend

- `AppLayout`: sidebar collapsible + mobile drawer, menu admin vs sekolah,
  link Log Aktivitas hanya super_admin; header status sekolah; logout confirm.
- `BhpHtmlMenu` (`AppLayout.tsx`): section **Distribusi BHP** dengan dua grup
  per sumber dana (`bos`/`bopd`), masing-masing mirror struktur original:
  sub-grup Distribusi Barang, Penyaluran, Laporan. Flat routes tetap
  didukung untuk backward compatibility.
- Komponen reuse: `Pagination`, `SearchInput`, `EmptyState`, `Modal`,
  `ConfirmDialog`, `StatusBadge`, `CardStat`.
- Form SPK: accordion item, live search katalog, draft autosave
  (`draft_spj_barang_{sekolah}_{bulan}`), datalist history merk/sertifikat/satuan.
- Halaman BHP: `resources/js/Pages/DistribusiBhp/` — `Dashboard`,
  `{BOS,BOPD}/Pengajuan/Index`, `{BOS,BOPD}/Review/Index`, `Dokumen/Show`,
  `{BOS,BOPD}/SaldoAwal/Index`, `{BOS,BOPD}/Settings/Edit`,
  `{BOS,BOPD}/Laporan/Index`, `{BOS,BOPD}/Laporan/KartuPersediaan`,
  `{BOS,BOPD}/Laporan/DokumenList` (view dokumen per jenis),
  `{BOS,BOPD}/Laporan/BukuPengeluaran`,
  `{BOS,BOPD}/Penerimaan/Index`, `{BOS,BOPD}/Penerimaan/LunasBayar`,
  `{BOS,BOPD}/Divisi/Index`,
  `{BOS,BOPD}/Master/{KodeRekening,Barang,Penyedia}/Index`.
- Settings BHP hanya kolom `format_nomor` (paper_size & orientation **dihapus** — hardcode per jenis dokumen di `DokumenController::PAPER`).
- Import SPJ: tombol "Import Excel" + modal unggah di `Spj/Index.tsx`
  (kondisional `canImportSpj`), link template `template_import_spj.xlsx`.
- Cetak: pre-flight check, cegah unduh kosong, modal progres (cookie status).
- Ikon: lucide-react. Tanpa `any` (aturan `docs/guidelines.md`).

## 7. Testing & Kualitas

- 418 test / 1948 assertions (50 file Feature+Unit, 16 di antaranya BHP):
  Feature per modul + Unit (Fortify, relasi, gate, rate limit). DB SQLite
  in-memory (`phpunit.xml`), seed `PeranDanHakAksesSeeder` per test.
- Gate: `composer test` (cepat), `composer test-coverage` (Xdebug; total ≥ 80%
  via `--min` **dan** tiap file ≥ 80% via `tests/coverage-per-file.php`).
- `vendor/bin/pint`, `npx tsc --noEmit` wajib hijau sebelum merge.
- Domain BHP dijaga `BhpDomainBoundaryTest` (tidak ada pencampuran tabel/model/
  route antara BM dan BHP).

## 8. Keputusan Arsitektur Penting (ADL ringkas)

| Keputusan | Alasan |
|-----------|--------|
| Inertia SPA, bukan Blade/API terpisah | 1 codebase, validasi server + UX reaktif |
| Status realisasi di-derive | Hindari flag basi; konsisten otomatis |
| Header Excel via `setCellValue` | `FromCollection` skip baris kosong → data geser |
| Import Acuan XLSX-only | Samakan template legacy `input_acuan.php` |
| Import SPJ all-or-nothing + restricted NPSN | Mencegah data parsial; rollout bertahap ke sekolah terpilih |
| Log via Spatie v5, bukan custom | Auto-diff, causer/subject morph, viewer siap |
| Morph activity UUID | Seluruh model ber-UUID |
| Tanpa purge log | Keputusan produk: simpan permanen |
| Ikon brand statis di `public/` + `site.webmanifest` | Tanpa pipeline build; disajikan langsung, cacheable, PWA-ready |
| BHP sebagai domain terpisah (bukan modul BM) | Stok, status, dan laporan berbeda; dicegah bercampur lewat test batas domain |
| Tabel BHP dipisah fisik per sumber dana (`bhp_bos_*`/`bhp_bopd_*`) | Integritas BOS/BOPD dijamin skema; sumber dana implisit dari nama tabel, `SumberDana` sebagai pemetaan tunggal |
| Middleware `SetSumberDanaBhp` fail-closed + restore state | `sumber_dana` invalid ditolak 422 (bukan fallback BOS); state tidak bocor antar-request (aman Octane/queue) |
| Dokumen BHP satu tabel per dana + kolom `jenis` | Lifecycle, penomoran, dan batch PDF identik untuk semua jenis dokumen |
| Stok 4-tier dihitung, bukan kolom | Hindari stok basi; satu sumber kebenaran `StokService` |
| `applyApprove` tanpa transaction sendiri | Agar approve + generate dokumen bisa atomic dalam satu transaction |
| `source_id` pada penerimaan | `no_bast` tidak unik di data legacy; jadikan kunci idempotensi impor |
| Foreign key DB untuk seluruh relasi BHP (2026-10-08, diterapkan ulang saat split 2026-10-12) | Mencegah orphan record di level database: detail/dokumen cascade, histori master restrict, `users.divisi_id` set null |
| Snapshot payload dokumen ter-scope sekolah pengajuan | Payload stok opname/buku tidak pernah memuat data sekolah lain (audit 2026-10-08) |
| Singleton settings per sumber dana via partial unique index | `get`/`create` bersamaan tidak menghasilkan dua baris settings; BOS & BOPD terpisah |
| Dokumen PDF per jenis template (8 Blade terpisah) + `DokumenController::VIEW`/`PAPER` map | Struktur kolom, kop surat, signature berbeda per jenis; paper/orientasi hardcode — bukan konfigurasi user |
| Agregasi laporan stok grouped (`rekapPerBarang`) | Laporan stok dari ~7N query menjadi 3 query grouped — aman untuk katalog besar |
