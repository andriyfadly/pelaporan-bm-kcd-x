<?php

namespace App\Http\Controllers\PelaporanBm;

use App\Http\Controllers\Controller;
use App\Models\PelaporanBm\KunciLaporan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KunciLaporanController extends Controller
{
    public function toggle(Request $request): RedirectResponse
    {
        $request->validate([
            'sekolah_id' => 'required|uuid|exists:master_data_sekolah,id',
            'bulan' => 'required|integer|between:1,12',
        ]);

        $kunci = KunciLaporan::firstOrNew([
            'sekolah_id' => $request->input('sekolah_id'),
            'bulan' => (int) $request->input('bulan'),
        ]);

        $kunci->status_kunci = ! $kunci->status_kunci;
        $kunci->dikunci_pada = $kunci->status_kunci ? now() : null;
        $kunci->dikunci_oleh = $kunci->status_kunci ? $request->user()->id : null;
        $kunci->save();

        $statusText = $kunci->status_kunci ? 'dikunci' : 'dibuka kuncinya';

        Log::info('kunci_laporan.toggle', [
            'aktor' => $request->user()->id,
            'sekolah_id' => $request->input('sekolah_id'),
            'bulan' => $request->input('bulan'),
            'status_kunci' => $kunci->status_kunci,
        ]);

        return back()->with('success', "Laporan bulan {$request->input('bulan')} berhasil {$statusText}.");
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $request->validate([
            'sekolah_id' => 'required|uuid|exists:master_data_sekolah,id',
            'bulan' => 'required|integer|between:1,12',
            'status_kirim' => 'required|string|in:draft,menunggu_approval,disetujui',
        ]);

        $kunci = KunciLaporan::firstOrNew([
            'sekolah_id' => $request->input('sekolah_id'),
            'bulan' => (int) $request->input('bulan'),
        ]);

        $statusKirim = $request->input('status_kirim');
        $kunci->status_kirim = $statusKirim;
        if ($statusKirim === 'disetujui') {
            $kunci->status_kunci = true;
            $kunci->dikunci_pada = now();
            $kunci->dikunci_oleh = $request->user()->id;
        } elseif ($statusKirim === 'draft') {
            $kunci->status_kunci = false;
        }
        $kunci->save();

        Log::info('kunci_laporan.update_status', [
            'aktor' => $request->user()->id,
            'sekolah_id' => $request->input('sekolah_id'),
            'bulan' => $request->input('bulan'),
            'status_kirim' => $statusKirim,
        ]);

        return back()->with('success', "Status laporan berhasil diperbarui menjadi {$statusKirim}.");
    }
}
