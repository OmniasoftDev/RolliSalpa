<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Appunti presi dal sito (sopralluoghi, riunioni, decisioni, note) con foto e PDF.
// Il PC li scarica da /api/appunti, Claude li elabora come le mail e li segna "elaborati".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appunti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tipo', 20);
            $table->json('macchine')->nullable();
            $table->text('testo');
            $table->timestamp('elaborato_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'created_at']);
        });

        Schema::create('appunti_allegati', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appunto_id')->constrained('appunti')->cascadeOnDelete();
            $table->string('nome');
            $table->string('percorso');
            $table->string('mime', 100);
            $table->unsignedBigInteger('dimensione');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appunti_allegati');
        Schema::dropIfExists('appunti');
    }
};
