<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    foreach (['realisasi_barang_sekolah', 'master_barang_sekolah'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('master_barang_sekolah', function (Blueprint $table): void {
        $table->id();
        $table->string('id_sekolah');
        $table->unsignedTinyInteger('bulan_realisasi');
    });
    Schema::create('realisasi_barang_sekolah', function (Blueprint $table): void {
        $table->id();
        $table->string('id_sekolah');
        $table->unsignedInteger('id_master_barang')->nullable();
        $table->unsignedTinyInteger('bulan_realisasi');
        $table->decimal('volume', 10, 2);
        $table->decimal('harga_satuan', 15, 2);
        $table->decimal('nilai_perolehan', 15, 2);
    });
});

it('fails reconciliation when a period has a cross-school master reference or money mismatch', function (): void {
    $masterId = DB::table('master_barang_sekolah')->insertGetId([
        'id_sekolah' => '2',
        'bulan_realisasi' => 6,
    ]);
    DB::table('realisasi_barang_sekolah')->insert([
        'id_sekolah' => '1',
        'id_master_barang' => $masterId,
        'bulan_realisasi' => 6,
        'volume' => 2,
        'harga_satuan' => 100,
        'nilai_perolehan' => 199,
    ]);

    $this->artisan('report:reconcile-period', ['bulan' => 6])
        ->expectsOutputToContain('cross-school master references: 1')
        ->expectsOutputToContain('money mismatches: 1')
        ->assertFailed();
});

it('passes reconciliation when a period has matching ownership and money', function (): void {
    $masterId = DB::table('master_barang_sekolah')->insertGetId([
        'id_sekolah' => '1',
        'bulan_realisasi' => 6,
    ]);
    DB::table('realisasi_barang_sekolah')->insert([
        'id_sekolah' => '1',
        'id_master_barang' => $masterId,
        'bulan_realisasi' => 6,
        'volume' => 2,
        'harga_satuan' => 100,
        'nilai_perolehan' => 200,
    ]);

    $this->artisan('report:reconcile-period', ['bulan' => 6])
        ->expectsOutputToContain('Reconciliation passed.')
        ->assertSuccessful();
});
