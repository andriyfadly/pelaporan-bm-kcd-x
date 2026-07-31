<?php

use App\Models\MasterBarangSekolah;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('master_barang_sekolah');
    Schema::create('master_barang_sekolah', function (Blueprint $table): void {
        $table->id();
        $table->string('id_sekolah');
        $table->string('nama_barang');
    });
});

it('scopes records to the authenticated school identifier', function (): void {
    MasterBarangSekolah::query()->insert([
        ['id_sekolah' => '1', 'nama_barang' => 'Barang Sekolah A'],
        ['id_sekolah' => '2', 'nama_barang' => 'Barang Sekolah B'],
    ]);

    $records = MasterBarangSekolah::query()->forSchool(1)->get();

    expect($records)->toHaveCount(1)
        ->and($records->first()->nama_barang)->toBe('Barang Sekolah A')
        ->and($records->pluck('nama_barang'))->not->toContain('Barang Sekolah B');
});
