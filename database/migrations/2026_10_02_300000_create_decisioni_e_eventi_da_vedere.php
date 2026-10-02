<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Da guardare": decisioni che aspettano Francesco (dal file del progetto, spuntabili dal web)
// e novita' (eventi) da segnare come viste.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decisioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('codice', 40);
            $table->unsignedInteger('ordine')->default(0);
            $table->text('testo');
            $table->string('fonte')->nullable();
            $table->json('macchine')->nullable();
            $table->date('data')->nullable();
            $table->boolean('fatta')->default(false);
            $table->date('fatta_il')->nullable();
            $table->boolean('fatta_web')->default(false);
            $table->timestamps();
            $table->unique(['project_id', 'codice']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->boolean('da_vedere')->default(false);
            $table->timestamp('visto_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['da_vedere', 'visto_at']);
        });
        Schema::dropIfExists('decisioni');
    }
};
