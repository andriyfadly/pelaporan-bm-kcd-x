---
paths:
  - 'tests/**'
---

# Tests

## withoutVite di base TestCase
Panggil $this->withoutVite() di tests/TestCase::setUp: halaman Inertia me-render 500 bila Vite manifest belum di-build. Test feature tidak merender asset frontend, jadi tanpa aturan ini test salah-gagal.

## Gate coverage per-file, bukan hanya total
composer test-coverage memeriksa total >= 80% (flag --min) DAN tiap file >= 80% via tests/coverage-per-file.php (parse Clover XML). Status proyek: 100% semua file. Saat menambah kode baru, tutup baris yang belum tercakup agar gate tetap hijau. Cek gap satu file: XDEBUG_MODE=coverage vendor/bin/phpunit <file> --coverage-clover /tmp/cov.xml lalu baca atribut count="0" pada <line type="stmt">.

## Logika waktu harus injectable
date('n')/jam sistem tidak bisa dikunci di test. Ekstrak ke helper ber-argumen opsional (defaultBulan(?int), bulanLapor(?int)) dan uji via ReflectionMethod. Untuk cabang privat (parser/formatter), uji langsung via ReflectionMethod ketimbang memaksa jalur HTTP.
