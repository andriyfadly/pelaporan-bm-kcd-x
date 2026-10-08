# Strategi Testing

> Gate: `composer test-coverage` (Xdebug, line coverage ≥ 80%) wajib hijau
> sebelum merge. Gate memeriksa dua level: total ≥ 80% (flag `--min` phpunit)
> **dan** tiap file ≥ 80% (script `tests/coverage-per-file.php` atas Clover XML).
> Status terakhir: 308 passed / 1465 assertions (44 file Feature+Unit, 11
> di antaranya domain Distribusi BHP). Coverage gate tetap wajib
> dijalankan sebelum merge.

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

## 3. Matriks Cakupan (40 Feature + 4 Unit, termasuk 14 file BHP)

| File | Menjamin |
|------|----------|
| `PelaporanBmTest` | Alur SPJ multi-item, realisasi, export 204/cookie, cetak pre-flight |
| `ExportDanLogLanjutanTest` | Baca-balik isi XLSX (`A8`/`A10`/VLOOKUP/`SUM`), cookie `complete`/`empty`, 204 tanpa log, kirim sukses, kunci/buka/verifikasi, logout, ganti password, tambah/ubah/hapus user, import + hapus-massal acuan, sinkron/hapus-item/hapus SPK, auto-log acuan & kode barang, unduh SPJ |
| `DbErrorHandlingTest` | `QueryException` → Inertia `Error` 500 (XHR) / redirect + flash (web biasa) / JSON 500 tanpa SQL bocor, `Log::error` konteks user+route |
| `ErrorPageTest` | Error page kustom: Blade `errors.page` (load awal) + Inertia `Error` (navigasi) untuk 404/403 |
| `LogViewerAccessTest` | `/admin/log-error`: tamu/operator → 403, super_admin → 200 |
| `ActivityLogTest` | Auto-log create/update/destroy + `old`, login, causer via HTTP, viewer 200/403, password tak bocor |
| `SecurityHardeningTest` | Cross-tenant 403, bulan terkunci diblokir, rate-limit, operator tanpa sekolah ditolak 403 di semua endpoint lintas-sekolah (index + unduh), dan role sekolah tanpa tenant tidak masuk jalur admin |
| `InputRealisasiTest` | Alur realisasi: pilih-bulan normalisasi, index hitung kekurangan, tambah/simpan (validasi item, acuan, batas anggaran), duplicate allocation ditolak, edit readonly, `super_admin` dapat mengedit laporan `disetujui` tanpa mengubah status approval, update uncheck ter-scope bulan+kodering, kirim balance→lock, submit ulang ditolak |
| `SpjGapTest` | SPJ: pilih-bulan, create/edit-spk lock, store-spk lock, destroy/update cross-tenant 403 + acuan lintas sekolah, cari-barang kosong & fallback master→SPJ |
| `SpjImportTest` | Import SPJ: sukses multi-item + lookup katalog, BA TGL serial Excel, all-or-nothing (baris invalid/kode tak dikenal/BA TGL rusak → 0 tersimpan), laporan terkunci ditolak, tenant isolation, allowlist NPSN (di daftar → sukses, luar → ditolak), flag `canImportSpj` per NPSN, batas 5000 baris, validasi payload, log `import-spj` terlihat super_admin & tersembunyi dari admin_kcd |
| `AcuanImportGapTest` | Import acuan: skip baris pendek/invalid, tanggal serial Excel, bulan dari request, target via NPSN, batas 5000 baris, tenant isolation store/destroy |
| `CetakBmSheetTest` | `safeCell` netralkan formula injection (`=`/`+`/`@`), `formatKotaKab` normalisasi "Kab."→"KABUPATEN" |
| `AcuanIndexEdgeTest` | Default bulan Januari→Desember (unit `defaultBulan`), `search_satuan` by nama/NPSN + escape wildcard `%`, `parseTanggal` kosong, `parseXlsx` zip rusak + rich-text/inlineStr, daftar sekolah untuk admin, bulan kosong tanpa filter |
| `CetakControllerEdgeTest` | `show()` stub "Belum Ada Sekolah" saat DB sekolah kosong, daftar sekolah admin, `check()` terfilter per sekolah & hitung `ba_tgl` null |
| `RekapanEdgeTest` | Label status menunggu/disetujui, realisasi per `acuan_id`, sort TUNTAS dulu + alfabetis, grup `TANPA KODERING` tak masuk progres, paritas legacy (baris dari acuan, realisasi dari alokasi `pelaporan_bm_realisasi`, SPJ tanpa acuan tak muncul/dihitung) |
| `DashboardEdgeTest` / `DashboardAdminParityTest` | Admin listSelesai/listBelum, fallback target semua sekolah, status `menunggu_approval`→SELESAI, unit `bulanLapor` Januari→Desember, dan data acuan/realisasi tahun lain tidak masuk monitoring |
| `ActivityLogFilterTest` | Filter `subject_type`, `dari`/`sampai`, `event`, `q` (description), `sekolah_id` (properties) |
| `ExportSafeCellTest` | `RealisasiBmSheet::safeCell` netralkan formula + null/kosong, `CetakBmSheet::formatKotaKab` kosong/spasi |
| `RekapanDanKunciTest` | Rekapan, toggle kunci, status draft→disetujui, metadata lock dibersihkan saat kembali ke draft |
| `UserManagementTest` | CRUD user, proteksi super_admin & hapus diri, pemetaan role admin_kcd/bendahara_sekolah, validasi pasangan role-sekolah |
| `PasswordExpiryTest` | Intersep 90 hari, dedicated page, kompleksitas, akses dashboard setelah password diperbarui |
| `TurnstileLoginTest` | Login dengan/without Turnstile sesuai env, penolakan Cloudflare, gagal koneksi, memo siteverify (token sekali pakai) |
| `KodeBarangTest` / `KodeBarangImportTest` | CRUD + leaf-search + import batas 20rb |
| `MigrasiLegacyTest` | 77 sekolah, 717 acuan, 490 SPJ, 42 realisasi, 13 kunci, users, katalog |
| `SekolahFlowFixTest` | Regresi alur sekolah |
| Unit: `FortifyActionsTest`, `ModelRelationsTest`, `ProvidersTest` | Action reset/update, relasi & accessor `is_realisasi`, gate super_admin |

### Distribusi BHP (14 file, 110 kasus)

| File | Menjamin |
|------|----------|
| `BhpDomainBoundaryTest` | Pemisahan domain: tidak ada tabel/model/route BM yang tercampur BHP (dan sebaliknya) |
| `BhpStokDanPengajuanTest` | Stok 4-tier, submit→reservasi, approve konversi ke pemakaian, reject lepas reservasi, stok tidak negatif |
| `BhpHttpFlowTest` | Alur HTTP pengajuan→review→approve, guard permission, cross-tenant 403 |
| `BhpMasterDataTest` | CRUD kode rekening/barang/penyedia, validasi sumber dana, riwayat harga |
| `BhpPenerimaanTest` | Penerimaan + detail, saldo awal, validasi sumber dana/sekolah |
| `BhpDokumenTest` | Auto-generate dokumen wajib saat approve (dengan pejabat lengkap), penomoran atomic per jenis/dana/periode, reject bila dokumen sudah ada |
| `BhpPejabatSekolahTest` | **Gate pejabat**: approval gagal bila pejabat belum lengkap (status tetap submitted, reservasi utuh, tanpa dokumen), snapshot penandatangan di payload, reprint pakai snapshot saat pejabat berganti, approval backdate pakai pejabat berlaku pada tanggal pengajuan, satu pejabat aktif per jabatan (auto-close H-1), overlap periode ditolak, isolasi sekolah 403, `generateTambahan` menolak pengajuan non-approved. Rute `/master-data/pejabat` |
| `ProfilSekolahTest` | Operator sekolah edit profil kontak (alamat/telepon/website/email) sekolahnya sendiri, nama/NPSN tidak bisa diubah operator, ter-isolasi antar sekolah, permission `kelola-master-data-sekolah` (bendahara 403), email invalid ditolak |
| `BhpDokumenTambahanTest` | `generateTambahan` BAST/BAP/stok opname/buku, idempotent, tolak jenis tak dikenal, snapshot payload **ter-scope sekolah pengajuan** (payload sekolah A tidak memuat stok/pengeluaran sekolah B), guard permission |
| `BhpPdfDokumenTest` | Rendering PDF per dokumen + batch kompilasi, `safeFilename`, histori `bhp_batch_dokumen` |
| `BhpLaporanTest` | `laporanStok` 4-tier (agregasi `rekapPerBarang` tanpa N+1), isolasi per sumber dana, `bukuPengeluaran`, `kartuBarang`, export XLSX stok/pengeluaran |
| `BhpKartuPersediaanTest` | Saldo berjalan kronologis, masuk-didahului pada tanggal sama, hanya mutasi dana terkait, abaikan pengajuan non-approved, pakai `jumlah_disetujui`, export + validasi `barang_id` (`uuid` + `exists`), UUID tak dikenal → validation error, barang sumber dana lain → redirect + flash (bukan 500) |
| `BhpImportSeederTest` | Impor master + penerimaan: skip soft-delete, hanya detail `cart_status=saved`, relasi rekening, `source_id` unik (no_bast duplikat/NULL tetap terpisah), tabel tanpa `deleted_at`, idempotensi, sumber tidak ada → dilewati |
| `BhpDivisiTest` | Buat divisi + akun operator (suffix NPSN), validasi prefix/suffix/username duplikat, isolasi pengajuan per divisi, submit/divisi lain 403, **dokumen divisi lain 403 (show/PDF/batch/tambahan)**, dashboard operator divisi hanya menghitung divisinya, reset password + toggle ter-scope sekolah |

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
- Belum ada test konkurensi multi-request; transaction dan `lockForUpdate()` sudah
  digunakan pada alokasi, tetapi verifikasi race condition nyata tetap perlu
  diuji pada PostgreSQL.
- Tanpa benchmark performa export besar; export masih perlu ditinjau untuk
  streaming/chunking dataset produksi.
- Logika yang bergantung `date('n')` global (default bulan filter, bulan lapor
  dashboard) diuji lewat helper kecil ber-argumen opsional
  (`AcuanController::defaultBulan(?int)`, `DashboardController::bulanLapor(?int)`)
  agar deterministik tanpa mengunci jam sistem.
