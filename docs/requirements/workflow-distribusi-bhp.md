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
User divisi membuat draft
        ↓
Memilih sumber dana
        ↓
Memilih barang dan jumlah
        ↓
Validasi stok tersedia
        ↓
Submit
        ↓
Reservasi stok
```

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
| `draft` | edit, submit, delete oleh pemilik berwenang |
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
8. Buat nomor dokumen.
9. Buat dokumen distribusi.
10. Catat audit log.

Jika satu langkah gagal, seluruh langkah rollback.
