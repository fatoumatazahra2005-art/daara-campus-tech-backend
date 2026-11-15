<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cours', function (Blueprint $table) {
            // Cours publié modifié par l'encadreur depuis la dernière relecture de l'administrateur.
            $table->boolean('a_relire')->default(false);
            $table->timestamp('modifie_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cours', function (Blueprint $table) {
            $table->dropColumn(['a_relire', 'modifie_le']);
        });
    }
};
