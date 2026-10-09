<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['bos', 'bopd'] as $dana) {
            $table = "bhp_{$dana}_settings";
            $columns = Schema::getColumnListing($table);
            $drop = array_intersect($columns, ['paper_size', 'orientation']);
            if ($drop) {
                Schema::table($table, fn ($t) => $t->dropColumn($drop));
            }
        }
    }

    public function down(): void
    {
        foreach (['bos', 'bopd'] as $dana) {
            Schema::table("bhp_{$dana}_settings", function ($table) {
                $table->string('paper_size', 10)->default('a4');
                $table->string('orientation', 20)->default('portrait');
            });
        }
    }
};
