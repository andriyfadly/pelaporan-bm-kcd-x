---
name: csv-excel-export
description: 'Standard patterns for CSV and Excel exports in Laravel. Enforces UTF-8 BOM encoding for Excel compatibility, streaming responses to prevent memory exhaustion, and clean column formatting.'
license: MIT
metadata:
  author: export
---

# CSV & Excel Export Standards

Handle large-scale tabular exports reliably without character corruption or memory limits.

## Core Rules

1. **Always Prepend UTF-8 BOM**:
   - Microsoft Excel corrupts UTF-8 characters (accents, formatting) without BOM:
     ```php
     fwrite($handle, "\xEF\xBB\xBF");
     ```

2. **Streamed Response with `php://output`**:
   - Never build huge CSV strings in memory. Stream chunks via `response()->stream()`:
     ```php
     return response()->stream(function () use ($query) {
         $handle = fopen('php://output', 'w');
         fwrite($handle, "\xEF\xBB\xBF");
         fputcsv($handle, $headers, ';');
         
         $query->chunk(500, function ($rows) use ($handle) {
             foreach ($rows as $row) {
                 fputcsv($handle, $row->toCsvArray(), ';');
             }
         });
         fclose($handle);
     }, 200, $headers);
     ```

3. **Pre-flight Check Before Download**:
   - Endpoint check data (`/check`) harus dipanggil sebelum memicu unduhan untuk mencegah pengunduhan file CSV kosong 0 baris.
