# Shared Platform Requirements

## 1. Authentication

- Login username.
- Session Laravel/Fortify.
- Password hash bcrypt.
- Password minimum 8 karakter dengan huruf besar, kecil, angka, dan simbol.
- Rotasi password operator/bendahara setiap 90 hari.
- Akun nonaktif tidak dapat login dan session aktifnya diputus.
- 2FA/passkey tetap menjadi kemampuan platform yang dapat diaktifkan.

## 2. Tenant Sekolah

- User sekolah selalu memiliki `sekolah_id`.
- User KCD/super admin dapat memiliki akses lintas sekolah sesuai permission.
- Semua query BM dan BHP harus menerapkan tenant scope.
- ID yang diterima dari request tidak boleh digunakan tanpa pemeriksaan ownership.
- Operator tanpa sekolah ditolak, bukan diperlakukan sebagai admin.

## 3. User, Role, Permission

Role platform:

- `super_admin`
- `admin_kcd`
- `operator_sekolah`
- `bendahara_sekolah`
- `admin_sekolah`/operator BHP sesuai keputusan final role matrix.

Permission menggunakan prefix domain, misalnya:

```text
lihat-pelaporan-bm
kelola-acuan-bm
kelola-spj-bm
kelola-realisasi-bm
kunci-laporan-bm
lihat-distribusi-bhp
kelola-master-bhp
kelola-penerimaan-bhp
kelola-pengajuan-bhp
review-pengajuan-bhp
approve-pengajuan-bhp
cetak-dokumen-bhp
```

## 4. Settings

### Shared

- NPSN.
- Nama sekolah.
- Alamat, telepon, website, email, kode pos.
- Kabupaten, kecamatan, provinsi.
- Pengguna barang dan kuasa pengguna barang.
- Jabatan sekolah.

### BHP-specific

- Format nomor dokumen BHP.
- Sequence dokumen BHP.
- Preferensi PDF BHP.

Settings BHP tidak boleh menjadi sumber penomoran SPJ BM.

## 5. Audit Log

Log minimal untuk:

- Login/logout.
- Perubahan user/role.
- CRUD master.
- Import.
- Perubahan acuan/SPJ/realisasi.
- Kirim/verifikasi/kunci laporan BM.
- Submit/approve/reject pengajuan BHP.
- Perubahan stok.
- Generate/cetak/unduh dokumen.

Password, token, dan secret tidak boleh masuk log.
