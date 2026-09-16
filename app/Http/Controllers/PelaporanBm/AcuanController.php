<?php

namespace App\Http\Controllers\PelaporanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AcuanController extends Controller
{
    use ResolvesSekolah;

    public function index(Request $request): Response
    {
        $defaultBulan = (int) date('n') - 1;
        if ($defaultBulan <= 0) {
            $defaultBulan = 12;
        }

        $filterBulan = $request->has('bulan') ? $request->input('bulan') : $defaultBulan;
        $searchSatuan = trim((string) $request->input('search_satuan', ''));
        $sekolahId = $this->resolveSekolahId($request);

        $query = Acuan::with('sekolah');

        if ($sekolahId) {
            $query->where('sekolah_id', $sekolahId);
        }

        if ($filterBulan !== '' && $filterBulan !== null) {
            $query->where('bulan', (int) $filterBulan);
        }

        if (! empty($searchSatuan)) {
            $escapedSatuan = addcslashes($searchSatuan, '%_\\');
            $query->whereHas('sekolah', function ($s) use ($escapedSatuan) {
                $s->where('nama_sekolah', 'like', "%{$escapedSatuan}%")
                    ->orWhere('npsn', 'like', "%{$escapedSatuan}%");
            });
        }

        $totalNominal = (float) (clone $query)->sum('nominal');
        $totalSekolah = (int) (clone $query)->whereNotNull('sekolah_id')->distinct('sekolah_id')->count('sekolah_id');

        $listBulan = Acuan::query()
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->whereNotNull('bulan')
            ->distinct()
            ->orderBy('bulan')
            ->pluck('bulan')
            ->toArray();

        if (empty($listBulan)) {
            $listBulan = range(1, 12);
        }

        return Inertia::render('PelaporanBm/Acuan/Index', [
            'items' => $query->latest('id')->paginate(50)->withQueryString(),
            'totalNominal' => $totalNominal,
            'totalSekolah' => $totalSekolah,
            'filterBulan' => $filterBulan !== '' && $filterBulan !== null ? (int) $filterBulan : '',
            'searchSatuan' => $searchSatuan,
            'listBulan' => $listBulan,
            'sekolahs' => $request->user()->sekolah_id ? [] : Sekolah::select('id', 'nama_sekolah')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sekolah_id' => 'nullable|uuid|exists:master_data_sekolah,id',
            'tanggal' => 'required|date',
            'kodering' => 'nullable|string|max:100',
            'bku' => 'nullable|string|max:100',
            'uraian' => 'required|string',
            'nominal' => 'required|numeric|min:0',
            'bulan' => 'required|integer|between:1,12',
        ]);

        // Tenant isolation: user sekolah selalu dipaksa ke sekolahnya sendiri,
        // sekolah_id kiriman request diabaikan (konsisten dengan ResolvesSekolah).
        if ($request->user()->sekolah_id) {
            $validated['sekolah_id'] = $request->user()->sekolah_id;
        }

        Acuan::create($validated);

        return back()->with('success', 'Data acuan berhasil ditambahkan.');
    }

    public function destroy(Acuan $acuan, Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);
        if ($sekolahId && $acuan->sekolah_id !== $sekolahId) {
            abort(403, 'Tidak memiliki akses');
        }

        $acuan->delete();

        return back()->with('success', 'Data acuan berhasil dihapus.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $bulan = $request->input('bulan');
        $query = Acuan::query();
        $sekolahId = $this->resolveSekolahId($request);
        if ($sekolahId) {
            $query->where('sekolah_id', $sekolahId);
        }
        if ($bulan !== '' && $bulan !== null) {
            $query->where('bulan', (int) $bulan);
        }
        $jumlah = (int) (clone $query)->count();
        $query->delete();

        activity('sistem')
            ->event('hapus-massal-acuan')
            ->withProperties([
                'ringkasan' => ($bulan ? "Hapus {$jumlah} acuan bulan {$bulan}" : "Hapus {$jumlah} acuan (semua bulan)"),
                'sekolah_id' => $sekolahId,
                'bulan' => $bulan,
                'jumlah' => $jumlah,
            ])
            ->log('hapus-massal-acuan');

        $pesan = $bulan ? "Data acuan untuk bulan {$bulan} berhasil dikosongkan." : 'Semua data acuan berhasil dikosongkan.';

        return back()->with('success', $pesan);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:xlsx,xls',
            'bulan' => 'nullable|integer|between:1,12',
            'sekolah_id' => 'nullable|uuid|exists:master_data_sekolah,id',
        ]);

        $sekolahId = $this->resolveSekolahId($request);
        $file = $request->file('file');
        $bulanInput = $request->input('bulan');

        $count = 0;
        $skipped = 0;
        // ponytail: format template legacy (input_acuan.php):
        // [0: Satuan Pendidikan, 1: NPSN, 2: Tanggal, 3: Kodering, 4: BKU, 5: Uraian, 6: Nominal, 7: Bulan]
        $rows = array_slice($this->parseXlsx($file->getRealPath()), 1);

        // ponytail: batas 5000 baris per import, naikkan jika kebutuhan riil melebihi
        if (count($rows) > 5000) {
            return back()->with('error', 'Berkas terlalu besar: maksimal 5000 baris data per impor.');
        }

        DB::transaction(function () use ($rows, $sekolahId, $bulanInput, &$count, &$skipped) {
            foreach ($rows as $row) {
                if (count($row) < 7) {
                    $skipped++;

                    continue;
                }

                $npsn = trim((string) ($row[1] ?? ''));
                $tanggal = $this->parseTanggal($row[2] ?? '');
                $kodering = trim((string) ($row[3] ?? ''));
                $bku = trim((string) ($row[4] ?? ''));
                $uraian = trim((string) ($row[5] ?? ''));
                $nominal = (float) str_replace(['.', ',', ' '], '', (string) ($row[6] ?? '0'));
                $bulan = ! empty($row[7]) ? (int) $row[7] : ((int) $bulanInput ?: (int) date('n'));

                if ($uraian === '' || $tanggal === '' || $bulan < 1 || $bulan > 12 || $nominal < 0) {
                    $skipped++;

                    continue;
                }

                $targetSekolahId = $sekolahId;
                if (! $targetSekolahId && $npsn !== '') {
                    $targetSekolahId = Sekolah::where('npsn', $npsn)->value('id');
                }

                Acuan::create([
                    'sekolah_id' => $targetSekolahId,
                    'tanggal' => $tanggal,
                    'kodering' => $kodering,
                    'bku' => $bku,
                    'uraian' => $uraian,
                    'nominal' => $nominal,
                    'bulan' => $bulan,
                ]);
                $count++;
            }
        });

        $pesan = "Berhasil mengimpor {$count} data acuan.";
        if ($skipped > 0) {
            $pesan .= " {$skipped} baris dilewati karena tidak valid.";
        }

        activity('sistem')
            ->event('import-acuan')
            ->withProperties([
                'ringkasan' => "Impor acuan: {$count} berhasil, {$skipped} dilewati",
                'sekolah_id' => $sekolahId,
                'bulan' => $bulanInput,
                'berhasil' => $count,
                'dilewati' => $skipped,
            ])
            ->log('import-acuan');

        return back()->with('success', $pesan);
    }

    private function parseTanggal(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return gmdate('Y-m-d', (int) (((float) $value - 25569) * 86400));
        }

        return strtotime($value) ? date('Y-m-d', strtotime($value)) : '';
    }

    /**
     * Parse Excel .xlsx native via ZipArchive + SimpleXML (tanpa paket tambahan).
     * Mendukung shared strings, inline strings, dan angka tanggal serial Excel.
     *
     * @return array<int, array<int, string>>
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
                        } elseif ($type === 'inlineStr') {
                            $val = (string) ($c->is->t ?? $val);
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
}
