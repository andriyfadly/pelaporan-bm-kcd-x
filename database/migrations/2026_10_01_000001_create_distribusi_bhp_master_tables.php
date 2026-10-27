<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bhp_kode_rekening', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 100);
            $table->string('nama', 255);
            $table->string('kode_barang', 100)->nullable();
            $table->string('uraian_kode_barang', 255)->nullable();
            $table->enum('sumber_dana', ['BOS', 'BOPD'])->default('BOS')->index();
            $table->timestamps();

            $table->unique(['kode', 'sumber_dana']);
        });

        Schema::create('bhp_barang', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode_barang', 100)->nullable();
            $table->string('nama_barang', 255);
            $table->string('spesifikasi_barang_nusp', 255)->nullable();
            $table->string('satuan', 50)->nullable();
            $table->foreignUuid('kode_rekening_id')->nullable()->index();
            $table->enum('sumber_dana', ['BOS', 'BOPD'])->default('BOS')->index();
            $table->timestamps();
        });

        Schema::create('bhp_harga_barang', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('barang_id')->index();
            $table->unsignedBigInteger('harga_satuan');
            $table->foreignUuid('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('bhp_penyedia', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama', 255);
            $table->string('alamat', 255)->nullable();
            $table->string('telepon', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->timestamps();

            $table->unique('nama');
        });

        Schema::create('bhp_penerimaan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->nullable()->index();
            $table->string('no_nota_invoice', 150)->nullable();
            $table->string('no_bast', 150)->nullable();
            $table->date('tanggal_bast')->nullable();
            $table->string('no_kwitansi', 150)->nullable();
            $table->foreignUuid('penyedia_id')->nullable()->index();
            $table->date('tanggal_titimangsa')->nullable();
            $table->boolean('is_saldo_awal')->default(false);
            $table->integer('periode_tahun')->nullable();
            $table->enum('sumber_dana', ['BOS', 'BOPD'])->default('BOS')->index();
            $table->timestamps();
        });

        Schema::create('bhp_penerimaan_detail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bhp_penerimaan_id')->index();
            $table->foreignUuid('barang_id')->nullable()->index();
            $table->foreignUuid('harga_barang_id')->nullable();
            $table->integer('jumlah')->default(0);
            $table->unsignedBigInteger('harga_satuan')->nullable();
            $table->unsignedBigInteger('nilai_total')->nullable();
            $table->string('merek_type', 255)->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('bhp_pengajuan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->nullable()->index();
            $table->foreignUuid('divisi_id')->nullable()->index();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('nomor_pengajuan', 150)->nullable();
            $table->date('tanggal')->nullable();
            $table->enum('sumber_dana', ['BOS', 'BOPD'])->default('BOS')->index();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft')->index();
            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bhp_pengajuan_detail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bhp_pengajuan_id')->index();
            $table->foreignUuid('barang_id')->nullable()->index();
            $table->integer('jumlah_diminta');
            $table->integer('jumlah_disetujui')->nullable();
            $table->unsignedBigInteger('harga_satuan')->nullable();
            $table->string('keperluan', 255)->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->string('status_reservasi', 30)->default('tidak_direservasi');
            $table->integer('jumlah_direservasi')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bhp_pengajuan_detail');
        Schema::dropIfExists('bhp_pengajuan');
        Schema::dropIfExists('bhp_penerimaan_detail');
        Schema::dropIfExists('bhp_penerimaan');
        Schema::dropIfExists('bhp_penyedia');
        Schema::dropIfExists('bhp_harga_barang');
        Schema::dropIfExists('bhp_barang');
        Schema::dropIfExists('bhp_kode_rekening');
    }
};
