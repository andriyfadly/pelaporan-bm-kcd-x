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
- **Footer app** (di `AppLayout`, bawah `main`, `flex-1` agar menempel dasar
  viewport): copyright "ASET KCD X - DISDIK JABAR" + versi aplikasi
  (`v1.0.0`, naikkan manual saat rilis), `text-[11px] text-slate-400`,
  menumpuk di mobile.
- Kartu: `bg-white rounded-xl border border-slate-200`.
- **Padding halaman hanya dari `main`** (`p-6 lg:p-10`). Wrapper halaman
  `space-y-*` **tanpa** padding horizontal dan **tanpa** `max-w-* mx-auto` —
  konten memenuhi lebar `main` di semua ukuran layar (lebar penuh konsisten
  antar modul, mulai Dashboard, Acuan, Rekapan, Cetak, User, Kode Barang,
  hingga Log Aktivitas).
- **Breakpoint grid** (dashboard admin): kartu metrik `sm:grid-cols-3`; dua tabel
  monitoring `xl:grid-cols-2` (di `lg` 1024px dua tabel jadi terlalu sempit).
- **Tabel dalam kartu tinggi tetap**: pembungkus `max-h-[420px] overflow-y-auto
  overflow-x-auto`; tabel `min-w-[440px]` agar sticky `thead` tetap terbaca saat
  di-scroll horizontal di HP.
- **Split form + daftar** (mis. Kode Barang): `xl:grid-cols-12`
  (`xl:col-span-4` form upload/cari, `xl:col-span-8` daftar) — bukan `lg`,
  karena pada 1024px kolom kanan terlalu sempit untuk tabel.
- **Header halaman + aksi**: `flex flex-col md:flex-row justify-between
  items-start md:items-center gap-4` agar tombol turun ke bawah di mobile.
- **`min-w` tabel per halaman** (aturan praktis ± 100–130px per kolom):
  `min-w-[440px]` (3 kolom), `min-w-[640px]` (rekening 5 kolom),
  `min-w-[720px]` (6 kolom), `min-w-[760px]` (log 5 kolom berisi teks panjang),
  `min-w-[900px]` (user 8 kolom), `min-w-[1024px]` (acuan 10 kolom),
  `min-w-[1200px]` (log fisik 15 kolom). Setel sesuai jumlah kolom agar scroll
  horizontal, bukan kolom terkompresi.

## 5. Komponen (`resources/js/Components/`)

| Komponen | Props inti | Aturan pakai |
|----------|-----------|--------------|
| `Pagination` | `links`, `total?` | Selalu di bawah tabel; sembunyi bila ≤ 3 link |
| `SearchInput` | `value`, `onChange`, `placeholder?`, `className?`, `ariaLabel?` | Ikon Search + tombol clear + `aria-label` (fallback ke placeholder); submit via form Enter |
| `EmptyState` | `icon`, `title`, `description` | Data kosong / filter tak cocok |
| `Modal` | `isOpen`, `onClose`, `title`, `maxWidth` | Form tambah/edit; ESC + scroll lock + fokus otomatis |
| `ConfirmDialog` | `title`, `message`, `confirmText`, `isDestructive` | Hapus, kirim laporan, kunci, ACC, buka kunci, logout |
| `StatusBadge` | status | `draft`/`menunggu_approval`/`disetujui`/kunci |
| `CardStat` | label, nilai, ikon | 4 kartu dashboard sekolah, rekap admin |

> **Wajib pakai komponen ini, jangan reinvent.** Halaman baru yang butuh modal,
> konfirmasi hapus, search box, atau empty state harus mengimpor dari
> `resources/js/Components/`. Dilarang `window.confirm()` (pakai
> `ConfirmDialog`), modal `fixed inset-0` ad-hoc (pakai `Modal`, yang sudah
> menangani ESC + scroll lock + fokus), atau input search manual (pakai
> `SearchInput`, tombol clear gratis).

### Konvensi kontrol form

- **Select**: `bg-white` eksplisit + `focus:ring-2 focus:ring-blue-500`.
- **Teks di atas latar berwarna** (§2): pakai shade teks dari hue latar (mis.
  `bg-blue-50` → `text-blue-900`/`text-blue-800`), bukan `text-slate-*` —
  slate di atas biru terlihat pudar dan gagal kontras.
- **Kotak info/panduan**: border penuh (`border border-{hue}-200`) + tint latar;
  jangan `border-l-4` dekoratif (lihat §1 butir 7).

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
- **Widget/filter yang bisa diklik = tombol, bukan `div onClick`** — wajib
  `<button type="button">` + `aria-pressed` saat bersifat toggle (contoh:
  widget filter TUNTAS/BELUM di Rekapan). Supaya bisa dijangkau keyboard dan
  status filter terbaca screen reader.
- **Toggle expand-baris tabel** (+/− rincian): `aria-expanded` +
  `aria-label` deskriptif (mis. "Lihat rincian uraian 5.2.3"), target sentuh
  minimal `w-6 h-6`.
- **Kontrol filter tanpa label visual** (select, `input type="date"`):
  wajib `aria-label` eksplisit (contoh: Log Aktivitas — "Filter aksi",
  "Tanggal mulai").
- **Toggle password** (mata terbuka/tertutup): `aria-label` dinamis +
  `aria-pressed={showPassword}`.
- **Guard aksi pada diri sendiri di level UI**: tombol destruktif (hapus,
  nonaktifkan) disembunyikan untuk akun yang sedang login — jangan andalkan
  `alert()` setelah klik; validasi backend tetap wajib.
- Placeholder/glyph redup minimal `text-slate-400` (slate-300 di atas putih
  hanya 2:1, gagal AA).

### Responsif

- Tabel dibungkus `overflow-x-auto` (scroll horizontal di HP, bukan remuk);
  `min-w` sesuai jumlah kolom — lihat skala §4.
- Grid tidak boleh turun ke kolom terlalu sempit: pakai breakpoint `sm`/`xl`
  sesuai §4, bukan `md`/`lg` yang memadatkan konten.
- Split form+daftar memakai `xl:` (§4); header halaman menumpuk di mobile
  (`flex-col md:flex-row`).
- Panel status dengan `min-w-[240px]` hanya di `md:` ke atas; di mobile
  `w-full`.
- Aksi ikon-only di dalam tabel: `aria-label` deskriptif (mis.
  `` `Hapus kode ${item.kode_barang}` ``) + `title`; target sentuh `p-1.5`.
- Kontras teks primer ≥ 4.5:1 di atas putih (slate-500 ke atas untuk teks kecil);
  teks di atas tint berwarna pakai shade hue yang sama (§5).

## 9. Yang Belum / Batasan Diketahui

- Mode gelap belum ada (seluruh token terang).
- Notifikasi hanya flash session (tanpa toast persisten / real-time).
- Bahasa tunggal Indonesia; format angka/tanggal id-ID hardcode.
- Avatar = inisial huruf (tanpa foto profil).

## 10. Checklist Desain untuk Kontributor

- [ ] Warna baru? Pakai token tabel §2 (jangan hex bebas).
- [ ] Halaman tabel baru? Kartu filter + tabel + `Pagination` + `EmptyState`.
- [ ] Modal / konfirmasi / search / empty state? Impor komponen §5, jangan tulis ulang.
- [ ] Aksi merusak? Wajib `ConfirmDialog` (`isDestructive` untuk hapus).
- [ ] Menu baru? Tambah di grup yang tepat + state aktif `currentPath`.
- [ ] Padding horizontal hanya di `main`; wrapper halaman tanpa `p-*` dan
      tanpa `max-w-* mx-auto` sendiri (konten lebar penuh, §4).
- [ ] Tabel 5+ kolom? Beri `min-w` skala §4 di dalam `overflow-x-auto`.
- [ ] Split form+daftar? Pakai `xl:grid-cols-12`, bukan `lg`.
- [ ] Widget/filter clickable? `<button>` + `aria-pressed` (bukan `div onClick`).
- [ ] Toggle expand-baris? `aria-expanded` + `aria-label`.
- [ ] Filter tanpa label visual (select/date)? `aria-label` eksplisit.
- [ ] Tombol ikon baru? Beri `aria-label` + `title`; toggle dropdown beri `aria-expanded`.
- [ ] Teks di atas tint berwarna? Pakai shade hue yang sama, bukan `slate-*`.
- [ ] Teks/glyph redup minimal `slate-400` (bukan `slate-300`).
- [ ] Kartu metrik tanpa `border-l-4` dekoratif (pakai border penuh / warna teks).
- [ ] `npx tsc --noEmit` hijau; tanpa `any` (aturan `docs/guidelines.md`).
