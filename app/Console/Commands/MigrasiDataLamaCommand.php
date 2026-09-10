<?php

namespace App\Console\Commands;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Spj;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrasiDataLamaCommand extends Command
{
    protected $signature = 'app:migrasi-data-lama';

    protected $description = 'Migrasi data dari tabel legacy ke skema baru ber-UUID';

    public function handle(): int
    {
        $this->info('Memulai transfer data legacy...');

        // 1. Migrasi Sekolah
        $petaSekolah = []; // id_lama => uuid_baru
        if (DB::getSchemaBuilder()->hasTable('kode_sekolah')) {
            $sekolahLama = DB::table('kode_sekolah')->get();
            foreach ($sekolahLama as $s) {
                $baru = Sekolah::updateOrCreate(
                    ['id_sekolah_lama' => $s->id_sekolah ?? $s->id],
                    [
                        'no_urut' => $s->no_urut ?? null,
                        'nama_sekolah' => $s->nama_sekolah,
                        'kota_kab' => $s->kota_kab ?? '',
                        'kode_sub_pengguna' => $s->kode_sub_pengguna ?? null,
                        'kode_wilayah' => $s->kode_wilayah ?? null,
                    ]
                );
                $petaSekolah[$s->id_sekolah ?? $s->id] = $baru->id;
                $petaSekolah[$s->id] = $baru->id;
            }
            $this->info('Sekolah dimigrasi: '.count($sekolahLama));
        }

        // 2. Migrasi User
        if (DB::getSchemaBuilder()->hasTable('legacy_users') || DB::getSchemaBuilder()->hasTable('users')) {
            $tableUser = DB::getSchemaBuilder()->hasTable('legacy_users') ? 'legacy_users' : null;
            if ($tableUser) {
                $usersLama = DB::table($tableUser)->get();
                foreach ($usersLama as $u) {
                    $sekolahUuid = $petaSekolah[$u->id_sekolah] ?? null;
                    $userBaru = User::updateOrCreate(
                        ['username' => $u->username],
                        [
                            'name' => $u->nama_sekolah ?? $u->username,
                            'email' => strtolower($u->username).'@kcd10.local',
                            'password' => $u->password,
                            'sekolah_id' => $sekolahUuid,
                            'is_active' => true,
                        ]
                    );
                    $roleName = ($u->role === 'admin') ? 'admin_kcd' : 'operator_sekolah';
                    $userBaru->assignRole($roleName);
                }
                $this->info('Users dimigrasi: '.count($usersLama));
            }
        }

        // 3. Migrasi Acuan
        if (DB::getSchemaBuilder()->hasTable('data_barang_acuan')) {
            $acuanLama = DB::table('data_barang_acuan')->get();
            foreach ($acuanLama as $a) {
                $sekolahUuid = $petaSekolah[$a->id_sekolah ?? ''] ?? null;
                Acuan::updateOrCreate(
                    ['id_acuan_lama' => $a->id],
                    [
                        'sekolah_id' => $sekolahUuid,
                        'satuan_pendidikan' => $a->satuan_pendidikan ?? null,
                        'npsn' => $a->npsn ?? null,
                        'tanggal' => ! empty($a->tanggal) ? $a->tanggal : null,
                        'kodering' => $a->kodering ?? null,
                        'bku' => $a->bku ?? null,
                        'uraian' => $a->uraian ?? '-',
                        'nominal' => (float) ($a->nominal ?? 0),
                        'bulan' => (string) ($a->bulan ?? date('n')),
                    ]
                );
            }
            $this->info('Acuan dimigrasi: '.count($acuanLama));
        }

        // 4. Migrasi SPJ
        if (DB::getSchemaBuilder()->hasTable('master_barang_sekolah')) {
            $spjLama = DB::table('master_barang_sekolah')->get();
            foreach ($spjLama as $sp) {
                $sekolahUuid = $petaSekolah[$sp->id_sekolah ?? ''] ?? null;
                $vol = (float) ($sp->volume ?? 1);
                $hrg = (float) ($sp->harga_satuan ?? 0);
                Spj::updateOrCreate(
                    ['id_spj_lama' => $sp->id],
                    [
                        'sekolah_id' => $sekolahUuid,
                        'no_sp2d' => $sp->no_sp2d ?? null,
                        'sumber_perolehan' => $sp->sumber_perolehan ?? null,
                        'bulan_realisasi' => (int) ($sp->bulan_realisasi ?? date('n')),
                        'no_spk' => $sp->no_spk ?? '-',
                        'ba_no' => $sp->ba_no ?? null,
                        'ba_tgl' => ! empty($sp->ba_tgl) ? $sp->ba_tgl : null,
                        'kode_barang' => $sp->kode_barang ?? '-',
                        'nama_barang' => $sp->nama_barang ?? '-',
                        'jenis_aset' => $sp->jenis_aset ?? 'Peralatan dan Mesin',
                        'merk_tipe' => $sp->merk_tipe ?? null,
                        'satuan' => $sp->satuan ?? 'Unit',
                        'volume' => $vol,
                        'harga_satuan' => $hrg,
                        'nilai_perolehan' => (float) ($sp->nilai_perolehan ?? ($vol * $hrg)),
                    ]
                );
            }
            $this->info('SPJ dimigrasi: '.count($spjLama));
        }

        // 5. Migrasi Kunci Laporan
        if (DB::getSchemaBuilder()->hasTable('realisasi_lock')) {
            $lockLama = DB::table('realisasi_lock')->get();
            foreach ($lockLama as $l) {
                $sekolahUuid = $petaSekolah[$l->id_sekolah ?? ''] ?? null;
                if ($sekolahUuid) {
                    KunciLaporan::updateOrCreate(
                        [
                            'sekolah_id' => $sekolahUuid,
                            'bulan' => (string) $l->bulan,
                        ],
                        [
                            'status_kunci' => (bool) ($l->is_lock ?? false),
                            'dikunci_pada' => ! empty($l->is_lock) ? now() : null,
                        ]
                    );
                }
            }
            $this->info('Status Kunci dimigrasi: '.count($lockLama));
        }

        $this->info('Migrasi data selesai.');

        return Command::SUCCESS;
    }
}
