# Design System & Panduan UI/UX — SI DIPTA Beu!

> Semua token di bawah diambil dari implementasi berjalan
> (`resources/js/Layouts/AppLayout.tsx`, komponen `resources/js/Components/`).
> Ikon: `lucide-react`. Tanpa library UI eksternal.

## 1. Prinsip Desain

1. **Peran jelas sejak bingkai**: sidebar berbeda untuk admin vs sekolah;
   pengguna tak pernah menebak haknya.
2. **Satu pola per masalah**: tabel + paginasi + filter yang sama di semua modul.
3. **Konfirmasi sebelum merusak**: hapus/kirim/kunci selalu via dialog.
4. **Jujur soal data kosong**: `EmptyState`, bukan tabel kosong; unduh kosong dicegah.
5. **Terbaca di lapangan**: kontras kuat, target sentuh cukup, responsif
   (sidebar → drawer di mobile).

## 2. Token Warna

| Token | Nilai | Pakai |
|-------|-------|-------|
| Primer | `#2563eb` (blue-600) | Menu aktif, tombol utama, link, badge aksi |
| Primer tua | `#1e3a8a` (blue-900) | Logo "SI DIPTA", judul brand |
| Primer muda | `#eff6ff` (blue-50) | Latar menu aktif |
| Aksen | `#f59e0b` (amber-500) | "Beu!" logo (font Yellowtail, miring −3°) |
| Latar app | `#f8fafc` (slate-50) | Background halaman |
| Teks | `slate-900` isi, `slate-500` sekunder, `slate-400` label | Hierarki teks |
| Sukses | `emerald-50/700` | Badge "Akses Sekolah", kekurangan = 0 |
| Bahaya | `red-600` + `red-50` hover | Logout, hapus |
| Header Excel | `DDEBF7` (biru muda) | Header tabel A8:Y9/Z9 file unduhan |

Menu aktif: `bg-[#eff6ff] text-[#2563eb] border-l-4 border-[#2563eb] rounded-l-none`.
Submenu aktif: teks bold + `bg-blue-50/50`.

## 3. Tipografi

- Keluarga: `font-sans` bawaan Tailwind.
- Logo: `font-black text-2xl uppercase tracking-tight` + sub `text-[8px]
  tracking-widest` ("SISTEM DIGITALISASI PELAPORAN ASET").
- Judul halaman: `font-bold text-base text-slate-800`.
- Label seksi: `text-[11px] font-bold uppercase tracking-wider text-slate-400`.
- Isi tabel: `text-sm`; submenu `text-[13.5px]`; meta `text-xs`/`text-[11px]`.

## 4. Layout

```text
+--------+------------------------------------------------+
| Side   | Header sticky (h-20, blur, judul + sekolah + avatar)|
| bar    +------------------------------------------------+
| w-72 / | Main (p-6 lg:p-10)                             |
| w-20   |  + kartu filter + kartu tabel + Pagination      |
+--------+------------------------------------------------+
```

- Sidebar: fixed, collapsible (`w-72 ↔ w-20`), drawer + overlay di mobile
  (`lg:` breakpoint). Grup: "Main Menu" → Dashboard, "Master Data"
  (dropdown), "Reports & Tools" → Laporan (admin) | "Menu Utama User" (sekolah).
- Header: tombol collapse (desktop) / hamburger (mobile), badge sekolah
  truncate `max-w-[260px]`, avatar inisial `bg-[#2563eb]`.
- Kartu: `bg-white rounded-xl border border-slate-200`.

## 5. Komponen (`resources/js/Components/`)

| Komponen | Props inti | Aturan pakai |
|----------|-----------|--------------|
| `Pagination` | `links`, `total?` | Selalu di bawah tabel; sembunyi bila ≤ 3 link |
| `SearchInput` | `value`, `onChange`, `placeholder?` | Ikon Search + tombol X; submit via form Enter |
| `EmptyState` | `icon`, `title`, `description` | Data kosong / filter tak cocok |
| `Modal` | standar | Form tambah/edit; fokus pertama otomatis |
| `ConfirmDialog` | `title`, `message`, `confirmText`, `isDestructive` | Hapus, kirim laporan, kunci, logout |
| `StatusBadge` | status | `draft`/`menunggu_approval`/`disetujui`/kunci |
| `CardStat` | label, nilai, ikon | 4 kartu dashboard sekolah, rekap admin |

## 6. Pola UX per Modul

- **Pilih bulan dulu**: SPJ & Input Realisasi selalu lewat `PilihBulan`
  (pilihan terakhir diingat localStorage).
- **Form SPK**: sticky badge kategori, 3 seksi (dokumen → item accordion),
  live search katalog (debounce), draft autosave
  `draft_spj_barang_{sekolah}_{bulan}`, datalist history merk/sertifikat/satuan.
- **Alokasi realisasi**: checkbox item SPJ → simpan; edit via uncheck;
  tolak melebihi sisa dengan pesan nominal rupiah.
- **Kirim laporan**: tombol aktif hanya saat kekurangan = 0 → ConfirmDialog →
  flash success + status berubah.
- **Cetak/unduh**: pre-flight check → modal progres → file; kosong → modal
  alert + cegah unduh (cookie `download_status`).
- **Viewer log**: filter 6 dimensi, diff JSON dalam `<details>`,
  waktu `toLocaleString('id-ID')`.
- **Flash**: `success` hijau / `error` merah dari session, tampil tiap redirect.

## 7. Format Data Tampilan

- Rupiah: `Rp 4.500.000` (`toLocaleString('id-ID')`, 0 desimal).
- Tanggal tabel: `d/m/Y`; input: `date` ISO; waktu log: lokal id-ID.
- Kota: `KABUPATEN X` / `KOTA X` (helper `formatKotaKab`, dipakai juga di Excel).
- Status kirim: `draft` (abu) → `menunggu_approval` (kuning) → `disetujui` (hijau);
  gembok visual saat `status_kunci`.

## 8. Aksesibilitas & Responsif

- Target sentuh ≥ 40px untuk aksi utama; menu sidebar `py-3`.
- Tabel dibungkus `overflow-x-auto` (scroll horizontal di HP, bukan remuk).
- `title` tooltip saat sidebar collapsed (ikon saja).
- Kontras teks primer ≥ 4.5:1 di atas putih (slate-500 ke atas untuk teks kecil).
- Dialog: tutup via tombol + overlay; konfirmasi destruktif berwarna merah.

## 9. Yang Belum / Batasan Diketahui

- Mode gelap belum ada (seluruh token terang).
- Notifikasi hanya flash session (tanpa toast persisten / real-time).
- Bahasa tunggal Indonesia; format angka/tanggal id-ID hardcode.
- Avatar = inisial huruf (tanpa foto profil).

## 10. Checklist Desain untuk Kontributor

- [ ] Warna baru? Pakai token tabel §2 (jangan hex bebas).
- [ ] Halaman tabel baru? Kartu filter + tabel + `Pagination` + `EmptyState`.
- [ ] Aksi merusak? Wajib `ConfirmDialog` (`isDestructive` untuk hapus).
- [ ] Menu baru? Tambah di grup yang tepat + state aktif `currentPath`.
- [ ] `npx tsc --noEmit` hijau; tanpa `any` (aturan `docs/guidelines.md`).
