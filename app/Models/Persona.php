<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Persona del progetto (committente, SCADA, fornitore, certificatore): arriva dal file del PC. */
class Persona extends Model
{
    protected $table = 'persone';

    protected $fillable = ['project_id', 'codice', 'ordine', 'nome', 'azienda', 'gruppo', 'ruolo', 'macchine', 'contatti', 'fonte'];

    protected function casts(): array
    {
        return ['macchine' => 'array'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
