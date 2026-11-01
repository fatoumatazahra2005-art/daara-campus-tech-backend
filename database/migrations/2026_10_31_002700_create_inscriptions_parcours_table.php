<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscriptions_parcours', function (Blueprint $table) {
            $table->id();
            $table->date('date_inscription');
            $table->decimal('progression', 5, 2)->default(0);
            $table->foreignId('statut_id')->constrained('code_lists');         // CodeList (StatutInscription), défaut EN_COURS
            $table->foreignId('apprenant_id')->constrained('apprenants');
            $table->foreignId('parcours_id')->constrained('parcours');
            $table->timestamps();

            $table->unique(['apprenant_id', 'parcours_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions_parcours');
    }
};
