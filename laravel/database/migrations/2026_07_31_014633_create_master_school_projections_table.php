<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('master_school_projections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('npsn', 20)->unique();
            $table->string('name', 150);
            $table->string('education_level', 50)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('region_code', 20)->nullable();
            $table->boolean('is_active');
            $table->timestamp('source_updated_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_school_projections');
    }
};
