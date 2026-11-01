<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statut_id')->constrained('code_lists');         // CodeList (StatutAbonnement), défaut EN_ATTENTE_PAIEMENT
            $table->unsignedInteger('credits_hors_ligne')->default(0);
            $table->date('date_resiliation')->nullable();
            $table->foreignId('apprenant_id')->constrained('apprenants');
            $table->foreignId('formule_id')->constrained('formules_abonnement');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnements');
    }
};
