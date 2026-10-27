<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventaris_register_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('master_data_sekolah')->restrictOnDelete();
            $table->string('kode_barang', 100);
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['sekolah_id', 'kode_barang']);
        });

        Schema::create('inventaris_unit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('master_data_sekolah')->restrictOnDelete();
            $table->foreignUuid('realisasi_id')->constrained('pelaporan_bm_realisasi')->restrictOnDelete();
            $table->unsignedInteger('unit_index');
            $table->string('kode_barang', 100);
            $table->string('nomor_register', 10);

            // Snapshot sumber realisasi; cetak dokumen lama tidak ikut berubah
            // ketika realisasi dikoreksi.
            $table->string('nama_barang', 255)->nullable();
            $table->string('jenis_aset', 100)->nullable();
            $table->string('merk_tipe', 255)->nullable();
            $table->string('no_sertifikat', 255)->nullable();
            $table->string('ukuran_bangunan', 100)->nullable();
            $table->string('satuan', 50)->nullable();
            $table->date('tanggal_perolehan')->nullable();
            $table->unsignedSmallInteger('bulan_perolehan')->nullable();
            $table->unsignedSmallInteger('tahun_perolehan')->nullable();
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('nilai_perolehan_unit', 15, 2)->default(0);

            $table->string('kondisi', 2)->default('B');
            $table->string('status', 20)->default('aktif');
            $table->foreignUuid('ruang_id')->nullable()->constrained('inventaris_ruang')->nullOnDelete();
            $table->foreignUuid('pegawai_id')->nullable()->constrained('inventaris_pegawai')->nullOnDelete();
            $table->string('foto_path', 255)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['sekolah_id', 'kode_barang', 'nomor_register']);
            $table->unique(['realisasi_id', 'unit_index']);
            $table->index(['sekolah_id', 'kode_barang']);
            $table->index(['sekolah_id', 'status']);
            $table->index(['sekolah_id', 'ruang_id']);
            $table->index(['sekolah_id', 'pegawai_id']);
        });

        Schema::create('inventaris_mutasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('master_data_sekolah')->restrictOnDelete();
            $table->foreignUuid('inventaris_unit_id')->constrained('inventaris_unit')->restrictOnDelete();
            $table->string('jenis', 20);
            $table->foreignUuid('asal_ruang_id')->nullable()->constrained('inventaris_ruang')->nullOnDelete();
            $table->foreignUuid('asal_pegawai_id')->nullable()->constrained('inventaris_pegawai')->nullOnDelete();
            $table->string('asal_kondisi', 2)->nullable();
            $table->foreignUuid('tujuan_ruang_id')->nullable()->constrained('inventaris_ruang')->nullOnDelete();
            $table->foreignUuid('tujuan_pegawai_id')->nullable()->constrained('inventaris_pegawai')->nullOnDelete();
            $table->string('tujuan_kondisi', 2)->nullable();
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sekolah_id', 'inventaris_unit_id']);
            $table->index(['sekolah_id', 'tanggal']);
        });

        Schema::create('inventaris_dokumen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('master_data_sekolah')->restrictOnDelete();
            $table->string('jenis', 50);
            $table->string('nomor', 150)->nullable();
            $table->date('tanggal')->nullable();
            $table->uuid('inventaris_unit_id')->nullable();
            $table->uuid('inventaris_pegawai_id')->nullable();
            $table->uuid('inventaris_ruang_id')->nullable();
            $table->json('payload');
            $table->string('file_path', 255)->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sekolah_id', 'jenis']);
            $table->index(['sekolah_id', 'nomor']);
        });

        Schema::create('inventaris_foto', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('master_data_sekolah')->restrictOnDelete();
            $table->foreignUuid('inventaris_unit_id')->constrained('inventaris_unit')->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64);
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sekolah_id', 'inventaris_unit_id']);
        });

        Schema::create('inventaris_sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->nullable()->constrained('master_data_sekolah')->nullOnDelete();
            $table->foreignUuid('realisasi_id')->nullable()->constrained('pelaporan_bm_realisasi')->nullOnDelete();
            $table->string('status', 20);
            $table->unsignedInteger('dibuat')->default(0);
            $table->unsignedInteger('tetap')->default(0);
            $table->unsignedInteger('surplus')->default(0);
            $table->unsignedInteger('perlu_review')->default(0);
            $table->text('error')->nullable();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventaris_sync_logs');
        Schema::dropIfExists('inventaris_foto');
        Schema::dropIfExists('inventaris_dokumen');
        Schema::dropIfExists('inventaris_mutasi');
        Schema::dropIfExists('inventaris_unit');
        Schema::dropIfExists('inventaris_register_sequences');
    }
};
