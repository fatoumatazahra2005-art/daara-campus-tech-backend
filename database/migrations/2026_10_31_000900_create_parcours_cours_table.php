<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcours_cours', function (Blueprint $table) {
            $table->foreignId('parcours_id')->constrained('parcours')->cascadeOnDelete();
            $table->foreignId('cours_id')->constrained('cours');
            $table->unsignedInteger('ordre');
            $table->boolean('obligatoire')->default(true);
            $table->timestamps();

            $table->primary(['parcours_id', 'cours_id']);
            $table->unique(['parcours_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcours_cours');
    }
};
