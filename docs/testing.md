# Strategi Testing

> Gate: `composer test-coverage` (Xdebug, line coverage ≥ 80%) wajib hijau
> sebelum merge. Gate memeriksa dua level: total ≥ 80% (flag `--min` phpunit)
> **dan** tiap file ≥ 80% (script `tests/coverage-per-file.php` atas Clover XML).
> Status terakhir: 877 passed / 6007 assertions (96 file Feature+Unit, 37
> di antaranya domain Distribusi BHP — termasuk hari kerja, profil sekolah, dan
> penandatangan PDF). Coverage gate tetap wajib dijalankan sebelum merge.

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

## 3. Matriks Cakupan (92 Feature + 4 Unit, termasuk 37 file BHP)

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

### Distribusi BHP (37 file, 402 kasus)

| File | Menjamin |
|------|----------|
| `BhpDomainBoundaryTest` | Pemisahan domain: tidak ada tabel/model/route BM yang tercampur BHP (dan sebaliknya); `sumber_dana` invalid di middleware → 422 (fail-closed), default BOS bila kosong, state sumber dana kembali setelah request, `availablePeriods` saldo awal hanya menampilkan periode sumber dana aktif |
| `BhpStokDanPengajuanTest` | Stok 4-tier, submit→reservasi, approve konversi ke pemakaian, reject lepas reservasi, stok tidak negatif, revalidasi sumber dana saat approve, detail_id asing/quantity invalid ditolak |
| `BhpIsolasiSekolahTest` | **Regresi P0 tanpa pool pusat**: stok sekolah A tak terlihat sekolah B, dua sekolah tak bisa oversell stok yang sama, saldo awal wajib sekolah |
| `BhpHttpFlowTest` | Alur HTTP pengajuan→review→approve, guard permission, cross-tenant 403, update/hapus draft, sekolah_id palsu diabaikan, divisi lintas sekolah ditolak, approve/reject/dokumen sekolah lain 403, **operator tidak dapat approve pengajuan sendiri (403)** |
| `BhpSettingsTest` | Format nomor default/kustom, singleton satu baris **per sumber dana** (BOS & BOPD independen), preferensi PDF, 403 role tanpa `kelola-master-bhp`, placeholder asing/tanpa `{urut:N}` ditolak, sequence terpisah per (jenis × sumber dana × tahun × bulan), nomor berurutan tidak duplikat, padding `{urut:N}` |
| `BhpMasterDataTest` | CRUD kode rekening/barang/penyedia, validasi sumber dana, riwayat harga |
| `BhpSaldoAwalTest` | Halaman saldo awal terpisah: akses per role, tambah manual, statistik, isolasi per sekolah, periode, **hapus per barang tanpa menghapus barang lain** (regresi P0), hapus barang terakhir membersihkan header, tolak hapus saat dipakai pengajuan aktif, pengajuan beda sumber dana tidak mengunci, import Excel (sukses, batal total, beda sumber dana, duplikat DB/berkas, kode kosong/tak dikenal, jumlah desimal `1.5` ditolak, harga desimal/teks, ribuan `1.500`, nol/negatif, >2000 baris, trailing kosong, bukan Excel, periode di luar rentang), unduh template, isolasi import antar sekolah |
| `BhpPenerimaanTest` | Penerimaan + detail, saldo awal (periode wajib, duplikat per barang ditolak, BOS≠BOPD, hapus sebelum/sesudah dipakai, ownership), boundary guest/bendahara/sumber dana/penyedia/barang invalid |
| `BhpDokumenTest` | Auto-generate dokumen wajib saat approve (dengan pejabat lengkap), penomoran atomic per jenis/dana/periode, reject bila dokumen sudah ada, rollback stok saat generate gagal, gate pejabat tidak mengubah state |
| `BhpPejabatSekolahTest` | **Gate pejabat**: approval gagal bila pejabat belum lengkap (status tetap submitted, reservasi utuh, tanpa dokumen), snapshot penandatangan di payload, reprint pakai snapshot saat pejabat berganti, approval backdate pakai pejabat berlaku pada tanggal pengajuan, satu pejabat aktif per jabatan (auto-close H-1), overlap periode ditolak, isolasi sekolah 403, `generateTambahan` menolak pengajuan non-approved. Rute `/master-data/penugasan` |
| `ProfilSekolahTest` | Operator sekolah edit profil kontak (alamat/telepon/website/email) sekolahnya sendiri, nama/NPSN tidak bisa diubah operator, ter-isolasi antar sekolah, permission `kelola-master-data-sekolah` (bendahara 403), email invalid ditolak, admin KCD tanpa `sekolah_id` diminta pilih sekolah dulu (tidak bisa simpan tanpa pilih), admin dengan sekolah terpilih bisa edit hanya sekolah itu |
| `BhpDokumenTambahanTest` | `generateTambahan` BAST/BAP/stok opname/buku, idempotent, tolak jenis tak dikenal, snapshot payload **ter-scope sekolah pengajuan** (payload sekolah A tidak memuat stok/pengeluaran sekolah B), guard permission |
| `BhpDemoSmkn4KuninganSeederTest` | Dataset demo SMKN 4 Kuningan: jumlah barang/penerimaan/pengajuan/dokumen per sumber dana (82 dokumen termasuk 6 stok opname bulanan), idempotent saat dijalankan dua kali, tiga pejabat berlaku sepanjang semester, tidak menyentuh sekolah lain |
| `BhpDemoSemesterBalanceTest` | Balance semester diturunkan langsung dari baris transaksi: rekonsiliasi bulanan = mutasi mentah, rantai saldo tidak putus, tidak ada stok negatif, buku penerimaan − buku pengeluaran = saldo akhir, approval sebagian memakai `jumlah_disetujui` |
| `BhpDemoDokumenBalanceTest` | Cetak sama dengan sumbernya: Nota Permintaan, SPB (sisa stok bukan 0), SPPB, buku pengeluaran/penyaluran, **stok opname satu per bulan, bertanggal hari kerja terakhir, dan cocok saldo akhir bulan itu**, opname pada hari libur ditolak, PDF semua jenis, **tanggal dokumen berbahasa Indonesia (bukan `30 January 2026`) dan kolom tabel buku bukan tanggal ISO mentah** |
| `HariKerjaTest` | Kalender hari kerja: Sabtu/Minggu libur tanpa memanggil API, libur nasional dari `tanggalmerah.upset.dev` dihitung libur, cuti bersama tetap hari kerja, hanya `type=holiday` yang diminta, hasil di-cache sehari, `config/hari_libur.php` tetap berlaku saat API mati, hari kerja terakhir mundur melewati libur |
| `BhpPdfDokumenTest` | Rendering PDF per dokumen + batch ZIP (satu PDF per dokumen), `safeFilename`, histori `bhp_{bos,bopd}_batch_dokumen` |
| `BhpPdfFormatTest` | **Format cetak baku**: ukuran & orientasi tiap jenis diperiksa dari `MediaBox` PDF jadi (A4 portrait/landscape, stok opname F4 210×330 mm), pemakaian kop per jenis (berkop vs tanpa kop), kop selalu membawa logo provinsi, batch ZIP mempertahankan format asli tiap dokumen |
| `BhpBukuPenerimaanTest` | Buku Penerimaan: hanya periode terpilih, tanpa filter memuat saldo awal, isolasi per sekolah, keterangan `Lunas Bayar` mengikuti bulan pembayaran, halaman + export XLSX + cetak PDF, penolakan tanpa permission, tenant lain, heading tetap ada saat kosong |
| `BhpRekonsiliasiBulananTest` | Rekonsiliasi bulanan menyambung saldo antar bulan, anomali saldo negatif ditandai, terpisah per sumber dana & sekolah, filter rentang memotong kolom tanpa memutus saldo berkesinambungan, tolak `bulan_awal > bulan_akhir`, saldo buka negatif dilaporkan, saldo awal tiap tahun dijumlah, rentang tak wajar di-clamp |
| `BhpBarangImportTest` | Import barang: buat barang + harga, nama sama menambah varian harga, duplikat nama+harga ditolak, NUSP berurutan per kode rekening, all-or-nothing, rekening tanpa `kode_barang` ditolak, harga nol/negatif ditolak, berkas tanpa baris data ditolak, harga berformat ribuan diterima, mengikuti sumber dana BOPD, unduh template, permission |
| `BhpMasterDataHalamanTest` | Halaman master data BHP (bukan hanya jalur `store`): daftar & CRUD penyedia, daftar & assign kode rekening |
| `BhpLaporanDokumenListTest` | Halaman daftar dokumen per jenis: semua jenis terbuka, nomor dokumen tampil, identitas memakai nomor Nota Permintaan (bukan UUID) |
| `BhpReviewPengajuanHttpTest` | Jalur sukses Review Pengajuan lewat HTTP: halaman review, approval dengan koreksi jumlah, penolakan |
| `BhpRouteSweepTest` | Sapu seluruh halaman GET BHP untuk setiap peran di tiga bentuk route (`/bos/`, `/bopd/`, legacy tanpa prefix): menangkap halaman 500, sumber dana salah, dan tautan 403 |
| `BhpSumberDanaPrefixTest` | Halaman berprefix `{bos\|bopd}` wajib memakai sumber dana dari URL, bukan default BOS (termasuk parameter export/cetak) |
| `KodeRekeningBhpSeederTest` | `kode_barang` pada kode rekening wajib terisi di seeder — tanpanya `NuspService` melempar `RuntimeException` dan Tambah Barang mustahil dari UI |
| `PdfPenandatanganSourceTest` | Sumber tunggal penandatangan PDF (`master_data_pegawai` + `master_data_jabatan` + `master_data_penugasan_pegawai`): label dibaca dari DB, tidak ada jabatan hard-coded di template, terisolasi per sekolah |
| `BhpLaporanTest` | `laporanStok` 4-tier (agregasi `rekapPerBarang` tanpa N+1), isolasi per sumber dana, `bukuPengeluaran`, `kartuBarang`, export XLSX stok/pengeluaran |
| `BhpLaporanPeriodeTest` | **Regresi laporan bulanan**: transaksi setelah akhir periode tidak bocor ke laporan stok, reservasi hanya dihitung sampai akhir periode, saldo awal mulai awal tahun periode, buku pengeluaran hanya bulan terpilih, kartu persediaan = saldo awal + mutasi periode + saldo akhir (sama dengan laporan stok), default bulan berjalan, nama berkas export berperiode, `bulan` di luar rentang ditolak |
| `BhpDokumenSnapshotPeriodeTest` | **Snapshot dokumen mengikuti tanggal dokumen**: stok opname bertanggal lampau memakai posisi stok tanggal itu dan tidak berubah setelah approval baru, buku pengeluaran/penyaluran bertanggal lampau hanya memuat bulan itu, kolom nilai memakai harga terakhir, referensi memakai nomor Nota Permintaan (bukan UUID), pejabat mengikuti tanggal pengajuan |
| `BhpStokAgregasiBarisTest` | **Regresi oversell multi-baris**: satu barang di beberapa baris dihitung total saat submit dan approve (6+6 dari stok 10 ditolak, 4+6 lolos), approval kedua tidak membuat dokumen ganda |
| `BhpAlurBersamaanTest` | Kontrak state di dalam transaksi: dua submit tidak oversell, submit ulang ditolak, reject setelah approve ditolak tanpa mengubah stok, approve kedua rollback penuh (status/reservasi/dokumen), `generateTambahan` idempotent, nomor dokumen unik per bulan, approval gagal tidak meninggalkan reservasi terbuka |
| `BhpDokumenIsolasiTest` | Dokumen lintas sekolah 403, dokumen BOPD tidak bisa dicetak lewat URL BOS (404), dokumen milik pengajuan lain 404 |
| `BhpKartuPersediaanTest` | Saldo berjalan kronologis, masuk-didahului pada tanggal sama, hanya mutasi dana terkait, abaikan pengajuan non-approved, pakai `jumlah_disetujui`, export + validasi `barang_id` (`uuid` + `exists` ke tabel sumber dana laporan), UUID tak dikenal → validation error, barang sumber dana lain → redirect + flash (bukan 500) |
| `BhpImportSeederTest` | Impor master + penerimaan: skip soft-delete, hanya detail `cart_status=saved`, relasi rekening, `source_id` unik (no_bast duplikat/NULL tetap terpisah), tabel tanpa `deleted_at`, idempotensi, sumber tidak ada → dilewati |
| `BhpDivisiTest` | Buat divisi + akun operator (suffix NPSN), validasi prefix/suffix/username duplikat, isolasi pengajuan per divisi, submit/divisi lain 403, **dokumen divisi lain 403 (show/PDF/batch/tambahan)**, dashboard operator divisi hanya menghitung divisinya, reset password + toggle ter-scope sekolah, **ubah/hapus divisi ter-scope sekolah (403 lintas sekolah, hapus ikut hapus operator, ditolak bila masih ada pengajuan)** |

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

### Matriks test master data & Pencatatan BM

| File | Cakupan |
|---|---|
| `MasterDataRuangPegawaiTest` | Tabel `master_data_ruang`/`master_data_pegawai` ada dan tabel lama hilang, kolom `aktif`, relasi `unitAktif`, nama ruang unik case-insensitive |
| `MasterDataPenugasanModelTest` | Skema jabatan/penugasan, kode jabatan unik per sekolah, satu pemegang terbuka per jabatan (partial unique), `scopeBerlakuPada` untuk tanggal lampau, `toSnapshot` + label Plt./Plh. |
| `PenugasanPegawaiServiceTest` | `simpan` membuat/menemukan pegawai + jabatan, auto-close H-1, overlap & tanggal terbalik ditolak, `akhiri`, `mutasi` (periode lama tertutup, pegawai tetap), prioritas definitif > plt > plh, `snapshotPenandatangan` menyebut jabatan kurang, isolasi sekolah |
| `MasterDataJabatanTest` | CRUD jabatan via HTTP, kode unik & format, jabatan terpakai dinonaktifkan bukan dihapus, isolasi sekolah 404, form pegawai tanpa field jabatan |
| `MasterDataPenugasanHttpTest` | Simpan/akhiri/mutasi via HTTP, PLT menutup definitif, overlap jadi flash error, cross-tenant 404, permission granular |
| `InventarisSnapshotPejabatTest` | Dokumen lampau memakai pejabat saat itu, dokumen baru memakai pejabat aktif, snapshot tidak berubah setelah master diedit, tanpa pejabat tetap bisa cetak, fakta integritas memakai jabatan per tanggal, kolom `jabatan`/`ruang` pegawai sudah hilang |
| `Inventaris*Test` (14 file) | Alur inventaris: sinkronisasi, mutasi/pengembalian, dokumen/QR/label/BA, ekspor, koreksi, registrasi bersamaan, tenant isolation, permission |
| `PencatatanBmHttpFlowTest` | Alur HTTP Pencatatan BM: halaman inventaris, tambah/ubah unit, validasi ruang & kondisi, mutasi |
| `MasterDataUiIntegrationTest` | Kontrak props controller ↔ halaman Inertia (controller berhenti mengirim prop yang dibaca TSX = halaman blank tanpa error PHP) |
| `NonaktifUserTest` | User dinonaktifkan tidak dapat login / memakai sesi lama |
| `CaseInsensitiveSearchTest` | Kompilasi `ILIKE` di PostgreSQL (SQLite test tidak peka huruf besar/kecil) dan larangan `LIKE` mentah di controller |
| `QueryLockSafetyTest` | Larangan `FOR UPDATE` pada query agregat (PostgreSQL menolak SQLSTATE 0A000, SQLite membuang klausa sehingga lolos suite lokal) |
| `LocaleIndonesiaTest` | Terjemahan `lang/id` untuk pesan validasi/auth/pagination yang dipakai, validator berbahasa Indonesia, kunci hilang jatuh ke fallback (bukan kunci mentah), zona waktu app mengikuti `APP_TIMEZONE` |
| `BackupNotificationConfigTest` | Kunci notifikasi `backup.notifications.notifications.*` lengkap & dibisukan lewat channel kosong, `BackupConfig::fromArray(config('backup'))` valid (kegagalan di sini = `NotificationCouldNotBeSent` pada backup yang sebenarnya sudah sukses), exclude `vendor`/`node_modules`/`.git`/`.env` |
| `BackupScheduleTest` | Jadwal backup akhir bulan (`dailyAt` + `isLastOfMonth()`): `lastDayOfMonth()` statis dan filter `when()` tidak dievaluasi `isDue()` |

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
- `APP_LOCALE` dikunci `en` di `phpunit.xml` agar suite hermetis, sedangkan
  produksi memakai terjemahan `lang/id`. Konsekuensinya: pesan Indonesia dan
  nama bulan/hari Indonesia **tidak** otomatis teruji — keduanya dikunci
  eksplisit di `LocaleIndonesiaTest` dan
  `BhpDemoDokumenBalanceTest::test_tanggal_dokumen_berbahasa_indonesia`.
- Tidak ada test yang membuka file PDF/Excel hasil unduhan secara visual;
  format diperiksa struktural (`MediaBox`, isi sel, teks HTML sumber).
