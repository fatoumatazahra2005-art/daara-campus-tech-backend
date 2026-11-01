<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signalements', function (Blueprint $table) {
            $table->id();
            $table->string('motif', 255);
            $table->dateTime('date_signalement');
            $table->boolean('traite')->default(false);
            $table->foreignId('utilisateur_id')->constrained('utilisateurs');
            $table->foreignId('message_id')->constrained('messages_forum')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['utilisateur_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signalements');
    }
};
