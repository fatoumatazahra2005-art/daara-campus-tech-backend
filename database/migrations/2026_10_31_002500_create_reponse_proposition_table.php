<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reponse_proposition', function (Blueprint $table) {
            $table->foreignId('reponse_id')->constrained('reponses_apprenant')->cascadeOnDelete();
            $table->foreignId('proposition_id')->constrained('propositions');
            $table->timestamps();

            $table->primary(['reponse_id', 'proposition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reponse_proposition');
    }
};
