<?php

namespace App\Http\Controllers;

use App\Models\LaporanRealisasi;
use App\Models\RealisasiBarangSekolah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportWorkflowController extends Controller
{
    public function storeRealisasi(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('user'), 403);

        $data = $request->validate([
            'bulan_realisasi' => ['required', 'integer', 'between:1,12'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'volume' => ['required', 'numeric', 'gt:0'],
            'harga_satuan' => ['required', 'numeric', 'gt:0'],
        ]);
        $schoolId = (string) $request->user()->id_sekolah;

        if ($this->reportLocked($schoolId, $data['bulan_realisasi'])) {
            return response()->json(['message' => 'Report is locked.'], 409);
        }

        $item = DB::transaction(function () use ($data, $schoolId): RealisasiBarangSekolah {
            $item = new RealisasiBarangSekolah;
            $item->forceFill([
                ...$data,
                'id_sekolah' => $schoolId,
                'nilai_perolehan' => bcmul((string) $data['volume'], (string) $data['harga_satuan'], 2),
            ]);
            $item->save();

            return $item;
        });

        return response()->json(['data' => $item], 201);
    }

    public function destroyRealisasi(Request $request, string $noSpk): JsonResponse
    {
        abort_unless($request->user()?->hasRole('user'), 403);

        $schoolId = (string) $request->user()->id_sekolah;

        DB::transaction(function () use ($schoolId, $noSpk): void {
            $items = RealisasiBarangSekolah::query()
                ->where('id_sekolah', $schoolId)
                ->where('no_spk', $noSpk)
                ->lockForUpdate()
                ->get(['id', 'bulan_realisasi']);

            foreach ($items->pluck('bulan_realisasi')->unique() as $month) {
                abort_if($this->reportLocked($schoolId, (int) $month), 409, 'Report is locked.');
            }

            RealisasiBarangSekolah::query()
                ->whereKey($items->pluck('id'))
                ->delete();
        });

        return response()->json(['message' => 'Realization document deleted.']);
    }

    public function updateRealisasi(Request $request, RealisasiBarangSekolah $realisasi): JsonResponse
    {
        abort_unless($request->user()?->hasRole('user'), 403);

        $data = $request->validate([
            'nama_barang' => ['required', 'string', 'max:255'],
            'volume' => ['required', 'numeric', 'gt:0'],
            'harga_satuan' => ['required', 'numeric', 'gt:0'],
        ]);
        $schoolId = (string) $request->user()->id_sekolah;

        abort_unless($realisasi->id_sekolah === $schoolId, 403);

        if ($this->reportLocked($schoolId, (int) $realisasi->bulan_realisasi)) {
            return response()->json(['message' => 'Report is locked.'], 409);
        }

        $realisasi->update([
            ...$data,
            'nilai_perolehan' => bcmul((string) $data['volume'], (string) $data['harga_satuan'], 2),
        ]);

        return response()->json(['data' => $realisasi->fresh()]);
    }

    public function submit(Request $request, int $month): JsonResponse
    {
        abort_unless($request->user()?->hasRole('user'), 403);
        abort_unless($month >= 1 && $month <= 12, 422);

        $report = DB::transaction(function () use ($request, $month): LaporanRealisasi {
            $report = LaporanRealisasi::query()->firstOrNew([
                'id_sekolah' => (string) $request->user()->id_sekolah,
                'bulan' => $month,
            ]);

            abort_if($report->exists && $report->status !== 'Ditolak', 409, 'Report cannot be submitted.');

            $report->id_sekolah = (string) $request->user()->id_sekolah;
            $report->status = 'Menunggu Approval';
            $report->tanggal_kirim = now();
            $report->save();

            return $report;
        });

        return response()->json(['data' => $report], 201);
    }

    public function approve(Request $request, int $schoolId, int $month): JsonResponse
    {
        return $this->review($request, $schoolId, $month, 'Disetujui');
    }

    public function reject(Request $request, int $schoolId, int $month): JsonResponse
    {
        return $this->review($request, $schoolId, $month, 'Ditolak');
    }

    private function review(Request $request, int $schoolId, int $month, string $status): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $report = DB::transaction(function () use ($schoolId, $month, $status): LaporanRealisasi {
            $report = LaporanRealisasi::query()
                ->where('id_sekolah', (string) $schoolId)
                ->where('bulan', $month)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($report->status === 'Menunggu Approval', 409, 'Report cannot be reviewed.');

            $report->status = $status;
            $report->save();

            return $report;
        });

        return response()->json(['data' => $report]);
    }

    private function reportLocked(string $schoolId, int $month): bool
    {
        return LaporanRealisasi::query()
            ->where('id_sekolah', $schoolId)
            ->where('bulan', $month)
            ->whereIn('status', ['Menunggu Approval', 'Disetujui'])
            ->exists();
    }
}
