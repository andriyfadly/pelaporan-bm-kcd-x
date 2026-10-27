<?php

namespace App\Http\Controllers\PelaporanBm;

use App\Http\Controllers\Concerns\LocksPelaporanBmPeriod;
use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\SearchesText;
use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\Realisasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AcuanController extends Controller
{
    use LocksPelaporanBmPeriod, ResolvesSekolah, SearchesText;

    public function index(Request $request): Response
    {
        $filterBulan = $request->has('bulan') ? $request->input('bulan') : $this->defaultBulan();
        $filterTahun = (int) $request->input('tahun', now()->year);
        $searchSatuan = trim((string) $request->input('search_satuan', ''));
        $sekolahId = $this->resolveSekolahId($request);

        $query = Acuan::with('sekolah')->where('tahun', $filterTahun);

        if ($sekolahId) {
            $query->where('sekolah_id', $sekolahId);
        }

        if ($filterBulan !== '' && $filterBulan !== null) {
            $query->where('bulan', (int) $filterBulan);
        }

        if (! empty($searchSatuan)) {
            $query->where(function ($q) use ($searchSatuan): void {
                $this->whereAnyLike($q, ['uraian'], $searchSatuan);
                $q->orWhereHas('sekolah', fn ($s) => $this->whereAnyLike($s, ['nama_sekolah', 'npsn'], $searchSatuan));
            });
        }

        $totalNominal = (float) (clone $query)->sum('nominal');
        $totalSekolah = (int) (clone $query)->whereNotNull('sekolah_id')->distinct('sekolah_id')->count('sekolah_id');

        $listBulan = Acuan::query()
            ->where('tahun', $filterTahun)
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
            'filterTahun' => $filterTahun,
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
            'tahun' => 'nullable|integer|between:2026,9999',
        ]);

        // Tenant isolation: user sekolah selalu dipaksa ke sekolahnya sendiri,
        // sekolah_id kiriman request diabaikan (konsisten dengan ResolvesSekolah).
        $validated['sekolah_id'] = $this->resolveSekolahIdWajib($request);
        $validated['tahun'] = (int) ($validated['tahun'] ?? date('Y', strtotime($validated['tanggal'])));
        $mutationBlocked = false;
        DB::transaction(function () use ($validated, &$mutationBlocked): void {
            if ($this->pelaporanPeriodIsLocked($this->lockPelaporanPeriod($validated['sekolah_id'], (int) $validated['bulan'], $validated['tahun']))) {
                $mutationBlocked = true;

                return;
            }

            Acuan::create($validated);
        });

        if ($mutationBlocked) {
            return back()->with('error', 'Laporan bulan ini telah dikunci atau dikirim.');
        }

        return back()->with('success', 'Data acuan berhasil ditambahkan.');
    }

    /**
     * Bulan default untuk filter: bulan berjalan - 1, dengan Januari (0)
     * dibulatkan mundur ke Desember.
     */
    protected function defaultBulan(?int $bulanSekarang = null): int
    {
        $bulan = ($bulanSekarang ?? (int) date('n')) - 1;

        return $bulan <= 0 ? 12 : $bulan;
    }

    public function destroy(Acuan $acuan, Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);
        if ($sekolahId && $acuan->sekolah_id !== $sekolahId) {
            abort(403, 'Tidak memiliki akses');
        }

        $error = null;
        DB::transaction(function () use ($acuan, &$error): void {
            if ($this->pelaporanPeriodIsLocked($this->lockPelaporanPeriod($acuan->sekolah_id, (int) $acuan->bulan, (int) $acuan->tahun))) {
                $error = 'Laporan bulan ini telah dikunci atau dikirim.';

                return;
            }

            $acuan = Acuan::whereKey($acuan->id)->lockForUpdate()->firstOrFail();
            if ($acuan->spj()->exists() || $acuan->realisasi()->exists()) {
                $error = 'Acuan sudah digunakan dan tidak dapat dihapus.';

                return;
            }

            $acuan->delete();
        });

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Data acuan berhasil dihapus.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $bulan = $request->input('bulan');
        $tahun = (int) $request->input('tahun', now()->year);
        $query = Acuan::query();
        $sekolahId = $this->resolveSekolahId($request);
        if ($sekolahId) {
            $query->where('sekolah_id', $sekolahId);
        }
        if ($bulan !== '' && $bulan !== null) {
            $query->where('bulan', (int) $bulan);
        }
        $query->where('tahun', $tahun);
        $jumlah = 0;
        $error = null;
        DB::transaction(function () use ($query, &$jumlah, &$error): void {
            $acuans = $query->orderBy('sekolah_id')->orderBy('bulan')->lockForUpdate()->get();
            foreach ($acuans->unique(fn (Acuan $acuan) => $acuan->sekolah_id.'|'.$acuan->bulan.'|'.$acuan->tahun) as $acuan) {
                if ($this->pelaporanPeriodIsLocked($this->lockPelaporanPeriod($acuan->sekolah_id, (int) $acuan->bulan, (int) $acuan->tahun))) {
                    $error = 'Salah satu periode telah dikunci atau dikirim.';

                    return;
                }
            }

            $ids = $acuans->modelKeys();
            if (Realisasi::whereIn('acuan_id', $ids)->exists() || Acuan::whereIn('id', $ids)->whereHas('spj')->exists()) {
                $error = 'Salah satu acuan sudah digunakan dan tidak dapat dihapus.';

                return;
            }

            $jumlah = count($ids);
            Acuan::whereIn('id', $ids)->delete();
        });

        if ($error !== null) {
            return back()->with('error', $error);
        }

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

        if (! $sekolahId) {
            $npsnList = collect($rows)->pluck(1)->map(fn ($npsn) => trim((string) $npsn))->filter()->unique();
            if (collect($rows)->contains(fn (array $row) => trim((string) ($row[1] ?? '')) === '')) {
                return back()->with('error', 'Impor dibatalkan: setiap baris wajib memiliki NPSN untuk menentukan sekolah.');
            }
            $knownNpsn = Sekolah::whereIn('npsn', $npsnList)->pluck('id', 'npsn');
            $unknownNpsn = $npsnList->reject(fn (string $npsn) => $knownNpsn->has($npsn));
            if ($unknownNpsn->isNotEmpty()) {
                return back()->with('error', 'Impor dibatalkan: NPSN tidak ditemukan: '.$unknownNpsn->implode(', ').'.');
            }
        }

        $importError = null;
        DB::transaction(function () use ($rows, $sekolahId, $bulanInput, &$count, &$skipped, &$importError) {
            $periods = collect($rows)->map(function (array $row) use ($sekolahId, $bulanInput): array {
                $targetSekolahId = $sekolahId ?: Sekolah::where('npsn', trim((string) ($row[1] ?? '')))->value('id');
                $bulan = ! empty($row[7]) ? (int) $row[7] : ((int) $bulanInput ?: (int) date('n'));
                $tahun = (int) date('Y', strtotime((string) ($row[2] ?? '')));

                return ['sekolah_id' => $targetSekolahId, 'bulan' => $bulan, 'tahun' => $tahun];
            })->unique(fn (array $period) => $period['sekolah_id'].'|'.$period['bulan'].'|'.$period['tahun'])->sortBy(fn (array $period) => $period['sekolah_id'].'|'.$period['tahun'].'|'.sprintf('%02d', $period['bulan']));

            foreach ($periods as $period) {
                if ($this->pelaporanPeriodIsLocked($this->lockPelaporanPeriod($period['sekolah_id'], $period['bulan'], $period['tahun']))) {
                    $importError = 'Impor dibatalkan: salah satu periode telah dikunci atau dikirim.';

                    return;
                }
            }

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
                    'tahun' => (int) date('Y', strtotime($tanggal)),
                ]);
                $count++;
            }
        });

        if ($importError !== null) {
            return back()->with('error', $importError);
        }

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
                        preg_match('/^[A-Z]+/', (string) $c['r'], $coordinate);
                        $column = 0;
                        foreach (str_split($coordinate[0] ?? 'A') as $letter) {
                            $column = ($column * 26) + ord($letter) - 64;
                        }
                        $row[$column - 1] = $val;
                    }
                    if (! empty(array_filter($row, fn ($v) => trim((string) $v) !== ''))) {
                        $row = array_replace(array_fill(0, max(array_keys($row)) + 1, ''), $row);
                        $rows[] = $row;
                    }
                }
            }
        }
        $zip->close();

        return $rows;
    }
}
