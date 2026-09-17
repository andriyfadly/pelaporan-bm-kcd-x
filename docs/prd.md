# Product Requirements Document (PRD): SI DIPTA Beu! — Pelaporan Belanja Modal KCD

> Status: menggambarkan implementasi berjalan (branch `feat/migrasi-laravel-12`).
> Stack rujukan: `docs/architecture.md`. Skema data: `docs/database.md`.

## 1. Latar Belakang & Masalah

Sekolah negeri (SMAN/SMKN/SLBN) di Cabang Dinas Pendidikan Wilayah X mengelola belanja
modal tahunan berbasis kodering rekening. Proses manual/parsial menimbulkan:

1. Selisih target acuan vs realisasi fisik per kodering yang terlambat ketahuan.
2. Keterlambatan pelaporan bulanan dan format rekap tidak seragam saat konsolidasi dinas.
3. Jejak audit lemah — siapa mengubah apa dan kapan tidak terlacak.

Sistem ini memigrasi aplikasi legacy PHP prosedural (`legacy/`) ke Laravel +
Inertia React, menyamakan output Excel legacy (25/26 kolom + sheet `KODE BARANG`),
dan menambahkan kontrol kunci laporan + log aktivitas sistem-wide.

## 2. Tujuan Produk

| ID | Tujuan | Indikator |
|----|--------|-----------|
| G1 | Sentralisasi SPJ & fisik barang | 1 dokumen SPK = N item barang, tersimpan atomik |
| G2 | Kesesuaian acuan vs realisasi | Per kodering terpantau: target, realisasi, kekurangan (real-time) |
| G3 | Standardisasi cetak | XLSX 25 kolom (realisasi) & 26 kolom (cetak) identik legacy, formula hidup |
| G4 | Akuntabilitas & kontrol | Kunci bulanan + log aktivitas queryable (super_admin & admin_kcd) |
| G5 | Migrasi aman dari legacy | Perintah `app:migrasi-data-lama` idempoten, terverifikasi test |

## 3. Persona & Peran

| Role | Identitas | Kebutuhan utama |
|------|-----------|-----------------|
| `super_admin` (`developer`) | Tanpa `sekolah_id`, semua permission | Operasional penuh + melihat log aktivitas seluruh sistem || `admin_kcd` | Tanpa `sekolah_id` | Upload acuan, pantau kepatuhan, verifikasi/kunci, kelola master & user |
| `operator_sekolah` (`[npsn]-admin`) | Terikat `sekolah_id` | Input SPK/barang, alokasi realisasi, kirim laporan, cetak |
| `bendahara_sekolah` | Terikat `sekolah_id` | Sama seperti operator (hak setara saat ini) |

Kredensial default (wajib diganti, lihat `docs/setup.md`):
operator `#SidiptaKCD10`, admin `#SidiptaBeuKCD10`. Rotasi password 90 hari.

## 4. Ruang Lingkup Fitur

### F1 — Dashboard peran (`/dashboard`)
- Admin: total target wilayah, total realisasi, sisa anggaran, tabel sekolah
  selesai vs belum lapor, tombol kunci instan.
- Sekolah: banner status bulan lalu, 4 kartu (target berjalan, realisasi,
  aset fisik, berkas SPK), riwayat 12 bulan.

### F2 — Buku SPJ & Data Barang (`/pelaporan-bm/spj`)
- Katalog dokumen per nomor SPK, terbaru dulu, live search.
- Form SPK multi-item (seksi dokumen + accordion item), kategori
  `Peralatan & Mesin` / `Buku`, draft autosave localStorage, datalist history.
- Pencarian katalog `/pelaporan-bm/cari-barang`: lowercase, escape wildcard,
  hanya kode leaf, limit 100 (replika `legacy/ajax_cari_barang.php`).
- Edit SPK ikut sinkron snapshot realisasi teralokasi (kecuali
  `kodering_belanja`/`acuan_id`/bulan alokasi). Hapus item/SPK hapus realisasi
  terkait dalam transaksi.
- Blokir bila bulan terkunci / `menunggu_approval` / `disetujui`.

### F3 — Input Realisasi (`/pelaporan-bm/input-realisasi`)
- Rekap per kodering: nominal acuan, realisasi, kekurangan.
- Alokasi item SPJ ke kodering (tombol `+`), dibatasi sisa anggaran kodering.
- Edit alokasi (uncheck). `Kirim Laporan` aktif hanya bila kekurangan = 0
  (validasi balance server-side) → `menunggu_approval` + kunci otomatis.

### F4 — Data Realisasi (`/pelaporan-bm/realisasi`)
- Tabel 19 kolom dari `pelaporan_bm_realisasi`, `ba_tgl DESC, id DESC`,
  paginasi 25, filter barang/bulan/tahun, kartu total tersaring.
- Unduh XLSX 25 kolom A–Y + sheet `KODE BARANG` (formula VLOOKUP/nilai/
  penyusutan). Data kosong → `204` + cookie `download_status=empty`.

### F5 — Cetak Laporan (`/pelaporan-bm/cetak`)
- Pre-flight `POST /pelaporan-bm/cetak/check` mencegah unduh kosong.
- Unduh XLSX 26 kolom A–Z (+ Kab/Kota), judul 3 baris, header biru muda,
  gridlines, auto-width, formula dinamis. Legacy: `legacy/proses_unduh_bm.php`.

### F6 — Pengawasan & Penguncian (admin)
- Rekapan (`/pelaporan-bm/rekapan`): acuan vs realisasi seluruh sekolah/bulan.
- Toggle kunci + update status (`draft`/`menunggu_approval`/`disetujui`).

### F7 — Log Aktivitas (`/admin/log-aktivitas`, super_admin + admin_kcd)
- Auto-log model (spatie/laravel-activitylog v5, log `sistem`): Spj,
  Realisasi, Acuan, KunciLaporan, KodeBarang, User (password/token dikecualikan).
- Manual: login/logout, ganti/reset password, kirim/verifikasi/kunci,
  import & hapus-massal, 3 unduhan, hapus SPK/prune/sinkron.
- Viewer: filter event/entitas/sekolah/tanggal/pencarian, diff before/after.
- Aktivitas super_admin disembunyikan dari admin_kcd.
- Detail: `docs/architecture.md` (bagian Logging) dan `docs/security.md`.

### F8 — Master Data (admin)
- Kode Barang (`/admin/kode-barang`): CRUD + import (maks 20.000 baris).
- Acuan (`/pelaporan-bm/acuan`): input manual + import XLSX-only (maks 5.000
  baris), filter bulan/sekolah, hapus massal.
- Sekolah: identitas + NPSN (dikelola via seeder/migrasi).
- User (`/admin/user`): CRUD + assign role, nonaktifkan/aktifkan user (sesi aktif diputus), proteksi hapus diri & super_admin.

### F9 — Autentikasi & keamanan akun
- Login username (tanpa email), Fortify session, bcrypt.
- Rotasi password 90 hari via `EnsurePasswordNotExpired` → `/ubah-password`.
- Kompleksitas: min 8, huruf besar/kecil, angka, simbol.
- Cloudflare Turnstile di login (opsional via env). 2FA (TOTP) + passkeys
  tersedia dari Fortify.

## 5. Kriteria Penerimaan

### Fungsional (ringkas)
- [ ] Operator hanya baca/tulis sekolahnya sendiri (403 lintas tenant).
- [ ] Alokasi melebihi sisa anggaran ditolak dengan pesan nominal.
- [ ] Kirim laporan ditolak bila total realisasi < total acuan.
- [ ] Mutasi bulan terkunci/dikirim ditolak.
- [ ] Unduh kosong → 204 + cookie `empty`, tanpa file.
- [ ] File XLSX: header `A8=No`, data dari `A10`, VLOOKUP range dinamis,
      label kota format `KABUPATEN/KOTA X`.
- [ ] Setiap mutasi tercatat di `activity_log` (log `sistem`); password tak bocor.
- [ ] Viewer log: super_admin 200, admin_kcd/operator 403.

### Non-fungsional
- Keamanan: lihat `docs/security.md` (RBAC matrix, throttle, validasi).
- Kualitas: Pint lolos, `tsc --noEmit` 0 error, PHPUnit coverage 100% (semua
  file) via `composer test-coverage`, 179 test / 930 assertions saat dokumen
  diperbarui.
- Dokumentasi: relative path saja (aturan `docs/guidelines.md`).

## 6. Di Luar Ruang Lingkup

- Aplikasi mobile native; notifikasi real-time (email/WA/push).
- Akuntansi ganda / integrasi SIPD/ARKAS; tanda tangan elektronik.
- Retensi/purge otomatis log (disimpan permanen hingga diputuskan lain).

## 7. Risiko & Mitigasi

| Risiko | Mitigasi |
|--------|----------|
| Template Excel berubah sepihak | Test baca-balik cell (A8/A10/VLOOKUP/SUM) di suite export |
| Import raksasa memperlambat DB | Batas 5.000/20.000 baris + transaksi + throttle |
| Kunci manual lupa dibuka | Toggle + status `draft` oleh admin, tercatat di log |
| Kredensial default bocor | Wajib ganti perdana (middleware), kompleksitas, 2FA opsional |

## 8. Lampiran

- Arsitektur: `docs/architecture.md` · Skema: `docs/database.md`
- Endpoint: `docs/api.md` · Modul per role: `docs/modules.md`
- Desain UI: `docs/design.md` · Keamanan: `docs/security.md`
- Testing: `docs/testing.md` · Deploy: `docs/deployment.md`
- Istilah: `docs/glossary.md` · Instalasi: `docs/setup.md`
