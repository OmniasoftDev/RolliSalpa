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

    /**
     * Sistemi del progetto (SCADA, Data Collector, gestionale, rete): campo `sistemi` del file,
     * salvato in info.sistemi. Ogni voce con tutte le chiavi presenti (elenchi vuoti dove mancano).
     */
    public function sistemi(): array
    {
        $elenco = fn (array $s, string $k) => array_values(array_filter(is_array($s[$k] ?? null) ? $s[$k] : [], 'is_array'));
        $sistemi = [];
        foreach ((array) ($this->info['sistemi'] ?? []) as $s) {
            if (! is_array($s) || ! is_string($s['codice'] ?? null) || ! preg_match('/^[A-Za-z0-9_-]{1,20}$/', $s['codice'])) {
                continue;
            }
            $voce = ['codice' => $s['codice']];
            foreach (['nome', 'tipo', 'proprieta', 'gestore', 'sintesi', 'aggiornato'] as $k) {
                $voce[$k] = is_scalar($s[$k] ?? null) ? (string) $s[$k] : '';
            }
            $voce['nome'] = $voce['nome'] !== '' ? $voce['nome'] : $s['codice'];
            foreach (['anagrafica', 'collegamenti', 'contenuti', 'foto', 'schermate', 'credenziali', 'aperti', 'documenti'] as $k) {
                $voce[$k] = $elenco($s, $k);
            }
            $sistemi[] = $voce;
        }

        return $sistemi;
    }

    /** Valore di progetto/info, con un default. */
    public function info(string $chiave, mixed $default = null): mixed
    {
        return $this->info[$chiave] ?? $default;
    }
}
