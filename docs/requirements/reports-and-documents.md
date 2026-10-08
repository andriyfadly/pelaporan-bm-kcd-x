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

- Daftar saldo awal.
- Buku penerimaan.
- Nota permintaan.
- Surat permintaan.
- Surat perintah penyaluran.
- BAST.
- BAP.
- Stok opname.
- Buku pengeluaran.
- Buku penyaluran.
- Kartu barang.
- Kartu persediaan.
- Batch PDF dan histori batch.

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
- Data tanggal dan penandatangan berasal dari domain/config yang sesuai.
- PDF dapat dibuka/diunduh tanpa membocorkan tenant lain.
- Batch generation menyimpan status per file dan error message.
