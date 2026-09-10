---
name: tenant-isolation
description: 'Enforces strict multi-tenant data isolation and authorization checks. Guarantees that users bound to a specific school (sekolah_id) can never view, update, delete, or export data belonging to other schools (IDOR prevention).'
license: MIT
metadata:
  author: security
---

# Tenant Isolation & Authorization Guard

Ensure zero cross-tenant data leaks and prevent IDOR (Insecure Direct Object Reference) vulnerabilities.

## Core Rules

1. **Always Scope by `sekolah_id` for School Roles**:
   - Jika `auth()->user()->role === 'user'`, setiap query Eloquent WAJIB memiliki filter:
     ```php
     ->where('sekolah_id', auth()->user()->sekolah_id)
     ```
   - Jangan pernah mempercayai parameter input request (seperti `request('sekolah_id')`) untuk peran non-admin.

2. **Route Model Binding / Direct Mutation Guard**:
   - Sebelum melakukan `update` atau `delete` pada resource (`Spj`, `Acuan`, dll.), periksa kepemilikan data:
     ```php
     if ($user->role !== 'admin' && (int)$record->sekolah_id !== (int)$user->sekolah_id) {
         abort(403, 'Akses ditolak: Anda tidak memiliki wewenang pada data sekolah ini.');
     }
     ```

3. **Pre-flight & Export Isolation**:
   - Pada endpoint cetak laporan atau ekspor CSV, parameter `sekolah_id` hanya boleh ditimpa jika request berasal dari user dengan role `admin`.
