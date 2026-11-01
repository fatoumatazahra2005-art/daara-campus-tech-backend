<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apprenants', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreign('id')->references('id')->on('utilisateurs')->cascadeOnDelete();
            $table->unsignedInteger('xp_total')->default(0);
            $table->unsignedInteger('niveau_xp')->default(1);
            $table->unsignedInteger('serie_jours_consecutifs')->default(0);
            $table->foreignId('niveau_id')->constrained('code_lists');         // CodeList (NiveauApprenant), défaut DEBUTANT
            $table->date('date_derniere_activite')->nullable();
            $table->boolean('essai_utilise')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apprenants');
    }
};
