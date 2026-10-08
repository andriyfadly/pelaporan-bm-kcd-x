# Security dan Acceptance Test Matrix

## 1. Security Requirements

- CSRF aktif untuk mutation web.
- Validasi request dilakukan server-side.
- Mass assignment memakai allowlist field.
- Upload membatasi tipe, ukuran, dan jumlah baris.
- Import menggunakan all-or-nothing validation jika menyentuh data transaksi.
- Export melindungi formula injection dengan escaping cell.
- Query lintas tenant harus menghasilkan `403` atau data kosong sesuai konteks, tidak membocorkan existence yang tidak berhak.
- Transaction dan row lock dipakai untuk alokasi BM dan approval/stok BHP.
- Error response tidak membocorkan SQL, secret, atau stack trace.
- Activity log mengecualikan password, token, dan secret.

## 2. Acceptance BM

- [ ] Operator hanya melihat sekolahnya sendiri.
- [ ] Acuan lintas sekolah ditolak.
- [ ] Nilai perolehan dihitung server-side.
- [ ] Alokasi melebihi sisa acuan ditolak.
- [ ] Duplicate allocation ditolak.
- [ ] Kirim laporan dengan kekurangan ditolak.
- [ ] Laporan submitted/disetujui read-only untuk role biasa.
- [ ] Kunci/buka laporan tercatat.
- [ ] Export 25 dan 26 kolom sesuai kontrak.
- [ ] Empty export menghasilkan 204/cookie status sesuai kontrak.
- [ ] Migrasi BM idempotent.

## 3. Acceptance BHP

- [ ] BOS dan BOPD tidak bercampur dalam satu transaksi.
- [ ] Barang BHP terikat sumber dana.
- [ ] Stok tersedia memakai formula fisik - terpakai - reservasi.
- [ ] Pengajuan melebihi stok ditolak.
- [ ] Submit membuat reservasi tepat satu kali.
- [ ] Reject/cancel melepaskan reservasi.
- [ ] Approval mengubah reservasi menjadi pemakaian.
- [ ] Concurrent approval tidak membuat stok negatif.
- [ ] Approval ulang ditolak/idempotent.
- [ ] Auto-generation dokumen atomic.
- [ ] Nomor dokumen tidak duplikat.
- [ ] User divisi hanya melihat pengajuan sesuai divisinya.
- [ ] Permission BHP tidak memberi akses mutation BM secara otomatis.

## 4. Quality Gates

```bash
vendor/bin/pint --format agent
npx tsc --noEmit
composer test -- --compact
composer test-coverage
```

Target coverage dan jumlah test mengikuti `docs/testing.md`; setiap feature baru harus menambah test feature dan test UI yang mewakili alur kritis.
