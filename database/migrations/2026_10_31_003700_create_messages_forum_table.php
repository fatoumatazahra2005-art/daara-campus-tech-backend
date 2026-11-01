<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages_forum', function (Blueprint $table) {
            $table->id();
            $table->text('contenu');
            $table->dateTime('date_envoi');
            $table->unsignedInteger('nb_votes')->default(0);                   // compteur dérivé de votes
            $table->unsignedInteger('nb_signalements')->default(0);            // compteur dérivé de signalements
            $table->boolean('est_masque')->default(false);
            $table->dateTime('date_masquage')->nullable();
            $table->foreignId('sujet_id')->constrained('sujets_discussion')->cascadeOnDelete();
            $table->foreignId('auteur_id')->constrained('utilisateurs');
            $table->foreignId('masque_par_id')->nullable()->constrained('administrateurs');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages_forum');
    }
};
