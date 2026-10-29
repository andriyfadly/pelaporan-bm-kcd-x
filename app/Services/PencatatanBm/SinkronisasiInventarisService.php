<?php

namespace App\Services\PencatatanBm;

use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PencatatanBm\InventarisRegisterSequence;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\SyncLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Membentuk unit inventaris individual dari realisasi yang periodenya sudah
 * disetujui. Aman dijalankan ulang: identitas unit adalah
 * (realisasi_id, unit_index), dan register dialokasikan atomik per
 * (sekolah_id, kode_barang) lewat tabel sequence.
 */
class SinkronisasiInventarisService
{
    public function syncPeriode(string $sekolahId, int $bulan, int $tahun, ?string $actorId = null): SyncResult
    {
        $total = new SyncResult;
        $dibuat = 0;
        $tetap = 0;
        $surplus = 0;
        $review = 0;

        $realisasiList = Realisasi::where('sekolah_id', $sekolahId)
            ->where('bulan_realisasi', $bulan)
            ->where('tahun', $tahun)
            ->orderBy('kode_barang')
            ->orderBy('id')
            ->get();

        foreach ($realisasiList as $realisasi) {
            $hasil = $this->syncRealisasi($realisasi, $actorId);
            $dibuat += $hasil->dibuat;
            $tetap += $hasil->tetap;
            $surplus += $hasil->surplus;
            $review += $hasil->perluReview;
        }

        return new SyncResult($dibuat, $tetap, $surplus, $review);
    }

    public function syncRealisasi(Realisasi $realisasi, ?string $actorId = null): SyncResult
    {
        $log = SyncLog::create([
            'sekolah_id' => $realisasi->sekolah_id,
            'realisasi_id' => $realisasi->id,
            'status' => 'berjalan',
            'actor_id' => $actorId,
            'started_at' => now(),
        ]);

        try {
            $hasil = $this->jalankan($realisasi);
        } catch (Throwable $e) {
            $log->update([
                'status' => 'gagal',
                'error' => Str::limit($e->getMessage(), 1000),
                'finished_at' => now(),
            ]);

            throw $e;
        }

        $log->update([
            // Gate status yang belum disetujui bukan sinkronisasi sukses.
            'status' => $hasil->dilewati ? 'dilewati' : 'sukses',
            'dibuat' => $hasil->dibuat,
            'tetap' => $hasil->tetap,
            'surplus' => $hasil->surplus,
            'perlu_review' => $hasil->perluReview,
            'finished_at' => now(),
        ]);

        return $hasil;
    }

    private function jalankan(Realisasi $realisasi): SyncResult
    {
        $sekolahId = $realisasi->sekolah_id;

        if (! $sekolahId) {
            return new SyncResult(dilewati: true);
        }

        $bulan = (int) $realisasi->bulan_realisasi;
        $tahun = (int) ($realisasi->tahun ?? now()->year);

        // Gate status: hanya laporan yang sudah disetujui Admin KCD yang
        // menjadi sumber inventaris.
        if (! $this->periodeDisetujui($sekolahId, $bulan, $tahun)) {
            return new SyncResult(dilewati: true);
        }

        return DB::transaction(function () use ($realisasi, $sekolahId): SyncResult {
            $realisasi = Realisasi::whereKey($realisasi->id)->lockForUpdate()->firstOrFail();
            $target = (int) floor((float) $realisasi->volume);

            $units = InventarisUnit::where('realisasi_id', $realisasi->id)
                ->orderBy('unit_index')
                ->lockForUpdate()
                ->get()
                ->keyBy('unit_index');

            $dibuat = 0;
            $tetap = 0;
            $surplus = 0;
            $review = 0;

            foreach ($units as $index => $unit) {
                if ($index <= $target) {
                    if ($unit->status === InventarisUnit::STATUS_DIHAPUS_KOREKSI) {
                        // Volume naik lagi: unit yang pernah dipensiunkan
                        // diaktifkan kembali dengan register yang sama.
                        $unit->update(['status' => InventarisUnit::STATUS_AKTIF]);
                    }

                    if ($unit->status === InventarisUnit::STATUS_AKTIF) {
                        $this->segarkanSnapshot($unit, $realisasi);
                        $tetap++;
                    }

                    continue;
                }

                if ($unit->status !== InventarisUnit::STATUS_AKTIF) {
                    continue;
                }

                $pernahDimutasi = $unit->mutasi()->exists();

                if ($pernahDimutasi) {
                    // Unit yang sudah berpindah tangan tidak boleh hilang
                    // karena koreksi sumber; tinjau manual.
                    $review++;

                    continue;
                }

                $unit->update(['status' => InventarisUnit::STATUS_DIHAPUS_KOREKSI]);
                $surplus++;
            }

            $indexBerikut = $units->isEmpty() ? 1 : (int) $units->keys()->max() + 1;

            for ($index = $indexBerikut; $index <= $target; $index++) {
                $this->buatUnit($realisasi, $sekolahId, $index);
                $dibuat++;
            }

            return new SyncResult($dibuat, $tetap, $surplus, $review);
        });
    }

    private function periodeDisetujui(string $sekolahId, int $bulan, int $tahun): bool
    {
        return KunciLaporan::where('sekolah_id', $sekolahId)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->where('status_kirim', 'disetujui')
            ->exists();
    }

    private function buatUnit(Realisasi $realisasi, string $sekolahId, int $index): void
    {
        $tanggal = $realisasi->ba_tgl ? Carbon::parse($realisasi->ba_tgl) : null;
        $tahun = $tanggal ? (int) $tanggal->format('Y') : (int) ($realisasi->tahun ?? now()->year);

        $nomor = InventarisRegisterSequence::alokasi($sekolahId, (string) $realisasi->kode_barang, $tahun);

        InventarisUnit::create([
            'sekolah_id' => $sekolahId,
            'realisasi_id' => $realisasi->id,
            'unit_index' => $index,
            'kode_barang' => $realisasi->kode_barang,
            'nomor_register' => $nomor,
            'nama_barang' => $realisasi->nama_barang,
            'jenis_aset' => $realisasi->jenis_aset,
            'merk_tipe' => $realisasi->merk_tipe,
            'no_sertifikat' => $realisasi->no_sertifikat,
            'ukuran_bangunan' => $realisasi->ukuran_bangunan,
            'satuan' => $realisasi->satuan,
            'tanggal_perolehan' => $tanggal,
            'bulan_perolehan' => $tanggal ? (int) $tanggal->format('n') : (int) $realisasi->bulan_realisasi,
            'tahun_perolehan' => $tahun,
            'harga_satuan' => $realisasi->harga_satuan,
            'nilai_perolehan_unit' => $realisasi->harga_satuan,
        ]);
    }

    /**
     * Salinan sumber untuk unit yang belum dimutasi boleh ikut koreksi;
     * dokumen yang sudah dicetak tetap memakai snapshot tersimpan.
     */
    private function segarkanSnapshot(InventarisUnit $unit, Realisasi $realisasi): void
    {
        if ($unit->mutasi()->exists()) {
            return;
        }

        $unit->fill([
            'nama_barang' => $realisasi->nama_barang,
            'jenis_aset' => $realisasi->jenis_aset,
            'merk_tipe' => $realisasi->merk_tipe,
            'no_sertifikat' => $realisasi->no_sertifikat,
            'ukuran_bangunan' => $realisasi->ukuran_bangunan,
            'satuan' => $realisasi->satuan,
            'harga_satuan' => $realisasi->harga_satuan,
            'nilai_perolehan_unit' => $realisasi->harga_satuan,
        ]);

        if ($unit->isDirty()) {
            $unit->save();
        }
    }
}
