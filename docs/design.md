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
6. **Satu sumber padding**: `main` di `AppLayout` pemilik padding halaman;
   komponen halaman tidak menambah padding horizontal sendiri.
7. **Anti-slop**: tak ada aksen dekoratif `border-l-4` pada kartu; garis sisi
   hanya untuk penanda menu aktif.

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
  (`lg:` breakpoint), tinggi `h-dvh` (bukan `h-screen`, agar tombol Logout tak
  terpotong chrome browser mobile). Grup: "Main Menu" → Dashboard, "Master Data"
  (dropdown), "Reports & Tools" → Laporan (admin) | "Menu Utama User" (sekolah).
- Header: tombol collapse (desktop) / hamburger (mobile), badge sekolah
  truncate `max-w-[260px]`, avatar inisial `bg-[#2563eb]`.
- Kartu: `bg-white rounded-xl border border-slate-200`.
- **Padding halaman hanya dari `main`** (`p-6 lg:p-10`). Wrapper halaman
  (`Dashboard.tsx` dll) memakai `max-w-7xl mx-auto space-y-*` **tanpa** padding
  horizontal — mencegah gutter ganda 40px di mobile.
- **Breakpoint grid** (dashboard admin): kartu metrik `sm:grid-cols-3`; dua tabel
  monitoring `xl:grid-cols-2` (di `lg` 1024px dua tabel jadi terlalu sempit).
- **Tabel dalam kartu tinggi tetap**: pembungkus `max-h-[420px] overflow-y-auto
  overflow-x-auto`; tabel `min-w-[440px]` agar sticky `thead` tetap terbaca saat
  di-scroll horizontal di HP.

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

## 5a. Favicon, Ikon Aplikasi & Web Manifest

Aset brand diturunkan dari satu master: `public/images/logolog.jpeg` (1600×1600,
JPEG sRGB). Semua ikon digenerate dengan **ImageMagick** (`magick` CLI — mesin
yang sama dengan ekstensi PHP Imagick, yang belum aktif di mesin dev).

| Aset | Path | Ukuran |
|------|------|--------|
| Favicon multi-res | `public/favicon.ico` | 16/32/48/64 |
| Favicon PNG | `public/favicon-16x16.png`, `public/favicon-32x32.png` | 16, 32 |
| Apple touch icon | `public/icons/apple-touch-icon.png` | 180 |
| PWA icons | `public/icons/icon-{72,96,144,192,384,512}.png` | 72–512 |
| Windows tile | `public/icons/mstile-150x150.png` | 150 |
| Web manifest | `public/site.webmanifest` | — |

- Wajib punya `192` & `512` dengan `purpose: any`; `512` tambahan
  `purpose: maskable` untuk Android adaptive icon.
- `theme_color: #2563eb` (primer, §2) dan `background_color: #f8fafc` (latar app).
- `<head>` (`resources/views/app.blade.php`) memuat semua `<link>` favicon,
  `<link rel="manifest">`, `theme-color`, dan meta MS Tile.

**Regenerate** (bila master logo berubah):

```bash
SRC=public/images/logolog.jpeg
magick "$SRC" -resize 512x512 public/icons/icon-512.png
magick "$SRC" -resize 192x192 public/icons/icon-192.png
magick "$SRC" -resize 180x180 public/icons/apple-touch-icon.png
magick "$SRC" -resize 32x32  public/favicon-32x32.png
magick "$SRC" -resize 16x16  public/favicon-16x16.png
magick "$SRC" -define icon:auto-resize=16,32,48,64 public/favicon.ico
```

> Catatan: agar bisa generate via PHP, pasang ekstensi Imagick
> (`brew install php-imagick` / `pecl install imagick`) lalu restart Valet.

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

### Target sentuh

- Aksi utama ≥ 44px. Menu sidebar `py-3`; tombol ikon header (hamburger,
  collapse) `p-2.5` (≈40px) + ikon `w-5 h-5` — naik dari `p-2`.
- Tombol ikon **wajib** punya `aria-label` (mis. "Buka menu navigasi",
  "Perkecil sidebar"); jangan andalkan ikon saja.

### State & ARIA

- Toggle drawer/hamburger: `aria-expanded={mobileOpen}` +
  `aria-controls="app-sidebar"`; `<aside id="app-sidebar">`.
- Dropdown sidebar (Master Data, Laporan): `aria-expanded` +
  `aria-controls` (`nav-master`, `nav-laporan`) pada tombol, `id` pada panel.
- Saat drawer mobile terbuka, scroll body dikunci (`document.body.style.overflow
  = 'hidden'`, reset di cleanup `useEffect`).
- `title` tooltip saat sidebar collapsed (ikon saja).
- Dialog: tutup via tombol + overlay; konfirmasi destruktif berwarna merah.

### Responsif

- Tabel dibungkus `overflow-x-auto` (scroll horizontal di HP, bukan remuk);
  minimal lebar konten `min-w-[440px]` untuk tabel monitoring.
- Grid tidak boleh turun ke kolom terlalu sempit: pakai breakpoint `sm`/`xl`
  sesuai §4, bukan `md`/`lg` yang memadatkan konten.
- Panel status dengan `min-w-[240px]` hanya di `md:` ke atas; di mobile
  `w-full`.
- Kontras teks primer ≥ 4.5:1 di atas putih (slate-500 ke atas untuk teks kecil).

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
- [ ] Padding horizontal hanya di `main`; wrapper halaman tanpa `p-*` sendiri.
- [ ] Tombol ikon baru? Beri `aria-label`; toggle dropdown beri `aria-expanded`.
- [ ] Kartu metrik tanpa `border-l-4` dekoratif (pakai border penuh / warna teks).
- [ ] `npx tsc --noEmit` hijau; tanpa `any` (aturan `docs/guidelines.md`).
