<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_data_jabatan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('master_data_sekolah')->restrictOnDelete();
            $table->string('kode', 50);
            $table->string('nama', 150);
            $table->string('kategori', 50)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['sekolah_id', 'kode']);
        });

        Schema::create('master_data_penugasan_pegawai', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('master_data_sekolah')->restrictOnDelete();
            $table->foreignUuid('pegawai_id')->constrained('master_data_pegawai')->restrictOnDelete();
            $table->foreignUuid('jabatan_id')->constrained('master_data_jabatan')->restrictOnDelete();
            $table->string('jenis_penugasan', 10)->default('definitif');
            $table->date('mulai_menjabat');
            $table->date('selesai_menjabat')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sekolah_id', 'jabatan_id', 'mulai_menjabat']);
            $table->index(['sekolah_id', 'pegawai_id']);
            $table->index(['sekolah_id', 'selesai_menjabat']);
        });

        // Satu jabatan hanya satu pemegang efektif. PLT/PLH menutup penugasan
        // sebelumnya, jadi tidak ada periode yang beririsan.
        DB::statement('CREATE UNIQUE INDEX master_data_penugasan_satu_aktif ON master_data_penugasan_pegawai (sekolah_id, jabatan_id) WHERE selesai_menjabat IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS master_data_penugasan_satu_aktif');
        Schema::dropIfExists('master_data_penugasan_pegawai');
        Schema::dropIfExists('master_data_jabatan');
    }
};
