<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Divisi BHP milik sekolah. Operator sekolah membuat divisi dan akun
     * operator_divisi per divisi; pengajuan BHP terikat ke divisi pengaju.
     */
    public function up(): void
    {
        Schema::create('bhp_divisi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->index();
            $table->string('nama', 150);
            $table->timestamps();

            $table->unique(['sekolah_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bhp_divisi');
    }
};
