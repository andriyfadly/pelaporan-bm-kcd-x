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
