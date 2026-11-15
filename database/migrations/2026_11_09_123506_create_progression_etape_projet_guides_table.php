<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('progression_etape_projet_guides', function (Blueprint $table) {
            $table->id();
            $table->boolean('est_terminee')->default(false);
            $table->dateTime('date_validation')->nullable();

            $table->foreignId('inscription_cours_id')
                ->constrained('inscriptions_cours')
                ->cascadeOnDelete();

            $table->foreignId('etape_projet_guide_id')
                ->constrained('etapes_projet_guide')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique([
                'inscription_cours_id',
                'etape_projet_guide_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progression_etape_projet_guides');
    }
};
