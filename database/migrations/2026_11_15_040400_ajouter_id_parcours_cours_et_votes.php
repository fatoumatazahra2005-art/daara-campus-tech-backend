<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ParcoursCours et Vote : une seule clé primaire (id), le couple devient unique,
 * comme Signalement. Les lignes existantes reçoivent un id automatiquement.
 */
return new class extends Migration
{
    private array $tables = [
        'parcours_cours' => ['parcours_id', 'cours_id'],
        'votes' => ['utilisateur_id', 'message_id'],
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $couple) {
            // 1. Retirer l'ancienne clé primaire composée.
            Schema::table($table, function (Blueprint $t) {
                $t->dropPrimary();
            });

            // 2. Nouvelle clé primaire id + couple unique.
            Schema::table($table, function (Blueprint $t) use ($couple) {
                $t->id();
                $t->unique($couple);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $couple) {
            Schema::table($table, function (Blueprint $t) use ($couple) {
                $t->dropUnique($couple);
                $t->dropColumn('id');
            });

            Schema::table($table, function (Blueprint $t) use ($couple) {
                $t->primary($couple);
            });
        }
    }
};
