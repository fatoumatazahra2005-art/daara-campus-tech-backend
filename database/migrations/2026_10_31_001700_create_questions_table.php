<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->text('enonce');
            $table->text('explication')->nullable();
            $table->foreignId('type_id')->constrained('code_lists');           // CodeList (TypeQuestion)
            $table->unsignedInteger('points')->default(1);
            $table->unsignedInteger('ordre');
            $table->boolean('actif')->default(true);
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['quiz_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
