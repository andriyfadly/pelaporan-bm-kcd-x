# PRD Pelaporan Belanja Modal

## 1. Tujuan Produk

Sistem menyediakan pencatatan dan rekonsiliasi belanja modal sekolah dari target acuan sampai realisasi fisik, dengan kontrol tenant, penguncian laporan, audit trail, dan output Excel yang konsisten dengan format legacy.

## 2. Persona

| Role | Kebutuhan |
|---|---|
| `super_admin` | Akses penuh, koreksi khusus, migrasi, dan seluruh audit log |
| `admin_kcd` | Kelola master/acuan, pantau sekolah, verifikasi, kunci laporan |
| `operator_sekolah` | Input SPJ, alokasi realisasi, kirim laporan sekolah sendiri |
| `bendahara_sekolah` | Operasional BM sesuai permission dan sekolah sendiri |

## 3. Fitur

### Dashboard

- Filter bulan dan tahun.
- Target sekolah berdasarkan keberadaan acuan pada periode.
- Rekap selesai dan belum realisasi.
- Ringkasan sekolah: acuan, realisasi, aset fisik, dan berkas SPK.
- Aksi kunci/buka laporan untuk admin.

### Master kode barang

- CRUD katalog kode barang aset.
- Import massal dengan validasi dan batas ukuran.
- Field: kode, uraian, kodering aset, jenis aset, umur ekonomis, satuan, harga standar.
- Kode barang unik.

### Acuan

- Input manual.
- Import XLSX.
- Filter sekolah, bulan, dan tahun.
- Field: sekolah, tanggal, kodering, BKU, uraian, nominal, bulan.
- Hapus massal harus tercatat dan tidak boleh menghapus relasi yang masih dipakai tanpa konfirmasi/guard.

### SPJ/SPK

- Pilih bulan.
- Katalog dokumen berdasarkan nomor SPK.
- Satu SPK memiliki banyak item.
- Field dokumen: SP2D, sumber perolehan, SPK, BA, tanggal BA, bulan realisasi.
- Field item: kode/nama barang, jenis aset, merk/tipe, sertifikat, ukuran, satuan, volume, harga satuan, nilai perolehan.
- Nilai perolehan dihitung server-side.
- Import Excel all-or-nothing.
- Edit/hapus diblokir saat periode terkunci atau sudah dikirim.

### Input realisasi

- Rekap acuan per kodering.
- Tampilkan nominal acuan, total realisasi, dan kekurangan.
- Alokasikan item SPJ ke acuan.
- Tolak alokasi melebihi sisa acuan.
- Satu item tidak boleh dialokasikan ganda pada konteks yang sama.
- Snapshot realisasi menyimpan data barang dan relasi SPJ/acuan.

### Pengiriman dan verifikasi

- Kirim hanya jika total realisasi memenuhi total acuan.
- Kirim mengubah status menjadi `menunggu_approval` dan mengunci periode.
- Admin KCD dapat memverifikasi dan mengubah menjadi `disetujui`.
- Reopen mengubah status menjadi `draft` sesuai permission.
- `super_admin` dapat melakukan koreksi khusus sesuai aturan security.

### Realisasi dan export

- Tabel realisasi dengan filter barang, bulan, tahun.
- Export 25 kolom plus sheet `KODE BARANG`.
- Cetak BM 26 kolom.
- Tidak ada data menghasilkan response kosong yang aman, bukan file rusak.

## 4. Kriteria Penerimaan

- Operator lintas sekolah menerima `403`.
- Acuan lintas sekolah tidak dapat dipakai untuk SPJ.
- Nilai perolehan selalu dihitung ulang server-side.
- Overspending ditolak dengan nominal kekurangan/sisa.
- Laporan kurang tidak dapat dikirim.
- Laporan terkunci tidak dapat dimutasi oleh role biasa.
- Export memenuhi header, formula, dan urutan yang ditentukan.
- Setiap perubahan penting masuk activity log tanpa password/token.
