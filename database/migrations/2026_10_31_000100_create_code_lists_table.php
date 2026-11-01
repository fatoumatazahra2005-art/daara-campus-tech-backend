<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_lists', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('value', 50);
            $table->text('description')->nullable();
            $table->unsignedInteger('ordre')->default(1);
            $table->boolean('active')->default(true);
            $table->boolean('est_systeme')->default(false);
            $table->date('date_creation');
            $table->timestamps();

            $table->unique(['type', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_lists');
    }
};
