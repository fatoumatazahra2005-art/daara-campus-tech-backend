<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etapes_projet_guide', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('ordre');
            $table->text('consigne');
            $table->text('code_depart')->nullable();
            $table->text('tests_attendus')->nullable();
            $table->foreignId('projet_guide_id')->constrained('projets_guides')->cascadeOnDelete();
            $table->foreignId('chapitre_id')->constrained('chapitres');
            $table->timestamps();

            $table->unique(['projet_guide_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etapes_projet_guide');
    }
};
