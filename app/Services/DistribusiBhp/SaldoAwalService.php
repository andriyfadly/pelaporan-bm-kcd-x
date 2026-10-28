<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\PengajuanDetail;
use App\Models\User;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lifecycle saldo awal BHP: pembuatan dan penghapusan transaksi
 * is_saldo_awal sebagai flow khusus (bukan sekadar flag penerimaan).
 *
 * Saldo awal selalu milik satu sekolah (tidak ada pool pusat). Tabel dipisah
 * per sumber dana, dan keunikan (sekolah + periode) dijaga aplikasi di dalam
 * transaction + partial unique index database.
 *
 * Penghapusan punya dua level yang sengaja dipisah:
 * - deleteDetail() = hapus satu barang (route saldo-awal.destroy);
 * - delete()       = hapus seluruh header + detail (PenerimaanController::destroy).
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
        // Tidak ada pool pusat: saldo awal wajib milik satu sekolah.
        if (trim($sekolahId) === '') {
            throw ValidationException::withMessages([
                'sekolah_id' => 'Saldo awal wajib terhubung ke satu sekolah.',
            ]);
        }

        $periode = (int) $data['periode_tahun'];
        $sumberDana = SumberDana::normalisasi($data['sumber_dana']);

        // Duplikat di dalam satu request.
        $dalamRequest = collect($details)->pluck('barang_id')->duplicates();
        if ($dalamRequest->isNotEmpty()) {
            $barang = $barangs[$dalamRequest->first()] ?? null;

            throw ValidationException::withMessages([
                'details' => 'Barang '.$barang?->nama_barang.' muncul lebih dari satu kali pada saldo awal ini.',
            ]);
        }

        return SumberDana::dengan($sumberDana, function () use ($sekolahId, $actor, $details, $barangs, $periode, $sumberDana, $data) {
            return DB::transaction(function () use ($sekolahId, $actor, $details, $barangs, $periode, $sumberDana, $data) {
                // Kunci baris saldo awal periode ini (bila sudah ada) supaya dua
                // request bersamaan tidak sama-sama lolos cek lalu bentrok di
                // index.
                Penerimaan::where('is_saldo_awal', true)
                    ->where('sekolah_id', $sekolahId)
                    ->where('periode_tahun', $periode)
                    ->lockForUpdate()
                    ->get();

                foreach ($details as $index => $detail) {
                    $barang = $barangs[$detail['barang_id']] ?? null;
                    if (! $barang) {
                        throw ValidationException::withMessages([
                            "details.{$index}.barang_id" => 'Barang tidak ditemukan.',
                        ]);
                    }

                    // Saldo awal boleh ditambah tiap periode (tiap tahun beda-beda);
                    // yang dilarang hanya duplikat barang pada periode yang sama.
                    if ($this->sudahAda($barang->id, $sekolahId, $periode)) {
                        throw ValidationException::withMessages([
                            "details.{$index}.barang_id" => 'Saldo awal untuk '.$barang->nama_barang.' periode '.$periode.' ('.$sumberDana.') sudah ada.',
                        ]);
                    }
                }

                try {
                    $penerimaan = Penerimaan::create([
                        'sekolah_id' => $sekolahId,
                        'is_saldo_awal' => true,
                        'periode_tahun' => $periode,
                        'tanggal_titimangsa' => $data['tanggal_titimangsa'] ?? null,
                        'no_nota_invoice' => $data['no_nota_invoice'] ?? null,
                        'no_bast' => $data['no_bast'] ?? null,
                        'tanggal_bast' => $data['tanggal_bast'] ?? null,
                        'no_kwitansi' => $data['no_kwitansi'] ?? null,
                        'penyedia_id' => $data['penyedia_id'] ?? null,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // Race: request lain menyisipkan saldo awal periode yang sama
                    // setelah cek di atas. Lempar sebagai ValidationException agar
                    // controller menampilkan pesan yang sama seperti cek normal.
                    throw ValidationException::withMessages([
                        'details' => 'Saldo awal periode '.$periode.' ('.$sumberDana.') sudah ada dan tidak dapat dibuat ulang.',
                    ]);
                }

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
        });
    }

    /**
     * Hapus SATU baris barang saldo awal (per-detail), menyisakan barang lain
     * pada header yang sama. Header ikut terhapus hanya bila detailnya habis.
     * Ditolak bila barang tersebut sudah terpakai pada pengajuan aktif.
     *
     * Ini flow yang dipakai route `saldo-awal.destroy` dan tombol hapus di
     * halaman saldo awal — jangan diganti dengan delete header.
     */
    public function deleteDetail(PenerimaanDetail $detail, User $actor): void
    {
        SumberDana::dengan($detail->sumber_dana, function () use ($detail, $actor) {
            DB::transaction(function () use ($detail, $actor) {
                // Lock baris detail agar dua request hapus bersamaan tidak
                // menghapus barang yang sama dua kali / menghitung sisa salah.
                $detail = PenerimaanDetail::whereKey($detail->id)->lockForUpdate()->firstOrFail();

                $penerimaan = Penerimaan::whereKey($detail->bhp_penerimaan_id)->lockForUpdate()->first();

                if (! $penerimaan || ! $penerimaan->is_saldo_awal) {
                    throw ValidationException::withMessages([
                        'penerimaan' => 'Hanya saldo awal yang dapat dihapus melalui flow ini.',
                    ]);
                }

                Barang::whereKey($detail->barang_id)->lockForUpdate()->firstOrFail();

                if ($this->dipakaiPadaPengajuanAktif([$detail->barang_id], $penerimaan->sekolah_id)) {
                    throw ValidationException::withMessages([
                        'penerimaan' => 'Barang saldo awal ini sudah dipakai pada pengajuan aktif dan tidak dapat dihapus.',
                    ]);
                }

                $barangNama = $detail->barang?->nama_barang;
                $detail->delete();

                // Header yatim dibersihkan supaya tidak meninggalkan saldo awal
                // kosong yang menghalangi import ulang periode yang sama.
                $sisa = PenerimaanDetail::where('bhp_penerimaan_id', $penerimaan->id)->count();
                if ($sisa === 0) {
                    $penerimaan->delete();
                }

                activity()
                    ->causedBy($actor)
                    ->withProperties([
                        'barang_id' => $detail->barang_id,
                        'barang' => $barangNama,
                        'jumlah' => $detail->jumlah,
                        'periode_tahun' => $penerimaan->periode_tahun,
                        'sumber_dana' => $penerimaan->sumber_dana,
                    ])
                    ->log('hapus-saldo-awal');
            });
        });
    }

    /**
     * Hapus SELURUH saldo awal (header + semua detail) — dipakai oleh
     * PenerimaanController::destroy. Ditolak bila ada barang yang sudah
     * terpakai pada pengajuan aktif.
     */
    public function delete(Penerimaan $penerimaan, User $actor): void
    {
        if (! $penerimaan->is_saldo_awal) {
            throw ValidationException::withMessages([
                'penerimaan' => 'Hanya saldo awal yang dapat dihapus melalui flow ini.',
            ]);
        }

        SumberDana::dengan($penerimaan->sumber_dana, function () use ($penerimaan, $actor) {
            DB::transaction(function () use ($penerimaan, $actor) {
                $penerimaan = Penerimaan::whereKey($penerimaan->id)->lockForUpdate()->firstOrFail();
                $details = $penerimaan->details()->lockForUpdate()->get();
                $barangIds = $details->pluck('barang_id')->filter()->sort()->values()->all();
                Barang::whereIn('id', $barangIds)->orderBy('id')->lockForUpdate()->get();

                if ($this->dipakaiPadaPengajuanAktif($barangIds, $penerimaan->sekolah_id)) {
                    throw ValidationException::withMessages([
                        'penerimaan' => 'Saldo awal sudah dipakai pada pengajuan aktif dan tidak dapat dihapus.',
                    ]);
                }

                $penerimaan->details()->delete();
                $penerimaan->delete();

                activity()
                    ->causedBy($actor)
                    ->withProperties(['periode_tahun' => $penerimaan->periode_tahun, 'sumber_dana' => $penerimaan->sumber_dana])
                    ->log('hapus-saldo-awal');
            });
        });
    }

    /**
     * Apakah salah satu barang saldo awal sudah dipakai pengajuan aktif
     * (submitted = reservasi, approved = terpakai)?
     *
     * Tabel sudah terpisah per sumber dana, jadi cukup scope sekolah. Karena
     * tidak ada pool pusat, $sekolahId tidak pernah null untuk saldo awal nyata.
     *
     * @param  list<string>  $barangIds
     */
    private function dipakaiPadaPengajuanAktif(array $barangIds, ?string $sekolahId): bool
    {
        if ($barangIds === []) {
            return false;
        }

        return PengajuanDetail::whereIn('barang_id', $barangIds)
            ->whereHas('pengajuan', fn ($q) => $q
                ->whereIn('status', ['submitted', 'approved'])
                ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId)))
            ->exists();
    }

    /**
     * Apakah saldo awal untuk periode ini sudah ada (level header)? Dipakai
     * untuk cek cepat sebelum membangun set barang existing saat import.
     */
    public function periodeSudahAda(string $sekolahId, string $sumberDana, int $periode): bool
    {
        return SumberDana::dengan($sumberDana, fn () => Penerimaan::query()
            ->where('sekolah_id', $sekolahId)
            ->where('is_saldo_awal', true)
            ->where('periode_tahun', $periode)
            ->exists());
    }

    /**
     * Daftar barang_id yang sudah punya saldo awal pada periode ini, dalam satu
     * query. Dipakai import agar tidak menembak query per baris berkas.
     *
     * @return array<string,true> set barang_id => true
     */
    public function barangSudahAda(string $sekolahId, string $sumberDana, int $periode): array
    {
        return SumberDana::dengan($sumberDana, fn () => PenerimaanDetail::query()
            ->whereHas('penerimaan', function ($query) use ($sekolahId, $periode): void {
                $query
                    ->where('sekolah_id', $sekolahId)
                    ->where('is_saldo_awal', true)
                    ->where('periode_tahun', $periode);
            })
            ->pluck('barang_id')
            ->filter()
            ->flip()
            ->map(fn () => true)
            ->all());
    }

    /**
     * Periode saldo awal yang sudah ada untuk barang ini (periode apa pun),
     * atau null bila belum ada.
     */
    private function sudahAda(string $barangId, string $sekolahId, int $periode): bool
    {
        return PenerimaanDetail::query()
            ->where('barang_id', $barangId)
            ->whereHas('penerimaan', function ($query) use ($sekolahId, $periode): void {
                $query
                    ->where('sekolah_id', $sekolahId)
                    ->where('is_saldo_awal', true)
                    ->where('periode_tahun', $periode);
            })
            ->exists();
    }
}
