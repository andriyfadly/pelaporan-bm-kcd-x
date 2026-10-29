<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `.agents/skills/`. You MUST activate the relevant skill whenever you work in that domain:
- `ponytail`: The lazy senior dev ladder: YAGNI, utamakan native/stdlib, no over-engineering, diff minimal.
- `ui-anti-slop`: Larangan micro-wrappers trivial (`<Button>`, `<TextInput>`), divitis, dan form dynamic engine.
- `tenant-isolation`: Wajib isolasi scope `sekolah_id` dan periksa kepemilikan mutasi (IDOR protection).
- `query-optimization`: Pencegahan N+1, eager loading relasi Eloquent, dan streaming data besar.
- `csv-excel-export`: Standar ekspor 26 kolom (UTF-8 BOM, streaming output, pre-flight check).
- `stop-ai-slop`: Larangan narasi basa-basi AI, code-first, dan maksimal 3 baris ringkasan.

## Reusable Components & Utilities

Gunakan komponen bersama yang telah tersedia sebelum membuat kode UI baru:
- `resources/js/Utils/format.ts`: `formatRupiah()`, `BULAN_LIST`, `getNamaBulan()`.
- `resources/js/Components/Pagination.tsx`: Kontrol navigasi tabel Inertia.
- `resources/js/Components/Modal.tsx`: Dialog modal accessible (ESC key, scroll lock, backdrop).
- `resources/js/Components/StatusBadge.tsx`: Badge status laporan dinas (disetujui, menunggu approval, draft, selesai).
- `resources/js/Components/CardStat.tsx`: Kartu ringkasan metrik statistik.
- `resources/js/Components/EmptyState.tsx`: Tampilan seragam saat tabel kosong.
- `resources/js/Components/SearchInput.tsx`: Input pencarian live dengan tombol clear.
- `resources/js/Components/ConfirmDialog.tsx`: Modal konfirmasi aksi destruktif pengganti `window.confirm()`.

## Conventions & Clean Architecture

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

### TypeScript Best Practices
- Strict Typing: Explicit type declarations on all props, states, and return values. Forbid `any`; use generics, discriminating unions, or `unknown`.
- Shared Contracts: Declare clear interfaces/types for models (e.g., `SpjItem`, `Acuan`, `Sekolah`) and colocate or export them for reuse.
- Strict Verification: Ensure `npx tsc --noEmit` always passes with 0 errors.

### SOLID Principles
- Single Responsibility (SRP): Each controller, model, and UI component has one clear responsibility. Keep UI components presentation-focused and move business calculations to backend/helpers.
- Open/Closed (OCP): Favor composition over inheritance; allow extension without mutating stable contracts.
- Liskov Substitution (LSP): Implementations must honor all expectations of their base contracts or props interfaces.
- Interface Segregation (ISP): Design small, client-specific interfaces rather than bloated general-purpose ones.
- Dependency Inversion (DIP): Depend on abstractions; inject dependencies via Laravel service container and React props.

### Clean Code & DRY (Don't Repeat Yourself)
- DRY: Centralize shared calculations (e.g., nominal sums, status checks, formatters) in helper functions, traits, or hooks. Never duplicate logic.
- Small & Readable: Functions and components must do one thing well. Use guard clauses / early returns to avoid deep nesting.
- Meaningful Naming: State intent clearly (`isApproved`, `canEdit`, `calculateDeficit`). Avoid abbreviations or vague names.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Project Documentation (`docs/`)

Dokumentasi sistem proyek dikelola di dalam direktori `docs/`:
- `docs/index.md`: Ringkasan sistem, daftar isi, dan perbandingan peran (super_admin, Admin KCD, Operator/Bendahara Sekolah).
- `docs/prd.md`: Product Requirements Document (latar belakang, personas, feature scope F1-F9, dan acceptance criteria).
- `docs/architecture.md`: Detail arsitektur teknis, tech stack (Laravel 13, Inertia React, TypeScript), pola kunci, dan keputusan desain.
- `docs/design.md`: Design system & panduan UI/UX (token warna, komponen, pola UX).
- `docs/database.md`: Diagram relasi, kamus tabel, dan konvensi skema (PostgreSQL).
- `docs/api.md`: Spesifikasi rute, controller, middleware, dan kontrak data.
- `docs/modules.md`: Alur fungsional modul (Dashboard Admin & Sekolah, Katalog SPJ vs Realisasi, Cetak 26 Kolom, Master Data).
- `docs/security.md`: Matriks RBAC, isolasi tenant, audit trail, dan checklist rilis.
- `docs/testing.md`: Strategi testing, perintah, dan matriks cakupan test.
- `docs/setup.md`: Petunjuk instalasi lokal dan verifikasi kualitas.
- `docs/deployment.md`: Deployment produksi, backup, dan troubleshooting.
- `docs/user-guide.md`: Panduan operasional untuk Admin KCD dan Operator Sekolah.
- `docs/guidelines.md`: Panduan pengembang, standar testing (PHPUnit coverage >= 80%), kode (Pint), dan aturan kerja.
- `docs/glossary.md`: Istilah BM, SPJ, kunci laporan, dan log aktivitas.

## Aturan Penulisan Path (Larangan Absolute Path)

- **DILARANG KERAS MENGGUNAKAN ABSOLUTE PATH**: Agent DILARANG KERAS memasukkan absolute path sistem lokal (seperti `/Users/...`, `/home/...`, `C:\...`) ke dalam file dokumentasi (`docs/`) maupun file markdown lainnya (`.md`).
- **WAJIB GUNAKAN RELATIVE PATH**: Selalu gunakan relative path dari root repository (contoh: `docs/index.md`, `app/Http/Controllers/PelaporanBm/DashboardController.php`, `resources/js/...`).

## DILARANG `migrate:fresh`

- **DILARANG KERAS** menjalankan `php artisan migrate:fresh` atau `php artisan migrate:fresh --seed` tanpa persetujuan eksplisit dari user. Perintah ini menghapus seluruh data di database utama. Gunakan test (sqlite :memory:) untuk reset skema.
- Jika perlu reset database, tanya user dulu.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>

<!--
Konten di bawah ini adalah aturan proyek dan TIDAK dikelola Laravel Boost.
`php artisan boost:update` hanya mengganti isi blok <laravel-boost-guidelines>
di atas, jadi taruh semua aturan spesifik proyek di luar blok tersebut.
-->

# Panduan Proyek — Pelaporan BM & Distribusi BHP KCD X

Dokumen ini untuk agent dan developer yang melanjutkan pekerjaan. Isinya aturan
yang tidak terlihat dari membaca satu file saja: batas domain, jebakan yang
sudah pernah menelan korban, dan cara memverifikasi perubahan.

## 1. Peta Domain

Satu aplikasi, tiga domain yang **tidak boleh saling bocor**:

| Domain | Prefix URL | Model | Controller | Pages | Dokumen |
|---|---|---|---|---|---|
| Pelaporan BM | `/pelaporan-bm`, `/dashboard`, `/rekapan` | `App\Models\*` | `App\Http\Controllers\PelaporanBm\*` | `resources/js/Pages/PelaporanBm\*` | `docs/prd.md`, `docs/modules.md` |
| Distribusi BHP | `/distribusi-bhp` | `App\Models\DistribusiBhp\*` | `App\Http\Controllers\DistribusiBhp\*` | `resources/js/Pages/DistribusiBhp\*` | `docs/requirements/distribusi-bhp-prd.md`, `docs/requirements/workflow-distribusi-bhp.md` |
| Pencatatan BM (inventaris) | `/pencatatan-bm` | `App\Models\PencatatanBm\*` | `App\Http\Controllers\PencatatanBm\*` | `resources/js/Pages/PencatatanBm\*` | `docs/modules.md` |
| Master data bersama | `/master-data`, `/admin` | `App\Models\Master\*` | `App\Http\Controllers\Master\*`, `Admin\*` | `resources/js/Pages/MasterData\*`, `Admin\*` | `docs/database.md`, `docs/security.md` |

Aplikasi legacy PHP murni ada di `legacy/` dan **hanya sebagai acuan paritas
perilaku**, bukan kode yang dijalankan.

## 2. Aturan Wajib (Non-Negotiable)

### Isolasi tenant

- Controller lintas sekolah wajib memanggil `resolveSekolahId($request)` di awal
  aksi (`index`, `unduh`, `check`) dan memakai hasilnya untuk scope query.
  Jangan pakai `$user->sekolah_id` langsung: user sekolah tanpa `sekolah_id`
  akan lolos ke jalur "lihat semua sekolah".
- Setiap mutasi (`update`, `delete`, `approve`, `reject`) wajib memeriksa
  kepemilikan record, bukan hanya UUID valid. IDOR adalah bug yang paling mahal
  di aplikasi ini karena satu sekolah bisa melihat data sekolah lain.
- Detail: `docs/security.md`. Skill: `tenant-isolation`.

### Sumber dana BHP

`App\Support\DistribusiBhp\SumberDana` adalah **satu-satunya** sumber kebenaran
pemisahan tabel BHP. Tabel transaksi & master barang dipisah fisik
(`bhp_bos_*` / `bhp_bopd_*`); tidak ada kolom `sumber_dana` di tabel itu.
`bhp_penyedia` dan `bhp_divisi` tetap satu tabel bersama.

```php
// Query apa pun terhadap tabel BHP harus dibungkus:
return SumberDana::dengan($sumberDana, function () use ($sekolahId): Response {
    $items = Pengajuan::with('details.barang')
        ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
        ->paginate(25);

    return Inertia::render('DistribusiBhp/Pengajuan/Index', ['items' => $items]);
});

// Validasi `exists` WAJIB lewat helper, bukan nama tabel literal:
'assignments.*.id' => ['required', 'uuid', SumberDana::existsRule('kode_rekening', $sumberDana)],
```

Aturan turunannya:

- `SumberDana::pilih($input)` memakai urutan: input eksplisit → sumber dana
  middleware (dari prefix URL) → default BOS. Jangan menulis `?? 'BOS'` sendiri.
- Halaman BHP wajib memakai prefix `/distribusi-bhp/{bos|bopd}/...` pada URL
  mutasinya. Route flat default ke BOS, sehingga aksi di halaman BOPD akan
  mengenai tabel yang salah. Penjaga regresi: `tests/Feature/BhpAksiPrefixSumberDanaTest.php`.
- Model BHP memakai trait `HasSumberDanaTable`; jangan menetapkan `$table`
  literal di model baru.

### Dilarang `migrate:fresh`

`php artisan migrate:fresh` menghapus seluruh database. Larangan ini murni soal
kehilangan data, bukan soal teknis: perintah itu **berjalan normal** sekarang
(dulu gagal di PostgreSQL pada migrasi split BHP karena drop tabel tanpa
`CASCADE` dan urutan drop salah — sudah diperbaiki di migrasi itu sendiri).

Untuk reset skema pakai test (SQLite `:memory:`). Kalau benar-benar perlu reset
database, **tanya user dulu**, dan lebih aman buat database baru lalu
`migrate --force` di sana.

### Dilarang `confirm()` bawaan browser

Aksi destruktif wajib memakai `resources/js/Components/ConfirmDialog.tsx`.
`confirm()` tidak bisa di-style, tidak bisa diuji, dan diblokir sebagian browser.
Ada test yang memindai pemakaian `confirm()` di halaman BHP.

### Dilarang absolute path di dokumentasi

Semua `.md` wajib memakai path relatif dari root repo (`docs/api.md`,
`app/Http/Controllers/...`). Jangan menulis `/Users/...`, `/home/...`, `C:\...`.

## 3. Konvensi UI

### Tabel vs daftar kartu

Tabel dengan **5 kolom atau lebih** tidak boleh hanya di-`overflow-x-auto`:
kolom aksi ada di ujung kanan dan tidak terlihat di ponsel. Setiap halaman
riwayat wajib punya dua tampilan:

```tsx
<ul className="md:hidden divide-y divide-slate-100">{/* kartu */}</ul>
<div className="hidden md:block overflow-x-auto">{/* tabel */}</div>
```

Kontrak ini dijaga `tests/Feature/BhpUiKontrakTest.php`, yang memindai source
dan gagal bila sebuah halaman punya tabel tanpa padanan daftar mobile. Menambah
halaman riwayat baru berarti menambah entri di data provider test itu.

Laporan yang memang 8+ kolom (Buku Penerimaan, rekonsiliasi bulanan) tetap
scroll horizontal di desktop, tetapi tetap wajib punya ringkasan mobile.

### Ukuran & kontras

| Hal | Aturan |
|---|---|
| Tombol utama / submit | `min-h-11` (44px) |
| Tombol aksi & navigasi | `min-h-10` (40px) |
| Aksi di dalam baris tabel | `min-h-9` (36px) |
| Ukuran teks minimum | `text-xs` (12px). **Jangan** `text-[11px]` |
| Warna teks sekunder | `text-slate-500` ke atas. **Jangan** `text-slate-400` untuk teks yang dibaca (kontras 2.56:1, gagal WCAG AA) |
| Tabel kosong | `<EmptyState>`, bukan baris `<td>Belum ada data` |

Ukur ulang bila mengganti warna teks — pakai skrip `contrast.py` dari skill
`a11y-audit` (argumen: warna teks, warna latar, mis. `#64748b #ffffff`).
Ambang: teks normal ≥ 4.5:1, teks besar ≥ 3:1.

### Label form

Setiap `<label>` wajib punya `htmlFor` yang cocok dengan `id` input. Tanpa itu
klik label tidak memfokuskan input dan screen reader tidak mengumumkan nama field.

### Komponen bersama

Cek daftar ini sebelum membuat komponen baru:

| Komponen | Kegunaan |
|---|---|
| `Pagination.tsx` | Navigasi paginator Inertia (target sentuh 36px, wrap) |
| `PeriodeFilter.tsx` | Pemilih bulan/tahun laporan BHP; satu-satunya pembentuk parameter `bulan`/`tahun` |
| `Modal.tsx` | Dialog accessible: `role="dialog"`, `aria-modal`, focus trap, focus restore, scroll lock |
| `ConfirmDialog.tsx` | Konfirmasi aksi destruktif, pengganti `confirm()` |
| `EmptyState.tsx` | Tampilan seragam saat data kosong (mendukung `action`) |
| `SearchInput.tsx` | Pencarian live dengan tombol clear |
| `CardStat.tsx` | Kartu metrik dashboard |
| `StatusBadge.tsx` | Badge status laporan dinas |

### Utilitas format

`resources/js/Utils/format.ts` — jangan duplikasi logika ini:

| Fungsi | Keluaran | Pakai untuk |
|---|---|---|
| `formatRupiah(n)` | `Rp 55.000` | nominal |
| `formatTanggal(v)` | `2026-06-25` | nilai `<input type="date">` |
| `formatTanggalTabel(v)` | `25-06-2026` | kolom tabel & kartu, sama dengan PDF |
| `tanggalHariIni()` | `YYYY-MM-DD` lokal | default tanggal form |
| `BULAN_LIST`, `getNamaBulan(n)` | `Januari`… | filter periode |

`tanggalHariIni()` memakai zona waktu lokal. Jangan pakai `toISOString()`
langsung: di WIB sebelum 07:00 hasilnya masih tanggal kemarin.

## 4. Alur Pengajuan BHP (Satu Tombol)

Perubahan desain yang disengaja — jangan dikembalikan ke alur lama:

1. Operator mengisi barang lalu menekan **Ajukan Pengajuan** (satu tombol).
   Tidak ada tombol "Simpan Draft" di UI.
2. Isian diautosave ke `localStorage` per sumber dana
   (`distribusi_bhp_pengajuan_BOS` / `_BOPD`), debounce 400 ms, dipulihkan saat
   halaman dibuka lagi, dan baru dihapus setelah submit berhasil. Ini pengganti
   draft server. Baris dengan `barang_id` yang sudah tidak ada di master dibuang
   saat pemulihan agar tidak menyimpan id mati.
3. `PengajuanController::store()` menjalankan create + `StokService::submit()`
   dalam **satu `DB::transaction`**. Stok kurang = seluruh transaksi batal,
   pesan dikirim sebagai `flash.error`. Tidak boleh ada draft menggantung.
4. `index()` menyembunyikan `status = draft` dari riwayat (sisa alur lama).
5. Isian form hanya dibersihkan bila `flash.success` ada — Inertia memanggil
   `onSuccess` juga saat server membalas dengan flash error.
6. Dashboard menampilkan statistik **per sumber dana**; kartu Draft sudah dihapus.

## 5. Lingkungan

| Lingkungan | Domain | APP_ENV | Database |
|---|---|---|---|
| Lokal | `http://localhost` / Valet | `local` | PostgreSQL lokal |
| Staging | `https://staging.sidipta-kcd10.my.id` | `staging` | `pelaporan_bm_staging` |
| Produksi | `https://sidipta-kcd10.my.id` | `production` | `aset_kcd_x_superapps_db` |

Staging berada di server yang sama dengan produksi tetapi **direktori, database,
user DB, dan cron terpisah**:

- Direktori: `/var/www/pelaporan-bm-staging` (produksi: `/var/www/aset-kcd-x-superapps`)
- Nginx: `/etc/nginx/sites-available/staging.sidipta-kcd10.my.id`
- Service queue: `pelaporan-bm-staging-queue.service` (belum wajib — aplikasi belum mengirim job async)
- Scheduler: `/etc/cron.d/pelaporan-bm-staging`
- Trafik masuk lewat Cloudflare Tunnel (`/etc/cloudflared/config.yml`), bukan
  port 80 publik. Menambah site baru berarti menambah entri `ingress` di file
  itu lalu `systemctl restart cloudflared`.

Prosedur lengkap dan rollback: `docs/deployment.md` §8 (Staging).

### Jebakan lingkungan

Tiga hal ini sudah pernah membuat staging/produksi gagal:

1. **Turnstile.** `TurnstileService::verify()` hanya meloloskan login tanpa
   Turnstile bila `app()->isLocal()` atau unit test. Di `staging`/`production`
   dengan `TURNSTILE_ENABLED=false`, semua login **ditolak** dengan pesan
   "Verifikasi keamanan gagal". Untuk staging pakai Cloudflare test key
   `1x00000000000000000000AA` (site) + `1x0000000000000000000000000000000AA`
   (secret) yang selalu lolos. Jangan pernah pakai key itu di produksi.
2. **Email notifikasi backup.** `config/backup.php` memvalidasi
   `BACKUP_NOTIFICATION_EMAIL`; nilai kosong membuat seluruh aplikasi 500
   (`InvalidConfig: is not a valid email address`) karena service provider
   dijalankan saat boot. Selalu isi dengan email valid, termasuk di staging.
3. **Seeder sekolah.** `KodeSekolahSeeder` membaca `storage/bm-kcd-x.sql` dan
   **diam-diam tidak melakukan apa pun** bila file itu tidak ada. Akibatnya
   `DemoDistribusiBhpSmkn4KuninganSeeder` gagal dengan "Sekolah NPSN 20246369
   tidak ditemukan". Buat sekolahnya dulu, baru seed demo.

### Kredensial

Password akun demo dan staging **tidak ditulis di repo**. Minta ke maintainer,
atau lihat `database/seeders/UserSeeder.php` dan
`database/seeders/DemoDistribusiBhpSmkn4KuninganSeeder.php` untuk nilai
default seeder (hanya berlaku pada instalasi baru).

## 6. Verifikasi Sebelum Commit

Jalankan berurutan; jangan mengaku selesai tanpa output yang mendukung:

```bash
composer analyse                                   # Larastan level 5, 0 error
npx tsc --noEmit                                   # 0 error
vendor/bin/pint --dirty --format agent             # formatting PHP
php artisan test --compact                         # seluruh suite
npm run build                                      # manifest Vite valid
```

Untuk perubahan UI, tambahkan pemeriksaan viewport 320 / 375 / 768 / 1280:
tidak ada horizontal scroll, tidak ada target sentuh < 24px, dan daftar kartu
muncul di bawah `md`.

Coverage: `composer test-coverage` menegakkan total **dan per file** ≥ 80%.
Menambah cabang logika baru berarti menambah test, bukan menurunkan gate.

## 7. Deployment

- Produksi: `./deploy.sh` (pull → `composer install` → tanya migrasi → backup
  `pg_dump -Fc` → migrate → build → cache → `permission:cache-reset`).
  Script membatalkan migrasi bila dump kosong, dan selalu `php artisan up`
  lewat `trap`.
- Bila `.env` berubah, wajib `php artisan config:cache` ulang; kalau tidak,
  nilai lama tetap terpakai.
- Setelah deploy: `php artisan permission:cache-reset` (Spatie menyimpan cache
  permission, bukan config).
- Rollback: `git reset --hard <tag>` + `php artisan migrate:rollback --step=N`
  hanya bila migrasi terakhir bermasalah; backup dulu.
- Detail produksi, backup, dan troubleshooting: `docs/deployment.md`.

## 8. Aturan Proyek di `.ai/rules`

`.ai/rules/index.md` memetakan glob file ke file aturan. Sebelum mengubah kode,
baca aturan yang glob-nya cocok dengan path yang disentuh, lalu
`grep -rin 'kata-kunci' .ai/rules` untuk menangkap aturan yang tidak tercocokkan
oleh path.

Aturan yang sudah tercatat (jangan diduplikasi di sini):

| Area | File |
|---|---|
| Semua file (deployment) | `.ai/rules/deployment.md` |
| `tests/**`, `phpunit.xml` | `.ai/rules/general.md`, `.ai/rules/tests.md` |
| `app/Http/Controllers/PelaporanBm/**` | `.ai/rules/pelaporan-bm.md` |

Aturan baru yang tahan lama (keputusan, jebakan non-obvious, batasan tetap)
dicatat dengan **`record-rule`**, bukan dengan memory pribadi agent — hanya
`.ai/rules` yang ikut ter-commit dan diwarisi orang lain.

## 9. Skill Domain

Aktifkan skill yang cocok sebelum menulis kode; jangan improvisasi:

| Situasi | Skill |
|---|---|
| Menyentuh scope `sekolah_id` / mutasi lintas sekolah | `tenant-isolation` |
| Query Eloquent baru, relasi, data besar | `query-optimization` |
| Ekspor CSV/Excel | `csv-excel-export` |
| Komponen UI baru, hindari wrapper trivial | `ui-anti-slop` |
| Desain ulang / audit tampilan | `impeccable`, `a11y-audit` |
| Over-engineering, diff membengkak | `ponytail` |
| Menulis test Laravel | `laravel-testing`, `testing-best-practices` |

## 10. Jebakan yang Sudah Pernah Terjadi

Daftar ini berasal dari bug nyata, bukan teori. Membacanya lebih murah daripada
menemukannya ulang.

| Gejala | Penyebab | Perbaikan |
|---|---|---|
| BOPD "id invalid" / "Sumber dana tidak sesuai" padahal data ada | Aksi memakai route flat (default BOS) | Pakai prefix `/distribusi-bhp/{dana}/...` + `SumberDana::dengan()` |
| Draft BOPD tidak bisa dihapus dari halaman BOPD | `destroy()` mengambil sumber dana dari input request, bukan dari record | Default `SumberDana::pilih($input ?? $pengajuan->sumber_dana)` |
| `select sum(...) ... for update` → SQLSTATE 0A000 | PostgreSQL menolak agregat + `lockForUpdate` | Ambil barisnya dulu dengan `lockForUpdate()->get()`, jumlahkan di PHP |
| Pencarian nama sekolah tidak menemukan apa pun di produksi | `nama_sekolah` huruf besar + `LIKE` peka huruf besar/kecil di PostgreSQL | `SearchesText::whereAnyLike()` (ILIKE + escape wildcard) |
| Seluruh aplikasi 500 setelah `.env` baru | `BACKUP_NOTIFICATION_EMAIL` kosong | Isi email valid, lalu `config:cache` |
| Login ditolak di staging/produksi | `TURNSTILE_ENABLED=false` tapi environment bukan local | Isi key Turnstile yang valid |
| Unduhan Excel 204 terus | Filter kosong — memang tidak ada berkas; status dikirim lewat cookie `download_status=empty` | Cek filter/periode, bukan queue |
| Test hijau lokal, pecah di produksi | SQLite test tidak menegakkan aturan PostgreSQL | Jalankan suite dengan `DB_CONNECTION=pgsql` untuk perubahan query ber-lock/skema |

