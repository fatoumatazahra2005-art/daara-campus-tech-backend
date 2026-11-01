<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telechargements', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date_telechargement');
            $table->dateTime('date_derniere_verification');
            $table->foreignId('chapitre_id')->constrained('chapitres');
            $table->foreignId('abonnement_id')->constrained('abonnements');
            $table->timestamps();

            // Un chapitre ne décompte un crédit qu'une seule fois par abonnement
            $table->unique(['abonnement_id', 'chapitre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telechargements');
    }
};
