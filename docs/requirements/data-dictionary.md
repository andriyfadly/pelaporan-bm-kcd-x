# Data Dictionary dan Migration Mapping

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

| Entitas source | Tabel target yang direkomendasikan |
|---|---|
| `Divisi` | `bhp_divisi` |
| `Koordinator` | `bhp_koordinator` |
| `KodeRekening` | `bhp_kode_rekening` |
| `Barang` | `bhp_barang` |
| `HargaBarang` | `bhp_harga_barang` |
| `Penyedia` | `bhp_penyedia` |
| `BukuPenerimaanBarang` | `bhp_penerimaan` |
| `BukuPenerimaanBarangDetail` | `bhp_penerimaan_detail` |
| `NotaPermintaanBarang` | `bhp_pengajuan` |
| `NotaPermintaanBarangDetail` | `bhp_pengajuan_detail` |
| `SuratPermintaanBarang` | `bhp_surat_permintaan` |
| `SuratPerintahPenyaluranBarang` | `bhp_surat_penyaluran` |
| `BeritaAcaraSerahTerima` | `bhp_bast` |
| `BeritaAcaraPemeriksaan` | `bhp_bap` |
| `StokOpname` | `bhp_stok_opname` |
| `BukuPengeluaranBarang` | `bhp_buku_pengeluaran` |
| `BukuPenyaluran` | `bhp_buku_penyaluran` |
| `KartuBarang` | `bhp_kartu_barang` |
| `KartuBarangPersediaan` | `bhp_kartu_persediaan` |
| `BatchPdfGeneration` | `bhp_batch_pdf_generation` |
| `BatchPdfFile` | `bhp_batch_pdf_file` |

## 4. Migration Rules

- Simpan ID source pada kolom `source_id`/`id_lama` bila diperlukan audit.
- Gunakan UUID target untuk seluruh entitas bisnis baru.
- Jalankan import dalam chunk dan transaction per batch.
- Upsert berdasarkan identifier source yang stabil.
- Laporkan jumlah source, inserted, updated, skipped, dan invalid.
- Jangan mengonversi data BM menjadi BHP karena kemiripan nama field.
