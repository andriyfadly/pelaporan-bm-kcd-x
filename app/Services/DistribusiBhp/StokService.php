<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\DistribusiBhp\PengajuanDetail;
use App\Models\User;
use App\Support\DistribusiBhp\SumberDana;
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
     *
     * Tidak ada pool pusat: penerimaan selalu milik satu sekolah. $sekolahId
     * null = tanpa filter (admin lintas sekolah, agregat seluruh sekolah);
     * selain itu scope TEPAT ke sekolah tersebut — penerimaan sekolah lain
     * tidak pernah ikut terhitung.
     */
    public function fisik(Barang $barang, ?string $sekolahId = null): int
    {
        return SumberDana::dengan($barang->sumber_dana, fn (): int => (int) PenerimaanDetail::where('barang_id', $barang->id)
            ->when($sekolahId !== null, fn ($q) => $q->whereHas('penerimaan', fn ($p) => $p->where('sekolah_id', $sekolahId)))
            ->sum('jumlah'));
    }

    /**
     * Stok terpakai: jumlah disetujui pada pengajuan berstatus approved.
     */
    public function terpakai(Barang $barang, ?string $sekolahId = null): int
    {
        return SumberDana::dengan($barang->sumber_dana, fn (): int => (int) PengajuanDetail::where('barang_id', $barang->id)
            ->whereHas('pengajuan', fn ($q) => $q
                ->where('status', 'approved')
                ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId)))
            ->sum('jumlah_disetujui'));
    }

    /**
     * Stok tereservasi: jumlah reservasi aktif pada pengajuan submitted.
     */
    public function reservasi(Barang $barang, ?string $sekolahId = null): int
    {
        return SumberDana::dengan($barang->sumber_dana, fn (): int => (int) PengajuanDetail::where('barang_id', $barang->id)
            ->whereHas('pengajuan', fn ($q) => $q
                ->where('status', 'submitted')
                ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId)))
            ->sum('jumlah_direservasi'));
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

        $dana = collect($barangs)->first()?->sumber_dana ?? SumberDana::aktif();

        return SumberDana::dengan($dana, fn (): array => $this->rekapDalamDana($barangs, $barangIds, $sekolahId));
    }

    /**
     * @param  iterable<Barang>  $barangs
     * @param  list<string>  $barangIds
     * @return array<string,array{fisik:int,terpakai:int,reservasi:int,tersedia:int}>
     */
    private function rekapDalamDana(iterable $barangs, array $barangIds, ?string $sekolahId): array
    {
        $fisik = PenerimaanDetail::whereIn('barang_id', $barangIds)
            ->when($sekolahId !== null, fn ($q) => $q->whereHas('penerimaan', fn ($p) => $p->where('sekolah_id', $sekolahId)))
            ->groupBy('barang_id')
            ->selectRaw('barang_id, sum(jumlah) as total')
            ->pluck('total', 'barang_id');

        $terpakai = PengajuanDetail::whereIn('barang_id', $barangIds)
            ->whereHas('pengajuan', fn ($q) => $q
                ->where('status', 'approved')
                ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId)))
            ->groupBy('barang_id')
            ->selectRaw('barang_id, sum(jumlah_disetujui) as total')
            ->pluck('total', 'barang_id');

        $reservasi = PengajuanDetail::whereIn('barang_id', $barangIds)
            ->whereHas('pengajuan', fn ($q) => $q
                ->where('status', 'submitted')
                ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId)))
            ->groupBy('barang_id')
            ->selectRaw('barang_id, sum(jumlah_direservasi) as total')
            ->pluck('total', 'barang_id');

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
     * Validasi stok selalu scoped TEPAT ke sekolah pengajuan: pengajuan
     * sekolah A hanya boleh mengonsumsi stok penerimaan sekolah A, tidak
     * pernah stok sekolah B (tidak ada pool pusat).
     *
     * @throws RuntimeException bila status bukan draft, detail kosong, atau stok tidak cukup.
     */
    public function submit(Pengajuan $pengajuan): Pengajuan
    {
        return SumberDana::dengan($pengajuan->sumber_dana, fn (): Pengajuan => DB::transaction(function () use ($pengajuan) {
            $pengajuan = Pengajuan::whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();

            if ($pengajuan->status !== 'draft') {
                throw new RuntimeException('Hanya pengajuan berstatus draft yang dapat disubmit.');
            }

            $details = $pengajuan->details()->lockForUpdate()->get();
            if ($details->isEmpty()) {
                throw new RuntimeException('Pengajuan tidak memiliki item.');
            }

            // Kunci semua barang dulu (urutan ID deterministik, anti-deadlock)
            // sebelum agregasi stok, agar submit concurrent tidak oversell.
            Barang::whereIn('id', $details->pluck('barang_id')->filter()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

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
        }));
    }

    /**
     * Reject pengajuan: lepaskan reservasi.
     */
    public function reject(Pengajuan $pengajuan, User $reviewer, string $alasan): Pengajuan
    {
        return SumberDana::dengan($pengajuan->sumber_dana, fn (): Pengajuan => DB::transaction(function () use ($pengajuan, $reviewer, $alasan) {
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
        }));
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
        return SumberDana::dengan($pengajuan->sumber_dana, fn (): Pengajuan => $this->applyApproveDalamDana($pengajuan, $reviewer, $jumlahDisetujui));
    }

    /**
     * @param  array<string,int>  $jumlahDisetujui
     */
    private function applyApproveDalamDana(Pengajuan $pengajuan, User $reviewer, array $jumlahDisetujui): Pengajuan
    {
        $pengajuan = Pengajuan::whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();

        if ($pengajuan->status !== 'submitted') {
            throw new RuntimeException('Hanya pengajuan berstatus submitted yang dapat disetujui.');
        }

        $details = $pengajuan->details()->lockForUpdate()->get();

        // Kunci semua barang yang terlibat dengan urutan ID deterministik
        // untuk mencegah deadlock antar approval concurrent.
        Barang::whereIn('id', $details->pluck('barang_id')->filter()->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $detailIds = $details->pluck('id')->all();
        foreach ($jumlahDisetujui as $detailId => $qty) {
            if (! in_array((string) $detailId, $detailIds, true)) {
                throw new RuntimeException('Detail ID tidak dikenal pada pengajuan ini: '.$detailId);
            }

            if (! is_int($qty) || $qty < 0) {
                throw new RuntimeException('Jumlah disetujui harus berupa angka bulat tidak negatif.');
            }
        }

        foreach ($details as $detail) {
            $barang = Barang::whereKey($detail->barang_id)->lockForUpdate()->first();
            if (! $barang) {
                throw new RuntimeException('Barang pada pengajuan tidak ditemukan.');
            }

            // Revalidasi sumber dana saat approval — jangan hanya andalkan
            // validasi submit (barang bisa berubah status dana setelahnya).
            if ($barang->sumber_dana !== $pengajuan->sumber_dana) {
                throw new RuntimeException('Sumber dana barang tidak sesuai dengan pengajuan.');
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
