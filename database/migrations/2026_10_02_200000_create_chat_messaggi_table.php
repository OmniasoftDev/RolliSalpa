<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Chat con Claude dal sito: una conversazione per progetto.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messaggi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ruolo', 12);
            $table->mediumText('testo');
            $table->json('uso')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messaggi');
    }
};
