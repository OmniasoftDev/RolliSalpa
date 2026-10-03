<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Persone e compiti: rubrica di progetto (dal file del PC, campo "persone") e compiti
// che Francesco assegna dal sito. I compiti proposti dal PC (campo "compitiProposti")
// arrivano come "proposto" e diventano di Francesco appena li conferma o li scarta.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persone', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('codice', 40);
            $table->unsignedInteger('ordine')->default(0);
            $table->string('nome');
            $table->string('azienda')->nullable();
            $table->string('gruppo', 60)->nullable();
            $table->text('ruolo')->nullable();
            $table->json('macchine')->nullable();
            $table->string('contatti')->nullable();
            $table->string('fonte')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'codice']);
        });

        Schema::create('compiti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('codice', 40)->nullable();
            $table->string('persona', 40)->nullable();
            $table->text('testo');
            $table->json('macchine')->nullable();
            $table->string('fonte')->nullable();
            $table->date('scadenza')->nullable();
            $table->string('stato', 12)->default('aperto');
            $table->date('assegnato_il')->nullable();
            $table->date('chiuso_il')->nullable();
            $table->text('esito')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'codice']);
            $table->index(['project_id', 'stato']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compiti');
        Schema::dropIfExists('persone');
    }
};
