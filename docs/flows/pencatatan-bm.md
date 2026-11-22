# Flow Pencatatan BM (Inventaris)

> **Status: Fixed** Dokumen ini adalah rujukan perilaku aktual untuk domain Pencatatan BM (inventaris). Alur sinkronisasi dari Pelaporan BM melalui KunciLaporan.status_kirim='disetujui' sudah terimplementasi sesuai kode.
> Pencatatan BM. Ringkasan modul ada di `docs/modules.md` §7; dokumen ini
> mencatat urutan langkah, guard, efek data, dan test penjaga.
>
> Sumber kebenaran: `routes/web.php` (prefix `pencatatan-bm`),
> `app/Http/Controllers/PencatatanBm/`, `app/Services/PencatatanBm/`,
> `app/Models/PencatatanBm/`, dan `resources/js/Pages/PencatatanBm/`.

## 1. Aktor dan permission

| Kemampuan | Permission | super_admin | admin_kcd | operator/bendahara |
|---|---|---|:---:|:---:|
| Lihat dashboard, inventaris, mutasi, pengembalian | `lihat-pencatatan-bm` | ✅ | ✅ lintas sekolah | ✅ sekolah sendiri |
| Ubah kondisi/penempatan, unggah foto | `kelola-inventaris-bm` | ✅ | ❌ | ✅ sekolah sendiri |
| Catat mutasi/penempatan | `mutasi-inventaris-bm` | ✅ | ❌ | ✅ sekolah sendiri |
| Proses pengembalian | `proses-pengembalian-bm` | ✅ | ❌ | ✅ sekolah sendiri |
| Cetak dokumen, label, ekspor, koreksi BA | `cetak-inventaris-bm` | ✅ | ✅ | ✅ |
| Sinkronisasi ulang | `sinkronisasi-inventaris-bm` | ✅ | ❌ | ❌ |
| Kelola ruang/pegawai/jabatan/penugasan | permission masing-masing | ✅ | ❌ | ✅ sekolah sendiri |

Batas tenant ditegakkan **404**, bukan 403, agar UUID sekolah lain tidak
membocorkan keberadaan data (`TenantScope::pastikanSekolahSendiri`). Pengecualian:
`operator_sekolah`/`bendahara_sekolah` tanpa `sekolah_id` ditolak 403.

## 2. Invariant yang tidak boleh berubah

1. Unit inventaris **hanya** lahir dari `pelaporan_bm_realisasi` yang periodenya
   berstatus `disetujui`; tidak ada input unit manual.
2. `sekolah_id` dan `realisasi_id` unit **tidak pernah berubah** setelah dibuat.
3. Nomor register dialokasikan atomik per `(sekolah_id, kode_barang)` dan selalu
   maju; nomor yang sudah terbit tidak dipakai ulang.
4. Setiap perubahan state unit lewat menu Mutasi/Pengembalian wajib meninggalkan
   satu baris `inventaris_mutasi` (histori tidak dihapus).
5. Dokumen tersimpan memakai **snapshot** sekolah, unit, pegawai, dan pejabat.
   Cetak ulang memakai payload, bukan data master hidup.
6. Gate sinkronisasi adalah `pelaporan_bm_kunci_laporan.status_kirim =
   'disetujui'` pada bulan+tahun yang sama — **bukan** `status_kunci`.
7. Aksi tulis yang menyentuh unit sekolah lain ditolak 404 sebelum validasi.

## 3. Peta flow

| ID | Flow | Pemicu | Hasil |
|---|---|---|---|
| `PB-01` | Sinkronisasi realisasi → unit | `POST /pencatatan-bm/sinkronisasi` | Unit dibuat/diperbarui/dipensiunkan, `inventaris_sync_log` |
| `PB-02` | Daftar, detail, kondisi, foto, ekspor | `/pencatatan-bm/inventaris*` | State unit / berkas |
| `PB-03` | Penempatan & mutasi | `POST /pencatatan-bm/mutasi/{unit}` | Baris mutasi + state unit |
| `PB-04` | Pengembalian | `POST /pencatatan-bm/pengembalian/{unit}` | Baris mutasi + unit `dikembalikan` |
| `PB-05` | Dokumen langsung (KIR, buku, sensus) | `GET /pencatatan-bm/dokumen/{kir,buku-inventaris,sensus}` | PDF, tanpa simpan |
| `PB-06` | Dokumen tersimpan & berita acara | `POST/GET /pencatatan-bm/dokumen*`, `/berita-acara*` | `inventaris_dokumen` + PDF |
| `PB-07` | Label & QR unit | `GET /pencatatan-bm/label/{unit}/...` | SVG/PDF |
| `PB-08` | Dashboard inventaris | `GET /pencatatan-bm` | Ringkasan baca-saja |

---

## PB-01 — Sinkronisasi realisasi menjadi unit inventaris

**Aktor:** `super_admin` (`sinkronisasi-inventaris-bm`).
**Route:** `GET /pencatatan-bm/sinkronisasi` (`pencatatan-bm.sinkronisasi.index`),
`POST /pencatatan-bm/sinkronisasi` (throttle 10/menit).
**Kode:** `PencatatanBm\SyncController`,
`SinkronisasiInventarisService::syncPeriode()/syncRealisasi()/jalankan()`.

**Prasyarat**

- Halaman index memakai `resolveSekolahId`; `super_admin` tanpa `sekolah_id`
  melihat semua sekolah dan wajib memilih sekolah di form.
- `POST` memvalidasi `sekolah_id` (uuid, ada), `bulan` 1–12, `tahun` 2026–9999.
  `POST` **tidak** memakai `resolveSekolahId`: hak akses sudah dibatasi
  permission super_admin.

**Langkah**

1. Ambil semua `Realisasi` milik sekolah pada `bulan_realisasi` + `tahun`,
   urut `kode_barang`, `id`.
2. Untuk setiap realisasi, buat `SyncLog` status `berjalan` (`started_at`).
3. Gate periode: cari `KunciLaporan` sekolah+bulan+tahun dengan
   `status_kirim = 'disetujui'`. Tidak ada → `SyncResult(dilewati: true)`,
   log `dilewati`, tidak ada unit disentuh.
4. Bila lolos, buka transaksi:
   - lock baris `Realisasi` (`lockForUpdate`);
   - target unit = `floor(volume)`;
   - lock semua unit `realisasi_id` tersebut, key `unit_index`.
5. Rekonsiliasi per `unit_index`:
   - `index <= target` dan unit `dihapus_koreksi` → aktifkan kembali dengan
     register lama; unit aktif → segarkan snapshot (kecuali sudah pernah
     dimutasi), hitung `tetap`;
   - `index > target` dan unit aktif yang **sudah pernah dimutasi** → biarkan
     aktif, hitung `perlu_review` (tidak dipensiunkan otomatis);
   - `index > target` dan belum pernah dimutasi → status `dihapus_koreksi`,
     hitung `surplus`.
6. Buat unit baru untuk indeks `max+1 … target`:
   - alokasi register: `insertOrIgnore` baris sequence, `lockForUpdate`, ambil
     `next_number`, `str_pad(..., 3, '0')`, lalu increment;
   - salin snapshot realisasi: kode/nama/jenis/merk/sertifikat/ukuran/satuan,
     harga satuan = nilai perolehan unit, tanggal/bulan/tahun perolehan dari
     `ba_tgl` (fallback bulan/tahun realisasi).
7. Update log: `sukses`/`dilewati`/`gagal`, jumlah `dibuat`, `tetap`, `surplus`,
   `perlu_review`, `finished_at`.
8. Controller membalas `flash.success` berisi ringkasan empat angka; exception
   → `report()` + `flash.error` “Sinkronisasi gagal: …”.

**Efek data**

- `inventaris_unit` (insert/update status & snapshot).
- `inventaris_register_sequence` (`next_number`).
- `inventaris_sync_log` (selalu satu baris per realisasi, termasuk `dilewati`).
- Tidak ada penghapusan fisik unit.

**Gagal / tepi**

- Volume pecahan dibulatkan ke bawah.
- Volume naik lagi mengaktifkan unit yang pernah dipensiunkan, bukan membuat
  register baru.
- Unit bermutasi yang berada di luar volume menjadi daftar tinjau manual di
  halaman sinkronisasi; tidak dihapus.
- Rerun idempoten karena identitas unit `(realisasi_id, unit_index)`.

**Test penjaga:** `InventarisSyncTest`, `InventarisCorrectionTest`,
`InventarisReviewFixTest`, `InventarisRegisterConcurrencyTest`,
`InventarisCabangTest`, `PencatatanBmHttpFlowTest::test_sinkronisasi_hanya_super_admin_dan_menghasilkan_unit`,
`ResyncInventarisCommand` (command `inventaris:resync`).

---

## PB-02 — Daftar, detail, ubah kondisi, foto, ekspor

**Route dan kode**

| Aksi | Route | Controller |
|---|---|---|
| Daftar | `GET /pencatatan-bm/inventaris` | `InventarisController::index` |
| Detail | `GET /pencatatan-bm/inventaris/{unit}` | `show` |
| Ubah kondisi/penempatan | `PUT /pencatatan-bm/inventaris/{unit}/kondisi` | `updateKondisi` |
| Unggah foto | `POST /pencatatan-bm/inventaris/{unit}/foto` | `UnitInventarisController::storeFoto` |
| Lihat foto | `GET /pencatatan-bm/foto/{foto}` | `UnitInventarisController::foto` |
| Ekspor | `GET /pencatatan-bm/inventaris-export` | `InventarisController::export` |

**Daftar**

- Scope `sekolah_id`; pencarian ILIKE pada `nama_barang`, `kode_barang`,
  `nomor_register`, `merk_tipe`; filter kondisi `B/RR/RB`; urut kode+register;
  pagination 25.
- **Semua status unit ikut tampil** (aktif, dikembalikan, dihapus_koreksi).
  Dashboard hanya menghitung unit aktif.

**Detail**

- `pastikanSekolahSendiri` → 404 lintas sekolah; `Gate::authorize('view')`.
- Memuat snapshot realisasi (`no_spk`, `ba_no`, `ba_tgl`, sumber perolehan),
  ruang/pegawai, dan seluruh riwayat mutasi beserta asal/tujuan dan pencatat.

**Ubah kondisi/penempatan**

- Validasi: kondisi wajib enum; `ruang_id`/`pegawai_id` nullable dan harus milik
  sekolah unit; keterangan maks 1000.
- Langsung `update()` pada unit. **Tidak menulis baris mutasi.** Untuk jejak
  audit penempatan, gunakan menu Mutasi (`PB-03`). Ini perilaku aktual, bukan
  alur yang disarankan untuk perubahan penempatan.

**Foto**

- Hanya gambar `jpg/jpeg/png/webp`, maks 5 MB.
- Disimpan di disk `public` pada `inventaris/foto/{sekolah_id}/…`; metadata
  `Foto`: path, mime, size, checksum SHA-256, uploader.
- `inventaris_unit.foto_path` diperbarui ke foto terakhir.
- Unduh foto memeriksa tenant (404) + `Gate view` unit.

**Ekspor**

- Permission `cetak-inventaris-bm`, throttle 30/menit.
- `InventarisExport` (XLSX) seluruh unit sekolah (semua status) dengan filter
  kondisi; urut kode+register; nama file `inventaris-YYYYmmdd-His.xlsx`.

**Test penjaga:** `PencatatanBmHttpFlowTest`, `InventarisHalamanTest`,
`InventarisTenantIsolationTest`, `InventarisPermissionTest`,
`InventarisDocumentTest` (foto), `InventarisCorrectionTest::test_export_excel_inventaris`.

---

## PB-03 — Penempatan dan mutasi unit

**Aktor:** `mutasi-inventaris-bm` + `Gate::authorize('mutasi')`.
**Route:** `GET /pencatatan-bm/mutasi`,
`POST /pencatatan-bm/mutasi/{unit}`.
**Kode:** `MutasiController`, `MutasiInventarisService::tempatkan()`.

**Validasi (`MutasiRequest`)**

- Tenant dicek di `authorize()` sebelum validasi; unit sekolah lain → 404.
- `ruang_id`/`pegawai_id` harus milik sekolah unit dan belum di-soft-delete.
- `kondisi` nullable enum; `tanggal` wajib; `keterangan` maks 1000.
- Minimal satu perubahan: ruang, pegawai, atau kondisi. Tanpa perubahan →
  validation error “Pilih ruang, pegawai, atau kondisi yang berubah.”

**Langkah service**

1. Transaksi + lock unit.
2. Tolak unit `dihapus_koreksi`.
3. Tentukan jenis: bila `ruang_id` dan `pegawai_id` unit masih `null` →
   `penempatan`; selain itu `mutasi`.
4. Kunci yang tidak dikirim = tidak berubah; `null` eksplisit = kosongkan.
5. Buat baris `inventaris_mutasi` berisi asal (ruang/pegawai/kondisi) dan
   tujuan, tanggal, keterangan, user.
6. Update unit: ruang, pegawai, kondisi, status `aktif`.

**Tepi**

- Mutasi kondisi saja tidak mengosongkan ruang/pegawai.
- Unit berstatus `dikembalikan` boleh ditempatkan ulang tanpa menghapus histori.
- `sekolah_id` unit tidak pernah berubah.

**Test penjaga:** `InventarisMutationTest`, `InventarisReviewFixTest`,
`InventarisReturnTest::test_unit_dikembalikan_boleh_ditempatkan_ulang...`,
`InventarisHalamanTest::test_mutasi_tanpa_perubahan_apa_pun_ditolak`.

---

## PB-04 — Pengembalian unit

**Aktor:** `proses-pengembalian-bm` + `Gate::authorize('kembalikan')`.
**Route:** `GET /pencatatan-bm/pengembalian`,
`POST /pencatatan-bm/pengembalian/{unit}`.
**Kode:** `PengembalianController`, `MutasiInventarisService::kembalikan()`.

**Prasyarat:** unit `aktif` dan sedang ditempatkan (`ruang_id` atau
`pegawai_id` tidak null).

**Langkah**

1. Transaksi + lock unit; tenant 404.
2. Validasi `kondisi` wajib enum, `tanggal` wajib, `keterangan` maks 1000.
3. Catat mutasi jenis `pengembalian` dengan tujuan ruang/pegawai `null` dan
   kondisi akhir.
4. Update unit: ruang/pegawai `null`, kondisi akhir, status `dikembalikan`.

**Gagal:** unit belum ditempatkan atau sudah dikembalikan → validation error;
pengembalian kedua ditolak. Histori tetap ada. Menghapus ruang/pegawai hanya
mengosongkan referensi (`nullOnDelete`), bukan menghapus histori.

**Test penjaga:** `InventarisReturnTest`, `InventarisHalamanTest`.

---

## PB-05 — Dokumen langsung: KIR, Buku Inventaris, Sensus

**Aktor:** `cetak-inventaris-bm`.
**Kode:** `DokumenInventarisController::kir/bukuInventaris/sensus`,
`DokumenInventarisService`.

| Dokumen | Route | Data | Output |
|---|---|---|---|
| KIR per ruang | `GET /pencatatan-bm/dokumen/kir?ruang={id}` | Unit `aktif` di ruang itu | PDF A4 landscape |
| Buku Inventaris | `GET /pencatatan-bm/dokumen/buku-inventaris?tahun=YYYY` | Unit aktif `tahun_perolehan` | PDF A4 landscape |
| Sensus Aset | `GET /pencatatan-bm/dokumen/sensus?tahun=YYYY` | Unit aktif `tahun_perolehan` | PDF A4 landscape |

- Ruang dicari dengan scope `sekolah_id`; ruang sekolah lain → 404.
- Tahun default tahun berjalan.
- Dokumen langsung **tidak** membuat baris `inventaris_dokumen`.
- Kop sekolah dari master; pejabat dari penugasan yang berlaku pada tanggal
  cetak; label jabatan termasuk Plt./Plh.
- `resolveSekolahIdWajib`: admin tanpa konteks sekolah ditolak 422.

**Test penjaga:** `InventarisDocumentTest`,
`InventarisSnapshotPejabatTest`, `InventarisHalamanTest`.

---

## PB-06 — Dokumen tersimpan dan berita acara

**Aktor:** `cetak-inventaris-bm` untuk semua route dokumen/BA; tenant 404.
**Kode:** `DokumenInventarisController`, `BeritaAcaraController`,
`DokumenInventarisService::simpan/unduh`, `QrLabelService::snapshot*`.

### Dokumen yang disimpan

| Jenis | Route | Isi payload | Syarat |
|---|---|---|---|
| `bast` | `POST /pencatatan-bm/dokumen/bast/{unit}` | sekolah, unit, pejabat, pencetak | unit sekolah sendiri |
| `fakta_integritas` | `POST /pencatatan-bm/dokumen/fakta-integritas/{pegawai}` | sekolah, pegawai+jabatan pada tanggal, unit aktif yang dipegang, pejabat | pegawai sekolah sendiri |
| `surat_pengembalian` | `POST /pencatatan-bm/dokumen/surat-pengembalian/{unit}` | payload dasar + snapshot mutasi pengembalian terakhir | sudah ada mutasi `pengembalian` |

- Tanggal opsional (default hari ini); BAST nomor opsional.
- `Dokumen` disimpan lebih dulu, lalu PDF dirender dari view
  `pencatatan-bm.dokumen` ke `storage/app/inventaris/dokumen/{sekolah_id}/…pdf`,
  dan `file_path` diperbarui.
- Daftar dokumen: `GET /pencatatan-bm/dokumen` (100 terbaru, scope sekolah,
  menyertakan daftar ruang untuk KIR).
- Unduh ulang: `GET /pencatatan-bm/dokumen/{dokumen}/pdf`; tenant 404.
- Cetak ulang memakai snapshot pejabat di payload; dokumen lama tanpa snapshot
  jatuh ke lookup hidup (shortcut kompatibilitas).

### Koreksi / pembatalan nomor berita acara

- `GET /pencatatan-bm/berita-acara`: 100 dokumen `bast` terbaru; menyertakan
  `nomor_dibatalkan_pada` dan payload.
- `PUT /pencatatan-bm/berita-acara/{dokumen}`: `nomor` wajib string maks 150;
  mengoreksi nomor dan menghapus `nomor_dibatalkan_pada`.
- `DELETE /pencatatan-bm/berita-acara/{dokumen}`: **tidak menghapus dokumen**;
  `alasan` wajib maks 500; set `nomor = null`, `nomor_dibatalkan_pada = now()`,
  simpan `payload.pembatalan = {alasan, oleh, pada}`.
- Nomor BA sekolah lain → 404.

**Test penjaga:** `InventarisDocumentTest`, `InventarisBeritaAcaraTest`,
`InventarisSnapshotPejabatTest`, `PdfPenandatanganSourceTest`.

---

## PB-07 — Label dan QR unit

**Aktor:** `cetak-inventaris-bm`.

| Route | Hasil |
|---|---|
| `GET /pencatatan-bm/label/{unit}/qr` | SVG QR |
| `GET /pencatatan-bm/label/{unit}/pdf` | PDF label A4 |

- QR berisi **signed URL** sementara (1 tahun) ke halaman detail unit; payload
  tidak memuat `sekolah_id`.
- Endpoint scan tetap memerlukan login dan memeriksa tenant, sehingga URL
  bertanda tangan tidak membuka data lintas sekolah.
- `qr`/`label` memeriksa tenant sebelum render (404).
- Label memuat register, kode/nama barang, merk, sekolah, ruang, pegawai,
  kondisi.

**Test penjaga:** `InventarisDocumentTest`, `InventarisModelTest`,
`InventarisTenantIsolationTest`.

---

## PB-08 — Dashboard inventaris

**Route:** `GET /pencatatan-bm` (`lihat-pencatatan-bm`).
**Kode:** `PencatatanBm\DashboardController`.

- Scope `sekolah_id`; `super_admin`/admin tanpa `sekolah_id` melihat agregat
  semua sekolah.
- Hanya unit `aktif` yang dihitung.
- Ringkasan: total unit, total nilai perolehan, sebaran kondisi B/RR/RB,
  jumlah kode barang unik, jumlah ruang, jumlah pegawai.
- 10 kode barang terbanyak (jumlah + nilai).
- Log sinkronisasi terakhir (status, dibuat, surplus, perlu review, waktu).

**Test penjaga:** `InventarisHalamanTest`, `PencatatanBmHttpFlowTest`,
`InventarisPermissionTest::test_admin_kcd_tanpa_sekolah_melihat_ringkasan_lintas_sekolah`.

---

## 4. Cakupan route → flow

| Route (method + URI) | Flow |
|---|---|
| `GET /pencatatan-bm` | PB-08 |
| `GET /pencatatan-bm/inventaris` | PB-02 |
| `GET /pencatatan-bm/inventaris/{unit}` | PB-02 |
| `PUT /pencatatan-bm/inventaris/{unit}/kondisi` | PB-02 |
| `POST /pencatatan-bm/inventaris/{unit}/foto` | PB-02 |
| `GET /pencatatan-bm/inventaris-export` | PB-02 |
| `GET /pencatatan-bm/foto/{foto}` | PB-02 |
| `GET /pencatatan-bm/mutasi` | PB-03 |
| `POST /pencatatan-bm/mutasi/{unit}` | PB-03 |
| `GET /pencatatan-bm/pengembalian` | PB-04 |
| `POST /pencatatan-bm/pengembalian/{unit}` | PB-04 |
| `GET /pencatatan-bm/dokumen` | PB-06 |
| `GET /pencatatan-bm/dokumen/kir` | PB-05 |
| `GET /pencatatan-bm/dokumen/buku-inventaris` | PB-05 |
| `GET /pencatatan-bm/dokumen/sensus` | PB-05 |
| `POST /pencatatan-bm/dokumen/bast/{unit}` | PB-06 |
| `POST /pencatatan-bm/dokumen/fakta-integritas/{pegawai}` | PB-06 |
| `POST /pencatatan-bm/dokumen/surat-pengembalian/{unit}` | PB-06 |
| `GET /pencatatan-bm/dokumen/{dokumen}/pdf` | PB-06 |
| `GET /pencatatan-bm/berita-acara` | PB-06 |
| `PUT /pencatatan-bm/berita-acara/{dokumen}` | PB-06 |
| `DELETE /pencatatan-bm/berita-acara/{dokumen}` | PB-06 |
| `GET /pencatatan-bm/label/{unit}/qr` | PB-07 |
| `GET /pencatatan-bm/label/{unit}/pdf` | PB-07 |
| `GET /pencatatan-bm/sinkronisasi` | PB-01 |
| `POST /pencatatan-bm/sinkronisasi` | PB-01 |

Tidak ada route Pencatatan BM di luar tabel ini.

## 5. Jebakan yang perlu diketahui

1. **Ubah kondisi di halaman detail tidak menulis histori mutasi.** Untuk
   perubahan penempatan yang perlu diaudit, gunakan PB-03. Jangan menganggap
   `inventaris_mutasi` selalu memuat seluruh perubahan.
2. **Gate sinkronisasi memakai `status_kirim`, bukan `status_kunci`.** Laporan
   yang sudah disetujui tetapi gemboknya dibuka tetap boleh disinkronkan.
3. **Unit di luar volume tidak dihapus.** Unit belum bermutasi → `dihapus_koreksi`;
   unit sudah bermutasi → daftar tinjau manual.
4. **Daftar inventaris memuat unit nonaktif.** Dashboard dan dokumen hanya
   memakai unit `aktif`; jangan menyamakan keduanya.
5. **Semua route `/berita-acara` berada di bawah permission cetak**, bukan
   permission kelola inventaris.
6. **Data sekolah lain = 404**, termasuk untuk foto dan dokumen, agar UUID tidak
   menjadi oracle keberadaan data.
