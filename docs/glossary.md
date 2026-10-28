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
| Sumber dana | `BOS`/`BOPD`; pada tabel BHP dipisah fisik (`bhp_bos_*`/`bhp_bopd_*`), implisit dari nama tabel |
| Barang | Master item BHP (`bhp_{bos,bopd}_barang`); punya kode, satuan, kode rekening |
| Kode Rekening (BHP) | Kode akun BHP (`bhp_{bos,bopd}_kode_rekening`), unik `kode` per tabel dana |
| Penyedia | Rekanan/supplier barang (`bhp_penyedia`, tabel bersama lintas dana) |
| Penerimaan | Pencatatan barang masuk (`bhp_{bos,bopd}_penerimaan` + detail); menambah stok fisik |
| Saldo awal | Penerimaan yang ditandai `is_saldo_awal` + `periode_tahun`; stok awal tahun |
| Pengajuan | Permintaan/distribusi BHP (`bhp_{bos,bopd}_pengajuan`); hidup dengan status |
| Nota Permintaan Barang | Dokumen pertama yang digenerate saat approve — daftar barang diminta |
| Surat Permintaan Barang | Dokumen penyaluran — rincian barang yang diminta sekolah |
| Surat Perintah Penyaluran | Dokumen perintah penyaluran dari penyedia/operator |
| BAST | Berita Acara Serah Terima — dokumen serah terima barang |
| BAP | Berita Acara Pemeriksaan — dokumen pemeriksaan barang |
| Stok Opname | Berita Acara Stok Opname — laporan stok fisik periodik |
| Buku Pengeluaran | Laporan item yang sudah approved beserta keperluan |
| Buku Penyaluran | Dokumen/laporan penyaluran BHP |
| Detail pengajuan | Baris barang pada pengajuan (`bhp_{bos,bopd}_pengajuan_detail`) |
| Reservasi | Stok yang "dipesan" pengajuan `submitted`; belum mengurangi terpakai |
| Terpakai | Jumlah disetujui (`jumlah_disetujui`) pada pengajuan `approved` |
| Fisik | Total barang masuk (Σ detail penerimaan) |
| Tersedia | `Fisik − Terpakai − Reservasi`, tidak pernah negatif — "stok 4-tier" |
| Stok 4-tier | Empat angka stok: Fisik, Terpakai, Reservasi, Tersedia (`StokService`) |
| Submit pengajuan | Aksi pemohon: validasi stok tersedia lalu buat reservasi (`draft → submitted`) |
| Approve | Aksi admin: koreksi jumlah, reservasi → pemakaian (`submitted → approved`) |
| Reject | Aksi admin: lepas reservasi + alasan wajib (`submitted → rejected`) |
| Dokumen BHP | Surat/berita acara hasil generate (`bhp_{bos,bopd}_dokumen`), satu tabel per dana dengan kolom `jenis` |
| Jenis dokumen | `nota_permintaan`, `surat_permintaan`, `surat_penyaluran`, `bast`, `bap`, `stok_opname`, `buku_pengeluaran`, `buku_penyaluran` |
| Dokumen wajib | 3 dokumen yang otomatis dibuat saat approve: nota, surat permintaan, surat penyaluran |
| Dokumen tambahan | Dokumen yang dibuat manual setelah approve (BAST/BAP/stok opname/buku), idempotent per jenis |
| Stok Opname bulanan | Snapshot stok fisik satu per bulan per sekolah; opname kedua di bulan yang sama ditolak |
| Nomor dokumen | Format `{urut:3}/BHP/{kode}/{sumber_dana}/{bulan_romawi}/{tahun}`, counter atomic |
| Batch PDF | Gabungan semua dokumen pengajuan dalam satu PDF; histori di `bhp_{bos,bopd}_batch_dokumen` |
| Buku pengeluaran | Laporan item yang sudah approved beserta keperluan |
| Buku penyaluran | Dokumen/laporan penyaluran BHP |
| Kartu barang | Profil barang + mutasi masuk/keluar (tanpa saldo berjalan) |
| Kartu persediaan | Mutasi kronologis **dengan saldo berjalan** per barang |
| Pejabat sekolah | Penandatangan dokumen (kepala sekolah, wakasek sarpras, pembantu pengurus barang). Tidak lagi tabel sendiri: diambil dari `master_data_penugasan_pegawai` pada jabatan berkode terkait |
| Penugasan pegawai | Periode seorang pegawai memegang satu jabatan (`definitif`/`plt`/`plh`), tabel `master_data_penugasan_pegawai` |
| operator_divisi | Role khusus BHP: membuat & mensubmit pengajuan, tanpa master/approval |
| Impor BHP | `DistribusiBhpImportSeeder` — impor master + penerimaan dari DB SQLite legacy |
| source_id | ID asli entitas dari DB sumber (`bhp_{bos,bopd}_penerimaan.source_id`) untuk jejak audit/impor |
| cart_status | Status baris di DB sumber; hanya `saved` yang diimpor (`in_cart` = draft keranjang) |
