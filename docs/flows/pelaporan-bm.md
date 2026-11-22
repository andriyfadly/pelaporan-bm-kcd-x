# Flow Pelaporan BM & Distribusi Belanja Modal

> **Status: Fixed (diturunkan dari `routes/web.php`, controller, service, model, dan halaman Inertia).** Dokumen ini adalah rujukan perilaku aktual domain Pelaporan BM. Jika route/controller/permission/state berubah, perbarui dokumen ini **dalam PR yang sama** (lihat `docs/flows/conventions.md`).
>
> Sumber utama: `routes/web.php` (prefix `pelaporan-bm`, `dashboard`, `rekapan`), `app/Http/Controllers/PelaporanBm/*`, `app/Models/PelaporanBm/*`, `app/Services/PencatatanBm/SinkronisasiInventarisService.php` (pemicu dari status disetujui), halaman `resources/js/Pages/PelaporanBm/*`, serta `docs/modules.md`, `docs/requirements/workflow-belanja-modal.md`, `docs/security.md`.

## 1. Lingkup dan batas domain

Mencakup **acuan belanja modal**, **SPJ/katalog dokumen per SPK**, **alokasi item SPJ ke kodering** menjadi baris **realisasi** (snapshot), **pengajuan kirim & verifikasi**, **rekapan lintas sekolah (admin)**, serta **cetak/unduh laporan**. Inventaris unit ada di [Pencatatan BM](pencatatan-bm.md); **sinkronisasi inventaris** dipicu dari persetujuan kunci laporan (lihat `lintas-domain-state-machine.md`).

## 2. Aktor dan permission (ringkas)

Sumber: `routes/web.php`, `docs/security.md`.

| Kemampuan | Permission / gate | Catatan |
|---|---|---|
| Dashboard BM (`GET /dashboard`) | `lihat-laporan-bm` (middleware grup terkait) | Operator tanpa `sekolah_id` umumnya ditolak di controller (403) pada jalur yang relevan. |
| Lihat grup SPJ/Realisasi/Cetak/Rekapan di area `pelaporan-bm.*` | `lihat-laporan-bm` (middleware grup prefix) | |
| Buat SPJ, simpan alokasi realisasi, kirim laporan, simpan acuan, import acuan | `input-spj-bm` | |
| Edit SPJ (item/SPK), edit alokasi (konteks tertentu) | `edit-spj-bm` | |
| Hapus SPJ/item/SPK, hapus acuan (termasuk massal) | `hapus-spj-bm` | |
| Unduh rekap/SPJ terkait (`GET /pelaporan-bm/unduh`) | `unduh-rekap-bm` + `throttle:30,1` | Nama route: `pelaporan-bm.unduh`. |
| Unduh daftar realisasi (`GET /pelaporan-bm/realisasi/unduh`) | `unduh-rekap-bm` + `throttle:30,1` | Nama route: `pelaporan-bm.realisasi.unduh`. |
| Cetak laporan (halaman, check, unduh) | `cetak-laporan-bm` + throttle/check sesuai rute | |
| Kunci/buka kunci laporan | `kunci-laporan-bm` | |
| Verifikasi status kirim (set draft/menunggu_approval/disetujui) | `verifikasi-laporan-bm` | |
| Pencarian barang untuk SPJ (`GET /pelaporan-bm/cari-barang`) | **tanpa permission tambahan** selain grup `lihat-laporan-bm` | `throttle:120,1`. |

Isolasi tenant: controller yang memakai `ResolvesSekolah` menentukan `sekolah_id` dari user; operator tanpa `sekolah_id` → 403 pada jalur yang relevan. Operator dipaksa sekolah sendiri; admin lintas sekolah mengikuti filter/parameter yang diizinkan. Mutasi wajib cek kepemilikan (IDOR) selain validasi UUID.

Lock periode: `LocksPelaporanBmPeriod` + `KunciLaporan`. Aksi tulis SPJ, alokasi realisasi, perubahan acuan memeriksa status kunci/kirim sesuai implementasi (umumnya tolak jika `status_kunci` atau `status_kirim` `menunggu_approval`/`disetujui`, kecuali kebijakan eksplisit di kode seperti pengecualian super_admin pada kasus tertentu di `InputRealisasiController`).

## 3. Model data (ringkas)

| Model | Tabel | Peran |
|---|---|---|
| `Acuan` | `pelaporan_bm_acuan` | Target anggaran per sekolah/bulan/tahun (kodering, uraian, nominal). |
| `Spj` | `pelaporan_bm_spj` | Katalog item SPJ/SPK (snapshot administratif + spesifikasi barang). |
| `Realisasi` | `pelaporan_bm_realisasi` | Baris realisasi teralokasi (snapshot dari SPJ + `kodering_belanja`, `acuan_id`, periode). |
| `KunciLaporan` | `pelaporan_bm_kunci_laporan` | `status_kunci`, `status_kirim` (`draft`/`menunggu_approval`/`disetujui`), metadata kunci/kirim. |

Relasi penting sesuai model: `Spj` terkait realisasi (hapus sinkron ditangani service/controller); `Realisasi` terikat `acuan_id`/kodering per periode.

## 4. Peta flow (ringkasan)

| ID | Flow | Pemicu utama (route) | Hasil |
|---|---|---|---|
| PBM-01 | Dashboard | `GET /dashboard` (`dashboard`) | Ringkasan admin/sekolah sesuai role (lihat `DashboardController`). |
| PBM-02 | Pilih bulan → katalog SPJ | `GET pelaporan-bm/spj/pilih-bulan`, `GET pelaporan-bm/spj` | Daftar grup SPK, acuan bulan, status lock/kirim, hak import (allowlist NPSN). |
| PBM-03 | Buat/edit SPK (multi-item) | `GET pelaporan-bm/spj/create` (`permission:input-spj-bm`), `GET pelaporan-bm/spj/edit-spk/{no_spk}` (`permission:edit-spj-bm`), `POST pelaporan-bm/spj/store-spk` (`input-spj-bm|edit-spj-bm`) | Simpan/perbarui SPJ; sinkron snapshot ke realisasi teralokasi sesuai implementasi. |
| PBM-04 | Import Excel SPJ | `POST pelaporan-bm/spj/import` (`input-spj-bm`, throttle `10,1`, allowlist NPSN) | All-or-nothing; error detail via flash/errors. |
| PBM-05 | Mutasi SPJ item/SPK | `POST pelaporan-bm/spj` (`input-spj-bm`), `PUT pelaporan-bm/spj/{spj}` (`edit-spj-bm`), `DELETE pelaporan-bm/spj/{spj}` (`hapus-spj-bm`), `DELETE pelaporan-bm/spj/spk/{no_spk}` (`hapus-spj-bm`) | Hapus dapat menghapus realisasi terkait dalam transaksi (lihat `SpjController`). |
| PBM-06 | Cari barang (autocomplete/helper) | `GET pelaporan-bm/cari-barang` (grup `lihat-laporan-bm`, throttle `120,1`) | Pencarian master barang/katalog sesuai implementasi `SpjController@cariBarang`. |
| PBM-07 | Unduh SPJ/rekap terkait (jalur khusus) | `GET pelaporan-bm/unduh` (`unduh-rekap-bm`, throttle `30,1`) | Ekspor/unduh sesuai `SpjController@unduh` (beda dari unduh realisasi). |
| PBM-08 | Pilih bulan input realisasi | `GET pelaporan-bm/input-realisasi/pilih-bulan` | Pilih `bulan_realisasi`. |
| PBM-09 | Daftar target acuan vs realisasi | `GET pelaporan-bm/input-realisasi` | Rekap per kodering: acuan vs realisasi, status lock/kirim, flag `canEditApproved` (super_admin) sesuai kode. |
| PBM-10 | Tambah alokasi | `GET pelaporan-bm/input-realisasi/tambah` (`input-spj-bm`), `POST pelaporan-bm/input-realisasi/simpan` (`input-spj-bm`) | Validasi sisa anggaran/lock, buat baris realisasi (snapshot). |
| PBM-11 | Edit alokasi (bulk/edit flow) | `GET pelaporan-bm/input-realisasi/edit` (`edit-spj-bm`), `POST pelaporan-bm/input-realisasi/update` (`edit-spj-bm`) | Alur edit alokasi sesuai `InputRealisasiController@edit` / `@update` (termasuk kebijakan super_admin setelah disetujui bila ada di kode). |
| PBM-12 | Kirim laporan | `POST pelaporan-bm/input-realisasi/kirim-laporan` (`input-spj-bm`) | Validasi syarat (mis. kekurangan 0) → set status kirim/kunci sesuai implementasi. |
| PBM-13 | Data realisasi (list/filter/unduh) | `GET pelaporan-bm/realisasi`, `GET pelaporan-bm/realisasi/unduh` (`unduh-rekap-bm`, throttle `30,1`) | Sumber `pelaporan_bm_realisasi`; ekspor Excel, respons 204 + cookie `download_status=empty` jika kosong. |
| PBM-14 | Cetak laporan | `GET pelaporan-bm/cetak` (`cetak-laporan-bm`), `POST pelaporan-bm/cetak/check` (`throttle:60,1`), `GET pelaporan-bm/cetak/unduh` (`throttle:30,1`) | Pre-flight jumlah baris; unduh XLSX; 204 + cookie jika kosong. |
| PBM-15 | Acuan (CRUD/import massal) | `GET pelaporan-bm/acuan`, `POST .../acuan` (`input-spj-bm`), `POST .../acuan/import` (`input-spj-bm`, throttle `10,1`), `POST .../acuan/destroy-all` (`hapus-spj-bm`, throttle `10,1`), `DELETE .../acuan/{acuan}` (`hapus-spj-bm`) | Tenant dipaksa; mutasi dicek lock periode; tidak boleh hapus jika sudah dipakai (SPJ/realisasi) sesuai `AcuanController`. |
| PBM-16 | Rekapan (admin) | `GET pelaporan-bm/rekapan` (`lihat-laporan-bm`) | Rekap lintas sekolah/bulan/tahun dari acuan + realisasi terikat `acuan_id`, status kirim/kunci, detail di UI. |
| PBM-17 | Kunci & verifikasi status | `POST pelaporan-bm/kunci-laporan/toggle` (`kunci-laporan-bm`), `POST pelaporan-bm/kunci-laporan/status` (`verifikasi-laporan-bm`) | Toggle kunci; ubah `status_kirim`/kunci; bila disetujui memicu sinkronisasi inventaris (best-effort). |

## 5. Alur inti (naratif singkat)

### 5.1 Dashboard (`PBM-01`)

Lihat `DashboardController@index`. Admin memakai filter `bulan`/`tahun` (default saat ini); target dihitung dari sekolah yang punya acuan pada periode filter (sesuai kode). Realisasi “sudah” mengacu `Realisasi` final (bukan hanya SPJ). Operator melihat ringkasan sekolah + rekap tahunan (termasuk `KunciLaporan` periode terkait).

### 5.2 SPJ (`PBM-02`–`PBM-06`)

- `pilihBulan` → `index?bulan=`: query `Spj` + relasi, filter tenant/operator, urutan terbaru, hitung `realisasiIds`, status lock/kirim dari `KunciLaporan`, daftar acuan bulan dan total nominal. UI: `resources/js/Pages/PelaporanBm/Spj/*`.
- `create` / `editSpk`: muat form SPK; tolak jika dikunci/dikirim sesuai aturan.
- `storeSpk`: validasi multi-item, transaksi, cek lock; hitung `nilai_perolehan = volume × harga satuan`; jika terkunci → flash error.
- `import`: middleware permission `input-spj-bm`, throttle `10,1`, allowlist NPSN di `SpjController`; all-or-nothing, detail error via flash/errors.
- `store` / `update` / `destroy` / `destroySpk`: hak sesuai permission tabel di atas; penghapusan dapat mempengaruhi realisasi dalam transaksi (cek kode).
- `cari-barang`: endpoint bantuan (throttle `120,1`) untuk UI SPJ.

### 5.3 Unduh SPJ/rekap jalur `unduh` (`PBM-07`)

`GET /pelaporan-bm/unduh` (`pelaporan-bm.unduh`) dengan `permission:unduh-rekap-bm` dan `throttle:30,1`. Perilaku ekspor/format mengikuti `SpjController@unduh` (berbeda dari unduh daftar realisasi). Jangan samakan dengan `realisasi/unduh`.

### 5.4 Input realisasi (`PBM-08`–`PBM-12`)

- `pilihBulan` → `index`: rekap per kodering acuan bulan: acuan, realisasi, kekurangan; status lock/kirim dari `KunciLaporan`; `canEditApproved` untuk `super_admin` saat disetujui (sesuai kode).
- `tambah` (`input-spj-bm`): tolak jika dikunci/kirim kecuali pengecualian; muat acuan, sisa anggaran, SPJ bulan itu dan `teralokasiIds`; simpan via `simpan`.
- `simpan`: validasi `kodering`, `bulan_realisasi`, `item_ids[]`; cek lock; transaksi membuat baris `Realisasi` snapshot dengan batas sisa anggaran.
- `edit` (`edit-spj-bm`) / `update` (`edit-spj-bm`): alur edit alokasi yang ada (lihat `InputRealisasiController`); hormati lock dan kebijakan super_admin yang tertulis di kode.
- `kirim-laporan` (`input-spj-bm`): syarat (mis. total kekurangan 0) → update `KunciLaporan` ke `menunggu_approval` dan kunci sesuai implementasi.

### 5.5 Realisasi, cetak, acuan, rekapan (`PBM-13`–`PBM-16`)

- **Realisasi** `index`: query `Realisasi` + `sekolah`, `resolveSekolahId`, filter `filter_barang`, `filter_sekolah`, `filter_bulan`, `filter_tahun` (berdasarkan `ba_tgl`), paginate 25, `availableYears` dari `ba_tgl`. **Unduh**: filter sama, urut ASC; kosong → 204 + cookie `download_status=empty`; export `RealisasiBmExport`, log `unduh-realisasi`, cookie `complete`.
- **Cetak** `show`: tahun dari `Realisasi.ba_tgl`, pilih sekolah; `check` hitung jumlah baris untuk kombinasi filter → `{"total_rows": n}`; `unduh` kosong → 204 + `download_status=empty`, export `CetakBmExport`, log `unduh-cetak`, cookie `complete`.
- **Acuan**: `index` filter `bulan`/`tahun`/`search_satuan`/`sekolah_id` (operator dipaksa); `store` override `sekolah_id`, default tahun dari konteks, cek lock periode dalam transaksi; `destroy`/`destroyAll` cek tenant, lock periode, tolak jika dipakai oleh SPJ/realisasi, log aktivitas pada kasus massal.
- **Rekapan**: baris dari sekolah yang punya acuan di periode (sesuai kode), realisasi dijumlah dari `Realisasi` terikat `acuan_id` pada acuan tersebut, tampilkan status kirim/kunci dan detail log fisik di UI.

### 5.6 Kunci & verifikasi (`PBM-17`)

- `toggle`: `kunci-laporan-bm` (role/admin sesuai middleware rute), toggle `status_kunci`, set metadata dan log.
- `updateStatus`: set `status_kirim` (`draft`/`menunggu_approval`/`disetujui`) dengan aturan kunci/metadata; jika `disetujui` memanggil `SinkronisasiInventarisService::syncPeriode(...)` (user pelaku). Gagal sync → status tetap disetujui, flash `warning`, log error; jika `perluReview > 0` → warning spesifik. Rinci di `lintas-domain-state-machine.md`.

## 6. State machine (ringkas)

| `status_kirim` | `status_kunci` (umum) | Catatan |
|---|---|---|
| `draft` | sering false | Operator dapat mengedit sesuai permission dan tidak ada lock keras lain dari policy |
| `menunggu_approval` | umumnya true setelah kirim | Verifikasi admin; operator read-only |
| `disetujui` | true | Gate sinkronisasi inventaris; pengecualian edit super_admin pada alur tertentu di kode |

Detail: `docs/requirements/workflow-belanja-modal.md` dan implementasi controller.

## 7. Integrasi dengan Pencatatan BM

Gate sinkronisasi inventaris adalah `status_kirim = 'disetujui'` pada periode sama — lihat [Pencatatan BM](pencatatan-bm.md) dan `lintas-domain-state-machine.md`.

## 8. Jebakan yang diketahui

1. Operator tanpa `sekolah_id` sering 403 di banyak endpoint Pelaporan BM.
2. Banyak mutasi memeriksa lock periode; tampilkan flash error di UI.
3. Import SPJ dibatasi allowlist NPSN.
4. Unduh kosong: 204 + cookie `download_status=empty` (bukan file) — pola sama di beberapa endpoint; frontend menunggu cookie/poll sesuai app.
5. Dashboard admin “sudah realisasi” mengacu `Realisasi`, bukan `Spj`; rekapan memakai realisasi terikat `acuan_id`.
6. Sinkronisasi inventaris gagal setelah persetujuan → status tetap disetujui, perlu cek log/tindak lanjut manual (`super_admin`).
7. `GET pelaporan-bm/unduh` (PBM-07) berbeda tujuan dengan `realisasi/unduh` (PBM-13); jangan keliru nama route.

## 9. File terkait (cepat)

- Routes: `routes/web.php` (blok `pelaporan-bm.*`, `dashboard`, `rekapan`).
- Controller: `app/Http/Controllers/PelaporanBm/*`.
- Model: `app/Models/PelaporanBm/{Acuan,Spj,Realisasi,KunciLaporan}.php`.
- Frontend: `resources/js/Pages/PelaporanBm/**`.
- Export: `app/Exports/*Bm*`, `RealisasiBmExport`, `CetakBmExport`, dll. sesuai kode.
- Test: cari pola `PelaporanBm`, `Spj`, `Acuan`, `Realisasi`, `Rekapan`, `KunciLaporan`, `InputRealisasi` di `tests/Feature`/`tests/Unit`.

## 10. Checklist perubahan (agar tetap konsisten)

Saat mengubah Pelaporan BM, update dokumen ini jika menyentuh: route baru/ubah/hapus, permission/middleware, state machine kunci/status kirim, lock periode, tenant 403/404, respons unduh (204+cookie), atau trigger sinkronisasi.