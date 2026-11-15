<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cours', function (Blueprint $table) {
            $table->foreignId('langue_id')
                ->nullable()
                ->constrained('code_lists');
        });

        $langues = DB::table('code_lists')
            ->where('type', 'Langue')
            ->pluck('id', 'value');

        $cours = DB::table('cours')->get(['id', 'langue']);

        foreach ($cours as $coursExistant) {
            $valeur = strtoupper(trim($coursExistant->langue));

            if (!isset($langues[$valeur])) {
                throw new RuntimeException(
                    "Langue inconnue pour le cours ID {$coursExistant->id} : {$coursExistant->langue}"
                );
            }

            DB::table('cours')
                ->where('id', $coursExistant->id)
                ->update([
                    'langue_id' => $langues[$valeur]
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('cours', function (Blueprint $table) {
            $table->dropForeign(['langue_id']);
            $table->dropColumn('langue_id');
        });
    }
};
