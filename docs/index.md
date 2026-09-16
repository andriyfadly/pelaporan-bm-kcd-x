# Dokumentasi SI DIPTA Beu! — Pelaporan Belanja Modal KCD

Sistem web Laravel 13 + Inertia React untuk pencatatan SPJ, alokasi realisasi,
verifikasi, rekapan, dan pelaporan belanja modal sekolah di Cabang Dinas
Pendidikan Wilayah X. Migrasi dari aplikasi legacy PHP (`legacy/`).

---

## Daftar Isi

1. [PRD](prd.md) — latar belakang, tujuan, persona, scope F1–F9, kriteria terima.
2. [Arsitektur & Teknologi](architecture.md) — stack, alur request, pola kunci, ADL.
3. [Design System & UI/UX](design.md) — token warna, tipografi, layout, komponen, pola UX.
4. [Skema Database](database.md) — diagram relasi + kamus 13 tabel + konvensi.
5. [Endpoint & Kontrak Data](api.md) — rute web, controller, middleware, payload.
6. [Alur & Modul per Role](modules.md) — detail operasional tiap modul.
7. [Keamanan](security.md) — matriks RBAC, isolasi tenant, audit trail, checklist rilis.
8. [Testing](testing.md) — strategi, perintah, matriks 16 file test.
9. [Instalasi Lokal](setup.md) — prasyarat, instalasi, verifikasi kualitas.
10. [Deployment Produksi](deployment.md) — prasyarat server, rilis, backup, troubleshooting.
11. [User Manual](user-guide.md) — panduan operator & admin KCD.
12. [Developer Guidelines](guidelines.md) — standar kode, testing, RBAC, relative-path.
13. [Glosarium](glossary.md) — istilah BM, SPJ, kunci, log.

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
| Pakai aplikasi | `user-guide.md` |

---

## Peran (ringkas)

| Role | Scope |
|------|-------|
| `super_admin` | Penuh + viewer log aktivitas |
| `admin_kcd` | Lintas sekolah: acuan, rekapan, kunci/verifikasi, master, user |
| `operator_sekolah` / `bendahara_sekolah` | Satu sekolah: SPJ, realisasi, kirim, cetak |

Detail: `prd.md` (§3), matriks hak: `security.md` (§1).
