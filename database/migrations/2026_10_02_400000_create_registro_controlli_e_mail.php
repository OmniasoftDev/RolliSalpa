<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Registro: ogni controllo orario del PC e ogni mail di progetto trovata in Outlook,
// per poter dimostrare che nessun controllo e nessuna mail sono andati persi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controlli', function (Blueprint $table) {
            $table->id();
            $table->string('codice', 19)->unique();          // "2026-10-02 16:00:01": inizio del controllo sul PC
            $table->dateTime('inizio');
            $table->dateTime('fine')->nullable();
            $table->string('esito', 20);                     // ok | avvisi | errore
            $table->string('outlook', 200)->nullable();
            $table->unsignedInteger('mail_finestra')->default(0);
            $table->unsignedInteger('mail_nuove')->default(0);
            $table->unsignedInteger('appunti')->default(0);
            $table->json('passi')->nullable();               // [{passo, esito}]
            $table->string('firma_pc', 64)->nullable();
            $table->string('firma_server', 64)->nullable();
            $table->boolean('quadra')->nullable();
            $table->timestamps();
        });

        Schema::create('mail_registro', function (Blueprint $table) {
            $table->id();
            $table->string('codice', 40)->unique();          // SHA-1 del Message-ID
            $table->dateTime('ricevuta')->index();
            $table->string('cartella', 120)->nullable();
            $table->string('da', 255)->nullable();
            $table->text('a')->nullable();
            $table->text('cc')->nullable();
            $table->string('oggetto', 500)->nullable();
            $table->mediumText('testo')->nullable();
            $table->json('allegati')->nullable();
            $table->boolean('inviata')->default(false);      // inviata da info@ (decisione di Francesco)
            $table->dateTime('elaborata_at')->nullable();    // registrata nei file del progetto dal PC
            $table->string('trovata_controllo', 19)->nullable();
            $table->timestamp('vista_at')->nullable();       // solo web: Francesco l'ha letta
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_registro');
        Schema::dropIfExists('controlli');
    }
};
