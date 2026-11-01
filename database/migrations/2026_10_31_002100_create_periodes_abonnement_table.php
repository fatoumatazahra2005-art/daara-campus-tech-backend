<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodes_abonnement', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->unsignedInteger('prix_paye');                              // FCFA ; 0 pour un essai
            $table->unsignedInteger('nb_echeances')->default(1);
            $table->boolean('est_essai')->default(false);
            $table->foreignId('abonnement_id')->constrained('abonnements')->cascadeOnDelete();
            $table->foreignId('formule_id')->constrained('formules_abonnement');
            $table->timestamps();

            $table->unique(['abonnement_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodes_abonnement');
    }
};
