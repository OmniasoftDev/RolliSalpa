<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['slug', 'nome', 'ordine', 'info', 'fasi', 'synced_at'];

    protected function casts(): array
    {
        return [
            'info' => 'array',
            'fasi' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class)->orderBy('ordine');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->orderByDesc('chiave');
    }

    public function decisioni(): HasMany
    {
        return $this->hasMany(Decisione::class)->orderBy('ordine');
    }

    public function persone(): HasMany
    {
        return $this->hasMany(Persona::class)->orderBy('ordine');
    }

    public function compiti(): HasMany
    {
        return $this->hasMany(Compito::class);
    }

    public function appuntamenti(): HasMany
    {
        return $this->hasMany(Appuntamento::class)->orderBy('inizio');
    }

    /** Valore di progetto/info, con un default. */
    public function info(string $chiave, mixed $default = null): mixed
    {
        return $this->info[$chiave] ?? $default;
    }
}
