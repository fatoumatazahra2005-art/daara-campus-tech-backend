<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projets', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 150);
            $table->text('consigne');
            $table->unsignedInteger('delai_jours');
            $table->decimal('note_minimale', 4, 2)->default(10);
            $table->foreignId('parcours_id')->constrained('parcours');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projets');
    }
};
