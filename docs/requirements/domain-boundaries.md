# Batas Domain BM dan BHP

## 1. Rule Utama

Belanja Modal dan Distribusi BHP adalah domain berbeda. Keduanya boleh berada dalam satu aplikasi, tetapi tidak boleh dicampurkan pada transaksi, status, stok, atau laporan.

## 2. Matriks Pemisahan

| Area | Pelaporan BM | Distribusi BHP |
|---|---|---|
| Tujuan | Pelaporan perolehan aset/belanja modal | Distribusi barang habis pakai |
| Entitas utama | Acuan, SPJ, Realisasi | Barang, Penerimaan, Pengajuan |
| Workflow | Input SPJ → alokasi → kirim → verifikasi | Penerimaan → stok → pengajuan → approval → distribusi |
| Nilai | Acuan per kodering dan nilai perolehan | Nilai item, stok, dan harga BHP |
| Status | draft, menunggu_approval, disetujui | draft, submitted, approved, rejected |
| Output | Excel 25/26 kolom dan rekapan BM | Dokumen distribusi dan laporan stok |
| Kunci | Kunci laporan sekolah/bulan | Lock transaksi dan konsistensi stok |

## 3. Shared Yang Diizinkan

- User, sekolah, session, role, permission framework.
- Identitas sekolah dan alamat umum.
- Jabatan sekolah dan koordinator bila diperlukan bersama.
- Activity log infrastructure.
- File storage, upload security, error handling, layout UI.

## 4. Yang Wajib Terpisah

- Model dan tabel transaksi.
- Status transition.
- Perhitungan nilai dan stok.
- Controller dan service domain.
- Route dan permission transaksi.
- Export dan template dokumen.
- Acceptance test.

## 5. Naming Boundary

```text
App\Models\PelaporanBm\*
App\Models\DistribusiBhp\*
App\Http\Controllers\PelaporanBm\*
App\Http\Controllers\DistribusiBhp\*
resources/js/Pages/PelaporanBm/*
resources/js/Pages/DistribusiBhp/*
```

Route prefix:

```text
/pelaporan-bm/*
/distribusi-bhp/*
```

## 6. Larangan Implementasi

- Jangan menambahkan `stok_tereservasi` ke model BM.
- Jangan memakai `Realisasi` BM untuk mengurangi stok BHP.
- Jangan memakai `Acuan` BM sebagai pagu pengajuan BHP.
- Jangan memakai nomor dokumen BHP untuk SPJ BM.
- Jangan memakai `status_kirim` BM sebagai status pengajuan BHP.
