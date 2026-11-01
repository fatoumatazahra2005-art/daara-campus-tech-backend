<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sujets_discussion', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 200);
            $table->text('contenu');
            $table->dateTime('date_creation');
            $table->boolean('est_resolu')->default(false);
            $table->foreignId('auteur_id')->constrained('utilisateurs');
            $table->foreignId('technologie_id')->constrained('technologies');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sujets_discussion');
    }
};
