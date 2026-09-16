<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Master\KodeBarang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KodeBarangController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $search = trim((string) $request->input('search', $request->input('q', '')));

        $query = KodeBarang::query()
            ->when($search, function ($q) use ($search) {
                $escaped = addcslashes($search, '%_\\');
                $q->where('kode_barang', 'like', "%{$escaped}%")
                    ->orWhere('uraian', 'like', "%{$escaped}%")
                    ->orWhere('kodering_aset', 'like', "%{$escaped}%")
                    ->orWhere('jenis_aset', 'like', "%{$escaped}%");
            })
            ->orderBy('kode_barang', 'asc');

        if ($request->wantsJson() || $request->has('ajax')) {
            return response()->json($query->limit(50)->get());
        }

        $items = $query->paginate(25)->withQueryString();

        return Inertia::render('Admin/KodeBarang/Index', [
            'items' => $items,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_barang' => 'required|string|max:50',
            'uraian' => 'required|string|max:255',
            'kodering_aset' => 'nullable|string|max:100',
            'jenis_aset' => 'nullable|string|max:100',
            'umur_ekonomis' => 'nullable|integer|min:0',
            'satuan' => 'nullable|string|max:50',
            'harga_standar' => 'nullable|numeric|min:0',
        ]);

        KodeBarang::updateOrCreate(
            ['kode_barang' => $validated['kode_barang']],
            $validated
        );

        return back()->with('success', 'Kode barang berhasil disimpan.');
    }

    public function destroy(KodeBarang $kodeBarang): RedirectResponse
    {
        $kodeBarang->delete();

        return back()->with('success', 'Kode barang berhasil dihapus.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        $rows = in_array($ext, ['xlsx', 'xls'], true)
            ? $this->parseXlsx($file->getRealPath())
            : $this->parseCsv($file->getRealPath());

        $dataRows = array_slice($rows, 1);

        // ponytail: batas 20000 baris per import (katalog legacy 12.938 kode), naikkan jika perlu
        if (count($dataRows) > 20000) {
            return back()->with('error', 'Berkas terlalu besar: maksimal 20000 baris data per impor.');
        }

        $count = 0;

        DB::transaction(function () use ($dataRows, &$count) {
            foreach ($dataRows as $row) {
                $kode = trim((string) ($row[0] ?? ''));
                if ($kode === '') {
                    continue;
                }

                KodeBarang::updateOrCreate(
                    ['kode_barang' => $kode],
                    [
                        'uraian' => trim((string) ($row[1] ?? 'Tanpa Nama')),
                        'kodering_aset' => trim((string) ($row[2] ?? '')) ?: null,
                        'jenis_aset' => trim((string) ($row[3] ?? '')) ?: 'Peralatan dan Mesin',
                        'umur_ekonomis' => (int) ($row[4] ?? 0),
                        'satuan' => trim((string) ($row[5] ?? 'Unit')) ?: 'Unit',
                        'harga_standar' => (float) str_replace([',', ' '], '', (string) ($row[6] ?? 0)),
                    ]
                );
                $count++;
            }
        });

        activity('sistem')
            ->event('import-kode-barang')
            ->withProperties([
                'ringkasan' => "Impor kode barang: {$count} baris",
                'jumlah' => $count,
            ])
            ->log('import-kode-barang');

        return back()->with('success', "Berhasil mengimpor {$count} data ke database!");
    }

    /**
     * Parse Excel .xlsx file natively using ZipArchive and SimpleXML (zero extra packages).
     */
    private function parseXlsx(string $filePath): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($filePath) !== true) {
            return [];
        }

        $strings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xml = simplexml_load_string($sharedXml);
            if ($xml !== false && isset($xml->si)) {
                foreach ($xml->si as $si) {
                    $text = (string) $si->t;
                    if ($text === '' && isset($si->r)) {
                        foreach ($si->r as $r) {
                            $text .= (string) $r->t;
                        }
                    }
                    $strings[] = $text;
                }
            }
        }

        $rows = [];
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml !== false) {
            $xml = simplexml_load_string($sheetXml);
            if ($xml !== false && isset($xml->sheetData->row)) {
                foreach ($xml->sheetData->row as $r) {
                    $row = [];
                    foreach ($r->c as $c) {
                        $val = (string) $c->v;
                        $type = (string) $c['t'];
                        if ($type === 's' && isset($strings[(int) $val])) {
                            $val = $strings[(int) $val];
                        }
                        $row[] = $val;
                    }
                    if (! empty(array_filter($row, fn ($v) => trim((string) $v) !== ''))) {
                        $rows[] = $row;
                    }
                }
            }
        }
        $zip->close();

        return $rows;
    }

    /**
     * Parse CSV / TXT file using fgetcsv.
     */
    private function parseCsv(string $filePath): array
    {
        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($data = fgetcsv($handle, 4000, ',')) !== false) {
                if (count($data) === 1 && str_contains((string) $data[0], ';')) {
                    $data = str_getcsv((string) $data[0], ';');
                }
                $rows[] = $data;
            }
            fclose($handle);
        }

        return $rows;
    }
}
