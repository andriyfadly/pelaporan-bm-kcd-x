---
paths:
  - 'app/Models/**'
---

# Model & Analisis Statis (Larastan)

`composer analyse` menjalankan PHPStan level 5 (config `phpstan.neon`) atas `app/`.

## Deklarasikan atribut model sebagai `@property`

Larastan membaca skema dari file migrasi, dan migrasi proyek ini banyak yang membangun nama tabel secara dinamis (`bhp_{$dana}_*`) atau menambah kolom di dalam loop (`pelaporan_bm_*`). Tabel seperti itu tidak terbaca, sehingga **setiap atribut model wajib punya baris `@property`** di docblock kelas model.

Tipe mengikuti cast, bukan tipe kolom mentah: cast `date` → `\Illuminate\Support\Carbon`, cast `array` → `array<string, mixed>`, `decimal:2` → `float`, kolom nullable diberi `|null`.

## Relasi wajib bergenerik

Setiap method relasi diberi PHPDoc:

```php
/** @return BelongsTo<Sekolah, $this> */
public function sekolah(): BelongsTo

/** @return HasMany<PengajuanDetail, $this> */
public function details(): HasMany
```

Tanpa itu Larastan memetakan relasi ke `Illuminate\Database\Eloquent\Model` dan semua akses atribut lewat relasi jadi error.

## Akibatnya

Menambah kolom = menambah `@property` di model. Menambah relasi = menambah `@return`. Kalau lupa, `composer analyse` gagal dan itu memang gate-nya.
