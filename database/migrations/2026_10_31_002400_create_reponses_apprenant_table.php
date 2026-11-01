<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reponses_apprenant', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date_reponse');
            $table->foreignId('tentative_id')->constrained('tentatives_quiz')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions');
            $table->timestamps();

            $table->unique(['tentative_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reponses_apprenant');
    }
};
