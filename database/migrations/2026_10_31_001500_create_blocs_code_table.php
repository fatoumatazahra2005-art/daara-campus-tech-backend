<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocs_code', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreign('id')->references('id')->on('blocs_contenu')->cascadeOnDelete();
            $table->text('code');
            $table->string('langage', 30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocs_code');
    }
};
