<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compito assegnato da Francesco a una persona del progetto. Nasce sul web, oppure
 * proposto dal PC (codice "cN", stato "proposto") finche' Francesco non lo conferma o lo scarta.
 */
class Compito extends Model
{
    public const STATI = [
        'proposto' => 'Da confermare',
        'aperto' => 'Assegnato',
        'fatto' => 'Fatto',
        'annullato' => 'Annullato',
    ];

    protected $table = 'compiti';

    protected $fillable = ['project_id', 'codice', 'persona', 'testo', 'macchine', 'fonte', 'scadenza', 'stato', 'assegnato_il', 'chiuso_il', 'esito'];

    protected function casts(): array
    {
        return [
            'macchine' => 'array',
            'scadenza' => 'date',
            'assegnato_il' => 'date',
            'chiuso_il' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scaduto(): bool
    {
        return $this->stato === 'aperto' && $this->scadenza !== null && $this->scadenza->lt(today());
    }
}
