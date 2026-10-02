<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    protected $fillable = ['project_id', 'codice', 'chiave', 'tipo', 'chi', 'testo', 'macchine'];

    protected function casts(): array
    {
        return ['macchine' => 'array'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
