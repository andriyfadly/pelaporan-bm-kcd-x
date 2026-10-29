# Dokumentasi SI DIPTA Beu! — Pelaporan Belanja Modal KCD

Sistem web Laravel 13 + Inertia React untuk pencatatan SPJ, alokasi realisasi,
verifikasi, rekapan, dan pelaporan belanja modal sekolah di Cabang Dinas
Pendidikan Wilayah X. Migrasi dari aplikasi legacy PHP (`legacy/`).

Sejak versi ini, aplikasi juga memuat **domain Distribusi BHP** (Barang Habis
Pakai) yang berdiri sendiri di bawah prefix `/distribusi-bhp`, terpisah dari
Pelaporan BM. Lihat `docs/requirements/` untuk kontrak kedua domain.

---

## Daftar Isi

1. [PRD](prd.md) — latar belakang, tujuan, persona, scope F1–F9, kriteria terima.
2. [Arsitektur & Teknologi](architecture.md) — stack, alur request, pola kunci, ADL.
3. [Design System & UI/UX](design.md) — token warna, tipografi, layout, komponen, pola UX.
4. [Skema Database](database.md) — diagram relasi + kamus tabel BM **dan BHP** + konvensi.
5. [Endpoint & Kontrak Data](api.md) — rute web, controller, middleware, payload (BM + BHP).
6. [Alur & Modul per Role](modules.md) — detail operasional tiap modul, termasuk BHP **dan Pencatatan BM (inventaris)**.
7. [Keamanan](security.md) — matriks RBAC (32 permission, 5 role), isolasi tenant, audit trail, checklist rilis.
8. [Testing](testing.md) — strategi, perintah, matriks file test, status, kontrak UI.
9. [Instalasi Lokal](setup.md) — prasyarat, instalasi, verifikasi kualitas.
10. [Deployment Produksi & Staging](deployment.md) — prasyarat server, rilis, backup, staging QA (§8), troubleshooting.
11. [User Manual](user-guide.md) — panduan operator & admin KCD (BM, BHP, dan pencatatan inventaris).
12. [Developer Guidelines](guidelines.md) — standar kode, testing, RBAC, relative-path.
13. [Glosarium](glossary.md) — istilah BM, SPJ, kunci, log, BHP, dan inventaris.

---

## Requirements (kontrak domain)

Sub-folder `docs/requirements/` memuat spesifikasi rinci kedua domain:

- BM: `pelaporan-belanja-modal-prd.md`, `workflow-belanja-modal.md`
- BHP: `distribusi-bhp-prd.md`, `workflow-distribusi-bhp.md`
- Lintas domain: `domain-boundaries.md`, `data-dictionary.md`,
  `reports-and-documents.md`, `rbac-matrix.md`, `security-and-acceptance.md`,
  `shared-platform-requirements.md`, `system-integration-prd.md`

> Sebagian requirements sudah diimplementasi; `data-dictionary.md` menandai
> status per item (✅ Sudah · 🟡 Sebagian · ⬜ Belum).

---

## Mulai Cepat

| Saya mau... | Buka |
|-------------|------|
| Paham produk & fitur | `prd.md` |
| Ngoding / ubah kode | `architecture.md` + `guidelines.md` |
| Desain halaman baru | `design.md` |
| Ubah skema / query | `database.md` |
| Tambah endpoint | `api.md` |
| Rilis ke produksi | `deployment.md` + `security.md` |
| QA perubahan sebelum rilis | `deployment.md` §8 (Staging) |
| Pakai aplikasi | `user-guide.md` |

---

## Peran (ringkas)

| Role | Scope |
|------|-------|
| `super_admin` | Penuh + log aktivitas lengkap |
| `admin_kcd` | Lintas sekolah: acuan, rekapan, kunci/verifikasi, master, user, log aktivitas (tanpa aktivitas super_admin) |
| `operator_sekolah` / `bendahara_sekolah` | Satu sekolah: SPJ, realisasi, kirim, cetak |
| `operator_divisi` | Khusus Distribusi BHP: membuat/mensubmit pengajuan, tanpa master/approval |

Detail: `prd.md` (§3), matriks hak: `security.md` (§1).
