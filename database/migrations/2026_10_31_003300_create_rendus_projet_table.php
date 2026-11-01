<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendus_projet', function (Blueprint $table) {
            $table->id();
            $table->date('date_attribution');
            $table->date('date_limite');
            $table->dateTime('date_rendu')->nullable();
            $table->string('lien_github', 255)->nullable();
            $table->foreignId('statut_id')->constrained('code_lists');         // CodeList (StatutRendu), défaut A_FAIRE
            $table->decimal('note', 4, 2)->nullable();
            $table->text('commentaire')->nullable();
            $table->foreignId('projet_id')->constrained('projets');
            $table->foreignId('apprenant_id')->constrained('apprenants');      // rendPar
            $table->foreignId('correcteur_id')->nullable()->constrained('encadreurs'); // corrigePar
            $table->timestamps();

            $table->unique(['apprenant_id', 'projet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendus_projet');
    }
};
