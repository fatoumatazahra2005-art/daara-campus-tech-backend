<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cours', function (Blueprint $table) {
            $table->dropColumn('langue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cours', function (Blueprint $table) {
            $table->string('langue', 10)->nullable();
        });
        DB::statement("
            UPDATE cours
            SET langue = LOWER(code_lists.value)
            FROM code_lists
            WHERE cours.langue_id = code_lists.id
        ");
    }
};
