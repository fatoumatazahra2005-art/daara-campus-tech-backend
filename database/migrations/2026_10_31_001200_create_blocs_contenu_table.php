<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocs_contenu', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('ordre');
            $table->string('type_bloc', 20);                                   // TEXTE, IMAGE, CODE
            $table->foreignId('chapitre_id')->constrained('chapitres')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['chapitre_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocs_contenu');
    }
};
