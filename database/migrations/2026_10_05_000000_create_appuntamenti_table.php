<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Appuntamenti che Francesco accetta dal sito: proposti dal PC (inviti e mail, campo "appuntamenti"
// del file di progetto, codice "apN") o nati dal calendario proposto di "Il mio lavoro" (codice "cal-...").
// Solo quelli accettati vanno nel calendario Google Salpa-Rolli: li crea il PC al controllo dopo (/api/spunte).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appuntamenti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('codice', 60);
            $table->string('origine', 10)->default('pc');
            $table->text('titolo');
            $table->text('dettaglio')->nullable();
            $table->string('luogo')->nullable();
            $table->string('fonte')->nullable();
            $table->json('macchine')->nullable();
            $table->dateTime('inizio');
            $table->dateTime('fine');
            $table->string('stato', 12)->default('proposto');
            $table->dateTime('deciso_il')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'codice']);
            $table->index(['project_id', 'stato']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appuntamenti');
    }
};
