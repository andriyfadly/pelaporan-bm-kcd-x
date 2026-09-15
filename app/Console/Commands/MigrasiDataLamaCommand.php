<?php

namespace App\Console\Commands;

use App\Models\Master\KodeBarang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PelaporanBm\Spj;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MigrasiDataLamaCommand extends Command
{
    protected $signature = 'app:migrasi-data-lama';

    protected $description = 'Migrasi semua tabel dan data dari storage/bm-kcd-x.sql ke database';

    public function handle(): int
    {
        $sqlPath = storage_path('bm-kcd-x.sql');
        if (! File::exists($sqlPath)) {
            $sqlPath = storage_path('bm_kcd_x.sql');
        }
        if (! File::exists($sqlPath)) {
            $this->error('File dump SQL (storage/bm-kcd-x.sql atau storage/bm_kcd_x.sql) tidak ditemukan.');

            return Command::FAILURE;
        }

        $this->info("Membaca dan memproses {$sqlPath}...");
        $sql = File::get($sqlPath);

        // 1. Migrasi Sekolah
        $petaSekolah = []; // id_lama => uuid_baru
        $sekolahLama = $this->parseTable($sql, 'kode_sekolah');
        foreach ($sekolahLama as $s) {
            $idLama = (int) ($s['id_sekolah'] ?? $s['id']);
            $baru = Sekolah::updateOrCreate(
                ['id_sekolah_lama' => $idLama],
                [
                    'no_urut' => isset($s['no_urut']) ? (int) $s['no_urut'] : null,
                    'nama_sekolah' => $s['nama_sekolah'],
                    'kota_kab' => $s['kota_kab'] ?? '',
                    'kode_sub_pengguna' => $s['kode_sub_pengguna'] ?? null,
                    'kode_wilayah' => $s['kode_wilayah'] ?? null,
                ]
            );
            $petaSekolah[$idLama] = $baru->id;
            $petaSekolah[(int) $s['id']] = $baru->id;
            $petaSekolah[(string) $s['id']] = $baru->id;
        }
        $this->info('Sekolah dimigrasi: '.count($sekolahLama));

        // 3. Migrasi Acuan
        $petaAcuan = []; // id_lama => uuid_baru
        $acuanLama = $this->parseTable($sql, 'data_barang_acuan');
        foreach ($acuanLama as $a) {
            $sekolahUuid = $petaSekolah[(int) ($a['id_sekolah'] ?? 0)] ?? null;
            $baru = Acuan::updateOrCreate(
                ['id_acuan_lama' => (int) $a['id']],
                [
                    'sekolah_id' => $sekolahUuid,
                    'tanggal' => ! empty($a['tanggal']) ? $a['tanggal'] : null,
                    'kodering' => $a['kodering'] ?? null,
                    'bku' => $a['bku'] ?? null,
                    'uraian' => $a['uraian'] ?? '-',
                    'nominal' => (float) ($a['nominal'] ?? 0),
                    'bulan' => (string) ($a['bulan'] ?? date('n')),
                ]
            );
            if ($sekolahUuid && ! empty($a['npsn'])) {
                Sekolah::where('id', $sekolahUuid)->whereNull('npsn')->update(['npsn' => trim($a['npsn'])]);
            }
            $petaAcuan[(int) $a['id']] = $baru->id;
        }
        $this->info('Acuan dimigrasi: '.count($acuanLama));

        // 4. Migrasi SPJ (Master Barang Sekolah)
        $petaSpj = []; // id_lama => uuid_baru
        $spjLama = $this->parseTable($sql, 'master_barang_sekolah');
        foreach ($spjLama as $sp) {
            $sekolahUuid = $petaSekolah[(int) ($sp['id_sekolah'] ?? 0)] ?? null;
            $acuanUuid = $petaAcuan[(int) ($sp['id_uraian'] ?? 0)] ?? null;
            $vol = (float) ($sp['volume'] ?? 1);
            $hrg = (float) ($sp['harga_satuan'] ?? 0);
            $baru = Spj::updateOrCreate(
                ['id_spj_lama' => (int) $sp['id']],
                [
                    'sekolah_id' => $sekolahUuid,
                    'acuan_id' => $acuanUuid,
                    'no_sp2d' => $sp['no_sp2d'] ?? null,
                    'sumber_perolehan' => $sp['sumber_perolehan'] ?? null,
                    'bulan_realisasi' => (int) ($sp['bulan_realisasi'] ?? date('n')),
                    'no_spk' => $sp['no_spk'] ?? '-',
                    'ba_no' => $sp['ba_no'] ?? null,
                    'ba_tgl' => ! empty($sp['ba_tgl']) ? $sp['ba_tgl'] : null,
                    'kode_barang' => $sp['kode_barang'] ?? '-',
                    'nama_barang' => $sp['nama_barang'] ?? '-',
                    'jenis_aset' => $sp['jenis_aset'] ?? 'Peralatan dan Mesin',
                    'merk_tipe' => $sp['merk_tipe'] ?? null,
                    'satuan' => $sp['satuan'] ?? 'Unit',
                    'volume' => $vol,
                    'harga_satuan' => $hrg,
                    'nilai_perolehan' => (float) ($sp['nilai_perolehan'] ?? ($vol * $hrg)),
                ]
            );
            $petaSpj[(int) $sp['id']] = $baru->id;
        }
        $this->info('SPJ dimigrasi: '.count($spjLama));

        // 5. Migrasi Realisasi
        $realisasiLama = $this->parseTable($sql, 'realisasi_barang_sekolah');
        foreach ($realisasiLama as $r) {
            $sekolahUuid = $petaSekolah[(int) ($r['id_sekolah'] ?? 0)] ?? null;
            $spjUuid = $petaSpj[(int) ($r['id_master_barang'] ?? 0)] ?? null;
            $acuanUuid = $petaAcuan[(int) ($r['id_uraian'] ?? 0)] ?? null;
            $vol = (float) ($r['volume'] ?? 0);
            $hrg = (float) ($r['harga_satuan'] ?? 0);
            Realisasi::updateOrCreate(
                ['id_realisasi_lama' => (int) $r['id']],
                [
                    'sekolah_id' => $sekolahUuid,
                    'spj_id' => $spjUuid,
                    'acuan_id' => $acuanUuid,
                    'no_sp2d' => $r['no_sp2d'] ?? null,
                    'sumber_perolehan' => $r['sumber_perolehan'] ?? null,
                    'kodering_belanja' => $r['kodering_belanja'] ?? null,
                    'bulan_realisasi' => (string) ($r['bulan_realisasi'] ?? date('n')),
                    'no_spk' => $r['no_spk'] ?? '-',
                    'ba_no' => $r['ba_no'] ?? null,
                    'ba_tgl' => ! empty($r['ba_tgl']) ? $r['ba_tgl'] : null,
                    'kode_barang' => $r['kode_barang'] ?? '-',
                    'nama_barang' => $r['nama_barang'] ?? '-',
                    'jenis_aset' => $r['jenis_aset'] ?? 'Peralatan dan Mesin',
                    'merk_tipe' => $r['merk_tipe'] ?? null,
                    'no_sertifikat' => $r['no_sertifikat'] ?? '-',
                    'ukuran_bangunan' => $r['ukuran_bangunan'] ?? '-',
                    'satuan' => $r['satuan'] ?? 'Unit',
                    'volume' => $vol,
                    'harga_satuan' => $hrg,
                    'nilai_perolehan' => (float) ($r['nilai_perolehan'] ?? ($vol * $hrg)),
                    'is_realisasi' => (bool) ($r['is_realisasi'] ?? true),
                ]
            );
        }
        $this->info('Realisasi dimigrasi: '.count($realisasiLama));

        // 6. Migrasi Status Laporan Realisasi
        $laporanLama = $this->parseTable($sql, 'laporan_realisasi');
        foreach ($laporanLama as $l) {
            $sekolahUuid = $petaSekolah[(int) ($l['id_sekolah'] ?? 0)] ?? null;
            if ($sekolahUuid) {
                $isLocked = in_array(strtolower((string) $l['status']), ['disetujui', 'menunggu approval']);
                KunciLaporan::updateOrCreate(
                    [
                        'sekolah_id' => $sekolahUuid,
                        'bulan' => (string) $l['bulan'],
                    ],
                    [
                        'status_kunci' => $isLocked,
                        'dikunci_pada' => $isLocked ? now() : null,
                    ]
                );
            }
        }
        $this->info('Status Laporan dimigrasi: '.count($laporanLama));

        // 7. Migrasi Katalog Kode Barang dari DB legacy MySQL (bukan dari dump SQL,
        //    karena bm_kcd_x.sql hanya memuat DB transaksional belanja_modal)
        $this->migrasiKodeBarang();
        $this->info('Semua data dari storage/bm-kcd-x.sql berhasil dimigrasikan!');

        return Command::SUCCESS;
    }

    private function migrasiKodeBarang(): void
    {
        $sqlPath = storage_path('db_inventaris.sql');
        if (! File::exists($sqlPath)) {
            $this->warn('File storage/db_inventaris.sql tidak ditemukan, katalog kode barang dilewati.');

            return;
        }

        $this->info('Membaca katalog kode barang dari db_inventaris.sql...');
        $sql = File::get($sqlPath);

        // Kode barang di-dump dalam 27 batch INSERT terpisah; parse semuanya.
        // Kolom: id, kode_barang, uraian, kodering_aset, jenis_aset, umur_ekonomis
        preg_match_all("/INSERT INTO `kode_barang`[^V]+VALUES\s*(.*?);\s*(?:\n|$)/s", $sql, $blokMatches);
        if (empty($blokMatches[1])) {
            $this->warn('Tabel kode_barang tidak ditemukan di db_inventaris.sql.');

            return;
        }

        $payload = [];
        $dilihat = [];
        foreach ($blokMatches[1] as $blok) {
            preg_match_all("/\((\d+),\s*'([^']*)',\s*'((?:[^'\\\\]|\\\\.)*)',\s*'([^']*)',\s*'([^']*)',\s*(\d+)\)/", $blok, $rows, PREG_SET_ORDER);

            foreach ($rows as $r) {
                $kode = trim($r[2]);
                if ($kode === '' || str_starts_with($kode, '#') || isset($dilihat[$kode])) {
                    continue;
                }
                $dilihat[$kode] = true;

                $payload[] = [
                    'kode_barang' => $kode,
                    'uraian' => trim($r[3]) !== '' ? trim($r[3]) : '-',
                    'kodering_aset' => trim($r[4]) !== '' ? trim($r[4]) : null,
                    'jenis_aset' => trim($r[5]) !== '' ? trim($r[5]) : null,
                    'umur_ekonomis' => (int) $r[6],
                    'satuan' => null,
                ];
            }
        }

        foreach (array_chunk($payload, 500) as $chunk) {
            KodeBarang::upsert(
                $chunk,
                ['kode_barang'],
                ['uraian', 'kodering_aset', 'jenis_aset', 'umur_ekonomis', 'satuan']
            );
        }

        $this->info('Katalog Kode Barang dimigrasi: '.KodeBarang::count());
    }

    /**
     * @return array<int, array<string, ?string>>
     */
    private function parseTable(string $sql, string $tableName): array
    {
        if (! preg_match("/INSERT INTO `{$tableName}`\s*\(([^)]+)\)\s*VALUES\s*(.+?);/s", $sql, $m)) {
            return [];
        }
        $cols = array_map(fn ($c) => trim(trim((string) $c), '`'), explode(',', $m[1]));
        preg_match_all("/\((.*?)\)(?:,\n|\n|,|$)/s", $m[2], $valMatches);
        $rows = [];
        foreach ($valMatches[1] as $r) {
            $tokens = str_getcsv($r, ',', "'", '\\');
            $item = [];
            foreach ($cols as $idx => $c) {
                $val = isset($tokens[$idx]) ? trim($tokens[$idx]) : null;
                if ($val === 'NULL' || $val === 'null') {
                    $val = null;
                }
                $item[$c] = $val;
            }
            $rows[] = $item;
        }

        return $rows;
    }
}
