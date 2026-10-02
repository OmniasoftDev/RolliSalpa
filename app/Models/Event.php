<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    protected $fillable = ['project_id', 'codice', 'chiave', 'tipo', 'chi', 'testo', 'macchine', 'da_vedere', 'visto_at'];

    protected function casts(): array
    {
        return ['macchine' => 'array', 'da_vedere' => 'boolean', 'visto_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Novita' segnalata dall'automazione e non ancora vista da Francesco. */
    public function nonVisto(): bool
    {
        return $this->da_vedere && ! $this->visto_at;
    }

    /** Testo da mostrare: senza il prefisso tecnico "[auto]" che l'automazione mette sugli eventi. */
    public function testoMostrato(): string
    {
        return (string) preg_replace('/^\[auto\]\s*/i', '', $this->testo);
    }

    /** "2026-10-02 10:56" -> "02/10/2026 10:56" */
    public function quando(): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}:\d{2}))?/', $this->chiave, $m)) {
            return $this->chiave;
        }

        return "{$m[3]}/{$m[2]}/{$m[1]}".(isset($m[4]) ? " {$m[4]}" : '');
    }
}
