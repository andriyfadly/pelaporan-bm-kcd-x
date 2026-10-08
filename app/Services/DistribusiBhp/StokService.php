<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Perhitungan dan mutasi stok BHP (4-tier).
 *
 * Tersedia = Fisik - Terpakai - Reservasi.
 * Semua mutasi stok harus lewat service ini agar konsisten dan atomic.
 *
 * Lihat docs/requirements/distribusi-bhp-prd.md.
 */
class StokService
{
    /**
     * Stok fisik: total barang masuk (penerimaan + saldo awal).
     * $sekolahId null = tanpa filter (admin lintas sekolah); user sekolah
     * mendapat penerimaan sekolahnya sendiri ditambah pool pusat (sekolah_id null).
     */
    public function fisik(Barang $barang, ?string $sekolahId = null): int
    {
        return (int) PenerimaanDetail::where('barang_id', $barang->id)
            ->when($sekolahId !== null, fn ($q) => $q->whereHas('penerimaan', fn ($p) => $p->where(function ($w) use ($sekolahId) {
                $w->where('sekolah_id', $sekolahId)->orWhereNull('sekolah_id');
            })))
            ->sum('jumlah');
    }

    /**
     * Stok terpakai: jumlah disetujui pada pengajuan berstatus approved.
     */
    public function terpakai(Barang $barang, ?string $sekolahId = null): int
    {
        return (int) DB::table('bhp_pengajuan_detail as d')
            ->join('bhp_pengajuan as p', 'p.id', '=', 'd.bhp_pengajuan_id')
            ->where('d.barang_id', $barang->id)
            ->where('p.status', 'approved')
            ->when($sekolahId !== null, fn ($q) => $q->where('p.sekolah_id', $sekolahId))
            ->sum('d.jumlah_disetujui');
    }

    /**
     * Stok tereservasi: jumlah reservasi aktif pada pengajuan submitted.
     */
    public function reservasi(Barang $barang, ?string $sekolahId = null): int
    {
        return (int) DB::table('bhp_pengajuan_detail as d')
            ->join('bhp_pengajuan as p', 'p.id', '=', 'd.bhp_pengajuan_id')
            ->where('d.barang_id', $barang->id)
            ->where('p.status', 'submitted')
            ->when($sekolahId !== null, fn ($q) => $q->where('p.sekolah_id', $sekolahId))
            ->sum('d.jumlah_direservasi');
    }

    /**
     * Stok tersedia, tidak pernah negatif.
     */
    public function tersedia(Barang $barang, ?string $sekolahId = null): int
    {
        return max(0, $this->fisik($barang, $sekolahId) - $this->terpakai($barang, $sekolahId) - $this->reservasi($barang, $sekolahId));
    }

    /**
     * Agregasi stok 4-tier untuk SEMUA barang sekaligus dalam 3 query grouped
     * (fisik, terpakai, reservasi) — dipakai laporan stok agar tidak N+1.
     * Baris barang tanpa mutasi tetap muncul dengan angka 0.
     *
     * @param  list<Barang>  $barangs
     * @return array<string,array{fisik:int,terpakai:int,reservasi:int,tersedia:int}> keyed by barang id
     */
    public function rekapPerBarang(iterable $barangs, ?string $sekolahId = null): array
    {
        $barangIds = collect($barangs)->pluck('id')->all();

        if ($barangIds === []) {
            return [];
        }

        $fisik = PenerimaanDetail::whereIn('barang_id', $barangIds)
            ->when($sekolahId !== null, fn ($q) => $q->whereHas('penerimaan', fn ($p) => $p->where(function ($w) use ($sekolahId) {
                $w->where('sekolah_id', $sekolahId)->orWhereNull('sekolah_id');
            })))
            ->groupBy('barang_id')
            ->selectRaw('barang_id, sum(jumlah) as total')
            ->pluck('total', 'barang_id');

        $terpakai = DB::table('bhp_pengajuan_detail as d')
            ->join('bhp_pengajuan as p', 'p.id', '=', 'd.bhp_pengajuan_id')
            ->whereIn('d.barang_id', $barangIds)
            ->where('p.status', 'approved')
            ->when($sekolahId !== null, fn ($q) => $q->where('p.sekolah_id', $sekolahId))
            ->groupBy('d.barang_id')
            ->selectRaw('d.barang_id, sum(d.jumlah_disetujui) as total')
            ->pluck('total', 'd.barang_id');

        $reservasi = DB::table('bhp_pengajuan_detail as d')
            ->join('bhp_pengajuan as p', 'p.id', '=', 'd.bhp_pengajuan_id')
            ->whereIn('d.barang_id', $barangIds)
            ->where('p.status', 'submitted')
            ->when($sekolahId !== null, fn ($q) => $q->where('p.sekolah_id', $sekolahId))
            ->groupBy('d.barang_id')
            ->selectRaw('d.barang_id, sum(d.jumlah_direservasi) as total')
            ->pluck('total', 'd.barang_id');

        $rekap = [];

        foreach ($barangs as $barang) {
            $f = (int) ($fisik[$barang->id] ?? 0);
            $t = (int) ($terpakai[$barang->id] ?? 0);
            $r = (int) ($reservasi[$barang->id] ?? 0);
            $rekap[$barang->id] = [
                'fisik' => $f,
                'terpakai' => $t,
                'reservasi' => $r,
                'tersedia' => max(0, $f - $t - $r),
            ];
        }

        return $rekap;
    }

    /**
     * Submit pengajuan: validasi stok lalu buat reservasi.
     *
     * Validasi stok selalu scoped ke sekolah pengajuan: penerimaan tanpa
     * sekolah (saldo awal pusat) tetap bisa dipakai, tapi pengajuan sekolah A
     * tidak mengonsumsi stok penerimaan milik sekolah B.
     *
     * @throws RuntimeException bila status bukan draft, detail kosong, atau stok tidak cukup.
     */
    public function submit(Pengajuan $pengajuan): Pengajuan
    {
        return DB::transaction(function () use ($pengajuan) {
            $pengajuan = Pengajuan::whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();

            if ($pengajuan->status !== 'draft') {
                throw new RuntimeException('Hanya pengajuan berstatus draft yang dapat disubmit.');
            }

            $details = $pengajuan->details()->lockForUpdate()->get();
            if ($details->isEmpty()) {
                throw new RuntimeException('Pengajuan tidak memiliki item.');
            }

            foreach ($details as $detail) {
                $barang = Barang::whereKey($detail->barang_id)->lockForUpdate()->first();
                if (! $barang) {
                    throw new RuntimeException('Barang pada pengajuan tidak ditemukan.');
                }

                if ($barang->sumber_dana !== $pengajuan->sumber_dana) {
                    throw new RuntimeException('Sumber dana barang tidak sesuai dengan pengajuan.');
                }

                $diminta = (int) $detail->jumlah_diminta;
                if ($diminta <= 0) {
                    throw new RuntimeException('Jumlah diminta harus lebih dari 0.');
                }

                $tersedia = $this->tersedia($barang, $pengajuan->sekolah_id);
                if ($diminta > $tersedia) {
                    throw new RuntimeException("Stok tersedia {$barang->nama_barang} tidak mencukupi (tersedia {$tersedia}, diminta {$diminta}).");
                }

                $detail->update([
                    'status_reservasi' => 'direservasi',
                    'jumlah_direservasi' => $diminta,
                ]);
            }

            $pengajuan->update(['status' => 'submitted']);

            return $pengajuan;
        });
    }

    /**
     * Reject pengajuan: lepaskan reservasi.
     */
    public function reject(Pengajuan $pengajuan, User $reviewer, string $alasan): Pengajuan
    {
        return DB::transaction(function () use ($pengajuan, $reviewer, $alasan) {
            $pengajuan = Pengajuan::whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();

            if ($pengajuan->status !== 'submitted') {
                throw new RuntimeException('Hanya pengajuan berstatus submitted yang dapat ditolak.');
            }

            if (trim($alasan) === '') {
                throw new RuntimeException('Alasan penolakan wajib diisi.');
            }

            $pengajuan->details()->update([
                'status_reservasi' => 'tidak_direservasi',
                'jumlah_direservasi' => 0,
            ]);

            $pengajuan->update([
                'status' => 'rejected',
                'rejection_reason' => $alasan,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            return $pengajuan;
        });
    }

    /**
     * Approve pengajuan: koreksi jumlah, konversi reservasi menjadi pemakaian.
     *
     * @param  array<string,int>  $jumlahDisetujui  map detail_id => jumlah disetujui
     */
    public function approve(Pengajuan $pengajuan, User $reviewer, array $jumlahDisetujui = []): Pengajuan
    {
        return DB::transaction(fn () => $this->applyApprove($pengajuan, $reviewer, $jumlahDisetujui));
    }

    /**
     * Inti approval tanpa membuka transaction sendiri, agar dapat dipanggil
     * di dalam transaction milik caller (mis. DokumenService).
     *
     * @param  array<string,int>  $jumlahDisetujui
     */
    public function applyApprove(Pengajuan $pengajuan, User $reviewer, array $jumlahDisetujui = []): Pengajuan
    {
        $pengajuan = Pengajuan::whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();

        if ($pengajuan->status !== 'submitted') {
            throw new RuntimeException('Hanya pengajuan berstatus submitted yang dapat disetujui.');
        }

        $details = $pengajuan->details()->lockForUpdate()->get();

        foreach ($details as $detail) {
            $barang = Barang::whereKey($detail->barang_id)->lockForUpdate()->first();
            if (! $barang) {
                throw new RuntimeException('Barang pada pengajuan tidak ditemukan.');
            }

            $disetujui = array_key_exists($detail->id, $jumlahDisetujui)
                ? (int) $jumlahDisetujui[$detail->id]
                : (int) $detail->jumlah_direservasi;

            if ($disetujui < 0) {
                throw new RuntimeException('Jumlah disetujui tidak boleh negatif.');
            }

            if ($disetujui > (int) $detail->jumlah_diminta) {
                throw new RuntimeException('Jumlah disetujui tidak boleh melebihi jumlah diminta.');
            }

            // Reservasi item ini dilepas, lalu divalidasi terhadap sisa tersedia
            // setelah reservasi sendiri dilepas (scoped ke sekolah pengajuan).
            $sisaTanpaReservasiIni = $this->fisik($barang, $pengajuan->sekolah_id)
                - $this->terpakai($barang, $pengajuan->sekolah_id)
                - ($this->reservasi($barang, $pengajuan->sekolah_id) - (int) $detail->jumlah_direservasi);

            if ($disetujui > $sisaTanpaReservasiIni) {
                throw new RuntimeException("Jumlah disetujui melebihi stok tersedia {$barang->nama_barang}.");
            }

            $detail->update([
                'jumlah_disetujui' => $disetujui,
                'status_reservasi' => 'tidak_direservasi',
                'jumlah_direservasi' => 0,
            ]);
        }

        $pengajuan->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return $pengajuan;
    }
}
