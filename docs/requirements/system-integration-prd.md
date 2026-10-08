# PRD Integrasi Pelaporan BM dan Distribusi BHP

## 1. Ringkasan

Project `pelaporan-bm-kcd-x` menjadi satu aplikasi untuk dua domain operasional:

1. **Pelaporan Belanja Modal (BM)**: pengelolaan acuan, SPJ/SPK, realisasi, rekonsiliasi, penguncian laporan, dan cetak laporan belanja modal.
2. **Distribusi BHP**: pengelolaan barang habis pakai, penerimaan, stok, pengajuan divisi, persetujuan, dokumen distribusi, dan laporan BHP.

Kedua domain hidup dalam satu aplikasi dan berbagi fondasi teknis, tetapi tetap memiliki model data, workflow, permission, controller, laporan, dan pengujian yang terpisah.

## 2. Tujuan

- Mempertahankan paritas fungsi Pelaporan BM yang sudah ada di target.
- Memigrasikan fungsi Distribusi BHP dari aplikasi Next.js/Prisma ke Laravel/Inertia.
- Menyediakan satu autentikasi dan administrasi sekolah untuk kedua domain.
- Mencegah pencampuran transaksi BM dengan transaksi BHP.
- Menyediakan migrasi data yang dapat diverifikasi dan dijalankan ulang.

## 3. Arsitektur Domain

```text
Shared Platform
├── Authentication
├── Users, roles, permissions
├── Sekolah / tenant
├── Settings identitas sekolah
├── Jabatan dan koordinator
└── Activity log

Pelaporan BM
├── Acuan
├── SPJ / SPK
├── Realisasi
├── Rekonsiliasi
├── Kunci laporan
└── Export/cetak BM

Distribusi BHP
├── Master barang dan rekening BHP
├── BOS / BOPD
├── Penerimaan dan saldo awal
├── Stok
├── Pengajuan divisi
├── Approval
├── Dokumen distribusi
└── Laporan BHP
```

## 4. Stack Target

- Laravel 13, PHP 8.4.
- PostgreSQL.
- Inertia.js, React 19, TypeScript.
- Tailwind CSS.
- Spatie Permission dan Activity Log.
- Laravel Excel untuk import/export.
- PHPUnit, TypeScript check, dan Laravel Pint.

## 5. Prinsip Integrasi

- Shared tidak berarti digabung: model bersama hanya untuk identitas dan platform.
- Semua query transaksi wajib memiliki batas tenant sekolah jika aktornya user sekolah.
- Domain BM dan BHP memakai namespace, route prefix, permission, dan test suite terpisah.
- Data `Barang` BM dan `Barang` BHP berbeda walaupun sama-sama memiliki kode/nama barang.
- `Acuan`, `SPJ`, dan `Realisasi` BM tidak menjadi sumber stok BHP.
- Pengajuan BHP tidak menjadi SPJ BM.
- Sumber dana BOS/BOPD pada BHP tidak mengubah makna `sumber_perolehan` BM.

## 6. Tahapan Produk

### Tahap 1: Fondasi dan paritas BM

- Rapikan kontrak shared user/sekolah/permission.
- Kunci acceptance test BM existing.
- Tetapkan migration baseline PostgreSQL.

### Tahap 2: Migrasi master dan penerimaan BHP

- Master BHP.
- Penerimaan.
- Saldo awal.
- Perhitungan stok.

### Tahap 3: Pengajuan dan approval BHP

- Pengajuan divisi.
- Reservasi stok.
- Review admin.
- Approval/rejection.

### Tahap 4: Dokumen dan laporan BHP

- Auto-generation dokumen.
- PDF.
- Batch PDF.
- Laporan operasional.

### Tahap 5: Migrasi data dan hardening

- Import data source.
- Rekonsiliasi jumlah dan nilai.
- Security test.
- Regression test.

## 7. Out of Scope

- Penggabungan tabel transaksi BM dan BHP.
- Akuntansi umum di luar kebutuhan dua domain.
- Integrasi SIPD/ARKAS.
- Aplikasi mobile native.
- Tanda tangan elektronik.
- Notifikasi email/WhatsApp/push pada fase awal.

## 8. Dokumen Requirement

- `domain-boundaries.md`: aturan pemisahan BM dan BHP.
- `pelaporan-belanja-modal-prd.md`: requirement produk BM.
- `workflow-belanja-modal.md`: alur dan state BM.
- `distribusi-bhp-prd.md`: requirement produk BHP.
- `workflow-distribusi-bhp.md`: alur dan transaction contract BHP.
- `shared-platform-requirements.md`: autentikasi, tenant, settings, dan audit.
- `data-dictionary.md`: kamus data dan mapping migrasi.
- `rbac-matrix.md`: matrix akses per domain.
- `reports-and-documents.md`: output Excel, PDF, dan numbering.
- `security-and-acceptance.md`: security requirement dan acceptance test.
