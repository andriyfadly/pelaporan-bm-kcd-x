---
paths:
  - 'app/Http/Controllers/PelaporanBm/**'
  - app/Http/Controllers/PelaporanBm/RekapanController.php
---

# Pelaporan Bm

## Wajib resolveSekolahId untuk scope tenant
Selalu panggil resolveSekolahId($request) di awal aksi lintas-sekolah (index/unduh/check) dan pakai hasilnya untuk scopee query. Jangan pakai $user->sekolah_id langsung: operator_sekolah/bendahara_sekolah tanpa sekolah_id akan lolos ke jalur "lihat semua sekolah" (admin). resolveSekolahId abort 403 untuk user sekolah tanpa sekolah_id dan memaksa ke sekolahnya.

## Dashboard admin: paritas legacy, tanpa fallback target
Dashboard admin monitoring wajib paritas legacy/index_admin.php: target = sekolah dengan acuan di bulan+tahun terpilih (TANPA fallback ke semua sekolah saat kosong), selesai hanya dari Realisasi final (bukan Spj draft), keduanya difilter tahun via whereYear + orWhereNull tanggal. Test penjaga: DashboardAdminParityTest.

## Rekapan admin: paritas legacy, realisasi dari alokasi acuan
Paritas legacy/rekapan_admin.php: baris tabel = sekolah dengan acuan di bulan terpilih (tanpa fallback semua sekolah); realisasi/log fisik hanya dari baris Realisasi yang dialokasikan ke acuan (acuan_id, paritas id_uraian), SPJ mentah tidak dihitung; badge SESUAI = kekurangan <= 0. Test penjaga: RekapanEdgeTest::test_parity_legacy_baris_dari_acuan_dan_realisasi_dari_alokasi.
