<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appunto extends Model
{
    public const TIPI = [
        'sopralluogo' => 'Sopralluogo',
        'riunione' => 'Riunione',
        'decisione' => 'Decisione',
        'nota' => 'Nota',
    ];

    protected $table = 'appunti';

    protected $fillable = ['project_id', 'user_id', 'tipo', 'macchine', 'testo', 'elaborato_at'];

    protected function casts(): array
    {
        return [
            'macchine' => 'array',
            'elaborato_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allegati(): HasMany
    {
        return $this->hasMany(AllegatoAppunto::class, 'appunto_id');
    }

    public function etichettaTipo(): string
    {
        return self::TIPI[$this->tipo] ?? $this->tipo;
    }
}
