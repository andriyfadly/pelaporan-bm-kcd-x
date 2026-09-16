---
name: ui-anti-slop
description: 'Stops and prevents AI slop in UI and component design. Banishes trivial wrappers (<Button>, <Card>, <TextInput>), speculative design systems, dynamic schema form/table engines, unnecessary nesting, and decorative bloat. Enforces pragmatic, direct, clean React + Tailwind code.'
license: MIT
metadata:
  author: antislop
---

# UI Anti-Slop (Stop Slop)

Eliminate AI slop, bloated wrappers, and over-engineered component hierarchies from frontend code.

## The Anti-Slop Commandments

1. **No Trivial Micro-Wrappers (The `<Button>` Trap)**:
   - Dilarang membuat komponen wrapper untuk tag HTML dasar (`<button>`, `<input>`, `<a>`, `<div>`) hanya untuk memetakan varian warna atau ukuran.
   - Gunakan native HTML tag dengan Tailwind utility classes langsung. HTML native tidak membatasi atribut native, event handler, atau aksesibilitas.

2. **No Speculative Design System / Dynamic Schema Engines**:
   - Dilarang membuat komponen tabel dinamis berbasis JSON config (`<DynamicTable columns={...} data={...} />`) yang berusaha menghandle seluruh jenis data.
   - Tabel harus ditulis deklaratif dengan `<table>`, `<thead>`, `<tbody>` langsung di halaman. Hanya ekstrak sub-elemen fungsional yang berulang (misal `<Pagination>`, `<StatusBadge>`).

3. **Rule of Three for Component Extraction**:
   - Dilarang mengekstrak komponen jika hanya dipakai di 1 atau 2 halaman.
   - Komponen baru HANYA boleh dibuat jika polanya identik dan telah digunakan minimal di **3 modul berbeda**.

4. **Kill the Container/Wrapper Divitis**:
   - Hapus pembungkus `<div>` yang tidak memiliki fungsi layout (`flex`, `grid`), positioning, atau styling.
   - Jangan membuat 5 lapis div hanya untuk satu teks atau satu tombol.

5. **Direct State Over Speculative Global Context**:
   - Jangan menambahkan React Context, Redux, atau store global jika state hanya dibutuhkan di satu halaman atau dialog.
   - Percayakan server state pada props Inertia.js dan form state pada `useForm`.

6. **Zero Filler Content**:
   - Dilarang menambahkan teks pengisi basi (*"Welcome back to your ultimate reporting experience!"*), chart kosmetik tanpa data nyata, atau animasi berlebihan yang memperlambat kinerja aplikasi.
