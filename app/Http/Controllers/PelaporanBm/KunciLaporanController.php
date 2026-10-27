<?php

namespace App\Http\Controllers\PelaporanBm;

use App\Http\Controllers\Controller;
use App\Models\PelaporanBm\KunciLaporan;
use App\Services\PencatatanBm\SinkronisasiInventarisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class KunciLaporanController extends Controller
{
    public function toggle(Request $request): RedirectResponse
    {
        $request->validate([
            'sekolah_id' => 'required|uuid|exists:master_data_sekolah,id',
            'bulan' => 'required|integer|between:1,12',
            'tahun' => 'nullable|integer|between:2026,9999',
        ]);

        $kunci = KunciLaporan::firstOrNew([
            'sekolah_id' => $request->input('sekolah_id'),
            'bulan' => (int) $request->input('bulan'),
            'tahun' => (int) $request->input('tahun', now()->year),
        ]);

        $kunci->status_kunci = ! $kunci->status_kunci;
        $kunci->dikunci_pada = $kunci->status_kunci ? now() : null;
        $kunci->dikunci_oleh = $kunci->status_kunci ? $request->user()->id : null;
        $kunci->save();

        $statusText = $kunci->status_kunci ? 'dikunci' : 'dibuka kuncinya';

        activity('sistem')
            ->performedOn($kunci)
            ->event($kunci->status_kunci ? 'kunci-laporan' : 'buka-kunci-laporan')
            ->withProperties([
                'ringkasan' => "Laporan bulan {$kunci->bulan} ".($kunci->status_kunci ? 'dikunci' : 'dibuka kuncinya'),
                'sekolah_id' => $kunci->sekolah_id,
                'bulan' => $kunci->bulan,
                'status_kunci' => $kunci->status_kunci,
            ])
            ->log($kunci->status_kunci ? 'kunci-laporan' : 'buka-kunci-laporan');

        return back()->with('success', "Laporan bulan {$request->input('bulan')} berhasil {$statusText}.");
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $request->validate([
            'sekolah_id' => 'required|uuid|exists:master_data_sekolah,id',
            'bulan' => 'required|integer|between:1,12',
            'tahun' => 'nullable|integer|between:2026,9999',
            'status_kirim' => 'required|string|in:draft,menunggu_approval,disetujui',
        ]);

        $kunci = KunciLaporan::firstOrNew([
            'sekolah_id' => $request->input('sekolah_id'),
            'bulan' => (int) $request->input('bulan'),
            'tahun' => (int) $request->input('tahun', now()->year),
        ]);

        $statusKirim = $request->input('status_kirim');
        $kunci->status_kirim = $statusKirim;
        if ($statusKirim === 'disetujui') {
            $kunci->status_kunci = true;
            $kunci->dikunci_pada = now();
            $kunci->dikunci_oleh = $request->user()->id;
        } elseif ($statusKirim === 'draft') {
            $kunci->status_kunci = false;
            $kunci->dikunci_pada = null;
            $kunci->dikunci_oleh = null;
        }
        $kunci->save();

        activity('sistem')
            ->performedOn($kunci)
            ->event('verifikasi-laporan')
            ->withProperties([
                'ringkasan' => "Status laporan bulan {$kunci->bulan} → {$statusKirim}",
                'sekolah_id' => $kunci->sekolah_id,
                'bulan' => $kunci->bulan,
                'status_kirim' => $statusKirim,
            ])
            ->log('verifikasi-laporan');

        // Sinkronisasi inventaris menyusul persetujuan. Kegagalannya tidak
        // membatalkan status: dicatat di inventaris_sync_logs dan bisa diulang.
        if ($statusKirim === 'disetujui') {
            try {
                $hasil = app(SinkronisasiInventarisService::class)->syncPeriode(
                    (string) $kunci->sekolah_id,
                    (int) $kunci->bulan,
                    (int) $kunci->tahun,
                    (string) $request->user()->id,
                );

                if ($hasil->perluReview > 0) {
                    return back()->with('warning', $hasil->perluReview.' unit perlu ditinjau manual karena sudah pernah dimutasi.');
                }
            } catch (Throwable $e) {
                report($e);

                return back()->with('warning', "Status disetujui, tetapi sinkronisasi inventaris gagal: {$e->getMessage()}");
            }
        }

        return back()->with('success', "Status laporan berhasil diperbarui menjadi {$statusKirim}.");
    }
}
