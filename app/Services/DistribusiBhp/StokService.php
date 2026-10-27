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
     *
     * $sampai = tanggal akhir efektif (Y-m-d) untuk laporan periode; null =
     * posisi saat ini.
     */
    public function fisik(Barang $barang, ?string $sekolahId = null, ?string $sampai = null): int
    {
        return SumberDana::dengan($barang->sumber_dana, fn (): int => (int) PenerimaanDetail::where('barang_id', $barang->id)
            ->whereHas('penerimaan', fn ($p) => $p
                ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId))
                ->when($sampai !== null, fn ($w) => $w->efektifSampai($sampai)))
            ->sum('jumlah'));
    }

    /**
     * Stok terpakai: jumlah disetujui pada pengajuan berstatus approved.
     */
    public function terpakai(Barang $barang, ?string $sekolahId = null, ?string $sampai = null): int
    {
        return SumberDana::dengan($barang->sumber_dana, fn (): int => (int) PengajuanDetail::where('barang_id', $barang->id)
            ->whereHas('pengajuan', fn ($q) => $q
                ->where('status', 'approved')
                ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId))
                ->when($sampai !== null, fn ($w) => $w->efektifSampai($sampai)))
            ->sum('jumlah_disetujui'));
    }

    /**
     * Stok tereservasi: jumlah reservasi aktif pada pengajuan submitted.
     */
    public function reservasi(Barang $barang, ?string $sekolahId = null, ?string $sampai = null): int
    {
        return SumberDana::dengan($barang->sumber_dana, fn (): int => (int) PengajuanDetail::where('barang_id', $barang->id)
            ->whereHas('pengajuan', fn ($q) => $q
                ->where('status', 'submitted')
                ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId))
                ->when($sampai !== null, fn ($w) => $w->efektifSampai($sampai)))
            ->sum('jumlah_direservasi'));
    }

    /**
     * Stok tersedia, tidak pernah negatif.
     */
    public function tersedia(Barang $barang, ?string $sekolahId = null, ?string $sampai = null): int
    {
        return max(
            0,
            $this->fisik($barang, $sekolahId, $sampai)
                - $this->terpakai($barang, $sekolahId, $sampai)
                - $this->reservasi($barang, $sekolahId, $sampai),
        );
    }

    /**
     * Agregasi stok 4-tier untuk SEMUA barang sekaligus dalam 3 query grouped
     * (fisik, terpakai, reservasi) — dipakai laporan stok agar tidak N+1.
     * Baris barang tanpa mutasi tetap muncul dengan angka 0.
     *
     * @param  list<Barang>  $barangs
     * @return array<string,array{fisik:int,terpakai:int,reservasi:int,tersedia:int}> keyed by barang id
     */
    public function rekapPerBarang(iterable $barangs, ?string $sekolahId = null, ?string $sampai = null): array
    {
        $barangIds = collect($barangs)->pluck('id')->all();

        if ($barangIds === []) {
            return [];
        }

        $dana = collect($barangs)->first()?->sumber_dana ?? SumberDana::aktif();

        return SumberDana::dengan($dana, fn (): array => $this->snapshot(
            fn (): array => $this->rekapDalamDana($barangs, $barangIds, $sekolahId, $sampai),
        ));
    }

    /**
     * Jalankan pembacaan multi-query dalam satu snapshot konsisten.
     *
     * Rekap stok memakai 3 agregat terpisah. Tanpa snapshot, approval yang
     * commit di antara query membuat angka fisik/terpakai/reservasi berasal dari
     * waktu berbeda (read skew) sehingga laporan tidak balance. Di PostgreSQL
     * dibungkus transaction REPEATABLE READ.
     *
     * Lewati bila sudah di dalam transaction: `SET TRANSACTION ISOLATION LEVEL`
     * hanya boleh jadi statement pertama (di dalam savepoint akan error), dan
     * pemanggil yang sudah transaksional memang sudah konsisten.
     */
    private function snapshot(\Closure $callback): mixed
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql' || $connection->transactionLevel() > 0) {
            return $callback();
        }

        return $connection->transaction(function () use ($connection, $callback) {
            $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            return $callback();
        });
    }

    /**
     * @param  iterable<Barang>  $barangs
     * @param  list<string>  $barangIds
     * @return array<string,array{fisik:int,terpakai:int,reservasi:int,tersedia:int}>
     */
    private function rekapDalamDana(iterable $barangs, array $barangIds, ?string $sekolahId, ?string $sampai): array
    {
        $fisik = PenerimaanDetail::whereIn('barang_id', $barangIds)
            ->whereHas('penerimaan', fn ($p) => $p
                ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId))
                ->when($sampai !== null, fn ($w) => $w->efektifSampai($sampai)))
            ->groupBy('barang_id')
            ->selectRaw('barang_id, sum(jumlah) as total')
            ->pluck('total', 'barang_id');

        $terpakai = PengajuanDetail::whereIn('barang_id', $barangIds)
            ->whereHas('pengajuan', fn ($q) => $q
                ->where('status', 'approved')
                ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId))
                ->when($sampai !== null, fn ($w) => $w->efektifSampai($sampai)))
            ->groupBy('barang_id')
            ->selectRaw('barang_id, sum(jumlah_disetujui) as total')
            ->pluck('total', 'barang_id');

        $reservasi = PengajuanDetail::whereIn('barang_id', $barangIds)
            ->whereHas('pengajuan', fn ($q) => $q
                ->where('status', 'submitted')
                ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId))
                ->when($sampai !== null, fn ($w) => $w->efektifSampai($sampai)))
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

            // Satu barang bisa muncul di beberapa baris draft; reservasi wajib
            // divalidasi terhadap TOTAL baris itu, bukan per baris — kalau tidak,
            // stok 10 bisa direservasi 8 + 8 dua-duanya "lolos".
            $dimintaPerBarang = $details->groupBy('barang_id')
                ->filter(fn ($rows, $barangId): bool => $barangId !== null && $barangId !== '')
                ->map(fn ($rows): int => (int) $rows->sum('jumlah_diminta'));

            foreach ($details as $detail) {
                $barang = Barang::whereKey($detail->barang_id)->lockForUpdate()->first();
                if (! $barang) {
                    throw new RuntimeException('Barang pada pengajuan tidak ditemukan.');
                }

                if ($barang->sumber_dana !== $pengajuan->sumber_dana) {
                    throw new RuntimeException('Sumber dana barang tidak sesuai dengan pengajuan.');
                }

                if ((int) $detail->jumlah_diminta <= 0) {
                    throw new RuntimeException('Jumlah diminta harus lebih dari 0.');
                }
            }

            foreach ($dimintaPerBarang as $barangId => $diminta) {
                $barang = Barang::whereKey($barangId)->lockForUpdate()->first();
                $tersedia = $this->tersedia($barang, $pengajuan->sekolah_id);
                if ($diminta > $tersedia) {
                    throw new RuntimeException("Stok tersedia {$barang->nama_barang} tidak mencukupi (tersedia {$tersedia}, diminta {$diminta}).");
                }
            }

            foreach ($details as $detail) {
                $detail->update([
                    'status_reservasi' => 'direservasi',
                    'jumlah_direservasi' => (int) $detail->jumlah_diminta,
                ]);
            }

            $pengajuan->update(['status' => 'submitted']);

            return $pengajuan;
        }, 3));
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
        }, 3));
    }

    /**
     * Approve pengajuan: koreksi jumlah, konversi reservasi menjadi pemakaian.
     *
     * @param  array<string,int>  $jumlahDisetujui  map detail_id => jumlah disetujui
     */
    public function approve(Pengajuan $pengajuan, User $reviewer, array $jumlahDisetujui = []): Pengajuan
    {
        return DB::transaction(fn () => $this->applyApprove($pengajuan, $reviewer, $jumlahDisetujui), 3);
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

        if ($details->isEmpty()) {
            throw new RuntimeException('Pengajuan tidak memiliki item.');
        }

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

        /** @var array<string,array{disetujui:int,reserved:int,detail:array<string,int>}> $perBarang */
        $perBarang = [];

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

            if ($disetujui > (int) $detail->jumlah_diminta) {
                throw new RuntimeException('Jumlah disetujui tidak boleh melebihi jumlah diminta.');
            }

            $id = (string) $detail->barang_id;
            $perBarang[$id] ??= ['disetujui' => 0, 'reserved' => 0, 'detail' => []];
            $perBarang[$id]['disetujui'] += $disetujui;
            $perBarang[$id]['reserved'] += (int) $detail->jumlah_direservasi;
            $perBarang[$id]['detail'][$detail->id] = $disetujui;
        }

        // Validasi stok per BARANG, bukan per baris detail: satu barang bisa
        // muncul di beberapa baris, dan `terpakai` belum menghitung baris yang
        // sedang disetujui (status pengajuan masih submitted). Tanpa agregasi,
        // 6 + 6 dari stok 10 dua-duanya lolos dan stok jadi minus.
        foreach ($perBarang as $barangId => $agg) {
            $barang = Barang::whereKey($barangId)->lockForUpdate()->firstOrFail();

            $sisaTanpaReservasiSendiri = $this->fisik($barang, $pengajuan->sekolah_id)
                - $this->terpakai($barang, $pengajuan->sekolah_id)
                - ($this->reservasi($barang, $pengajuan->sekolah_id) - $agg['reserved']);

            if ($agg['disetujui'] > $sisaTanpaReservasiSendiri) {
                throw new RuntimeException("Jumlah disetujui melebihi stok tersedia {$barang->nama_barang}.");
            }
        }

        foreach ($details as $detail) {
            $detail->update([
                'jumlah_disetujui' => $perBarang[(string) $detail->barang_id]['detail'][$detail->id],
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
