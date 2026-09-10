---
name: query-optimization
description: 'Database query optimization patterns for Laravel Eloquent. Prevents N+1 query problems, enforces eager loading, and optimizes heavy aggregation queries and bulk CSV streaming.'
license: MIT
metadata:
  author: database
---

# Database Query Optimization

Prevent performance bottlenecks, slow database calls, and N+1 queries in Laravel Eloquent.

## Core Rules

1. **Eager Loading Over Lazy Loops (Kill N+1)**:
   - Dilarang mengakses relasi Eloquent di dalam loop `@foreach` atau `map()` tanpa eager loading:
     ```php
     // Benar:
     Spj::with('items')->where('sekolah_id', $id)->get();
     
     // Salah (N+1):
     $spjs = Spj::where('sekolah_id', $id)->get();
     foreach ($spjs as $s) { $s->items; }
     ```

2. **Database-Level Aggregation Over In-Memory Sums**:
   - Hitung total/sum langsung di database engine, bukan menarik seluruh collection ke memori PHP:
     ```php
     // Benar:
     $total = Spj::where('is_realisasi', 1)->sum('nilai_perolehan');
     
     // Salah:
     $total = Spj::where('is_realisasi', 1)->get()->sum('nilai_perolehan');
     ```

3. **LazyCollection & Chunking for Large Exports**:
   - Untuk ekspor ribuan baris CSV 26 kolom, gunakan `cursor()` atau `LazyCollection` agar memory footprint PHP tetap di bawah 10MB.
