<?php

namespace Database\Seeders;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Divisi;
use App\Models\DistribusiBhp\Dokumen;
use App\Models\DistribusiBhp\HargaBarang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\DistribusiBhp\Penyedia;
use App\Models\Master\Jabatan;
use App\Models\Master\Pegawai;
use App\Models\Master\PenugasanPegawai;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\DokumenService;
use App\Services\DistribusiBhp\SaldoAwalService;
use App\Services\DistribusiBhp\StokService;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dataset demo DISTRIBUSI BHP untuk SMK Negeri 4 Kuningan, semester 1 2026
 * (Januari–Juni), sumber dana BOS dan BOPD.
 *
 * Semua baris memakai penanda `DEMO-SMKN4-2026` pada kode barang, nomor BAST,
 * dan nomor pengajuan sehingga mudah dikenali dan dibersihkan kembali.
 * Aman dijalankan berulang: transaksi dicari lewat identitas penanda, bukan
 * dibuat ulang.
 *
 * Tidak didaftarkan di DatabaseSeeder — jalankan eksplisit:
 * php artisan db:seed --class=DemoDistribusiBhpSmkn4KuninganSeeder
 */
class DemoDistribusiBhpSmkn4KuninganSeeder extends Seeder
{
    public const NPSN = '20246369';

    public const TAHUN = 2026;

    public const PENANDA = 'DEMO-SMKN4-2026';

    public const PASSWORD = 'Demo#Smkn4Kuningan';

    /**
     * Katalog barang demo: kode, nama, satuan, harga BOS, harga BOPD.
     * Harga sengaja berbeda antar sumber dana agar tertukar langsung terlihat.
     *
     * @var list<array{string,string,string,int,int}>
     */
    private const BARANG = [
        ['A4', 'Kertas HVS A4 70gr', 'Rim', 55000, 52000],
        ['F4', 'Kertas HVS F4 70gr', 'Rim', 60000, 57000],
        ['PULPEN', 'Pulpen Hitam', 'Pcs', 3500, 3200],
        ['PENSIL', 'Pensil 2B', 'Pcs', 4000, 3800],
        ['MAP', 'Map Plastik', 'Pcs', 2500, 2400],
        ['TINTA', 'Tinta Printer Hitam', 'Botol', 95000, 90000],
        ['SPIDOL', 'Spidol Whiteboard', 'Pcs', 12000, 11000],
        ['AGENDA', 'Buku Agenda', 'Buah', 30000, 28000],
        ['STAPLER', 'Stapler', 'Buah', 45000, 42000],
        ['LAKBAN', 'Lakban Bening', 'Roll', 15000, 14000],
    ];

    /** Saldo awal 1 Januari 2026, urut sama dengan BARANG. */
    private const SALDO_AWAL = [
        'BOS' => [200, 150, 400, 300, 250, 60, 120, 80, 25, 100],
        'BOPD' => [80, 60, 150, 120, 100, 24, 48, 32, 10, 40],
    ];

    /**
     * Penerimaan per bulan: [hari, [[index barang, jumlah BOS], ...]].
     * Jumlah BOPD diskalakan 40% supaya proporsional dengan saldo awalnya.
     *
     * @var list<array{int,list<array{int,int}>}>
     */
    private const PENERIMAAN = [
        [5, [[0, 40], [2, 60]]],
        [12, [[1, 30], [3, 50], [4, 40]]],
        [20, [[5, 12], [6, 24], [7, 16]]],
    ];

    /** Pengajuan per bulan: [[index barang, jumlah BOS], ...]. */
    private const PENGAJUAN = [
        [[0, 26], [2, 30]],
        [[1, 20], [3, 25]],
        [[4, 30], [9, 10]],
        [[5, 6], [6, 10], [7, 8]],
    ];

    private const DIVISI = ['Tata Usaha', 'Sarana Prasarana', 'Kurikulum', 'Kesiswaan'];

    private const PENYEDIA = [
        ['CV Sumber Kertas Kuningan', 'Jl. Raya Cirendang No. 12', '0232-123456'],
        ['PT Alat Tulis Cirebon', 'Jl. Siliwangi No. 88, Cirebon', '0231-654321'],
        ['Toko Berkah Office', 'Jl. Veteran No. 45, Kuningan', '0232-778899'],
        ['CV Media Cetak Jabar', 'Jl. Soekarno Hatta No. 9, Bandung', '022-990011'],
    ];

    private const PEJABAT = [
        ['kepala_sekolah', 'Dra. Nining Kurniasih', '197005121995122001', 'Pembina Tk. I / IV-b'],
        ['wakasek_sarpras', 'Hendi Suryana, S.Pd.', '198203152008011005', 'Penata / III-c'],
        ['pembantu_pengurus_barang', 'Rina Marlina, A.Md.', '199007232015032002', 'Pengatur / II-c'],
    ];

    public function run(): void
    {
        $sekolah = Sekolah::where('npsn', self::NPSN)->first();

        if (! $sekolah) {
            throw new RuntimeException(
                'Sekolah NPSN '.self::NPSN.' tidak ditemukan. Jalankan KodeSekolahSeeder terlebih dahulu.'
            );
        }

        DB::transaction(function () use ($sekolah): void {
            $divisi = $this->seedMaster($sekolah);
            $users = $this->seedPengguna($sekolah, $divisi);

            foreach (SumberDana::SEMUA as $dana) {
                SumberDana::dengan($dana, function () use ($sekolah, $dana, $divisi, $users): void {
                    $this->seedDana($sekolah, $dana, $divisi, $users);
                });
            }

            foreach (SumberDana::SEMUA as $dana) {
                SumberDana::dengan($dana, fn () => $this->seedDokumenTambahan($sekolah, $dana));
            }
        });

        $this->command?->info('Dataset demo BHP SMK Negeri 4 Kuningan siap (semester 1 '.self::TAHUN.').');
    }

    /**
     * @return array<string, Divisi>
     */
    private function seedMaster(Sekolah $sekolah): array
    {
        Jabatan::pastikanWajib($sekolah->id);

        foreach (self::PEJABAT as [$kode, $nama, $nip, $pangkat]) {
            $pegawai = Pegawai::firstOrCreate(
                ['sekolah_id' => $sekolah->id, 'nip' => $nip],
                ['nama_lengkap' => $nama, 'pangkat_golongan' => $pangkat],
            );

            PenugasanPegawai::firstOrCreate([
                'sekolah_id' => $sekolah->id,
                'pegawai_id' => $pegawai->id,
                'jabatan_id' => Jabatan::where('sekolah_id', $sekolah->id)->where('kode', $kode)->value('id'),
            ], [
                'jenis_penugasan' => 'definitif',
                'mulai_menjabat' => '2024-07-01',
            ]);
        }

        foreach (self::PENYEDIA as [$nama, $alamat, $telepon]) {
            Penyedia::firstOrCreate(['nama' => $nama], ['alamat' => $alamat, 'telepon' => $telepon]);
        }

        $divisi = [];
        foreach (self::DIVISI as $nama) {
            $divisi[$nama] = Divisi::firstOrCreate(['sekolah_id' => $sekolah->id, 'nama' => $nama]);
        }

        return $divisi;
    }

    /**
     * @param  array<string, Divisi>  $divisi
     * @return array{operator: User, reviewer: User, divisi: array<string, User>}
     */
    private function seedPengguna(Sekolah $sekolah, array $divisi): array
    {
        $operator = $this->user('demo-operator-'.self::NPSN, 'Operator Demo SMKN 4 Kuningan', $sekolah, 'operator_sekolah');
        $reviewer = $this->user('demo-reviewer-'.self::NPSN, 'Reviewer Demo SMKN 4 Kuningan', $sekolah, 'admin_kcd');

        $perDivisi = [];
        foreach ($divisi as $nama => $item) {
            $perDivisi[$nama] = $this->user(
                'demo-'.self::NPSN.'-'.$this->slug($nama),
                'Operator '.$nama,
                $sekolah,
                'operator_divisi',
                $item->id,
            );
        }

        return ['operator' => $operator, 'reviewer' => $reviewer, 'divisi' => $perDivisi];
    }

    private function user(string $username, string $nama, Sekolah $sekolah, string $role, ?string $divisiId = null): User
    {
        $user = User::updateOrCreate(['username' => $username], [
            'name' => $nama,
            'password' => self::PASSWORD,
            'sekolah_id' => $sekolah->id,
            'divisi_id' => $divisiId,
            'is_active' => true,
            'password_changed_at' => now(),
        ]);

        $user->syncRoles([$role]);

        return $user;
    }

    /**
     * @param  array<string, Divisi>  $divisi
     * @param  array{operator: User, reviewer: User, divisi: array<string, User>}  $users
     */
    private function seedDana(Sekolah $sekolah, string $dana, array $divisi, array $users): void
    {
        $barang = $this->seedBarang($dana);
        $this->seedSaldoAwal($sekolah, $dana, $barang, $users['operator']);

        $nomorDivisi = array_values($divisi);

        for ($bulan = 1; $bulan <= 6; $bulan++) {
            $this->seedPenerimaan($sekolah, $dana, $bulan, $barang);
            $this->seedPengajuan(
                $sekolah,
                $dana,
                $bulan,
                $barang,
                $nomorDivisi,
                $users['divisi'],
                $users['reviewer'],
            );
        }

        $this->seedPengajuanTambahan($sekolah, $dana, $barang, $nomorDivisi, $users);
    }

    /**
     * @return array<int, Barang>
     */
    private function seedBarang(string $dana): array
    {
        $kolom = $dana === SumberDana::BOPD ? 4 : 3;
        $hasil = [];

        foreach (self::BARANG as $index => [$kode, $nama, $satuan]) {
            $barang = Barang::updateOrCreate(
                ['kode_barang' => self::PENANDA.'-'.$dana.'-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)],
                ['nama_barang' => $nama, 'satuan' => $satuan],
            );

            HargaBarang::updateOrCreate(
                ['barang_id' => $barang->id, 'harga_satuan' => self::BARANG[$index][$kolom]],
            );

            $hasil[$index] = $barang;
        }

        return $hasil;
    }

    /**
     * @param  array<int, Barang>  $barang
     */
    private function seedSaldoAwal(Sekolah $sekolah, string $dana, array $barang, User $operator): void
    {
        $sudahAda = Penerimaan::where('sekolah_id', $sekolah->id)
            ->where('is_saldo_awal', true)
            ->where('periode_tahun', self::TAHUN)
            ->exists();

        if ($sudahAda) {
            return;
        }

        $details = [];
        foreach (self::SALDO_AWAL[$dana] as $index => $jumlah) {
            $details[] = [
                'barang_id' => $barang[$index]->id,
                'jumlah' => $jumlah,
                'harga_satuan' => self::BARANG[$index][$dana === SumberDana::BOPD ? 4 : 3],
                'keterangan' => 'Saldo awal '.self::TAHUN,
            ];
        }

        app(SaldoAwalService::class)->create([
            'sumber_dana' => $dana,
            'is_saldo_awal' => true,
            'periode_tahun' => self::TAHUN,
            'tanggal_titimangsa' => self::TAHUN.'-01-01',
        ], $sekolah->id, $operator, $details, collect($barang)->keyBy('id')->all());
    }

    /**
     * @param  array<int, Barang>  $barang
     */
    private function seedPenerimaan(Sekolah $sekolah, string $dana, int $bulan, array $barang): void
    {
        foreach (self::PENERIMAAN as $urutan => [$hari, $items]) {
            $tanggal = sprintf('%d-%02d-%02d', self::TAHUN, $bulan, $hari);

            $penerimaan = Penerimaan::firstOrCreate([
                'source_id' => self::PENANDA.'-'.$dana.'-TERIMA-'.str_pad((string) $bulan, 2, '0', STR_PAD_LEFT).'-'.($urutan + 1),
            ], [
                'sekolah_id' => $sekolah->id,
                'no_bast' => 'BAST/'.self::PENANDA.'/'.$dana.'/'.str_pad((string) $bulan, 2, '0', STR_PAD_LEFT).'/'.($urutan + 1),
                'no_nota_invoice' => 'INV-'.$dana.'-'.sprintf('%02d%02d', $bulan, $urutan + 1),
                'no_kwitansi' => 'KW-'.$dana.'-'.sprintf('%02d%02d', $bulan, $urutan + 1),
                'tanggal_bast' => $tanggal,
                'tanggal_titimangsa' => $tanggal,
                'penyedia_id' => Penyedia::where('nama', self::PENYEDIA[($bulan + $urutan) % count(self::PENYEDIA)][0])->value('id'),
                'is_saldo_awal' => false,
                'periode_tahun' => null,
            ]);

            foreach ($items as [$index, $jumlah]) {
                $jumlah = $this->skala($dana, $jumlah);
                $harga = self::BARANG[$index][$dana === SumberDana::BOPD ? 4 : 3];

                $penerimaan->details()->updateOrCreate(['barang_id' => $barang[$index]->id], [
                    'jumlah' => $jumlah,
                    'harga_satuan' => $harga,
                    'nilai_total' => $jumlah * $harga,
                ]);
            }
        }
    }

    /**
     * @param  array<int, Barang>  $barang
     * @param  list<Divisi>  $divisi
     * @param  array<string, User>  $operatorDivisi
     */
    private function seedPengajuan(
        Sekolah $sekolah,
        string $dana,
        int $bulan,
        array $barang,
        array $divisi,
        array $operatorDivisi,
        User $reviewer,
    ): void {
        foreach (self::PENGAJUAN as $urutan => $items) {
            $divisiItem = $divisi[($bulan + $urutan) % count($divisi)];
            $nomor = self::PENANDA.'-'.$dana.'-'.str_pad((string) $bulan, 2, '0', STR_PAD_LEFT).'-'.($urutan + 1);

            $pengajuan = Pengajuan::firstOrNew(['nomor_pengajuan' => $nomor]);

            if ($pengajuan->exists && $pengajuan->status !== 'draft') {
                continue;
            }

            if (! $pengajuan->exists) {
                $pengajuan->fill([
                    'sekolah_id' => $sekolah->id,
                    'divisi_id' => $divisiItem->id,
                    'user_id' => $operatorDivisi[$divisiItem->nama]->id,
                    'tanggal' => sprintf('%d-%02d-25', self::TAHUN, $bulan),
                    'status' => 'draft',
                ])->save();

                foreach ($items as [$index, $jumlah]) {
                    $pengajuan->details()->create([
                        'barang_id' => $barang[$index]->id,
                        'jumlah_diminta' => $this->skala($dana, $jumlah),
                        'harga_satuan' => self::BARANG[$index][$dana === SumberDana::BOPD ? 4 : 3],
                        'keperluan' => 'Kebutuhan operasional '.$divisiItem->nama,
                    ]);
                }
            }

            $pengajuan->load('details');
            app(StokService::class)->submit($pengajuan);

            // Pengajuan Juni terakhir: setujui sebagian untuk membuktikan bahwa
            // pengeluaran memakai jumlah_disetujui, bukan jumlah_diminta.
            // Pengajuan inilah yang juga memuat dokumen tambahan.
            $sebagian = [];
            if ($bulan === 6 && $urutan === count(self::PENGAJUAN) - 1) {
                $detail = $pengajuan->details()->first();
                $sebagian[$detail->id] = max(1, (int) floor($detail->jumlah_direservasi / 2));
            }

            app(DokumenService::class)->approveDanGenerate($pengajuan->fresh('details'), $reviewer, $sebagian);
        }
    }

    /**
     * Pengajuan yang ditolak dan yang masih menunggu, supaya alur reservasi
     * terlihat pada data demo.
     *
     * @param  array<int, Barang>  $barang
     * @param  list<Divisi>  $divisi
     * @param  array{operator: User, reviewer: User, divisi: array<string, User>}  $users
     */
    private function seedPengajuanTambahan(
        Sekolah $sekolah,
        string $dana,
        array $barang,
        array $divisi,
        array $users,
    ): void {
        $ditolak = Pengajuan::firstOrNew(['nomor_pengajuan' => self::PENANDA.'-'.$dana.'-DITOLAK']);

        if (! $ditolak->exists) {
            $ditolak->fill([
                'sekolah_id' => $sekolah->id,
                'divisi_id' => $divisi[0]->id,
                'user_id' => $users['divisi'][$divisi[0]->nama]->id,
                'tanggal' => self::TAHUN.'-05-27',
                'status' => 'draft',
            ])->save();

            $ditolak->details()->create([
                'barang_id' => $barang[4]->id,
                'jumlah_diminta' => $this->skala($dana, 25),
                'harga_satuan' => self::BARANG[4][$dana === SumberDana::BOPD ? 4 : 3],
                'keperluan' => 'Kebutuhan kegiatan yang dibatalkan',
            ]);
        }

        $ditolak->load('details');
        if ($ditolak->status === 'draft') {
            $ditolak = app(StokService::class)->submit($ditolak);
        }
        if ($ditolak->status === 'submitted') {
            app(StokService::class)->reject($ditolak, $users['reviewer'], 'Kegiatan ditunda ke semester berikutnya.');
        }

        $menunggu = Pengajuan::firstOrNew(['nomor_pengajuan' => self::PENANDA.'-'.$dana.'-MENUNGGU']);

        if (! $menunggu->exists) {
            $menunggu->fill([
                'sekolah_id' => $sekolah->id,
                'divisi_id' => $divisi[2]->id,
                'user_id' => $users['divisi'][$divisi[2]->nama]->id,
                'tanggal' => self::TAHUN.'-06-28',
                'status' => 'draft',
            ])->save();

            $menunggu->details()->create([
                'barang_id' => $barang[0]->id,
                'jumlah_diminta' => $this->skala($dana, 8),
                'harga_satuan' => self::BARANG[0][$dana === SumberDana::BOPD ? 4 : 3],
                'keperluan' => 'Persiapan tahun ajaran baru',
            ]);
        }

        $menunggu->load('details');
        if ($menunggu->status === 'draft') {
            app(StokService::class)->submit($menunggu);
        }
    }

    /**
     * Dokumen tambahan: BAST/BAP/buku pada pengajuan terakhir bulan Juni, dan
     * SATU Stok Opname per bulan (Januari–Juni) pada pengajuan terakhir bulan
     * itu — tabel dokumen unik per (pengajuan, jenis), jadi tiap bulan perlu
     * pengajuan sendiri sebagai induknya. generateTambahan() idempotent.
     */
    private function seedDokumenTambahan(Sekolah $sekolah, string $dana): void
    {
        $service = app(DokumenService::class);
        $pengajuanBulan = fn (int $bulan): ?Pengajuan => Pengajuan::where('sekolah_id', $sekolah->id)
            ->where('status', 'approved')
            ->whereYear('tanggal', self::TAHUN)
            ->whereMonth('tanggal', $bulan)
            // Tanggal pengajuan sama di satu bulan; urutkan nomor sebagai
            // tiebreak agar pilihan induk dokumen stabil antar-run.
            ->orderByDesc('tanggal')
            ->orderByDesc('nomor_pengajuan')
            ->first();

        $juni = $pengajuanBulan(6);

        if ($juni) {
            foreach (['bast', 'bap', 'buku_pengeluaran', 'buku_penyaluran'] as $jenis) {
                $service->generateTambahan($juni, $jenis, self::TAHUN.'-06-30');
            }
        }

        for ($bulan = 1; $bulan <= 6; $bulan++) {
            // Sudah ada opname bulan ini (mis. data lama) → jangan buat lagi;
            // service memang menolak opname kedua di bulan yang sama.
            $sudahAda = Dokumen::where('jenis', 'stok_opname')
                ->whereYear('tanggal_titimangsa', self::TAHUN)
                ->whereMonth('tanggal_titimangsa', $bulan)
                ->whereHas('pengajuan', fn ($q) => $q->where('sekolah_id', $sekolah->id))
                ->exists();

            if ($sudahAda) {
                continue;
            }

            $pengajuan = $pengajuanBulan($bulan);

            if (! $pengajuan) {
                continue;
            }

            // Tanggal akhir bulan: 31 Jan, 28 Feb 2026 (bukan tahun kabisat), dst.
            $akhirBulan = Carbon::create(self::TAHUN, $bulan, 1)->daysInMonth;

            $service->generateTambahan(
                $pengajuan,
                'stok_opname',
                sprintf('%d-%02d-%02d', self::TAHUN, $bulan, $akhirBulan),
            );
        }
    }

    /** BOPD memakai 40% volume BOS agar proporsional dengan saldo awalnya. */
    private function skala(string $dana, int $jumlah): int
    {
        return $dana === SumberDana::BOPD ? max(1, (int) round($jumlah * 0.4)) : $jumlah;
    }

    private function slug(string $nama): string
    {
        return str_replace(' ', '-', strtolower($nama));
    }
}
