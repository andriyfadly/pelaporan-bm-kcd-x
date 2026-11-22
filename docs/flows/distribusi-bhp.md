# Flow Distribusi BHP (Barang Habis Pakai)

> **Status: Fixed** Rujukan utama: `routes/web.php` (prefix `distribusi-bhp`, grup `bos`/`bopd` + kompatibel tanpa prefix), controller `App\Http\Controllers\DistribusiBhp\*`, model `App\Models\DistribusiBhp\*`, service (`StokService`, `DokumenService`, `SaldoAwalService`), support `App\Support\DistribusiBhp\SumberDana`, middleware `sumber-dana-bhp`, serta dokumen `docs/requirements/workflow-distribusi-bhp.md`, `docs/requirements/distribusi-bhp-prd.md`, `docs/modules.md`. Alur pengajuan, review, dan sinkronisasi stok sudah terimplementasi sesuai kode dengan aturan sumber dana (BOS/BOPD), tenant isolation via `SumberDana::dengan()`, dan gate `status_kirim='disetujui'` untuk sinkronisasi inventaris.
>
> Riset via subagent scout **tidak tersedia** pada penulisan awal karena kegagalan provider; verifikasi dengan uji/manual bila perlu.

## 1. Lingkup dan batas domain

Mengelola BHP dari **penerimaan/saldo awal**, **master (rekening, barang, penyedia, divisi, dll.)**, **pengajuan oleh divisi/operator**, **review & persetujuan admin**, hingga **dokumen distribusi/laporan** dan **pengaturan format nomor**. Terpisah dari Pelaporan BM dan Pencatatan BM.

### Sumber dana (wajib dipahami)

- Nilai sah: `BOS`, `BOPD` (lihat `SumberDana`).
- **Tabel transaksi & master barang dipisah fisik**: tabel `bhp_bos_*` / `bhp_bopd_*` melalui trait `HasSumberDanaTable`. Tidak ada kolom `sumber_dana` pada tabel-tabel tersebut; `bhp_penyedia` dan `bhp_divisi` **satu tabel bersama**.
- Setiap request BHP harus berjalan dalam konteks sumber dana: URL berprefix `/distribusi-bhp/bos/...` atau `/distribusi-bhp/bopd/...` (middleware `sumber-dana-bhp` menyetel sumber dana aktif). Route tanpa prefix ada untuk kompatibilitas lama dan default ke `BOS`.
- Query/mutasi selalu dibungkus `SumberDana::dengan($sumberDana, fn () => ...)` di controller/service yang relevan. Validasi `exists` untuk master “per dana” harus menggunakan `SumberDana::existsRule(...)`, bukan nama tabel literal.
- `SumberDana::pilih($input)` urutan: input eksplisit → sumber dana dari middleware → default `BOS`. Jangan hardcode `?? 'BOS'` sendiri di tempat yang harus mengikuti konteks.

### Isolasi tenant (sekolah) dan divisi

- Controller memakai `ResolvesSekolah` untuk `sekolah_id` (operator dipaksa sekolah sendiri; admin lintas sekolah tergantung pola controller).
- Banyak aksi membatasi akses: pengajuan boleh diakses hanya jika `sekolah_id` cocok (dan untuk operator divisi sering juga `divisi_id` cocok). Review menolak jika reviewer mencoba memproses pengajuan sekolah/divisi di luar cakupan.
- Operator divisi: `PengajuanController` memfilter list ke `divisi_id` user; pembuatan/pembaruan draft memastikan `divisi_id` user valid untuk sekolah pengajuan (`pastikanDivisiSekolah`). Review juga menolak **self-approval** (pembuat tidak boleh approve/reject sendiri).
- Dokumen tambahan (snapshot stok opname/buku dll.) harus selalu terfilter `sekolah_id` milik pengajuan (sesuai catatan di `docs/requirements/workflow-distribusi-bhp.md` §6/6b).

## 2. Aktor dan permission (ringkas)

Sumber: `routes/web.php`. Contoh kemampuan (tidak eksklusif):

| Area | Permission (contoh) | Catatan |
|---|---|---|
| Dashboard BHP | `lihat-distribusi-bhp` | Statistik BOS+BOPD dijumlahkan di dashboard (iterasi kedua sumber dana). |
| Settings BHP | `kelola-master-bhp` | `GET/PUT /distribusi-bhp/settings` |
| Divisi | `kelola-divisi-bhp` | Termasuk reset password/toggle operator divisi (lihat route). |
| Penyedia | lihat `lihat-distribusi-bhp`, kelola `kelola-penyedia-bhp` | |
| Pengajuan (operator/divisi sesuai hak) | `kelola-pengajuan-bhp` | CRUD draft, submit pada rute per sumber dana. |
| Review pengajuan | `review-pengajuan-bhp` | List + approve/reject (tanpa prefix sumber dana di action review yang sama grup; sumber dana dikirim via query/input sesuai implementasi). |
| Penerimaan, barang, kode rekening, saldo awal, laporan, dokumen | Lihat `routes/web.php` | Pola serupa: middleware permission per route/group. |

Route per sumber dana: `Route::prefix('bos')->name('bos.')...`, `prefix('bopd')->name('bopd.')...`, ditambah grup tanpa prefix yang memakai middleware `sumber-dana-bhp` (default BOS).

## 3. Model data (ringkas, domain BHP)

Model utama di `App\Models\DistribusiBhp\*` (nama tabel mengikuti `HasSumberDanaTable` untuk transaksi/master yang dipisah):

- `Barang`, `KodeRekening` (per sumber dana via tabel fisik)
- `Penyedia` (umum bersama), `Divisi` (umum bersama)
- `Penerimaan`, `PenerimaanDetail`
- `SaldoAwal`
- `Pengajuan`, `PengajuanDetail`
- `Dokumen`, nomor/format terkait via `Settings` BHP (lihat `DokumenService`, `DokumenSettings`, `DokumenNumberFormatter`)

Stok dihitung/diupdate melalui `StokService` (fisik = penerimaan + saldo awal; terpakai dari pengajuan approved; reservasi dari submitted; tersedia = max(0, fisik − terpakai − reservasi)). Detail rumus: `docs/requirements/workflow-distribusi-bhp.md` §1, §5.

## 4. Peta flow (ringkasan)

| ID | Flow | Pemicu utama | Hasil |
|---|---|---|---|
| BHP-01 | Dashboard | `GET /distribusi-bhp/dashboard` | Statistik gabungan BOS+BOPD (total barang, draft/submitted/approved, total penerimaan) ter-scope sekolah/divisi sesuai role. |
| BHP-02 | Settings & master (umum/BHP) | `settings`, `divisi`, `penyedia`, `barang`, `kode-rekening` | CRUD master; beberapa aksi lintas divisi/operator. |
| BHP-03 | Penerimaan / Buku penerimaan | Route penerimaan (`PenerimaanController`) termasuk `GET /distribusi-bhp/penerimaan/lunas-bayar` (`penerimaan.lunas-bayar`, permission `kelola-penerimaan-bhp`) | Input dokumen penerimaan + detail, validasi sumber dana/barang/rekening, commit update stok fisik (lihat `workflow-distribusi-bhp.md` §1); `lunas-bayar` adalah subhalaman/aksi bantu penerimaan sesuai controller. |
| BHP-04 | Saldo awal & import | `SaldoAwalController`, `SaldoAwalService`, `SaldoAwalImport` | Saldo awal per barang/periode/sumber dana; import all-or-nothing. |
| BHP-05 | Pengajuan (divisi/operator) | `GET/POST/PUT/DELETE /distribusi-bhp/{bos|bopd}/pengajuan...`, `POST .../submit` | Draft → submit (reservasi). Draft hanya bisa diubah/dihapus bila `draft`. |
| BHP-06 | Review pengajuan (admin) | `GET /distribusi-bhp/review-pengajuan`, `POST .../approve`, `POST .../reject` | Approve penuh/koreksi/jumlah atau reject (alasan wajib). Transaksi atomik via `DokumenService` + `StokService`. |
| BHP-07 | Dokumen & laporan | `DokumenController`, `LaporanController`, export/PDF sesuai route | Generate/lihat dokumen hasil approval dan laporan (stok, buku, dll.). |
| BHP-08 | Settings nomor dokumen | `SettingsController` | Format nomor configurable; sequence aman (lock/unique + retry sesuai service). |

## 5. Alur inti (naratif singkat)

### 5.1 Dashboard (`BHP-01`)

- `DashboardController@index`: `resolveSekolahId($request)`. `divisiId` dari user (`$request->user()->divisi_id`) → operator divisi hanya melihat agregat divisinya (pengajuan), penerimaan tetap ter-scope sekolah. Loop `SumberDana::SEMUA`, di dalam `SumberDana::dengan($dana, ...)` menghitung: jumlah barang (`Barang::count()`), pengajuan `draft/submitted/approved` (query scoped), jumlah penerimaan. Render `DistribusiBhp/Dashboard` dengan `stats` gabungan BOS+BOPD.

### 5.2 Pengajuan (`BHP-05`)

- **Index** (`PengajuanController@index`): validasi optional `sumber_dana` (in SEMUA), normalisasi (`SumberDana::normalisasi`). Dalam `SumberDana::dengan` mengambil `Pengajuan` dengan `details.barang`, filter `sekolah_id`, filter `divisi_id` jika user punya divisi, paginate. Mengambil daftar `Barang` (ordered) lalu `StokService::rekapPerBarang($barang, $sekolahId)` untuk menampilkan `tersedia` per barang di UI. Props: `items`, `sumberDana`, `barang` (dengan `tersedia`).
- **Store**: validasi payload (`sumber_dana` required in SEMUA, `tanggal`, `items[]` dengan `barang_id` melalui `SumberDana::existsRule('barang', $sumberDana)`, `jumlah_diminta >=1`, `keperluan` nullable). `SumberDana::dengan($sumberDana, ...)`: `pastikanDivisiSekolah` (divisi user harus milik sekolah pengajuan); pastikan setiap barang ada dan sesuai dana (`barangsSesuaiDana`). Transaksi: buat `Pengajuan` (`status=draft`, `divisi_id` dari user, `user_id`, `sekolah_id`) lalu `simpanDetail`. Redirect ke `distribusi-bhp.{bos|bopd}.pengajuan.index` sesuai `sumberDana`.
- **Update** (`update`): cek sekolah (`authorizeSekolah`), hanya `draft`. Validasi payload + normalisasi sumber dana; sumber dana pengajuan **tidak boleh diubah**. Dalam konteks dana, cek divisi sekolah, ambil barang sesuai dana. Transaksi: lock `Pengajuan`, pastikan masih `draft`, update `tanggal`, hapus semua detail lalu simpan ulang (`simpanDetail`).
- **Destroy**: cek sekolah, hanya `draft`. Input `sumber_dana` request harus cocok dengan `pengajuan->sumber_dana` (normalisasi). Transaksi lock + pastikan `draft`, hapus detail lalu hapus pengajuan. Redirect ke index sumber dana pengajuan.
- **Submit** (`submit`): cek sekolah; `sumber_dana` dari input atau fallback ke `pengajuan->sumber_dana`, harus cocok. Memanggil `StokService::submit($pengajuan->load('details'))`; jika `RuntimeException` → flash error. Sukses → flash sukses (“disubmit dan stok direservasi”). **Catatan:** UI desain yang disengaja di proyek ini: “satu tombol Ajukan Pengajuan” dan autosave lokal (lihat `AGENTS.md` §4) — perilaku server tetap submit setelah create dalam alur tertentu sesuai implementasi terbaru; cek kode `PengajuanController@store`/frontend saat mengubah.

**Aturan barang per dana:** sebelum transaksi, `barangsSesuaiDana` memastikan setiap `barang_id` ada di hasil query `Barang` dalam konteks dana yang sama (karena tabel terpisah). Jika tidak ada → `ValidationException` per item.

### 5.3 Review pengajuan (`BHP-06`)

- **Index** (`ReviewPengajuanController@index`): `resolveSekolahId`, `sumber_dana` dari query (default `BOS`, normalisasi), `SumberDana::dengan` memuat `Pengajuan` `submitted` + `details.barang`, `pemohon`, filter sekolah, paginate. Jika ada sekolah dan ada item, mengecek kelengkapan pejabat penandatangan (jabatan dari `Jabatan::KODE_PENANDATANGAN`) via `PenugasanPegawaiService::berlakuPada(..., now()->toDateString())`; jika kurang lengkap set `pejabatWarning` (approval tetap divalidasi ulang di service). Render `DistribusiBhp/Review/Index`.
- **authorizeSekolah**: jika `sekolahId` resolver mengarah ke sekolah tertentu, `pengajuan->sekolah_id` harus sama; jika user punya `divisi_id`, `pengajuan->divisi_id` harus sama (operator divisi tidak boleh review pengajuan divisi lain). Lintasan lain → `abort(403)`.
- **pastikanBukanPembuat**: `pengajuan->user_id` sama dengan user login → `abort(403)`.
- **Approve**: validasi `review_notes` nullable, `jumlah_disetujui` array of int min 0 (opsional). Memanggil `DokumenService::approveDanGenerate($pengajuan->load('details'), user, jumlah_disetujui, review_notes)`. `RuntimeException` → flash error. Sukses → flash sukses (stok + dokumen). **Atomic:** harus dalam satu transaksi DB mengikuti kontrak di `workflow-distribusi-bhp.md` §5 (lock pengajuan, status masih `submitted`, lock barang, hitung ulang stok, validasi jumlah approved, update detail approved, konversi reservasi→pemakaian, buat nomor dokumen via sequence lock/unique+retry, buat dokumen, audit log). Jika gagal → rollback.
- **Reject**: validasi `rejection_reason` required. `StokService::reject(..., reason)`. `RuntimeException` → flash error; sukses → reservasi dilepas, status `rejected` (sesuai service).

### 5.4 Penerimaan, saldo awal, buku penerimaan (`BHP-03`, `BHP-04`)

- **Penerimaan** (`PenerimaanController`): CRUD/pencatatan dokumen penerimaan (invoice, BAST, kwitansi, penyedia, tanggal, sumber dana) dan detail barang masuk; commit menerapkan perubahan stok fisik dalam transaksi (lihat `workflow-distribusi-bhp.md` §1). Route terkait termasuk `GET /distribusi-bhp/penerimaan/lunas-bayar` (`penerimaan.lunas-bayar`, permission `kelola-penerimaan-bhp`, `PenerimaanController@lunasBayar`). Halaman: `DistribusiBhp/Penerimaan/*`, `DistribusiBhp/BukuPenerimaan/*` sesuai route/nama controller.
- **Saldo awal** (`SaldoAwalController`, `SaldoAwalService`, `SaldoAwalImport`): saldo per barang/periode/sumber dana; import Excel/CSV **all-or-nothing** dengan validasi baris.
- **Dokumen BAP/BAST internal** terkait penerimaan/buku: lihat route `buku-penerimaan`, `bap`, `bast` di `web.php` dan controller terkait.

### 5.5 Dokumen & laporan (`BHP-07`)

- `DokumenController`: unduhan/preview dokumen distribusi yang dihasilkan dari approval (NPB, SPB, SPPB, BAST, BAP, Stok Opname, Buku Pengeluaran, Buku Penyaluran, Kartu Barang, Kartu Persediaan, BAST Internal — sesuai `docs/requirements/distribusi-bhp-prd.md` §8 dan implementasi view/export).
- `LaporanController`: laporan stok, penerimaan, pengeluaran, dsb., ter-filter sumber dana/periode/sekolah sesuai route.
- Nomor dokumen dihasilkan atomik dalam transaksi approval (lock sequence row + insert first row anti-race via unique constraint + retry) sesuai `workflow-distribusi-bhp.md` §5.

### 5.6 Settings (`BHP-08`)

- `SettingsController@edit/update` (permission `kelola-master-bhp`): format nomor dokumen BHP dan pengaturan terkait (`DokumenSettings`, `DokumenNumberFormatter`). Perubahan format tidak boleh membuat duplikat nomor untuk transaksi yang sama periode/aturan (tanggung jawab service/sequence).

## 6. State machine (pengajuan)

| State | Aksi valid (ringkas) | Larangan |
|---|---|---|
| `draft` | Edit, submit, delete oleh pemilik berwenang (divisi/sekolah sesuai hak) | — |
| `submitted` | Review oleh admin/reviewer yang berwenang: approve (penuh/koreksi) atau reject (alasan wajib); tidak boleh diedit pemohon | Edit oleh pembuat; approve ganda |
| `approved` | Lihat, cetak/dokumen; tidak boleh approve ulang | Edit, submit ulang sebagai flow yang sama (buat pengajuan baru jika perlu) |
| `rejected` | Lihat alasan; buat pengajuan baru atau revisi sesuai policy bisnis | Edit draft lama yang sudah rejected dalam konteks flow yang sama (biasanya buat baru) |

Catatan: self-approval ditolak di `ReviewPengajuanController`.

## 7. Isolasi scope & sumber dana (cek cepat)

1. **Prefix URL menentukan konteks**: aksi di halaman BOPD harus lewat `/distribusi-bhp/bopd/...`; route flat default BOS → aksi dari UI BOPD bisa salah tabel jika tidak dikirim ke prefix benar.
2. **Jangan gunakan nama tabel literal** di `exists` untuk master per dana; pakai `SumberDana::existsRule(...)`.
3. **Operator divisi**: list/index pengajuan dibatasi `divisi_id` user; store/update memastikan divisi milik sekolah; review membatasi divisi.
4. **Dashboard** menjumlahkan BOS+BOPD; angka per sumber dana bisa berbeda per halaman pengajuan/penerimaan (lihat konteks `sumberDana`).

## 8. Jebakan yang diketahui

1. **Race condition stok pada approval**: approval harus lock pengajuan, lock barang terkait, hitung ulang stok (fisik/terpakai/reservasi), lalu baru ubah. Jangan melakukan kalkulasi “optimistic” di luar transaksi sesuai kontrak `StokService`/`DokumenService`.
2. **Nomor dokumen duplikat**: gunakan mekanisme lock + unique constraint + retry di service; jangan generate di controller tanpa kontrak itu.
3. **Draft dengan `barang_id` yang sudah tidak valid**: UI autosave lokal (AGENTS.md) perlu menjaga agar tidak menyimpan id mati; server tetap validasi `exists` per konteks dana.
4. **Pejabat penandatangan tidak lengkap**: review menampilkan peringatan jika kurang lengkap pada tanggal hari ini; approval bisa tetap gagal di service jika syarat tidak terpenuhi — tangani pesan error dari `RuntimeException`.
5. **Konkurensi edit draft**: update/delete memakai lock + cek status `draft` lagi di transaksi; tetap bisa gagal jika sudah disubmit bersamaan.

## 9. File terkait (cepat)

- Routes: `routes/web.php` (bagian `distribusi-bhp`, helper `$bhpRoutes`, prefix `bos`/`bopd`, middleware `sumber-dana-bhp`).
- Controller: `app/Http/Controllers/DistribusiBhp/*`.
- Services: `app/Services/DistribusiBhp/{StokService,DokumenService,SaldoAwalService}.php`, support `App\Support\DistribusiBhp\SumberDana`.
- Model: `app/Models/DistribusiBhp/*`, penugasan/jabatan terkait review: `App\Models\Master\Jabatan`, service penugasan master.
- Frontend: `resources/js/Pages/DistribusiBhp/**` (Dashboard, Pengajuan, Review, Penerimaan, Laporan, Settings, dsb.).
- Test: cari `DistribusiBhp`, `Pengajuan`, `Review`, `Stok`, `SaldoAwal`, `Dokumen` di `tests/Feature`/`tests/Unit` (mis. `tests/Feature/Bhp*Test.php`).