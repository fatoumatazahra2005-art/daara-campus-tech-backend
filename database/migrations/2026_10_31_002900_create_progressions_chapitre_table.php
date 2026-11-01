<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progressions_chapitre', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date_debut');
            $table->dateTime('date_fin')->nullable();
            $table->boolean('est_termine')->default(false);
            $table->foreignId('inscription_cours_id')->constrained('inscriptions_cours')->cascadeOnDelete();
            $table->foreignId('chapitre_id')->constrained('chapitres');
            $table->timestamps();

            $table->unique(['inscription_cours_id', 'chapitre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progressions_chapitre');
    }
};
