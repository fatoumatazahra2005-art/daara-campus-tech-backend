<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tentatives_quiz', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date_debut');
            $table->dateTime('date_fin')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->foreignId('statut_id')->constrained('code_lists');         // CodeList (StatutTentative), défaut EN_COURS
            $table->foreignId('apprenant_id')->constrained('apprenants');
            $table->foreignId('quiz_id')->constrained('quizzes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tentatives_quiz');
    }
};
