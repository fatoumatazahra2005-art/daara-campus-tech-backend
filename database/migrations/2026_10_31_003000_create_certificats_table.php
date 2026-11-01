<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificats', function (Blueprint $table) {
            $table->id();
            $table->date('date_obtention');
            $table->string('code_verification', 40)->unique();
            $table->string('nom_apprenant', 201);
            $table->string('titre_cours', 150);
            $table->string('url_pdf', 500)->nullable();
            $table->foreignId('inscription_cours_id')->unique()->constrained('inscriptions_cours');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificats');
    }
};
