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
| `Divisi` | `bhp_divisi` | ⬜ Belum | `bhp_pengajuan.divisi_id` sudah ada (nullable), tabel master belum |
| `Koordinator` | `bhp_koordinator` | ⬜ Belum | Belum ada tabel |
| `KodeRekening` | `bhp_kode_rekening` | ✅ Sudah | unique `(kode, sumber_dana)` |
| `Barang` | `bhp_barang` | ✅ Sudah | `stok_tereservasi` dihitung dari reservasi, bukan kolom |
| `HargaBarang` | `bhp_harga_barang` | ✅ Sudah | Riwayat harga per barang |
| `Penyedia` | `bhp_penyedia` | ✅ Sudah | unique `nama` |
| `BukuPenerimaanBarang` | `bhp_penerimaan` | ✅ Sudah | + `source_id` unik (jejak impor) |
| `BukuPenerimaanBarangDetail` | `bhp_penerimaan_detail` | ✅ Sudah | hanya baris `cart_status=saved` yang diimpor |
| `NotaPermintaanBarang` | `bhp_pengajuan` | ✅ Sudah | Digabung sebagai pengajuan + workflow status |
| `NotaPermintaanBarangDetail` | `bhp_pengajuan_detail` | ✅ Sudah | |
| `SuratPermintaanBarang` | `bhp_surat_permintaan` | ⬜ Belum | Dibuat sebagai **dokumen** `bhp_dokumen` (`jenis=surat_permintaan`), bukan tabel terpisah |
| `SuratPerintahPenyaluranBarang` | `bhp_surat_penyaluran` | ⬜ Belum | Idem (`jenis=surat_penyaluran`) |
| `BeritaAcaraSerahTerima` | `bhp_bast` | ⬜ Belum | Idem (`jenis=bast`) |
| `BeritaAcaraPemeriksaan` | `bhp_bap` | ⬜ Belum | Idem (`jenis=bap`) |
| `StokOpname` | `bhp_stok_opname` | ⬜ Belum | Idem (`jenis=stok_opname`) |
| `BukuPengeluaranBarang` | `bhp_buku_pengeluaran` | ⬜ Belum | Idem (`jenis=buku_pengeluaran`) |
| `BukuPenyaluran` | `bhp_buku_penyaluran` | ⬜ Belum | Idem (`jenis=buku_penyaluran`) |
| `KartuBarang` | `bhp_kartu_barang` | 🟡 Sebagian | Dihitung runtime (`LaporanService::kartuBarang`), tanpa tabel |
| `KartuBarangPersediaan` | `bhp_kartu_persediaan` | 🟡 Sebagian | Dihitung runtime dengan saldo berjalan (`LaporanService::kartuPersediaan`), tanpa tabel |
| `BatchPdfGeneration` | `bhp_batch_pdf_generation` | 🟡 Sebagian | Dipetakan ke `bhp_batch_dokumen` |
| `BatchPdfFile` | `bhp_batch_pdf_file` | 🟡 Sebagian | Detail batch disimpan sebagai kolom `files` (json) di `bhp_batch_dokumen` |
| `MonthlySequence` / `DocumentSequence` | `bhp_nomor_sequence` | ✅ Sudah | Counter atomic per (jenis, sumber dana, tahun, bulan) |
| — | `bhp_dokumen` | ✅ Sudah | Tabel dokumen hasil generate (jenis, nomor, payload) |

Perubahan pemetaan dari rencana awal (keputusan implementasi):

- Dokumen turunan (surat permintaan, surat penyaluran, BAST, BAP, stok opname,
  buku pengeluaran/penyaluran) **tidak** dibuat sebagai tabel terpisah, tetapi
  satu tabel `bhp_dokumen` dengan kolom `jenis`. Alasan: lifecycle, penomoran,
  dan batch PDF identik untuk semua jenis.
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

