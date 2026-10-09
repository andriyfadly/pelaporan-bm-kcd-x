# Data Dictionary dan Migration Mapping

> **Status:** sebagian sudah diimplementasi. Kolom **Status** menandai realitas
> kode saat ini: ✅ Sudah · 🟡 Sebagian · ⬜ Belum.

## 1. Shared

| Entitas | Tabel target | Keterangan |
|---|---|---|
| Sekolah | `master_data_sekolah` | Tenant sekolah |
| User | `users` | Akun dan relasi sekolah |
| Activity | `activity_log` | Audit trail |

## 2. Pelaporan BM

| Entitas | Tabel target | Sumber/relasi |
|---|---|---|
| Kode barang aset | `master_data_kode_barang` | Katalog aset |
| Acuan | `pelaporan_bm_acuan` | Target per sekolah/periode |
| SPJ | `pelaporan_bm_spj` | Dokumen dan item fisik |
| Realisasi | `pelaporan_bm_realisasi` | Snapshot alokasi |
| Kunci | `pelaporan_bm_kunci_laporan` | Lock/status kirim |

Source legacy BM:

```text
data_barang_acuan       → pelaporan_bm_acuan
master_barang_sekolah   → pelaporan_bm_spj
realisasi_barang_sekolah→ pelaporan_bm_realisasi
laporan_realisasi       → pelaporan_bm_kunci_laporan
kode_barang             → master_data_kode_barang
```

## 3. Distribusi BHP

Tabel yang benar-benar ada saat ini lihat `docs/database.md` §5. Beberapa
entitas source digabung atau belum dibuat; lihat kolom Status.

| Entitas source | Tabel target | Status | Catatan |
|---|---|---|---|
| `Divisi` | `bhp_divisi` | ✅ Sudah | Tabel master divisi; `bhp_pengajuan.divisi_id` FK restrict, `users.divisi_id` FK set null |
| `Koordinator` | `bhp_koordinator` | ⬜ Belum | Belum ada tabel |
| `KodeRekening` | `bhp_{bos,bopd}_kode_rekening` | ✅ Sudah | Tabel per sumber dana; unique `kode` per tabel |
| `Barang` | `bhp_{bos,bopd}_barang` | ✅ Sudah | `stok_tereservasi` dihitung dari reservasi, bukan kolom |
| `HargaBarang` | `bhp_{bos,bopd}_harga_barang` | ✅ Sudah | Riwayat harga per barang |
| `Penyedia` | `bhp_penyedia` | ✅ Sudah | unique `nama`; tabel bersama lintas sumber dana |
| `BukuPenerimaanBarang` | `bhp_{bos,bopd}_penerimaan` | ✅ Sudah | + `source_id` unik (jejak impor); `sekolah_id` NOT NULL |
| `BukuPenerimaanBarangDetail` | `bhp_{bos,bopd}_penerimaan_detail` | ✅ Sudah | hanya baris `cart_status=saved` yang diimpor |
| `NotaPermintaanBarang` | `bhp_{bos,bopd}_pengajuan` | ✅ Sudah | Digabung sebagai pengajuan + workflow status |
| `NotaPermintaanBarangDetail` | `bhp_{bos,bopd}_pengajuan_detail` | ✅ Sudah | |
| `SuratPermintaanBarang` | `bhp_surat_permintaan` | ✅ Sudah | Dibuat sebagai **dokumen** `bhp_{bos,bopd}_dokumen` (`jenis=surat_permintaan`), bukan tabel terpisah; halaman read-only mandiri tersedia |
| `SuratPerintahPenyaluranBarang` | `bhp_surat_penyaluran` | ✅ Sudah | Idem (`jenis=surat_penyaluran`) |
| `BeritaAcaraSerahTerima` | `bhp_bast` | ✅ Sudah | Idem (`jenis=bast`) |
| `BeritaAcaraPemeriksaan` | `bhp_bap` | ✅ Sudah | Idem (`jenis=bap`) |
| `StokOpname` | `bhp_stok_opname` | ✅ Sudah | Idem (`jenis=stok_opname`) |
| `BukuPengeluaranBarang` | `bhp_buku_pengeluaran` | ✅ Sudah | Idem (`jenis=buku_pengeluaran`); juga halaman dari data pengajuan approved |
| `BukuPenyaluran` | `bhp_buku_penyaluran` | ✅ Sudah | Idem (`jenis=buku_penyaluran`) |
| `KartuBarang` | `bhp_kartu_barang` | 🟡 Sebagian | Dihitung runtime (`LaporanService::kartuBarang`), tanpa tabel |
| `KartuBarangPersediaan` | `bhp_kartu_persediaan` | 🟡 Sebagian | Dihitung runtime dengan saldo berjalan (`LaporanService::kartuPersediaan`), tanpa tabel |
| `BatchPdfGeneration` | `bhp_batch_pdf_generation` | 🟡 Sebagian | Dipetakan ke `bhp_{bos,bopd}_batch_dokumen` |
| `BatchPdfFile` | `bhp_batch_pdf_file` | 🟡 Sebagian | Detail batch disimpan sebagai kolom `files` (json) di `bhp_{bos,bopd}_batch_dokumen` |
| `MonthlySequence` / `DocumentSequence` | `bhp_{bos,bopd}_nomor_sequence` | ✅ Sudah | Counter atomic per (jenis, tahun, bulan) per tabel dana; insert row pertama anti-race |
| — | `bhp_{bos,bopd}_dokumen` | ✅ Sudah | Tabel dokumen hasil generate (jenis, nomor, payload); payload ter-scope sekolah pengajuan |
| — | `bhp_{bos,bopd}_settings` | ✅ Sudah | Satu baris per dana (`is_singleton` + unique index): hanya `format_nomor` (paper size & orientation dihapus — hardcode per jenis di `DokumenController::PAPER`) |
| — | `pejabat_sekolah` | ✅ Sudah | Riwayat pejabat penandatangan per sekolah (kepala sekolah, wakasek sarpras, pembantu pengurus barang): nama, pangkat/golongan, NIP, masa jabatan; snapshot disimpan di `bhp_{bos,bopd}_dokumen.payload.penandatangan` |
| — | Foreign key BHP | ✅ Sudah | FK per tabel (cascade detail/dokumen, restrict master historis, set null `users.divisi_id`), diterapkan ulang saat split 2026-10-12 |
| — | Pemisahan per sumber dana | ✅ Sudah | Tabel transaksi & master dipecah `bhp_bos_*`/`bhp_bopd_*` (2026-10-12); sumber dana implisit dari nama tabel, tanpa kolom `sumber_dana` |

Perubahan pemetaan dari rencana awal (keputusan implementasi):

- Dokumen turunan (surat permintaan, surat penyaluran, BAST, BAP, stok opname,
  buku pengeluaran/penyaluran) **tidak** dibuat sebagai tabel terpisah, tetapi
  satu tabel dokumen per sumber dana (`bhp_{bos,bopd}_dokumen`) dengan kolom
  `jenis`. Alasan: lifecycle, penomoran, dan batch PDF identik untuk semua jenis.
- Semua tabel transaksi & master barang BHP **dipisah fisik per sumber dana**
  (`bhp_bos_*`/`bhp_bopd_*`, migrasi 2026-10-12) agar BOS dan BOPD tidak bisa
  tercampur di level skema. Hanya `bhp_penyedia` dan `bhp_divisi` yang bersama.
- `KartuBarang` dan `KartuBarangPersediaan` dihitung dari mutasi
  penerimaan/pengajuan, tidak disimpan sebagai tabel agar tidak ada duplikasi
  data stok.

## 4. Migration Rules

- Simpan ID source pada kolom `source_id`/`id_lama` bila diperlukan audit.
- Gunakan UUID target untuk seluruh entitas bisnis baru.
- Jalankan import dalam chunk dan transaction per batch.
- Upsert berdasarkan identifier source yang stabil.
- Laporkan jumlah source, inserted, updated, skipped, dan invalid.
- Jangan mengonversi data BM menjadi BHP karena kemiripan nama field.

### Status kepatuhan aturan impor

| Aturan | Status | Keterangan |
|---|---|---|
| `source_id` untuk audit | 🟡 Sebagian | Baru pada `bhp_penerimaan` |
| UUID target | ✅ Sudah | Semua tabel BHP (`HasUuids`) |
| Chunk + transaction | ✅ Sudah | `DistribusiBhpImportSeeder` |
| Upsert identifier stabil | ✅ Sudah | Berbasis `source_id` (penerimaan) / natural key |
| Laporan inserted/updated/skipped/invalid | 🟡 Sebagian | Dilaporkan sebagai jumlah per entitas; skipped/invalid belum dipisah |
| Jangan campur data BM→BHP | ✅ Sudah | Domain terpisah, dikunci oleh `BhpDomainBoundaryTest` |

