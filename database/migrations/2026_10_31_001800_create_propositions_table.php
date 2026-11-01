<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propositions', function (Blueprint $table) {
            $table->id();
            $table->string('texte', 500);
            $table->boolean('est_correcte')->default(false);
            $table->unsignedInteger('ordre');
            $table->boolean('actif')->default(true);
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['question_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propositions');
    }
};
