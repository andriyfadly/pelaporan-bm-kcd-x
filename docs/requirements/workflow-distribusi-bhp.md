# Workflow Distribusi BHP

## 1. Penerimaan

```text
Admin memilih BOS/BOPD
        ↓
Input dokumen penerimaan
        ↓
Tambah detail barang dan jumlah
        ↓
Validasi barang/rekening/sumber dana
        ↓
Commit penerimaan dan update stok fisik
```

## 2. Pengajuan

```text
User divisi mengisi form pengajuan
        ↓
Isian disimpan otomatis di perangkat (localStorage), belum menyentuh server
        ↓
Memilih sumber dana, barang, dan jumlah
        ↓
Klik "Ajukan Pengajuan" → konfirmasi
        ↓
Validasi stok tersedia, buat pengajuan + reservasi dalam satu transaksi
        ↓
Reservasi stok terbentuk (status submitted)
```

Catatan: status `draft` di database hanya sisa alur lama. Isian yang belum
diajukan tidak pernah menjadi baris database, dan draft tidak ditampilkan di
daftar pengajuan. Bila reservasi gagal (stok kurang), seluruh transaksi batal
sehingga tidak ada record menggantung.

## 3. Approval

```text
Admin membuka pengajuan submitted
        ↓
Review item dan jumlah
        ↓
Approve penuh / koreksi / reject
        ↓
Transaction lock
        ↓
Reservasi dikurangi
Pemakaian ditambah
Status diperbarui
Dokumen dibuat
```

## 4. State Machine

| State | Aksi valid |
|---|---|
| `draft` | sisa alur lama: hanya transien di dalam transaksi ajukan; tidak tampil di daftar |
| `submitted` | review, approve, reject; tidak boleh diedit pemohon |
| `approved` | lihat, cetak; tidak boleh approve ulang |
| `rejected` | lihat alasan; buat pengajuan baru atau revisi sesuai policy |

## 5. Transaction Contract

Approval harus menjalankan urutan berikut dalam satu transaction:

1. Lock pengajuan.
2. Pastikan status masih `submitted`.
3. Lock seluruh barang terkait.
4. Hitung ulang stok fisik, terpakai, reservasi, dan tersedia.
5. Validasi jumlah approved.
6. Update detail approved.
7. Konversi reservasi menjadi pemakaian.
8. Buat nomor dokumen (lock row sequence; insert row pertama anti-race via
   unique constraint + retry).
9. Buat dokumen distribusi.
10. Catat audit log.

Jika satu langkah gagal, seluruh langkah rollback.

## 6. Isolasi Scope (tambahan, hasil audit 2026-10-08)

- Snapshot payload dokumen tambahan (`stok_opname`, `buku_pengeluaran`,
  `buku_penyaluran`) selalu difilter `sekolah_id` milik pengajuan — dokumen
  sekolah A tidak menyimpan stok/pengeluaran sekolah B.
- Operator divisi hanya dapat mengakses dokumen (lihat, PDF, batch, tambahan)
  untuk pengajuan divisinya sendiri.
- Statistik dashboard operator divisi difilter `divisi_id`.
