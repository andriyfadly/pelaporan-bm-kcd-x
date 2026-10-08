# RBAC Matrix BM dan BHP

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
