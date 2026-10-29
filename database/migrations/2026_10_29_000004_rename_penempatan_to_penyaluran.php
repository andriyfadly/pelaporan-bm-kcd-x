<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Istilah "penempatan" diganti "penyaluran" agar sama dengan aplikasi
     * acuan: penyaluran adalah penempatan pertama barang ke ruang/penerima,
     * sedangkan mutasi hanya perpindahan setelah barang disalurkan.
     */
    public function up(): void
    {
        DB::table('inventaris_mutasi')->where('jenis', 'penempatan')->update(['jenis' => 'penyaluran']);
    }

    public function down(): void
    {
        DB::table('inventaris_mutasi')->where('jenis', 'penyaluran')->update(['jenis' => 'penempatan']);
    }
};
