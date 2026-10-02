<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabelle del pannello. Il contenuto arriva da /api/sync (file JSON sul PC di Francesco);
// l'unico dato che nasce qui sono le spunte delle domande (questions.fatto_web).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 30)->unique();
            $table->string('nome');
            $table->unsignedInteger('ordine')->default(0);
            $table->json('info')->nullable();
            $table->json('fasi')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('codice', 30);
            $table->unsignedInteger('ordine')->default(0);
            $table->json('dati');
            $table->timestamps();
            $table->unique(['project_id', 'codice']);
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->string('chiave', 64);
            $table->unsignedInteger('ordine')->default(0);
            $table->string('chi')->nullable();
            $table->text('cosa');
            $table->boolean('fatto')->default(false);
            $table->date('fatto_il')->nullable();
            $table->text('risposta')->nullable();
            $table->boolean('fatto_web')->default(false);
            $table->timestamps();
            $table->unique(['machine_id', 'chiave']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('codice', 40);
            $table->string('chiave', 20);
            $table->string('tipo', 30)->nullable();
            $table->string('chi')->nullable();
            $table->text('testo');
            $table->json('macchine')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'codice']);
            $table->index(['project_id', 'chiave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('machines');
        Schema::dropIfExists('projects');
    }
};
