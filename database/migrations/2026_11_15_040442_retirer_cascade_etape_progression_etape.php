<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une progression d'étape appartient à l'inscription (composition : cascade conservée).
 * Le lien vers l'étape est une simple association : une étape qui a des progressions
 * ne peut pas être supprimée (règle 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progression_etape_projet_guides', function (Blueprint $table) {
            $table->dropForeign(['etape_projet_guide_id']);

            $table->foreign('etape_projet_guide_id')
                ->references('id')
                ->on('etapes_projet_guide')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('progression_etape_projet_guides', function (Blueprint $table) {
            $table->dropForeign(['etape_projet_guide_id']);

            $table->foreign('etape_projet_guide_id')
                ->references('id')
                ->on('etapes_projet_guide')
                ->cascadeOnDelete();
        });
    }
};
