<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Decisione che aspetta Francesco: arriva dal file del progetto, si spunta dal web quando e' presa. */
class Decisione extends Model
{
    protected $table = 'decisioni';

    protected $fillable = ['project_id', 'codice', 'ordine', 'testo', 'fonte', 'macchine', 'data', 'fatta', 'fatta_il', 'fatta_web'];

    protected function casts(): array
    {
        return [
            'macchine' => 'array',
            'data' => 'date',
            'fatta' => 'boolean',
            'fatta_il' => 'date',
            'fatta_web' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
