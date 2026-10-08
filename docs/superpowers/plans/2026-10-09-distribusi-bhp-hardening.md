# Distribusi BHP Hardening Implementation Plan

> **Untuk agent implementasi:** gunakan `subagent-driven-development` atau `executing-plans` saat menjalankan rencana ini. Kerjakan setiap task dengan test gagal terlebih dahulu, implementasi minimal, lalu verifikasi.

**Goal:** Menutup gap implementasi Distribusi BHP BOS/BOPD pada saldo awal, validasi sumber dana, concurrency stok, settings, tenant isolation, dan coverage test dengan flow yang lebih kuat daripada `sistem-manajemen-aset`.

**Architecture:** Tiga subsistem: `SaldoAwalService` untuk lifecycle saldo awal, penguatan `StokService` untuk validasi sumber dana dan concurrency, hardening settings dan endpoint penerimaan. Tetap gunakan tabel transaksi yang ada; tanpa ledger baru.

**Tech Stack:** Laravel 13.34, PHP 8.4, PostgreSQL, Eloquent, Inertia React, PHPUnit 13, Laravel Pint.

**Spec:** `docs/requirements/distribusi-bhp-prd.md`, `docs/requirements/workflow-distribusi-bhp.md`, `docs/modules.md`

## Global Constraints

- BOS dan BOPD wajib terisolasi pada setiap master, penerimaan, saldo, pengajuan, laporan, dan dokumen.
- User sekolah tidak boleh memilih atau mengganti `sekolah_id` melalui request.
- Semua mutasi wajib memakai transaction.
- Saldo stok dihitung dari transaksi, bukan kolom stok denormalisasi.
- Saldo awal hanya boleh dibuat satu kali untuk kombinasi sekolah, sumber dana, periode, dan barang.
- Settings tidak menyimpan data sekolah atau penandatangan.
- Reviewer berasal dari authenticated user, bukan dari request body.
- Tidak menambah dependency.
- Setiap perubahan PHP wajib diverifikasi dengan `vendor/bin/pint --dirty --format agent`.
- Setiap perubahan frontend wajib lulus `npx tsc --noEmit` dan `npm run build`.

## Task

- A1: Test contract saldo awal (periode wajib, duplikat ditolak, BOS≠BOPD, detail invalid).
- A2: `SaldoAwalService::create()` + partial unique index di DB.
- A3: Endpoint index/destroy saldo awal + boundary test.
- A4: Import Excel — DITUNDA (opsional, bukan kebutuhan rilis pertama).
- B1: Revalidasi sumber dana di `applyApprove()`.
- B2: Lock barang saat submit/approve (urutan ID deterministik).
- B3: Validasi approval quantity (key asing, non-integer, melebihi diminta).
- B4: Test atomicity approval-dokumen (rollback nomor gagal, gate pejabat).
- C1: Scope settings → hanya `kelola-master-bhp` (sudah begitu), tambah test operator_divisi/bendahara 403.
- C2: Singleton settings aman concurrency pgsql.
- C3: Validasi format nomor (placeholder whitelist).
- C4: Boundary test penerimaan/settings.
- D1: Update docs.
- D2: UI saldo awal (filter, warning, delete guard).
- E1-E3: Full verification (test, pint, tsc, build, coverage).

## Keputusan

- Keputusan C1: settings global; update hanya role yang punya `kelola-master-bhp`. Operator sekolah yang punya permission tsb tetap boleh (konsisten existing).
- Keputusan B3: quantity 0 pada detail = detail tidak disetujui (jumlah_disetujui 0, tetap tersimpan untuk audit).
- Key `detail_id` asing di payload approval → ditolak dengan RuntimeException.
- Import saldo awal (A4) ditunda; flow manual + uniqueness DB wajib sekarang.
