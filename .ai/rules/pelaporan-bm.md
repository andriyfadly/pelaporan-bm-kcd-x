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

## lockForUpdate tidak boleh digabung agregat (PostgreSQL 0A000)
PostgreSQL menolak `select sum(...) ... for update` (SQLSTATE 0A000) dan SQLite membuang klausa lock, jadi bug ini lolos seluruh suite lokal lalu pecah di produksi. Untuk hitung-terkunci, ambil barisnya dulu (`->lockForUpdate()->get(['nilai_perolehan'])`) lalu jumlahkan di PHP. Produksi sempat 500 di InputRealisasiController::simpan karena pola agregat+lock. Verifikasi perubahan query ber-lock: jalankan suite dengan `DB_CONNECTION=pgsql` ke DB lokal kosong (SQLite tidak akan menangkapnya).
