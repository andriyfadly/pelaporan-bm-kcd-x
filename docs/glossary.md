# Glosarium

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
| NPSN | Nomor Pokok Sekolah Nasional; dipakai untuk username `[npsn]-admin` |
| Pre-flight | Pengecekan `cetak/check` sebelum unduh diizinkan |
| Realisasi | Baris alokasi item SPJ ke kodering (`pelaporan_bm_realisasi`) |
| Rekapan | Perbandingan acuan vs realisasi seluruh sekolah per bulan |
| SP2D | Surat Perintah Pencairan Dana |
| SPJ | Surat Pertanggungjawaban — dokumen belanja + item barang |
| SPK | Surat Perintah Kerja — dokumen kontrak; 1 SPK = N item |
| Subject | Entitas yang dicatat log (`activity_log.subject` → model) |
| Tenant | Ruang data per sekolah (`sekolah_id`); admin = lintas tenant |
