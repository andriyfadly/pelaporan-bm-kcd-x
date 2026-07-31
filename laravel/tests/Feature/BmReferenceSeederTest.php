<?php

use Database\Seeders\BmReferenceSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    foreach (['data_barang_acuan', 'kode_sekolah'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('kode_sekolah', function (Blueprint $table): void {
        $table->id();
        $table->unsignedInteger('no_urut');
        $table->string('nama_sekolah');
        $table->string('kota_kab');
        $table->string('kode_sub_pengguna');
        $table->string('kode_wilayah');
        $table->unsignedInteger('id_sekolah')->nullable();
        $table->uuid('public_id')->nullable()->unique();
    });
    Schema::create('data_barang_acuan', function (Blueprint $table): void {
        $table->id();
        $table->string('id_sekolah');
        $table->string('satuan_pendidikan')->nullable();
        $table->string('npsn')->nullable();
        $table->date('tanggal')->nullable();
        $table->string('kodering')->nullable();
        $table->string('bku')->nullable();
        $table->text('uraian')->nullable();
        $table->decimal('nominal', 15, 2)->default(0);
        $table->string('bulan')->nullable();
        $table->uuid('public_id')->nullable()->unique();
    });
});

it('seeds BM school and acuan baseline idempotently without users', function (): void {
    $this->seed(BmReferenceSeeder::class);
    $this->seed(BmReferenceSeeder::class);

    expect(DB::table('kode_sekolah')->count())->toBe(100)
        ->and(DB::table('data_barang_acuan')->count())->toBe(300)
        ->and(DB::table('data_barang_acuan')->sum('nominal'))->toBe('150000000.00')
        ->and(DB::table('kode_sekolah')->where('id_sekolah', 100)->value('nama_sekolah'))->toBe('SMAN 100 Cirebon');
});
