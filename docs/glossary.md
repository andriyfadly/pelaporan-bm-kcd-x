# Glosarium

## Istilah Pelaporan BM

| Istilah | Arti di sistem ini |
|---------|--------------------|
| Acuan | Target pagu anggaran per kodering per bulan per sekolah (`pelaporan_bm_acuan`) |
| BA / BAST | Berita Acara (Serah Terima); nomor + tanggal dokumen penerimaan barang |
| BM | Belanja Modal |
| BOS | Bantuan Operasional Sekolah (Reguler/Kinerja) — sumber perolehan |
| Causer | Aktor pencatat log (`activity_log.causer` → User) |
| Cetak 26 kolom | Laporan XLSX resmi 26 kolom A–Z + Kab/Kota (`CetakBmExport`) |
| Draft autosave | Draf form SPK tersimpan di localStorage per sekolah+bulan |
| KCD | Cabang Dinas Pendidikan (admin wilayah) |
| Kodering | Kode rekening belanja (mis. `5.2.02`) — kunci alokasi realisasi |
| Kunci laporan | Status `status_kunci` + `status_kirim`; bulan terkunci tak bisa dimutasi |
| Kode leaf | Kode barang tanpa turunan prefix (satu-satunya yang bisa dipilih) |
| Import SPJ | Unggah Excel item SPJ massal via template 12 kolom; all-or-nothing; dibatasi allowlist NPSN (`SpjController::NPSN_IMPORT_SPJ`) |
| NPSN | Nomor Pokok Sekolah Nasional; dipakai untuk username `[npsn]-admin` |
| Pre-flight | Pengecekan `cetak/check` sebelum unduh diizinkan |
| Realisasi | Baris alokasi item SPJ ke kodering (`pelaporan_bm_realisasi`) |
| Rekapan | Perbandingan acuan vs realisasi seluruh sekolah per bulan |
| SP2D | Surat Perintah Pencairan Dana |
| SPJ | Surat Pertanggungjawaban — dokumen belanja + item barang |
| SPK | Surat Perintah Kerja — dokumen kontrak; 1 SPK = N item |
| Subject | Entitas yang dicatat log (`activity_log.subject` → model) |
| Tenant | Ruang data per sekolah (`sekolah_id`); admin = lintas tenant |

## Istilah Distribusi BHP

| Istilah | Arti di sistem ini |
|---------|--------------------|
| BHP | Barang Habis Pakai — domain terpisah dari Belanja Modal |
| BOPD | Belanja Operasional Pendidikan Daerah — sumber dana BHP selain BOS |
| Barang | Master item BHP (`bhp_barang`); punya kode, satuan, kode rekening, sumber dana |
| Kode Rekening (BHP) | Kode akun BHP (`bhp_kode_rekening`), unik per `(kode, sumber_dana)` |
| Penyedia | Rekanan/supplier barang (`bhp_penyedia`) |
| Penerimaan | Pencatatan barang masuk (`bhp_penerimaan` + detail); menambah stok fisik |
| Saldo awal | Penerimaan yang ditandai `is_saldo_awal` + `periode_tahun`; stok awal tahun |
| Pengajuan | Permintaan/distribusi BHP (`bhp_pengajuan`); hidup dengan status |
| Detail pengajuan | Baris barang pada pengajuan (`bhp_pengajuan_detail`) |
| Reservasi | Stok yang "dipesan" pengajuan `submitted`; belum mengurangi terpakai |
| Terpakai | Jumlah disetujui (`jumlah_disetujui`) pada pengajuan `approved` |
| Fisik | Total barang masuk (Σ detail penerimaan) |
| Tersedia | `Fisik − Terpakai − Reservasi`, tidak pernah negatif — "stok 4-tier" |
| Stok 4-tier | Empat angka stok: Fisik, Terpakai, Reservasi, Tersedia (`StokService`) |
| Submit pengajuan | Aksi pemohon: validasi stok tersedia lalu buat reservasi (`draft → submitted`) |
| Approve | Aksi admin: koreksi jumlah, reservasi → pemakaian (`submitted → approved`) |
| Reject | Aksi admin: lepas reservasi + alasan wajib (`submitted → rejected`) |
| Dokumen BHP | Surat/berita acara hasil generate (`bhp_dokumen`), satu tabel dengan kolom `jenis` |
| Jenis dokumen | `nota_permintaan`, `surat_permintaan`, `surat_penyaluran`, `bast`, `bap`, `stok_opname`, `buku_pengeluaran`, `buku_penyaluran` |
| Dokumen wajib | 3 dokumen yang otomatis dibuat saat approve: nota, surat permintaan, surat penyaluran |
| Dokumen tambahan | Dokumen yang dibuat manual setelah approve (BAST/BAP/stok opname/buku), idempotent per jenis |
| Nomor dokumen | Format `{urut:3}/BHP/{kode}/{sumber_dana}/{bulan_romawi}/{tahun}`, counter atomic |
| Batch PDF | Gabungan semua dokumen pengajuan dalam satu PDF; histori di `bhp_batch_dokumen` |
| Buku pengeluaran | Laporan item yang sudah approved beserta keperluan |
| Buku penyaluran | Dokumen/laporan penyaluran BHP |
| Kartu barang | Profil barang + mutasi masuk/keluar (tanpa saldo berjalan) |
| Kartu persediaan | Mutasi kronologis **dengan saldo berjalan** per barang |
| operator_divisi | Role khusus BHP: membuat & mensubmit pengajuan, tanpa master/approval |
| Impor BHP | `DistribusiBhpImportSeeder` — impor master + penerimaan dari DB SQLite legacy |
| source_id | ID asli entitas dari DB sumber (`bhp_penerimaan.source_id`) untuk jejak audit/impor |
| cart_status | Status baris di DB sumber; hanya `saved` yang diimpor (`in_cart` = draft keranjang) |
