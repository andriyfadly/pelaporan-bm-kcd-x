---
paths:
  - 'tests/**'
---

# Tests

## withoutVite di base TestCase
Panggil $this->withoutVite() di tests/TestCase::setUp: halaman Inertia me-render 500 bila Vite manifest belum di-build. Test feature tidak merender asset frontend, jadi tanpa aturan ini test salah-gagal.
