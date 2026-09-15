<?php

namespace App\Http\Controllers\PelaporanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        if (empty($validated['sekolah_id']) && $request->user()->sekolah_id) {
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

        Log::warning('acuan.destroy_all', [
            'aktor' => $request->user()->id,
            'sekolah_id' => $request->user()->sekolah_id,
            'bulan' => $bulan,
            'jumlah' => $jumlah,
        ]);

        $pesan = $bulan ? "Data acuan untuk bulan {$bulan} berhasil dikosongkan." : 'Semua data acuan berhasil dikosongkan.';

        return back()->with('success', $pesan);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:csv,txt',
            'bulan' => 'nullable|integer|between:1,12',
            'sekolah_id' => 'nullable|uuid|exists:master_data_sekolah,id',
        ]);

        $sekolahId = $this->resolveSekolahId($request);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $bulanInput = $request->input('bulan');

        $count = 0;
        $skipped = 0;
        // ponytail: parser CSV/TXT native; format template legacy:
        // [0: Satuan Pendidikan, 1: NPSN, 2: Tanggal, 3: Kodering, 4: BKU, 5: Uraian, 6: Nominal, 7: Bulan]
        if (in_array($extension, ['csv', 'txt'])) {
            $handle = fopen($file->getRealPath(), 'r');
            fgetcsv($handle); // lewati baris header

            $rows = [];
            while (($row = fgetcsv($handle, 2000, ',')) !== false) {
                $rows[] = $row;
            }
            fclose($handle);

            // ponytail: batas 5000 baris per import, naikkan jika kebutuhan riil melebihi
            if (count($rows) > 5000) {
                return back()->with('error', 'Berkas terlalu besar: maksimal 5000 baris data per impor.');
            }

            DB::transaction(function () use ($rows, $sekolahId, $bulanInput, &$count, &$skipped) {
                foreach ($rows as $row) {
                    if (count($row) < 4) {
                        $skipped++;

                        continue;
                    }

                    if (count($row) >= 7) {
                        // Template Lengkap Vendor: Satuan Pendidikan, NPSN, Tanggal, Kodering, BKU, Uraian, Nominal, Bulan
                        $satuanPendidikan = trim($row[0] ?? '');
                        $npsn = trim($row[1] ?? '');
                        $tanggal = trim($row[2] ?? '') ?: now()->toDateString();
                        $kodering = trim($row[3] ?? '');
                        $bku = trim($row[4] ?? '');
                        $uraian = trim($row[5] ?? 'Acuan Import');
                        $nominal = (float) str_replace(['.', ',', ' '], '', $row[6] ?? '0');
                        $bulan = ! empty($row[7]) ? (int) $row[7] : ((int) $bulanInput ?: (int) date('n'));
                    } else {
                        // Format Sederhana: Tanggal, Kodering, BKU, Uraian, Nominal, [Bulan]
                        $satuanPendidikan = '';
                        $npsn = '';
                        $tanggal = trim($row[0] ?? '') ?: now()->toDateString();
                        $kodering = trim($row[1] ?? '');
                        $bku = trim($row[2] ?? '');
                        $uraian = trim($row[3] ?? 'Acuan Import');
                        $nominal = (float) str_replace(['.', ',', ' '], '', $row[4] ?? '0');
                        $bulan = ! empty($row[5]) ? (int) $row[5] : ((int) $bulanInput ?: (int) date('n'));
                    }

                    // Validasi per-baris: bulan & tanggal wajib valid, nominal tidak negatif
                    if ($bulan < 1 || $bulan > 12 || $nominal < 0 || ! strtotime($tanggal)) {
                        $skipped++;

                        continue;
                    }

                    $targetSekolahId = $sekolahId;
                    if (! $targetSekolahId && ! empty($npsn)) {
                        // Hanya NPSN exact — tanpa fuzzy match nama (risiko salah atribusi)
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
        } else {
            return back()->with('error', 'Silakan gunakan berkas CSV yang diekspor dari template Excel resmi.');
        }

        $pesan = "Berhasil mengimpor {$count} data acuan.";
        if ($skipped > 0) {
            $pesan .= " {$skipped} baris dilewati karena tidak valid.";
        }

        Log::info('acuan.import', [
            'aktor' => $request->user()->id,
            'sekolah_id' => $sekolahId,
            'bulan' => $bulanInput,
            'berhasil' => $count,
            'dilewati' => $skipped,
        ]);

        return back()->with('success', $pesan);
    }
}
