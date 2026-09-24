# Strategi Testing

> Gate: `composer test-coverage` (Xdebug, line coverage ≥ 80%) wajib hijau
> sebelum merge. Gate memeriksa dua level: total ≥ 80% (flag `--min` phpunit)
> **dan** tiap file ≥ 80% (script `tests/coverage-per-file.php` atas Clover XML).
> Status: 179 passed / 930 assertions (total **100.0%**, semua file 100%).

## 1. Perintah

```bash
composer test            # cepat, tanpa coverage (iterasi harian)
composer test-coverage   # gate penuh, gagal bila < 80%
php artisan test tests/Feature/PelaporanBmTest.php   # satu file
php artisan test --filter=nama_test                   # satu kasus
vendor/bin/pint --format agent   # format (terpisah dari gate)
npx tsc --noEmit                     # type-check frontend
```

Coverage satu file (untuk audit gap, tanpa menjalankan seluruh suite):

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit tests/Feature/AcuanIndexEdgeTest.php \
  --coverage-clover /tmp/cov.xml
```

`composer test-coverage` menjalankan dua pengecekan: total ≥ 80% (flag `--min`
phpunit) **dan** tiap file ≥ 80% lewat `tests/coverage-per-file.php` yang
mem-parse Clover XML dan keluar `exit 1` bila ada satu file di bawah ambang.
Status kini: **100% total, semua file 100%**.

## 2. Fondasi

- DB SQLite in-memory (`phpunit.xml`); tiap Feature memakai
  `RefreshDatabase` + seed `PeranDanHakAksesSeeder`, user via
  `User::create` + `assignRole` (pola baku).
- `TURNSTILE_ENABLED=false` di-override di `phpunit.xml` agar key produksi
  dari `.env` tidak bocor ke test env; skenario Turnstile aktif diatur
  per-test via `config([...])` + `Http::fake()`.
- `tests/TestCase::setUp()` memanggil `withoutVite()` — halaman Inertia
  500 bila Vite manifest belum di-build, dan test feature tidak merender
  asset frontend.
- Import xlsx dibuat in-test via `ZipArchive` (tanpa paket tambahan);
  CSV via `UploadedFile::fake()->createWithContent()`.
- Export XLSX diverifikasi baca-balik cell (header `A8`, data `A10`,
  VLOOKUP dinamis, `SUM`), bukan sekadar status unduhan.
- Cabang privat (parser `parseXlsx`/`parseTanggal`, formatter `formatKotaKab`,
  `safeCell`) diuji via `ReflectionMethod` — tidak perlu memaksa jalur HTTP.
- Logika bergantung `date('n')` global diuji lewat helper ber-argumen opsional
  (`AcuanController::defaultBulan(?int)`, `DashboardController::bulanLapor(?int)`)
  agar deterministik tanpa mengunci jam sistem.

## 3. Matriks Cakupan (24 Feature + 4 Unit)

| File | Menjamin |
|------|----------|
| `PelaporanBmTest` | Alur SPJ multi-item, realisasi, export 204/cookie, cetak pre-flight |
| `ExportDanLogLanjutanTest` | Baca-balik isi XLSX (`A8`/`A10`/VLOOKUP/`SUM`), cookie `complete`/`empty`, 204 tanpa log, kirim sukses, kunci/buka/verifikasi, logout, ganti password, tambah/ubah/hapus user, import + hapus-massal acuan, sinkron/hapus-item/hapus SPK, auto-log acuan & kode barang, unduh SPJ |
| `DbErrorHandlingTest` | `QueryException` → Inertia `Error` 500 (XHR) / redirect + flash (web biasa) / JSON 500 tanpa SQL bocor, `Log::error` konteks user+route |
| `ErrorPageTest` | Error page kustom: Blade `errors.page` (load awal) + Inertia `Error` (navigasi) untuk 404/403 |
| `LogViewerAccessTest` | `/admin/log-error`: tamu/operator → 403, super_admin → 200 |
| `ActivityLogTest` | Auto-log create/update/destroy + `old`, login, causer via HTTP, viewer 200/403, password tak bocor |
| `SecurityHardeningTest` | Cross-tenant 403, bulan terkunci diblokir, rate-limit, operator tanpa sekolah ditolak 403 di semua endpoint lintas-sekolah (index + unduh) |
| `InputRealisasiTest` | Alur realisasi: pilih-bulan normalisasi, index hitung kekurangan, tambah/simpan (validasi item, acuan, batas anggaran), edit readonly, update uncheck ter-scope bulan+kodering, kirim balance→lock |
| `SpjGapTest` | SPJ: pilih-bulan, create/edit-spk lock, store-spk lock, destroy/update cross-tenant 403 + acuan lintas sekolah, cari-barang kosong & fallback master→SPJ |
| `AcuanImportGapTest` | Import acuan: skip baris pendek/invalid, tanggal serial Excel, bulan dari request, target via NPSN, batas 5000 baris, tenant isolation store/destroy |
| `CetakBmSheetTest` | `safeCell` netralkan formula injection (`=`/`+`/`@`), `formatKotaKab` normalisasi "Kab."→"KABUPATEN" |
| `AcuanIndexEdgeTest` | Default bulan Januari→Desember (unit `defaultBulan`), `search_satuan` by nama/NPSN + escape wildcard `%`, `parseTanggal` kosong, `parseXlsx` zip rusak + rich-text/inlineStr, daftar sekolah untuk admin, bulan kosong tanpa filter |
| `CetakControllerEdgeTest` | `show()` stub "Belum Ada Sekolah" saat DB sekolah kosong, daftar sekolah admin, `check()` terfilter per sekolah & hitung `ba_tgl` null |
| `RekapanEdgeTest` | Label status menunggu/disetujui, realisasi per `acuan_id`, sort TUNTAS dulu + alfabetis, grup `TANPA KODERING` tak masuk progres, paritas legacy (baris dari acuan, realisasi dari alokasi `pelaporan_bm_realisasi`, SPJ tanpa acuan tak muncul/dihitung) |
| `DashboardEdgeTest` | Admin listSelesai/listBelum, fallback target semua sekolah, status `menunggu_approval`→SELESAI, unit `bulanLapor` Januari→Desember |
| `ActivityLogFilterTest` | Filter `subject_type`, `dari`/`sampai`, `event`, `q` (description), `sekolah_id` (properties) |
| `ExportSafeCellTest` | `RealisasiBmSheet::safeCell` netralkan formula + null/kosong, `CetakBmSheet::formatKotaKab` kosong/spasi |
| `RekapanDanKunciTest` | Rekapan, toggle kunci, status draft→disetujui |
| `UserManagementTest` | CRUD user, proteksi super_admin & hapus diri, pemetaan role admin_kcd/bendahara_sekolah |
| `PasswordExpiryTest` | Intersep 90 hari, dedicated page, kompleksitas |
| `TurnstileLoginTest` | Login dengan/without Turnstile sesuai env, penolakan Cloudflare, gagal koneksi, memo siteverify (token sekali pakai) |
| `KodeBarangTest` / `KodeBarangImportTest` | CRUD + leaf-search + import batas 20rb |
| `MigrasiLegacyTest` | 77 sekolah, 717 acuan, 490 SPJ, 42 realisasi, 13 kunci, users, katalog |
| `SekolahFlowFixTest` | Regresi alur sekolah |
| Unit: `FortifyActionsTest`, `ModelRelationsTest`, `ProvidersTest` | Action reset/update, relasi & accessor `is_realisasi`, gate super_admin |

## 4. Menambah Test Baru

1. Ikuti pola: seed peran → buat user + role → `actingAs` → assert redirect/
   DB/log (bukan hanya status 200).
2. Untuk aksi tulis yang memicu activity log, bungkus setup dengan
   `activity()->withoutLogging(...)` agar hitungan log presisi.
3. Kasus keamanan baru (tenant/kunci) masuk `SecurityHardeningTest`,
   bukan file acak.
4. Utamakan menutup baris yang belum tercakup **karena menjamin perilaku nyata**
   (batas, fallback, cabang error), bukan sekadar menembak angka coverage.
   Preferensi assert: nilai hasil (Inertia props / cell XLSX / isi DB), bukan
   hanya status.
5. Jalankan file barunya + full suite + `composer test-coverage` sebelum commit.

## 5. Batasan Diketahui

- Tanpa test browser/JS (Vite/Inertia diuji via HTTP + `tsc`).
- Tanpa test konkurensi transaksi; tanpa benchmark performa export besar.
- Logika yang bergantung `date('n')` global (default bulan filter, bulan lapor
  dashboard) diuji lewat helper kecil ber-argumen opsional
  (`AcuanController::defaultBulan(?int)`, `DashboardController::bulanLapor(?int)`)
  agar deterministik tanpa mengunci jam sistem.
