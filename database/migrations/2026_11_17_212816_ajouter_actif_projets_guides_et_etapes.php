<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppression logique (règle 3) du projet guidé et de ses étapes :
 * une fois le cours publié, ils sont désactivés au lieu d'être supprimés.
 * Les éléments existants restent actifs (valeur par défaut true).
 */
return new class extends Migration
{
    // À adapter si la commande de vérification affiche d'autres noms.
    private array $tables = ['projets_guides', 'etapes_projet_guide'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'actif')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->boolean('actif')->default(true);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'actif')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('actif');
                });
            }
        }
    }
};
