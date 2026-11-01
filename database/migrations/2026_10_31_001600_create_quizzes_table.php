<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 150);
            $table->unsignedInteger('duree_limite')->nullable();               // minutes ; null = illimité
            $table->foreignId('mode_id')->constrained('code_lists');           // CodeList (ModeQuiz)
            $table->decimal('seuil_reussite', 5, 2)->default(50);
            $table->unsignedInteger('xp_bonus')->default(0);
            $table->boolean('actif')->default(true);
            $table->date('date_creation');
            $table->foreignId('chapitre_id')->unique()->constrained('chapitres')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
