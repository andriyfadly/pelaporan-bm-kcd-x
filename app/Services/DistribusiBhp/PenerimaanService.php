<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\User;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lifecycle penerimaan biasa (bukan saldo awal).
 *
 * Saldo awal punya flow sendiri di {@see SaldoAwalService}; class ini khusus
 * koreksi penerimaan reguler yang salah input.
 */
class PenerimaanService
{
    public function __construct(private readonly StokService $stok) {}

    /**
     * Hapus penerimaan biasa beserta detailnya, dengan syarat stok yang tersisa
     * masih menutup pemakaian (approved) dan reservasi (submitted).
     */
    public function hapus(Penerimaan $penerimaan, User $actor): void
    {
        if ($penerimaan->is_saldo_awal) {
            throw ValidationException::withMessages([
                'penerimaan' => 'Saldo awal dihapus melalui halaman Saldo Awal.',
            ]);
        }

        SumberDana::dengan($penerimaan->sumber_dana, function () use ($penerimaan, $actor) {
            DB::transaction(function () use ($penerimaan, $actor) {
                $penerimaan = Penerimaan::whereKey($penerimaan->id)->lockForUpdate()->firstOrFail();
                $details = $penerimaan->details()->lockForUpdate()->get();
                $barangIds = $details->pluck('barang_id')->filter()->unique()->sort()->values()->all();

                // Urutan ID deterministik supaya dua penghapusan bersamaan tidak deadlock.
                Barang::whereIn('id', $barangIds)->orderBy('id')->lockForUpdate()->get();

                foreach ($details->groupBy('barang_id') as $barangId => $rows) {
                    $barang = Barang::whereKey($barangId)->lockForUpdate()->first();

                    if (! $barang) {
                        continue;
                    }

                    $dihapus = (int) $rows->sum('jumlah');
                    $sisa = $this->stok->fisik($barang, $penerimaan->sekolah_id) - $dihapus;
                    $terikat = $this->stok->terpakai($barang, $penerimaan->sekolah_id)
                        + $this->stok->reservasi($barang, $penerimaan->sekolah_id);

                    if ($sisa < $terikat) {
                        throw ValidationException::withMessages([
                            'penerimaan' => "Penerimaan tidak dapat dihapus: stok {$barang->nama_barang} tersisa {$sisa}, sedangkan yang sudah terpakai/direservasi {$terikat}.",
                        ]);
                    }
                }

                $ringkasan = $details->map(fn ($d): array => [$d->barang_id, (int) $d->jumlah])->all();
                $penerimaan->details()->delete();
                $penerimaan->delete();

                activity()
                    ->causedBy($actor)
                    ->withProperties([
                        'no_bast' => $penerimaan->no_bast,
                        'tanggal_bast' => $penerimaan->tanggal_bast?->toDateString(),
                        'sumber_dana' => $penerimaan->sumber_dana,
                        'detail' => $ringkasan,
                    ])
                    ->log('hapus-penerimaan');
            });
        });
    }
}
