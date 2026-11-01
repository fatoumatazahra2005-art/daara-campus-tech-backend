<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapitres', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 150);
            $table->text('objectif')->nullable();
            $table->text('prerequis')->nullable();
            $table->unsignedInteger('ordre');
            $table->unsignedInteger('duree_estimee')->nullable();
            $table->unsignedInteger('xp_recompense')->default(10);
            $table->boolean('actif')->default(true);
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['module_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapitres');
    }
};
