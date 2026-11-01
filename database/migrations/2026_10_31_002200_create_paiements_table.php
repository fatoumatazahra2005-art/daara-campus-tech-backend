<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('montant');                                // FCFA, entier
            $table->date('date_echeance');
            $table->dateTime('date_paiement')->nullable();
            $table->unsignedInteger('numero_echeance')->default(1);
            $table->foreignId('methode_id')->constrained('code_lists');        // CodeList (MethodePaiement)
            $table->foreignId('statut_id')->constrained('code_lists');         // CodeList (StatutPaiement), défaut EN_ATTENTE
            $table->string('reference', 100)->nullable()->unique();
            $table->string('numero_payeur', 20)->nullable();
            $table->dateTime('date_notification')->nullable();
            $table->foreignId('periode_id')->constrained('periodes_abonnement');
            $table->timestamps();

            $table->unique(['periode_id', 'numero_echeance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
