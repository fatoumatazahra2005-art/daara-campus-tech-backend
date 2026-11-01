<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscription_parcours_cours', function (Blueprint $table) {
            $table->foreignId('inscription_parcours_id')->constrained('inscriptions_parcours')->cascadeOnDelete();
            $table->foreignId('inscription_cours_id')->constrained('inscriptions_cours');
            $table->timestamps();

            $table->primary(['inscription_parcours_id', 'inscription_cours_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscription_parcours_cours');
    }
};
