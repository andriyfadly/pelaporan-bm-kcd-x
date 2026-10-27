# Workflow Pelaporan Belanja Modal

## 1. Acuan

```text
Admin KCD memilih sekolah dan periode
        ↓
Input/import acuan
        ↓
Validasi kodering, nominal, bulan
        ↓
Acuan tersedia sebagai target realisasi
```

## 2. SPJ

```text
Operator memilih bulan
        ↓
Membuat SPK
        ↓
Menambahkan satu atau beberapa item
        ↓
Server menghitung volume × harga satuan
        ↓
SPJ tersimpan
```

Edit SPJ memperbarui snapshot data realisasi terkait tanpa mengubah relasi alokasi secara diam-diam. Penghapusan SPJ dan realisasi terkait dilakukan dalam satu transaction.

## 3. Alokasi

```text
Operator membuka periode
        ↓
Memilih kodering acuan
        ↓
Memilih item SPJ yang belum dialokasikan
        ↓
Server lock acuan dan item
        ↓
Validasi sisa nominal
        ↓
Membuat realisasi snapshot
```

## 4. Kirim dan Verifikasi

```text
Hitung total acuan dan realisasi
        ↓
Jika kekurangan = 0, operator kirim
        ↓
status_kirim = menunggu_approval
status_kunci = true
        ↓
Admin KCD verifikasi
        ↓
status_kirim = disetujui
```

Kirim ditolak bila masih ada kekurangan atau periode telah dikirim sebelumnya.

## 5. State Rules

| State | Operator | Admin KCD | Super Admin |
|---|---|---|---|
| `draft` | edit | lihat/edit sesuai permission | edit |
| `menunggu_approval` | read-only | verifikasi/kembalikan | sesuai permission |
| `disetujui` | read-only | read-only atau buka edit | koreksi khusus |
| `status_kunci=true` | read-only | buka kunci | bypass sesuai policy |
