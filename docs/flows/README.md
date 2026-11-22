# Flow Dokumen (Repository)

Dokumen di direktori ini adalah **rujukan perilaku aktual** (flow) per domain, diturunkan dari `routes/web.php`, controller, model, service, dan halaman Inertia. Setiap dokumen memuat: aktor/permission, invariant, peta flow, route → flow, guard/tepi, dan test penjaga.

## Daftar Dokumen

| File | Domain | Status |
|---|---|---|
| [`repository-overview.md`](repository-overview.md) | Gambaran alur lintas domain (auth → role → Pelaporan BM → Pencatatan BM → Distribusi BHP → Master/Admin) | Fixed (disusun dari `routes/web.php`, controller/service/model utama, dan dokumen flow) |
| [`pencatatan-bm.md`](pencatatan-bm.md) | Pencatatan BM (inventaris aset dari realisasi disetujui) | Fixed |
| [`pelaporan-bm.md`](pelaporan-bm.md) | Pelaporan BM & distribusi belanja modal (acuan, SPJ, realisasi, cetak, rekapan, kunci) | Fixed (diturunkan dari `routes/web.php`, controller, service, model, dan halaman Inertia) |
| [`distribusi-bhp.md`](distribusi-bhp.md) | Distribusi BHP (BOS/BOPD: master, pengajuan, review, dokumen, laporan, saldo awal) | Fixed (dokumen alur lengkap dengan aturan sumber dana, tenant isolation, dan state machine) |
| [`master-admin-auth.md`](master-admin-auth.md) | Master data, Admin (user/log/kode barang), autentikasi & rotasi password, izin lintas domain | Fixed (rujukan `docs/security.md`, resolver, dan permission middleware di `routes/web.php`) |
| [`lintas-domain-state-machine.md`](lintas-domain-state-machine.md) | State machine lintas domain (kunci laporan BM, status kirim, gate sinkronisasi ke Pencatatan BM, status pengajuan BHP) | Fixed (state machine dan gate sinkronisasi terpetakan dari kode) |

## Cakupan dan Catatan Riset (2026-05-15)

- **Inventarisasi route/controller/model/page/test**: selesai (dibaca dari repo: `routes/web.php`, controller di `App\Http\Controllers\PelaporanBm`, `DistribusiBhp`, `PencatatanBm`, `Master`, `Admin`, model terkait, halaman `resources/js/Pages/*`, serta `docs/modules.md`, `docs/requirements/*`, `docs/security.md`).
- **Riset alur via subagent scout (`PelaporanBmFlows`, `DistribusiBhpFlows`)**: **gagal** pada provider (API key terblokir/403 saat job berjalan). Isi dokumen Pelaporan BM dan Distribusi BHP di repo ini disusun langsung dari pembacaan kode dan dokumen yang ada, lalu sebaiknya divalidasi ulang dengan uji manual atau scout baru bila kredensial tersedia.

## Cara Memperbarui

1. Jika route/controller/permission berubah, perbarui dokumen flow yang relevan di PR yang sama.
2. Tambahkan baris di tabel di atas saat file baru ditambahkan.
3. Pastikan path di dokumen menggunakan path relatif dari root repo (larangan absolute path).
