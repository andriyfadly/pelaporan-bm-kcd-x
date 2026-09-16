---
paths:
  - 'app/Http/Controllers/PelaporanBm/**'
---

# Pelaporan Bm

## Wajib resolveSekolahId untuk scope tenant
Selalu panggil resolveSekolahId($request) di awal aksi lintas-sekolah (index/unduh/check) dan pakai hasilnya untuk scopee query. Jangan pakai $user->sekolah_id langsung: operator_sekolah/bendahara_sekolah tanpa sekolah_id akan lolos ke jalur "lihat semua sekolah" (admin). resolveSekolahId abort 403 untuk user sekolah tanpa sekolah_id dan memaksa ke sekolahnya.
