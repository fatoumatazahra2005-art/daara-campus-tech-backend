<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('email', 150)->unique();
            $table->string('telephone', 20)->nullable()->unique();
            $table->string('mot_de_passe_hash', 255);
            $table->foreignId('role_id')->constrained('code_lists');           // CodeList (RoleUser)
            $table->foreignId('statut_compte_id')->constrained('code_lists');  // CodeList (StatutCompte), défaut ACTIF
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('utilisateurs');
    }
};
