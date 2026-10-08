<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lifecycle saldo awal BHP: pembuatan dan penghapusan transaksi
 * is_saldo_awal sebagai flow khusus (bukan sekadar flag penerimaan).
 *
 * Saldo awal unik per kombinasi sekolah + sumber dana + periode + barang;
 * keunikan dijaga di aplikasi di dalam transaction. # ponytail: tanpa
 * unique index DB karena kombinasi ada di tabel header — kalau race
 * duplicate jadi masalah nyata, pindahkan kunci ke tabel detail
 * (denormalisasi sekolah_id/sumber_dana/periode_tahun) lalu buat
 * partial unique index.
 */
class SaldoAwalService
{
    /**
     * Buat saldo awal (header + detail) dalam satu transaction.
     *
     * @param  array<string,mixed>  $data  payload penerimaan tervalidasi dari controller
     * @param  array<int, array{barang_id:string,jumlah:int,harga_satuan:int,merek_type?:?string,keterangan?:?string}>  $details
     * @param  array<string, Barang>  $barangs  map barang_id => Barang hasil isolasi sumber dana
     */
    public function create(array $data, string $sekolahId, User $actor, array $details, array $barangs): Penerimaan
    {
        $periode = (int) $data['periode_tahun'];
        $sumberDana = $data['sumber_dana'];

        // Duplikat di dalam satu request.
        $dalamRequest = collect($details)->pluck('barang_id')->duplicates();
        if ($dalamRequest->isNotEmpty()) {
            $barang = $barangs[$dalamRequest->first()] ?? null;

            throw ValidationException::withMessages([
                'details' => 'Barang '.$barang?->nama_barang.' muncul lebih dari satu kali pada saldo awal ini.',
            ]);
        }

        return DB::transaction(function () use ($data, $sekolahId, $actor, $details, $barangs, $periode, $sumberDana) {
            foreach ($details as $index => $detail) {
                $barang = $barangs[$detail['barang_id']] ?? null;
                if (! $barang) {
                    throw ValidationException::withMessages([
                        "details.{$index}.barang_id" => 'Barang tidak ditemukan.',
                    ]);
                }

                if ($this->sudahAda($barang->id, $sekolahId, $sumberDana, $periode)) {
                    throw ValidationException::withMessages([
                        "details.{$index}.barang_id" => 'Saldo awal untuk '.$barang->nama_barang.' periode '.$periode.' ('.$sumberDana.') sudah ada.',
                    ]);
                }
            }

            $penerimaan = Penerimaan::create([
                'sekolah_id' => $sekolahId !== '' ? $sekolahId : null,
                'is_saldo_awal' => true,
                'periode_tahun' => $periode,
                'tanggal_titimangsa' => $data['tanggal_titimangsa'] ?? null,
                'sumber_dana' => $sumberDana,
                'no_nota_invoice' => $data['no_nota_invoice'] ?? null,
                'no_bast' => $data['no_bast'] ?? null,
                'tanggal_bast' => $data['tanggal_bast'] ?? null,
                'no_kwitansi' => $data['no_kwitansi'] ?? null,
                'penyedia_id' => $data['penyedia_id'] ?? null,
            ]);

            foreach ($details as $detail) {
                $penerimaan->details()->create([
                    'barang_id' => $detail['barang_id'],
                    'jumlah' => $detail['jumlah'],
                    'harga_satuan' => $detail['harga_satuan'],
                    'nilai_total' => $detail['jumlah'] * $detail['harga_satuan'],
                    'merek_type' => $detail['merek_type'] ?? null,
                    'keterangan' => $detail['keterangan'] ?? null,
                ]);
            }

            activity()
                ->performedOn($penerimaan)
                ->causedBy($actor)
                ->withProperties(['periode_tahun' => $periode, 'sumber_dana' => $sumberDana, 'jumlah_item' => count($details)])
                ->log('kelola-saldo-awal');

            return $penerimaan;
        });
    }

    /**
     * Hapus saldo awal (header + detail atomic). Ditolak bila sudah
     * terpakai pada pengajuan approved/submitted.
     */
    public function delete(Penerimaan $penerimaan, User $actor): void
    {
        if (! $penerimaan->is_saldo_awal) {
            throw ValidationException::withMessages([
                'penerimaan' => 'Hanya saldo awal yang dapat dihapus melalui flow ini.',
            ]);
        }

        $dipakai = DB::table('bhp_pengajuan_detail as d')
            ->join('bhp_pengajuan as p', 'p.id', '=', 'd.bhp_pengajuan_id')
            ->whereIn('p.status', ['submitted', 'approved'])
            ->whereIn('d.barang_id', $penerimaan->details()->pluck('barang_id'))
            ->when($penerimaan->sekolah_id !== null, fn ($q) => $q->where('p.sekolah_id', $penerimaan->sekolah_id))
            ->exists();

        if ($dipakai) {
            throw ValidationException::withMessages([
                'penerimaan' => 'Saldo awal sudah dipakai pada pengajuan aktif dan tidak dapat dihapus.',
            ]);
        }

        DB::transaction(function () use ($penerimaan, $actor) {
            $penerimaan->details()->delete();
            $penerimaan->delete();

            activity()
                ->causedBy($actor)
                ->withProperties(['periode_tahun' => $penerimaan->periode_tahun, 'sumber_dana' => $penerimaan->sumber_dana])
                ->log('hapus-saldo-awal');
        });
    }

    public function sudahAdaUntuk(string $barangId, string $sekolahId, string $sumberDana, int $periode): bool
    {
        return $this->sudahAda($barangId, $sekolahId, $sumberDana, $periode);
    }

    private function sudahAda(string $barangId, string $sekolahId, string $sumberDana, int $periode): bool
    {
        return PenerimaanDetail::query()
            ->where('barang_id', $barangId)
            ->whereHas('penerimaan', function ($query) use ($sekolahId, $sumberDana, $periode): void {
                $query
                    ->where('sekolah_id', $sekolahId !== '' ? $sekolahId : null)
                    ->where('sumber_dana', $sumberDana)
                    ->where('is_saldo_awal', true)
                    ->where('periode_tahun', $periode);
            })
            ->exists();
    }
}
