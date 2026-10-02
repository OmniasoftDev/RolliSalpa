<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Machine extends Model
{
    protected $fillable = ['project_id', 'codice', 'ordine', 'dati'];

    protected function casts(): array
    {
        return ['dati' => 'array'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('ordine');
    }

    public function dato(string $chiave, mixed $default = null): mixed
    {
        return $this->dati[$chiave] ?? $default;
    }

    /** Stato di una fase: ok / corso / attesa / rischio / no / na. */
    public function fase(string $chiave): string
    {
        $s = $this->dati['fasi'][$chiave] ?? 'no';

        return in_array($s, ['ok', 'corso', 'attesa', 'rischio', 'no', 'na'], true) ? $s : 'no';
    }

    /** Macchina da seguire: una fase bloccata o in attesa, o una domanda aperta. */
    public function daSeguire(): bool
    {
        foreach ((array) ($this->dati['fasi'] ?? []) as $s) {
            if ($s === 'rischio' || $s === 'attesa') {
                return true;
            }
        }

        return $this->questions->contains(fn (Question $q) => ! $q->fatto);
    }
}
