---
paths:
  - 'app/Http/Controllers/PelaporanBm/**'
---

# Pelaporan Bm

## Wajib resolveSekolahId untuk scope tenant
Selalu panggil resolveSekolahId($request) di awal aksi lintas-sekolah (index/unduh/check) dan pakai hasilnya untuk scopee query. Jangan pakai $user->sekolah_id langsung: operator_sekolah/bendahara_sekolah tanpa sekolah_id akan lolos ke jalur "lihat semua sekolah" (admin). resolveSekolahId abort 403 untuk user sekolah tanpa sekolah_id dan memaksa ke sekolahnya.

## Dashboard admin: paritas legacy, tanpa fallback target
Dashboard admin monitoring wajib paritas legacy/index_admin.php: target = sekolah dengan acuan di bulan+tahun terpilih (TANPA fallback ke semua sekolah saat kosong), selesai hanya dari Realisasi final (bukan Spj draft), keduanya difilter tahun via whereYear + orWhereNull tanggal. Test penjaga: DashboardAdminParityTest.
