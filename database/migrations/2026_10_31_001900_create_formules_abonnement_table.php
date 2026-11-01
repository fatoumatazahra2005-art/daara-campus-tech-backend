<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formules_abonnement', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 100)->unique();
            $table->text('description')->nullable();
            $table->foreignId('periodicite_id')->constrained('code_lists');    // CodeList (Periodicite)
            $table->unsignedInteger('prix');                                   // FCFA, entier
            $table->unsignedTinyInteger('nb_echeances_max')->default(1);
            $table->unsignedInteger('quota_hors_ligne')->default(0);
            $table->unsignedInteger('jours_essai')->default(0);
            $table->unsignedInteger('limite_chapitres_essai')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formules_abonnement');
    }
};
