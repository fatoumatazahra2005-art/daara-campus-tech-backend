<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX technologies_nom_unique_ci ON technologies (LOWER(TRIM(nom)))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS technologies_nom_unique_ci');
    }
};
