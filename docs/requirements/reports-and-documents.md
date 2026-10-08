# Requirements Laporan dan Dokumen

## 1. Pelaporan BM

- Dashboard status pelaporan per bulan.
- Rekapan acuan vs realisasi per sekolah dan kodering.
- Data realisasi dengan filter barang, bulan, dan tahun.
- Export realisasi 25 kolom A-Y.
- Sheet `KODE BARANG` untuk lookup aset.
- Cetak BM 26 kolom A-Z dengan identitas sekolah/kota.
- Pre-flight check sebelum export.
- Empty result tidak menghasilkan file kosong yang menyesatkan.

## 2. Distribusi BHP

Status: L = laporan, D = dokumen.

| Item | Status |
|---|---|
| Daftar saldo awal | ✅ Sudah (flag `is_saldo_awal` pada penerimaan) |
| Buku penerimaan | ✅ Sudah |
| Nota permintaan | ✅ Sudah (dokumen auto saat approve) |
| Surat permintaan | ✅ Sudah (dokumen auto) |
| Surat perintah penyaluran | ✅ Sudah (dokumen auto) |
| BAST | ✅ Sudah (dokumen tambahan) |
| BAP | ✅ Sudah (dokumen tambahan) |
| Stok opname | ✅ Sudah (dokumen tambahan) |
| Buku pengeluaran | ✅ Sudah (laporan + dokumen) |
| Buku penyaluran | 🟡 Sebagian (dokumen ada; laporan penyaluran khusus belum) |
| Kartu barang | ✅ Sudah (runtime, tanpa tabel) |
| Kartu persediaan | ✅ Sudah (runtime dengan saldo berjalan + export) |
| Batch PDF dan histori batch | ✅ Sudah (`bhp_batch_dokumen`) |

## 3. Numbering

### BM

Nomor SPK/BA dan format laporan mengikuti kebutuhan Pelaporan BM serta data existing target.

### BHP

Format dan sequence dokumen disimpan pada konfigurasi BHP. Generator harus:

- atomic terhadap concurrent request.
- memiliki context periode/sumber dana/divisi bila format memerlukannya.
- menolak placeholder tidak dikenal atau mendokumentasikan fallback.
- mengembalikan nomor yang sama saat retry idempotent untuk approval yang sama.

## 4. PDF

- Template BHP tidak boleh digunakan pada laporan BM.
- Data tanggal dan penandatangan berasal dari snapshot pejabat sekolah pada
  payload dokumen (bukan pejabat aktif saat cetak), sehingga reprint dokumen
  lama tidak berubah ketika pejabat berganti.
- PDF dapat dibuka/diunduh tanpa membocorkan tenant lain.
- Batch generation menyimpan status per file dan error message.
