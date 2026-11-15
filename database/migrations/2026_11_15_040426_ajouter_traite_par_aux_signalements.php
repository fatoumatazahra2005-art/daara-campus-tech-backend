<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lien « traitePar » du diagramme : administrateur qui a traité le signalement
 * (vide tant que traite = false).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signalements', function (Blueprint $table) {
            $table->foreignId('traite_par_id')->nullable()->after('traite')->constrained('administrateurs');
        });
    }

    public function down(): void
    {
        Schema::table('signalements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('traite_par_id');
        });
    }
};
