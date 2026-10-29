# PRD Distribusi BHP

## 1. Tujuan Produk

Sistem mengelola barang habis pakai sekolah dari penerimaan/saldo awal sampai pengajuan divisi, persetujuan admin, pengurangan stok, pembuatan dokumen distribusi, dan laporan. Domain ini berdampingan dengan Pelaporan BM dan tidak memakai transaksi BM.

## 2. Sumber Dana

Sumber dana BHP pada fase awal:

- `BOS`
- `BOPD`

Semua master dan transaksi BHP wajib memiliki sumber dana. Item dengan sumber dana berbeda tidak boleh berada dalam satu transaksi pengajuan/penerimaan.

## 3. Master Data

- Kode rekening BHP, unik per sumber dana.
- Barang BHP: kode, nama, spesifikasi/NUSP, satuan, rekening, sumber dana.
- Harga barang: satu barang dapat memiliki beberapa harga historis.
- Penyedia.
- Divisi.
- Keperluan.
- Koordinator.
- Penandatangan dokumen: master pejabat sekolah per jabatan
  (kepala sekolah, wakasek sarpras, pembantu pengurus barang) dengan nama,
  pangkat/golongan, NIP, jenis jabatan (definitif/plt/plh), dan riwayat masa
  jabatan. Approval pengajuan mensyaratkan ketiga jabatan terisi dan berlaku
  pada tanggal pengajuan; snapshot pejabat tersimpan pada payload dokumen
  sehingga reprint tidak berubah saat pejabat berganti.

## 4. Penerimaan dan Saldo Awal

- Buku penerimaan dengan invoice, BAST, kwitansi, penyedia, tanggal, dan sumber dana.
- Detail barang masuk: barang, jumlah, harga, nilai total, merk/tipe, keterangan.
- Saldo awal per barang, periode, dan sumber dana.
- Import saldo awal all-or-nothing dengan validasi baris.
- Manajemen status bayar/lunas bila diperlukan operasional.
- Pembuatan dokumen BAP dan BAST internal.

## 5. Stok

```text
Fisik     = total penerimaan + saldo awal
Terpakai  = total pengajuan approved yang telah disalurkan
Reservasi = total pengajuan submitted yang belum selesai
Tersedia  = max(0, Fisik - Terpakai - Reservasi)
```

Semua perubahan stok harus berada dalam database transaction dan menggunakan row lock pada barang/detail yang relevan.

## 6. Pengajuan Divisi

- User divisi mengisi form pengajuan (isian disimpan otomatis di perangkat).
- Sistem mengisi identitas pemohon dari akun/jabatan aktif.
- Pemohon memilih sumber dana.
- Pemohon menambahkan barang dan jumlah.
- Sistem menampilkan stok tersedia.
- Jumlah tidak boleh nol/negatif atau melebihi stok tersedia.
- Tombol ajukan langsung membuat reservasi; tidak ada draft tersimpan di server.
- Submit mengubah status menjadi `submitted` dan membuat reservasi.
- Pengajuan submitted tidak dapat diedit pemohon.

## 7. Review dan Approval

- Admin melihat daftar submitted berdasarkan sumber dana/periode/divisi.
- Admin dapat menyetujui penuh, mengoreksi jumlah, atau menolak.
- Rejection wajib memiliki alasan.
- Approval satu kali harus atomic: status, jumlah disetujui, reservasi, pemakaian, dan dokumen.
- Approval ulang ditolak.
- Jika approval gagal, seluruh perubahan rollback.

## 8. Dokumen

Satu approval menghasilkan dokumen terkait sesuai kebutuhan transaksi:

- Nota Permintaan Barang.
- Surat Permintaan Barang.
- Surat Perintah Penyaluran Barang.
- Berita Acara Serah Terima.
- Berita Acara Pemeriksaan.
- Stok Opname.
- Buku Pengeluaran Barang.
- Buku Penyaluran.
- Kartu Barang.
- Kartu Persediaan Barang.
- BAST Internal.

Nomor dan tanggal dokumen harus konsisten dalam satu approval. Format nomor configurable melalui settings BHP dan sequence tidak boleh duplikat.

## 9. Kriteria Penerimaan

- BOS dan BOPD terisolasi pada master, stok, pengajuan, dan laporan.
- Stok tidak pernah negatif pada concurrent approval.
- Rejected/cancelled mengembalikan reservasi.
- Approved mengubah reservasi menjadi pemakaian sesuai jumlah approved.
- Koreksi jumlah approval tidak menghapus histori jumlah diminta.
- Dokumen tidak dibuat dua kali untuk approval yang sama.
- User divisi hanya melihat divisi/pengajuan yang menjadi kewenangannya.
- Admin tidak dapat memproses sekolah lain tanpa permission/tenant scope.
