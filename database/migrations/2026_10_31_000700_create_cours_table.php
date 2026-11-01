<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cours', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 150);
            $table->text('description')->nullable();
            $table->foreignId('niveau_id')->constrained('code_lists');         // CodeList (NiveauApprenant)
            $table->string('langue', 10)->default('fr');
            $table->boolean('est_certifiant')->default(false);
            $table->foreignId('statut_id')->constrained('code_lists');         // CodeList (StatutCours), défaut BROUILLON
            $table->text('motif_refus')->nullable();
            $table->date('date_creation');
            $table->date('date_publication')->nullable();
            $table->foreignId('auteur_id')->constrained('encadreurs');
            $table->foreignId('technologie_id')->constrained('technologies');
            $table->foreignId('valide_par_id')->nullable()->constrained('administrateurs');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cours');
    }
};
