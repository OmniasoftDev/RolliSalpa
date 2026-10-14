<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cassaforte delle credenziali di sistemi e macchine (Francesco, 14/10/2026): utente, segreto e note
// cifrati con APP_KEY (cast encrypted del model), indirizzo in chiaro. Ogni "Mostra", creazione,
// modifica ed eliminazione lascia una riga in credenziali_accessi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credenziali', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('oggetto_tipo', 10);
            $table->string('oggetto_codice', 20);
            $table->string('etichetta', 150);
            $table->string('indirizzo')->nullable();
            $table->text('utente')->nullable();
            $table->text('segreto')->nullable();
            $table->text('note')->nullable();
            $table->string('fonte')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'oggetto_tipo', 'oggetto_codice']);
        });

        Schema::create('credenziali_accessi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credenziale_id')->nullable()->constrained('credenziali')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('azione', 20);
            $table->string('nota', 250)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('credenziale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credenziali_accessi');
        Schema::dropIfExists('credenziali');
    }
};
