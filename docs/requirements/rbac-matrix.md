# RBAC Matrix BM dan BHP

> **Kolom aktual:** `## Implementasi Saat Ini` di bawah memuat permission dan
> role yang benar-benar terpasang (`PeranDanHakAksesSeeder`). Tabel di atasnya
> adalah target/rencana.

| Kemampuan | Super Admin | Admin KCD | Operator/Bendahara Sekolah | User Divisi BHP |
|---|:---:|:---:|:---:|:---:|
| Lihat dashboard BM | Ya | Ya | Sekolah sendiri | Sesuai akses |
| Kelola acuan BM | Ya | Ya | Tidak | Tidak |
| Kelola SPJ BM | Ya | Ya | Sekolah sendiri | Tidak |
| Alokasi realisasi BM | Ya | Sesuai permission | Sekolah sendiri | Tidak |
| Kirim laporan BM | Ya | Sesuai permission | Sekolah sendiri | Tidak |
| Verifikasi/kunci BM | Ya | Ya | Tidak | Tidak |
| Kelola master BHP | Ya | Ya | Sesuai permission | Tidak |
| Kelola penerimaan BHP | Ya | Ya | Sesuai permission | Tidak |
| Buat pengajuan BHP | Ya | Ya | Tidak/opsional | Divisi sendiri |
| Review pengajuan BHP | Ya | Ya | Tidak | Tidak |
| Approve pengajuan BHP | Ya | Ya | Tidak | Tidak |
| Cetak dokumen BHP | Ya | Ya | Sesuai akses | Sesuai akses |
| Lihat audit log | Ya | Terbatas sesuai policy | Tidak | Tidak |

## Guard Wajib

- Permission UI bukan pengganti authorization backend.
- Semua mutation memeriksa role, permission, dan tenant.
- User sekolah tidak boleh mengirim `sekolah_id` milik sekolah lain untuk mengubah scope query.
- Permission BM tidak otomatis memberi permission BHP, dan sebaliknya.

## Implementasi Saat Ini

**5 role:** `super_admin`, `admin_kcd`, `operator_sekolah`,
`bendahara_sekolah`, `operator_divisi`.

**17 permission** (10 BM + 7 BHP):

```text
BM : lihat-laporan-bm, input-spj-bm, edit-spj-bm, hapus-spj-bm,
     verifikasi-laporan-bm, kunci-laporan-bm, unduh-rekap-bm,
     cetak-laporan-bm, kelola-user, lihat-log-aktivitas
BHP: lihat-distribusi-bhp, kelola-master-bhp, kelola-penerimaan-bhp,
     kelola-pengajuan-bhp, review-pengajuan-bhp, approve-pengajuan-bhp,
     cetak-dokumen-bhp
```

Pemetaan role → permission BHP:

| Permission BHP | super_admin | admin_kcd | operator_sekolah | bendahara_sekolah | operator_divisi |
|---|:---:|:---:|:---:|:---:|:---:|
| `lihat-distribusi-bhp` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `kelola-master-bhp` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `kelola-penerimaan-bhp` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `kelola-pengajuan-bhp` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `review-pengajuan-bhp` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `approve-pengajuan-bhp` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `cetak-dokumen-bhp` | ✅ | ✅ | ✅ | ❌ | ❌ |

Catatan: matriks target di atas mengasumsikan pemisahan review/approve dan
pembatasan operator pada master; implementasi sekarang memberi operator_sekolah
permission BHP penuh (setara admin) untuk memudahkan operasional sekolah.
Tinjau ulang sebelum go-live bila ingin pengetatan.
