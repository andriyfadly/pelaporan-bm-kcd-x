<?php

namespace App\Services\PencatatanBm;

use App\Models\PencatatanBm\Dokumen;
use Illuminate\Validation\ValidationException;

/**
 * Penjaga nomor dokumen inventaris: nomor yang masih aktif maupun serial yang
 * pernah dibatalkan tidak boleh dipakai ulang. Nomor surat dinas yang sudah
 * terbit tidak boleh menunjuk dokumen lain.
 *
 * Pemanggil wajib berada di dalam transaksi dan sudah mengunci baris sekolah
 * sebagai mutex, supaya dua request bersamaan tidak lolos bersamaan.
 */
class NomorDokumenGuard
{
    public function pastikanTersedia(string $sekolahId, string $jenis, string $nomor, ?string $excludeId = null): void
    {
        $nomor = trim($nomor);

        if ($nomor === '') {
            return;
        }

        $serial = explode('/', $nomor)[0];

        $query = Dokumen::query()
            ->where('sekolah_id', $sekolahId)
            ->where('jenis', $jenis)
            ->where(function ($q) use ($nomor, $serial): void {
                $q->where(fn ($aktif) => $aktif->whereNotNull('nomor')->where('nomor', $nomor))
                    ->orWhere('payload->pembatalan->nomor_urut', $serial);
            });
        if ($excludeId !== null) {
            $query->whereKeyNot($excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'nomor' => "Nomor {$nomor} sudah pernah dipakai untuk dokumen {$jenis}.",
            ]);
        }
    }
}
