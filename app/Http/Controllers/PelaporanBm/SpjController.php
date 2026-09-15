<?php

namespace App\Http\Controllers\PelaporanBm;

use App\Http\Controllers\Controller;
use App\Models\Master\KodeBarang;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PelaporanBm\Spj;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpjController extends Controller
{
    public function pilihBulan(Request $request): Response
    {
        $bulan = (int) ($request->input('bulan') ?: date('n'));
        if ($bulan < 1 || $bulan > 12) {
            $bulan = (int) date('n');
        }

        return Inertia::render('PelaporanBm/Spj/PilihBulan', [
            'bulanAwal' => $bulan,
        ]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $bulan = (int) ($request->input('bulan') ?: date('n'));
        $sekolahId = $user->sekolah_id ?: $request->input('sekolah_id');
        $mode = $request->input('mode', '');

        $query = Spj::with(['acuan', 'sekolah'])
            ->where('bulan_realisasi', $bulan);

        if ($sekolahId) {
            $query->where('sekolah_id', $sekolahId);
        }

        // Legacy data_barang.php: ORDER BY id DESC (terbaru dulu)
        $items = $query->orderByDesc('created_at')->get();

        $isLocked = false;
        $statusKirim = 'draft';
        if ($sekolahId) {
            $kunci = KunciLaporan::where('sekolah_id', $sekolahId)
                ->where('bulan', (string) $bulan)
                ->first();
            $isLocked = (bool) ($kunci?->status_kunci ?? false);
            $statusKirim = $kunci?->status_kirim ?? 'draft';
        }

        $acuanList = Acuan::where('bulan', $bulan)
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->get();
        $totalAcuan = (float) $acuanList->sum('nominal');

        return Inertia::render('PelaporanBm/Spj/Index', [
            'items' => $items,
            'acuanList' => $acuanList,
            'totalAcuan' => $totalAcuan,
            'bulan' => $bulan,
            'isLocked' => $isLocked,
            'statusKirim' => $statusKirim,
            'mode' => $mode,
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $sekolahId = $user->sekolah_id ?: $request->input('sekolah_id');
        $kategori = $request->input('kategori', 'Peralatan & Mesin');
        $bulan = (int) ($request->input('bulan') ?: date('n'));

        $kunci = KunciLaporan::where('sekolah_id', $sekolahId)->where('bulan', (string) $bulan)->first();
        if ($kunci?->status_kunci || in_array($kunci?->status_kirim, ['menunggu_approval', 'disetujui'], true)) {
            return redirect()->route('pelaporan-bm.spj.index', ['bulan' => $bulan])
                ->with('error', 'Laporan bulan ini telah dikunci/dikirim.');
        }

        return Inertia::render('PelaporanBm/Spj/FormSpk', [
            'kategori' => $kategori,
            'bulan' => $bulan,
            'isEdit' => false,
            'spkData' => null,
        ]);
    }

    public function editSpk(Request $request, string $no_spk): Response|RedirectResponse
    {
        $user = $request->user();
        $sekolahId = $user->sekolah_id ?: $request->input('sekolah_id');
        $bulan = (int) ($request->input('bulan') ?: date('n'));

        $items = Spj::where('sekolah_id', $sekolahId)
            ->where('no_spk', $no_spk)
            ->where('bulan_realisasi', $bulan)
            ->orderBy('created_at')
            ->get();

        if ($items->isEmpty()) {
            return redirect()->route('pelaporan-bm.spj.index', ['bulan' => $bulan])
                ->with('error', 'Data Dokumen SPK tidak ditemukan.');
        }

        $kunci = KunciLaporan::where('sekolah_id', $sekolahId)->where('bulan', (string) $bulan)->first();
        if ($kunci?->status_kunci || in_array($kunci?->status_kirim, ['menunggu_approval', 'disetujui'], true)) {
            return redirect()->route('pelaporan-bm.spj.index', ['bulan' => $bulan])
                ->with('error', 'Laporan bulan ini telah dikunci/dikirim.');
        }

        $first = $items->first();
        $spkData = [
            'no_spk' => $first->no_spk,
            'no_sp2d' => $first->no_sp2d ?? '',
            'sumber_perolehan' => $first->sumber_perolehan ?? 'BOS Reguler',
            'ba_no' => $first->ba_no ?? '',
            'ba_tgl' => $first->ba_tgl ?? '',
            'kategori' => $first->kategori ?? 'Peralatan & Mesin',
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'kode_barang' => $item->kode_barang,
                'nama_barang' => $item->nama_barang,
                'jenis_aset' => $item->jenis_aset,
                'merk_tipe' => $item->merk_tipe ?? '',
                'no_sertifikat' => $item->no_sertifikat ?? '',
                'ukuran_bangunan' => $item->ukuran_bangunan ?? '',
                'satuan' => $item->satuan ?? 'UNIT',
                'volume' => (float) $item->volume,
                'harga_satuan' => (float) $item->harga_satuan,
                'nilai_perolehan' => (float) $item->nilai_perolehan,
            ])->all(),
        ];

        return Inertia::render('PelaporanBm/Spj/FormSpk', [
            'kategori' => $first->kategori ?? 'Peralatan & Mesin',
            'bulan' => $bulan,
            'isEdit' => true,
            'spkData' => $spkData,
        ]);
    }

    public function storeSpk(Request $request): RedirectResponse
    {
        $user = $request->user();
        $sekolahId = $user->sekolah_id ?: $request->input('sekolah_id');

        $validated = $request->validate([
            'is_edit' => 'nullable|boolean',
            'no_spk_lama' => 'nullable|string|max:150',
            'no_spk' => 'required|string|max:150',
            'no_sp2d' => 'nullable|string|max:100',
            'sumber_perolehan' => 'required|string|max:100',
            'bulan_realisasi' => 'required|integer|between:1,12',
            'kategori' => 'nullable|string|max:50',
            'ba_no' => 'nullable|string|max:150',
            'ba_tgl' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|string',
            'items.*.kode_barang' => 'required|string|max:50',
            'items.*.nama_barang' => 'required|string|max:255',
            'items.*.jenis_aset' => 'required|string|max:100',
            'items.*.merk_tipe' => 'nullable|string|max:255',
            'items.*.no_sertifikat' => 'nullable|string|max:150',
            'items.*.ukuran_bangunan' => 'nullable|string|max:150',
            'items.*.satuan' => 'nullable|string|max:50',
            'items.*.volume' => 'required|numeric|min:0.01',
            'items.*.harga_satuan' => 'required|numeric|min:0',
        ]);

        $bulan = $validated['bulan_realisasi'];
        $kunci = KunciLaporan::where('sekolah_id', $sekolahId)->where('bulan', (string) $bulan)->first();
        if ($kunci?->status_kunci || in_array($kunci?->status_kirim, ['menunggu_approval', 'disetujui'], true)) {
            return back()->with('error', 'Laporan bulan ini telah dikunci atau dikirim.');
        }

        $isEdit = ! empty($validated['is_edit']);
        $noSpkLama = $validated['no_spk_lama'] ?? $validated['no_spk'];

        DB::transaction(function () use ($validated, $sekolahId, $isEdit, $noSpkLama, $bulan) {
            $itemIdsKept = [];

            foreach ($validated['items'] as $item) {
                $nilaiPerolehan = (float) $item['volume'] * (float) $item['harga_satuan'];
                $itemPayload = [
                    'sekolah_id' => $sekolahId,
                    'no_sp2d' => $validated['no_sp2d'] ?? null,
                    'sumber_perolehan' => $validated['sumber_perolehan'],
                    'bulan_realisasi' => $bulan,
                    'no_spk' => $validated['no_spk'],
                    'ba_no' => $validated['ba_no'] ?? null,
                    'ba_tgl' => $validated['ba_tgl'] ?? null,
                    'kategori' => $validated['kategori'] ?? null,
                    'kode_barang' => $item['kode_barang'],
                    'nama_barang' => $item['nama_barang'],
                    'jenis_aset' => $item['jenis_aset'],
                    'merk_tipe' => $item['merk_tipe'] ?? null,
                    'no_sertifikat' => $item['no_sertifikat'] ?? null,
                    'ukuran_bangunan' => $item['ukuran_bangunan'] ?? null,
                    'satuan' => $item['satuan'] ?? 'UNIT',
                    'volume' => $item['volume'],
                    'harga_satuan' => $item['harga_satuan'],
                    'nilai_perolehan' => $nilaiPerolehan,
                ];

                if (! empty($item['id'])) {
                    $existing = Spj::where('sekolah_id', $sekolahId)->where('id', $item['id'])->first();
                    if ($existing) {
                        $existing->update($itemPayload);
                        $itemIdsKept[] = $existing->id;

                        continue;
                    }
                }

                $newRecord = Spj::create($itemPayload);
                $itemIdsKept[] = $newRecord->id;
            }

            if ($isEdit) {
                $pruned = Spj::where('sekolah_id', $sekolahId)
                    ->where('no_spk', $noSpkLama)
                    ->where('bulan_realisasi', $bulan)
                    ->whereNotIn('id', $itemIdsKept)
                    ->get();

                $prunedIds = $pruned->pluck('id')->all();
                if ($prunedIds !== []) {
                    Realisasi::whereIn('spj_id', $prunedIds)->delete();
                    Spj::whereIn('id', $prunedIds)->delete();
                }
            }
        });

        return redirect()->route('pelaporan-bm.spj.index', ['bulan' => $bulan])
            ->with('success', 'Dokumen SPK berhasil disimpan.');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $sekolahId = $user->sekolah_id ?: $request->input('sekolah_id');

        $validated = $request->validate([
            'no_spk' => 'required|string|max:150',
            'no_sp2d' => 'nullable|string|max:100',
            'sumber_perolehan' => 'nullable|string|max:100',
            'bulan_realisasi' => 'required|integer|between:1,12',
            'kategori' => 'nullable|string|max:50',
            'ba_no' => 'nullable|string|max:150',
            'ba_tgl' => 'nullable|date',
            'kode_barang' => 'required|string|max:50',
            'nama_barang' => 'required|string|max:255',
            'jenis_aset' => 'required|string|max:100',
            'merk_tipe' => 'nullable|string|max:255',
            'no_sertifikat' => 'nullable|string|max:100',
            'ukuran_bangunan' => 'nullable|string|max:100',
            'satuan' => 'nullable|string|max:50',
            'volume' => 'required|numeric|min:0.01',
            'harga_satuan' => 'required|numeric|min:0',
            'acuan_id' => 'nullable|uuid|exists:pelaporan_bm_acuan,id',
        ]);

        // Check lock status
        if ($this->isLaporanTerkunci($sekolahId, (int) $validated['bulan_realisasi'])) {
            return back()->with('error', 'Laporan bulan ini telah dikunci atau dikirim.');
        }

        $validated['sekolah_id'] = $sekolahId;
        $validated['nilai_perolehan'] = $validated['volume'] * $validated['harga_satuan'];

        Spj::create($validated);

        return back()->with('success', 'Data SPJ berhasil disimpan.');
    }

    private function isLaporanTerkunci(?string $sekolahId, int $bulan): bool
    {
        if (! $sekolahId) {
            return false;
        }

        $kunci = KunciLaporan::where('sekolah_id', $sekolahId)
            ->where('bulan', (string) $bulan)
            ->first();

        return (bool) ($kunci?->status_kunci || in_array($kunci?->status_kirim, ['menunggu_approval', 'disetujui'], true));
    }

    public function destroy(Spj $spj, Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->sekolah_id && $spj->sekolah_id !== $user->sekolah_id) {
            abort(403, 'Tidak memiliki akses');
        }

        if ($this->isLaporanTerkunci($spj->sekolah_id, (int) $spj->bulan_realisasi)) {
            return back()->with('error', 'Laporan bulan ini telah dikunci atau dikirim.');
        }

        DB::transaction(function () use ($spj) {
            $spj->realisasi()->delete();
            $spj->delete();
        });

        return back()->with('success', 'Data item SPJ berhasil dihapus.');
    }

    public function destroySpk(Request $request, string $no_spk): RedirectResponse
    {
        $user = $request->user();
        $sekolahId = $user->sekolah_id ?: $request->input('sekolah_id');
        $bulan = (int) ($request->input('bulan') ?: date('n'));

        if ($this->isLaporanTerkunci($sekolahId, $bulan)) {
            return back()->with('error', 'Laporan bulan ini telah dikunci atau dikirim.');
        }

        DB::transaction(function () use ($sekolahId, $bulan, $no_spk) {
            $items = Spj::where('sekolah_id', $sekolahId)
                ->where('bulan_realisasi', $bulan)
                ->where('no_spk', $no_spk)
                ->get();

            $spjIds = $items->pluck('id')->all();
            if ($spjIds !== []) {
                Realisasi::whereIn('spj_id', $spjIds)->delete();
            }

            Spj::where('sekolah_id', $sekolahId)
                ->where('bulan_realisasi', $bulan)
                ->where('no_spk', $no_spk)
                ->delete();
        });

        return back()->with('success', 'Seluruh data dokumen SPK berhasil dihapus.');
    }

    public function update(Request $request, Spj $spj): RedirectResponse
    {
        $user = $request->user();

        if ($user->sekolah_id && $spj->sekolah_id !== $user->sekolah_id) {
            abort(403, 'Tidak memiliki akses');
        }

        if ($this->isLaporanTerkunci($spj->sekolah_id, (int) $spj->bulan_realisasi)) {
            return back()->with('error', 'Laporan bulan ini telah dikunci atau dikirim.');
        }

        $validated = $request->validate([
            'no_spk' => 'required|string|max:150',
            'no_sp2d' => 'nullable|string|max:100',
            'sumber_perolehan' => 'nullable|string|max:100',
            'bulan_realisasi' => 'required|integer|between:1,12',
            'kategori' => 'nullable|string|max:50',
            'ba_no' => 'nullable|string|max:150',
            'ba_tgl' => 'nullable|date',
            'kode_barang' => 'required|string|max:50',
            'nama_barang' => 'required|string|max:255',
            'jenis_aset' => 'required|string|max:100',
            'merk_tipe' => 'nullable|string|max:255',
            'no_sertifikat' => 'nullable|string|max:100',
            'ukuran_bangunan' => 'nullable|string|max:100',
            'satuan' => 'nullable|string|max:50',
            'volume' => 'required|numeric|min:0.01',
            'harga_satuan' => 'required|numeric|min:0',
            'acuan_id' => 'nullable|uuid|exists:pelaporan_bm_acuan,id',
        ]);

        $validated['nilai_perolehan'] = $validated['volume'] * $validated['harga_satuan'];
        $spj->update($validated);

        return back()->with('success', 'Data SPJ berhasil diperbarui.');
    }

    public function unduh(Request $request): StreamedResponse
    {
        $user = $request->user();
        $sekolahId = $user->sekolah_id ?: $request->input('sekolah_id');
        $bulan = (int) ($request->input('bulan') ?: date('n'));

        $query = Spj::with('sekolah')->where('bulan_realisasi', $bulan);
        if ($sekolahId) {
            $query->where('sekolah_id', $sekolahId);
        }

        $items = $query->orderBy('no_spk')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"rekap_bm_bulan_{$bulan}.csv\"",
        ];

        return response()->stream(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['No SPK', 'No SP2D', 'Sumber', 'Kode Barang', 'Nama Barang', 'Jenis Aset', 'Merk/Tipe', 'Satuan', 'Volume', 'Harga Satuan', 'Nilai Perolehan']);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->no_spk,
                    $item->no_sp2d,
                    $item->sumber_perolehan,
                    $item->kode_barang,
                    $item->nama_barang,
                    $item->jenis_aset,
                    $item->merk_tipe,
                    $item->satuan,
                    $item->volume,
                    $item->harga_satuan,
                    $item->nilai_perolehan,
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function cariBarang(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        // Samakan perilaku legacy ajax_cari_barang.php:
        // lowercase matching, escape wildcard LIKE, hanya kode leaf, limit 100
        $keyword = mb_strtolower($q, 'UTF-8');
        $escaped = addcslashes($keyword, '%_\\');
        $searchParam = '%'.$escaped.'%';

        $masterResults = KodeBarang::query()
            ->where(function ($query) use ($searchParam) {
                $query->whereRaw('LOWER(kode_barang) LIKE ?', [$searchParam])
                    ->orWhereRaw('LOWER(uraian) LIKE ?', [$searchParam]);
            })
            ->whereNotExists(function ($sub) {
                $sub->selectRaw(1)
                    ->from('master_data_kode_barang as k2')
                    ->whereColumn('k2.kode_barang', 'like', DB::raw("master_data_kode_barang.kode_barang || '%'"))
                    ->whereColumn('k2.kode_barang', '!=', 'master_data_kode_barang.kode_barang');
            })
            ->orderBy('kode_barang')
            ->limit(100)
            ->get(['kode_barang', 'uraian as nama_barang', 'kodering_aset', 'jenis_aset', 'satuan']);

        if ($masterResults->isNotEmpty()) {
            return response()->json($masterResults);
        }

        $results = Spj::select('kode_barang', 'nama_barang', 'jenis_aset', 'satuan')
            ->when($request->user()->sekolah_id, fn ($q) => $q->where('sekolah_id', $request->user()->sekolah_id))
            ->where(function ($query) use ($searchParam) {
                $query->whereRaw('LOWER(kode_barang) LIKE ?', [$searchParam])
                    ->orWhereRaw('LOWER(nama_barang) LIKE ?', [$searchParam]);
            })
            ->distinct()
            ->limit(10)
            ->get();

        return response()->json($results);
    }
}
