<?php

use App\Models\DataBarangAcuan;
use App\Models\KodeBarang;
use App\Models\LaporanRealisasi;
use App\Models\MasterBarangSekolah;
use App\Models\RealisasiBarangSekolah;
use App\Models\Sekolah;

it('maps models to legacy tables and database domains', function (): void {
    expect((new Sekolah)->getTable())->toBe('kode_sekolah')
        ->and((new DataBarangAcuan)->getTable())->toBe('data_barang_acuan')
        ->and((new LaporanRealisasi)->getTable())->toBe('laporan_realisasi')
        ->and((new MasterBarangSekolah)->getTable())->toBe('master_barang_sekolah')
        ->and((new RealisasiBarangSekolah)->getTable())->toBe('realisasi_barang_sekolah')
        ->and((new KodeBarang)->getTable())->toBe('kode_barang')
        ->and((new KodeBarang)->getConnectionName())->toBe('inventory');
});

it('keeps ownership and public identifiers outside mass assignment', function (): void {
    $model = new MasterBarangSekolah;

    $model->fill(['nama_barang' => 'Laptop']);

    expect($model->isFillable('id'))->toBeFalse()
        ->and($model->isFillable('public_id'))->toBeFalse()
        ->and($model->isFillable('id_sekolah'))->toBeFalse()
        ->and($model->getAttribute('nama_barang'))->toBe('Laptop');
});

it('uses public ids for route model binding', function (): void {
    expect((new Sekolah)->getRouteKeyName())->toBe('public_id')
        ->and((new DataBarangAcuan)->getRouteKeyName())->toBe('public_id')
        ->and((new LaporanRealisasi)->getRouteKeyName())->toBe('public_id')
        ->and((new MasterBarangSekolah)->getRouteKeyName())->toBe('public_id')
        ->and((new RealisasiBarangSekolah)->getRouteKeyName())->toBe('public_id');
});
