<?php

namespace App\Services;

use App\Models\Master\PejabatSekolah;
use App\Models\Master\Sekolah;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Master pejabat sekolah: penandatangan dokumen BHP.
 *
 * Approval pengajuan mensyaratkan tiga jabatan terisi (kepala sekolah,
 * wakasek sarpras, pembantu pengurus barang) yang berlaku pada tanggal
 * pengajuan. Snapshot pejabat disimpan di payload dokumen sehingga dokumen
 * lama tidak berubah ketika pejabat berganti.
 */
class PejabatSekolahService
{
    /**
     * Snapshot tiga jabatan penandatangan yang berlaku pada tanggal tertentu.
     * Melempar RuntimeException bila ada jabatan yang belum terisi — pesan
     * menyebut semua jabatan yang bermasalah sekaligus.
     *
     * @return array<string,array<string,mixed|null>> keyed by jabatan
     */
    public function snapshotPenandatangan(string $sekolahId, string $tanggal): array
    {
        $masalah = [];
        $snapshot = [];

        foreach (PejabatSekolah::JABATAN as $jabatan) {
            $pejabat = PejabatSekolah::berlakuPada($sekolahId, $jabatan, $tanggal)->first();

            if (! $pejabat) {
                $masalah[] = PejabatSekolah::LABEL_JABATAN[$jabatan];
            } elseif (trim((string) $pejabat->nama) === '') {
                $masalah[] = PejabatSekolah::LABEL_JABATAN[$jabatan].' (nama kosong)';
            } else {
                $snapshot[$jabatan] = $pejabat->toSnapshot();
            }
        }

        if ($masalah !== []) {
            throw new RuntimeException('Approval tidak dapat dilakukan, pejabat penandatangan belum lengkap: '.implode(', ', $masalah).'. Lengkapi di menu Pejabat Sekolah.');
        }

        return $snapshot;
    }

    /**
     * Simpan/ubah pejabat. Guard: periode tidak boleh overlap dengan pejabat
     * lain pada jabatan yang sama (sekolah scope dari caller, bukan input).
     */
    public function simpan(string $sekolahId, string $userId, array $data, ?PejabatSekolah $pejabat = null): PejabatSekolah
    {
        return DB::transaction(function () use ($sekolahId, $userId, $data, $pejabat) {
            Sekolah::whereKey($sekolahId)->lockForUpdate()->firstOrFail();

            $mulai = Carbon::parse($data['mulai_menjabat']);
            $selesai = ! empty($data['selesai_menjabat']) ? Carbon::parse($data['selesai_menjabat']) : null;

            if ($selesai && $selesai->lt($mulai)) {
                throw new RuntimeException('Tanggal selesai menjabat tidak boleh sebelum tanggal mulai.');
            }

            // Entry baru tanpa tanggal selesai menjadi pejabat aktif: tutup
            // dulu pejabat aktif lama (H-1) agar tidak dianggap tumpang tindih.
            if ($selesai === null) {
                PejabatSekolah::where('sekolah_id', $sekolahId)
                    ->where('jabatan', $data['jabatan'])
                    ->when($pejabat, fn ($q) => $q->whereKeyNot($pejabat->id))
                    ->whereNull('selesai_menjabat')
                    ->lockForUpdate()
                    ->get()
                    ->each(function ($lama) use ($mulai, $userId) {
                        $lama->update([
                            'selesai_menjabat' => $mulai->copy()->subDay()->toDateString(),
                            'updated_by' => $userId,
                        ]);
                    });
            }

            $tumpangTindih = PejabatSekolah::where('sekolah_id', $sekolahId)
                ->where('jabatan', $data['jabatan'])
                ->when($pejabat, fn ($q) => $q->whereKeyNot($pejabat->id))
                ->whereDate('mulai_menjabat', '<=', $selesai ?? $mulai)
                ->where(fn ($q) => $q
                    ->whereNull('selesai_menjabat')
                    ->orWhereDate('selesai_menjabat', '>=', $mulai))
                ->lockForUpdate()
                ->exists();

            if ($tumpangTindih) {
                throw new RuntimeException('Periode masa jabatan tumpang tindih dengan pejabat lain pada jabatan yang sama.');
            }

            $payload = [
                'jabatan' => $data['jabatan'],
                'jenis_jabatan' => $data['jenis_jabatan'] ?? 'definitif',
                'nama' => $data['nama'],
                'pangkat_golongan' => $data['pangkat_golongan'] ?? null,
                'nip' => $data['nip'] ?? null,
                'mulai_menjabat' => $mulai->toDateString(),
                'selesai_menjabat' => $selesai?->toDateString(),
                'updated_by' => $userId,
            ];

            if ($pejabat) {
                $pejabat->update($payload);

                return $pejabat->refresh();
            }

            return PejabatSekolah::create($payload + [
                'sekolah_id' => $sekolahId,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Akhiri masa jabatan pejabat aktif. Riwayat tidak dihapus karena
     * snapshot dokumen mereferensikan official_id.
     */
    public function akhiriMasaJabatan(string $sekolahId, string $userId, PejabatSekolah $pejabat, string $tanggal): PejabatSekolah
    {
        abort_unless($pejabat->sekolah_id === $sekolahId, 403, 'Tidak memiliki akses ke pejabat ini.');

        $akhir = Carbon::parse($tanggal);

        if ($akhir->lt(Carbon::parse($pejabat->mulai_menjabat))) {
            throw new RuntimeException('Tanggal selesai tidak boleh sebelum tanggal mulai menjabat.');
        }

        $pejabat->update([
            'selesai_menjabat' => $akhir->toDateString(),
            'updated_by' => $userId,
        ]);

        return $pejabat->refresh();
    }
}
