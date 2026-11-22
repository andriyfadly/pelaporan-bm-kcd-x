# Flow Lintas Domain — State Machine & Gate Sinkronisasi

> **Status: Fixed** Rujukan utama: `app/Models/PelaporanBm/KunciLaporan.php`, `app/Http/Controllers/PelaporanBm/KunciLaporanController.php`, `app/Services/PencatatanBm/SinkronisasiInventarisService.php`, `app/Http/Controllers/PencatatanBm/SyncController.php`, model/flow BHP di `app/Models/DistribusiBhp/*`, controller `DistribusiBhp/{PengajuanController,ReviewPengajuanController}.php`, service `DistribusiBhp/{StokService,DokumenService}.php`, serta `docs/flows/{pelaporan-bm,distribusi-bhp,pencatatan-bm}.md`. State machine lintas domain (kunci laporan → sinkronisasi → inventaris, status pengajuan BHP) sudah terimplementasi sesuai kode dengan gate `status_kirim='disetujui'` sebagai penentu sinkronisasi inventaris.

## 1. Tujuan & ruang lingkup

Dokumen ini merangkum **state machine lintas domain** yang sudah “paten” di kode:

- Pelaporan BM: kunci laporan + status kirim → memicu **gate sinkronisasi** ke Pencatatan BM.
- Distribusi BHP: status pengajuan (dan implikasi stok/dokumen), serta catatan status penerimaan (implisit).
- Pencatatan BM: gate sinkronisasi hanya dari laporan BM yang **`status_kirim = 'disetujui'`**.

## 2. Pelaporan BM — `pelaporan_bm_kunci_laporan`

### 2.1 Field/state

Model: `App\Models\PelaporanBm\KunciLaporan`.

| Field | Tipe | Nilai/arti |
|---|---|---|
| `sekolah_id`, `bulan`, `tahun` | identitas | Satu record logis per (sekolah, bulan, tahun) |
| `status_kunci` | boolean | `true` = laporan dikunci (read-only untuk operator/admin biasa sesuai policy) |
| `status_kirim` | string | `draft` \| `menunggu_approval` \| `disetujui` (nilai aktual di kode) |
| `dikunci_pada`, `dikunci_oleh` | metadata | Diisi saat dikunci |
| `dikirim_pada` | datetime/null | Diisi saat kirim/approval sesuai alur |

Log aktivitas (`LogsActivity`, log `sistem`): `sekolah_id`, `bulan`, `tahun`, `status_kunci`, `status_kirim`, `dikunci_oleh`.

### 2.2 Aksi & efek (dari `KunciLaporanController`)

- **Toggle kunci** (`POST /pelaporan-bm/kunci-laporan/toggle`, permission `kunci-laporan-bm`, role `super_admin|admin_kcd`): membalik `status_kunci`, set/clear `dikunci_pada` dan `dikunci_oleh`, log aktivitas `kunci-laporan`/`buka-kunci-laporan`.  
  **Penting:** toggle **tidak** otomatis mengubah `status_kirim` menjadi draft; itu diatur oleh `updateStatus`.

- **Update status kirim** (`POST /pelaporan-bm/kunci-laporan/status`, permission `verifikasi-laporan-bm`, role `super_admin|admin_kcd`): `status_kirim` hanya boleh `draft|menunggu_approval|disetujui`.

  Perilaku inti (sesuai `KunciLaporanController@updateStatus`):
  - **`menunggu_approval`**: set `status_kirim='menunggu_approval'`, `status_kunci=true`, set `dikirim_pada` & `dikunci_pada/oleh`, log `kirim-laporan`.  
    (Operator sekolah mengirim laporan melalui route terkait; controller ini mengatur status verifikasi/kunci.)
  - **`disetujui`**: set `status_kirim='disetujui'`, `status_kunci=true`, set metadata kunci/pengirim sesuai logika; log `verifikasi-laporan`. **Lalu** mencoba **sinkronisasi inventaris** (lihat §3). Gagal sinkronisasi → `status_kirim` tetap `disetujui`, dikembalikan pesan `warning` dan error di-log (`report`). Jika hasil sync ada `perluReview > 0`, dikembalikan `warning` khusus.
  - **`draft`**: set `status_kirim='draft'`, buka kunci (`status_kunci=false`), bersihkan metadata kunci (dan kirim sesuai implementasi) agar operator bisa edit; log `verifikasi-laporan` (kembalikan ke draft).

Catatan policy tambahan (ringkas, lihat `docs/security.md`): role `super_admin` punya pengecualian edit alokasi setelah disetujui pada konteks tertentu; guard server-side wajib.

### 2.3 State diagram (konseptual)

```
[draft] --(operator kirim / admin set menunggu_approval)--> [menunggu_approval]
  ^                                                              |
  |                                                              v
  +------------------(admin kembalikan ke draft)-----------------+
                                                                 |
                                        (admin set disetujui)    v
                                                            [disetujui]
                                                                 |
                                                                 +--> trigger SinkronisasiInventarisService::syncPeriode(...)
                                                                      (best-effort; warning jika gagal/perlu review)
```

## 3. Gate sinkronisasi ke Pencatatan BM

### 3.1 Gate resmi (satu-satunya yang dipakai)

- **Pemicu**: `KunciLaporan.status_kirim === 'disetujui'` pada periode `(sekolah_id, bulan, tahun)`.
- **Eksekusi**: `SinkronisasiInventarisService::syncPeriode($sekolahId, $bulan, $tahun, $userId)` dipanggil dari `PelaporanBm\KunciLaporanController@updateStatus` setelah status disetujui (setelah commit/log sesuai alur). **Bukan** hanya `status_kunci`.
- **Manual**: `super_admin` dapat menjalankan sinkronisasi via `PencatatanBm\SyncController` (permission `sinkronisasi-inventaris-bm`, throttle) yang juga memanggil service yang sama; service tetap menerapkan gate (jika periode tidak memenuhi syarat sinkronisasi sesuai aturan bisnis di service, hasil bisa `dilewati`/tidak membuat unit).

### 3.2 Efek sinkronisasi (ringkas)

- Membuat/memperbarui/memensiunkan unit inventaris dari `Realisasi` periode tersebut (lihat `docs/flows/pencatatan-bm.md` PB-01).
- Menulis `inventaris_sync_log` (status `berjalan|sukses|dilewati|gagal`, jumlah dibuat/tetap/surplus/perlu_review, waktu).
- **Idempoten** pada `realisasi_id + unit_index`; volume naik bisa mengaktifkan kembali unit yang pernah dihapus karena koreksi (sesuai implementasi).

### 3.3 Perilaku kegagalan

| Kondisi | Dampak pada Pelaporan BM | Dampak operasional |
|---|---|---|
| Exception di sinkronisasi | `status_kirim` tetap `disetujui` (tidak di-rollback oleh catch), log error, flash `warning` ke user | Admin/super_admin harus periksa log dan jalankan sinkronisasi ulang jika perlu |
| `perluReview > 0` | Tetap `disetujui`, flash `warning` spesifik | Tinjau manual di menu Pencatatan BM (sinkronisasi/inventaris) |

## 4. Distribusi BHP — Pengajuan (`bhp_*_pengajuan`)

### 4.1 Status

Model: `App\Models\DistribusiBhp\Pengajuan`, field `status` (string). Konstanta/logika helper: `isDraft()`, `isSubmitted()`, `isApproved()`.

Nilai yang dipakai di kode: **`draft`**, **`submitted`**, **`approved`**, **`rejected`**.

Catatan tambahan dari model: scope `efektifSampai`/`dalamPeriode` memakai `tanggal` bisnis, fallback `created_at`.

### 4.2 Transisi & guard (ringkas)

- **Create** (`PengajuanController@store`, permission `kelola-pengajuan-bhp`): buat pengajuan baru, status awal **`draft`** (sesuai implementasi). Detail disimpan; validasi `barang_id` harus sesuai sumber dana (`SumberDana::existsRule`, tabel terpisah BOS/BOPD).
- **Update/Destroy**: hanya jika `draft` dan milik sekolah/divisi sesuai hak; perubahan sumber dana tidak diizinkan (sudah terkunci ke record). Update/destroy memakai lock + cek status lagi di transaksi.
- **Submit** (`POST .../pengajuan/{pengajuan}/submit`): `StokService::submit(...)` mengubah status ke `submitted` dan **mereservasi stok** (mengurangi ketersediaan untuk pemakaian lain). Gagal (stok tidak cukup / kondisi lain) → `RuntimeException`, flash error, status tidak berpindah.

- **Review — Approve** (`ReviewPengajuanController@approve`, permission review/approve sesuai route): hanya `submitted`, reviewer bukan pembuat (`pastikanBukanPembuat`). Memanggil `DokumenService::approveDanGenerate(...)` yang dalam transaksi: lock pengajuan masih `submitted`, lock barang, hitung ulang stok, validasi jumlah approved, set status **`approved`**, konversi reservasi → pemakaian, buat nomor dokumen (sequence lock + unique + retry), buat dokumen, audit log. Gagal → rollback, flash error.
- **Review — Reject** (`ReviewPengajuanController@reject`): `StokService::reject(...)` mengubah status ke **`rejected`**, lepaskan reservasi, simpan alasan review (wajib). Gagal → flash error.

### 4.3 Diagram (konseptual)

```
[draft] --submit--> [submitted]
                        |
           +------------+------------+
           |                         |
        approve                    reject
           |                         |
           v                         v
      [approved]                [rejected]
```

## 5. Distribusi BHP — Penerimaan (`bhp_*_penerimaan`)

### 5.1 Status eksplisit

Tidak ada kolom status FSM di `Penerimaan` (lihat `fillable`/`casts`). Status operasional bersifat **implisit**:

- `is_saldo_awal` boolean + `periode_tahun` (untuk saldo awal).
- Identitas dokumen: `no_nota_invoice`, `no_bast`, `no_kwitansi`, `penyedia_id`, tanggal (`tanggal_bast`, `tanggal_titimangsa`).
- Tanggal efektif: `tanggalEfektif()` / scope `efektifSampai()` (saldo awal memakai awal tahun periode; fallback `created_at`).

### 5.2 Implikasi

- Perubahan stok fisik terjadi saat penerimaan/saldo awal diproses sesuai implementasi `PenerimaanController`/service terkait (lihat `docs/flows/distribusi-bhp.md` BHP-03, BHP-04). Tidak ada transisi “draft→submitted→approved” seperti pengajuan; audit/log dicatat per aksi controller/service yang ada.

## 6. Pencatatan BM — Gate sinkronisasi (ringkas)

- Route sinkronisasi manual: `GET|POST /pencatatan-bm/sinkronisasi` (`sinkronisasi-inventaris-bm`, `super_admin`). `POST` memvalidasi `sekolah_id`, `bulan`, `tahun`.
- Service `SinkronisasiInventarisService`: gate periode mengacu pada `KunciLaporan` dengan `status_kirim = 'disetujui'` (bukan `status_kunci`) — jika tidak ada, hasil `dilewati`, tidak ada unit diubah.
- Detail alur unit/register/mutasi: `docs/flows/pencatatan-bm.md`.

## 7. Invariant lintas domain (cek cepat)

1. **Pelaporan BM → Inventaris**: hanya setelah `status_kirim = 'disetujui'` (otomatis via controller atau manual via menu sinkronisasi yang tetap menilai gate). Jangan mengandalkan hanya status kunci.
2. **Sumber dana BHP**: konteks BOS/BOPD wajib sesuai prefix; `Pengajuan`/`Penerimaan` tabel terpisah per dana — cek `SumberDana` di setiap mutasi.
3. **Stok BHP**: `submit` → reservasi (`submitted`); `approve` → konversi ke pemakaian + dokumen (`approved`); `reject` → lepas reservasi (`rejected`). Semua perubahan stok yang kritis harus dalam transaksi dengan `lockForUpdate` sesuai service.
4. **Tenant**: `sekolah_id` selalu dari resolver server; aksi lintas sekolah ditolak 403/404 sesuai kebijakan controller (lihat `docs/security.md`).

## 8. File terkait (cepat)

- `app/Models/PelaporanBm/KunciLaporan.php`
- `app/Http/Controllers/PelaporanBm/KunciLaporanController.php`
- `app/Services/PencatatanBm/SinkronisasiInventarisService.php`
- `app/Http/Controllers/PencatatanBm/SyncController.php`
- `app/Models/DistribusiBhp/Pengajuan.php`, `app/Models/DistribusiBhp/Penerimaan.php`
- `app/Http/Controllers/DistribusiBhp/PengajuanController.php`, `ReviewPengajuanController.php`
- `app/Services/DistribusiBhp/{StokService,DokumenService}.php`
