# Konvensi Dokumentasi Flow (Agar Konsisten)

Dokumen ini dipakai agar setiap perubahan flow tidak “hanyut” dan mudah di-audit.

## 1. Prinsip

- **Sumber kebenaran** adalah kode: `routes/web.php`, controller, model, service, policy/gate, dan halaman Inertia. Dokumen flow mendeskripsikan perilaku aktual.
- **Jangan tulis absolute path** (contoh `/Users/...`) di markdown — pakai path relatif dari root repo.
- **Satu flow = satu dokumen** di `docs/flows/` (kecuali state machine lintas domain).
- **Status** di header: `Fixed` (patokan perilaku stabil), `Draft` (disusun dari kode, perlu validasi ulang), `Deprecated` (tidak dipakai lagi).

## 2. Struktur minimum per dokumen flow

Setiap file flow idealnya punya bagian:

1. **Judul + status + sumber rujukan** (file penting: routes, controller, service, docs terkait).
2. **Aktor & permission** (tabel ringkas).
3. **Invariant** (hal yang tidak boleh berubah: tenant, idempotensi, gate sinkronisasi, dll.).
4. **Peta flow / tabel ID flow** (BHP-01, PBM-01, PB-01, dst.) yang memetakan trigger → hasil.
5. **Detail per flow**: route, controller, validasi kunci, side effect (DB/log), error/tepi.
6. **Route map** (route yang dicakup oleh dokumen).
7. **Jebakan/tepi yang diketahui**.
8. **File terkait (cepat)**.

## 3. Saat mengubah kode (PR checklist mini)

Jika PR mengubah salah satu dari berikut, **wajib** update flow doc yang relevan:

- route baru/diubah/dihapus
- permission/middleware/role berubah
- state machine (status, gate, transisi) berubah
- isolasi tenant berubah (scope, 403/404, resolver)
- side effect penting berubah (log, sinkronisasi, nomor dokumen, stok)

Perbarui juga tabel di `docs/flows/README.md`.

## 4. Penamaan & referensi

- Gunakan nama file `kebab-case.md` di `docs/flows/`.
- ID flow: prefix domain (`PB-01` Pencatatan BM, `PBM-01` Pelaporan BM, `BHP-01` Distribusi BHP, atau nama deskriptif untuk auth/master jika perlu).
- Sebut file dengan backtick path relatif, contoh: `app/Http/Controllers/PelaporanBm/KunciLaporanController.php`.

## 5. Tautan silang yang disarankan

- Pelaporan BM ↔ Pencatatan BM: gate `status_kirim = 'disetujui'` → sinkronisasi inventaris (`lintas-domain-state-machine.md`).
- Distribusi BHP: selalu sebut konteks **sumber dana BOS/BOPD** dan aturan `SumberDana::dengan()` / `existsRule`.
- Security/tenant: rujuk `docs/security.md` untuk matriks RBAC lengkap.

## 6. Validasi cepat (opsional tapi dianjurkan)

- Setelah ubah route, cek konsistensi: `php artisan route:list --name=...` atau `rg` di `routes/web.php`.
- Untuk flow kritis (stok, approval, sinkronisasi), tambahkan/pertahankan test Feature/Unit yang disebut di doc (section “Test penjaga” jika ada).
