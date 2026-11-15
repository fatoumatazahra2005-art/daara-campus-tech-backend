<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppression logique (règle 3 et règle 29) des projets de parcours, des projets guidés et de leurs étapes :
 * un élément déjà utilisé par des apprenants est désactivé au lieu d'être supprimé.
 */
return new class extends Migration
{
    // Adapter ces noms au résultat de la commande de vérification.
    private array $tables = ['projets', 'projets_guides', 'etapes_projet_guide'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('actif')->default(true);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('actif');
            });
        }
    }
};
