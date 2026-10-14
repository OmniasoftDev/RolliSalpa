<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Cassaforte delle credenziali (Francesco, 14/10/2026): accessi a SCADA, router, database dei
 * sistemi e delle macchine. Utente, segreto e note sono cifrati con APP_KEY (cast encrypted)
 * e nascosti in toArray/JSON: si leggono in chiaro solo da "Mostra", dopo la conferma della password.
 * Mai nelle API verso il PC, nella chat con Claude o nelle bozze di mail.
 */
class Credenziale extends Model
{
    public const TIPI_OGGETTO = ['sistema', 'macchina'];

    protected $table = 'credenziali';

    protected $fillable = ['project_id', 'oggetto_tipo', 'oggetto_codice', 'etichetta', 'indirizzo', 'utente', 'segreto', 'note', 'fonte', 'user_id'];

    protected $hidden = ['utente', 'segreto', 'note'];

    protected function casts(): array
    {
        return [
            'utente' => 'encrypted',
            'segreto' => 'encrypted',
            'note' => 'encrypted',
        ];
    }

    /**
     * La cassaforte e' attiva solo dopo l'import di database/sql/2026-10-14-credenziali.sql:
     * prima di allora le pagine mostrano un avviso e le scritture rispondono 503. Un solo controllo per richiesta.
     */
    public static function attiva(): bool
    {
        return once(fn () => Schema::hasTable('credenziali') && Schema::hasTable('credenziali_accessi'));
    }

    /** Credenziali di un sistema o di una macchina, con gli ultimi accessi; null se la cassaforte non e' attiva. */
    public static function perOggetto(Project $progetto, string $tipo, string $codice): ?Collection
    {
        if (! self::attiva()) {
            return null;
        }

        return self::where('project_id', $progetto->id)->where('oggetto_tipo', $tipo)->where('oggetto_codice', $codice)
            ->orderBy('etichetta')->get()
            ->each(fn (self $c) => $c->setRelation('ultimiAccessi', $c->accessi()->with('user')->latest('id')->limit(5)->get()));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accessi(): HasMany
    {
        return $this->hasMany(CredenzialeAccesso::class, 'credenziale_id');
    }

    /** Utente mascherato per l'elenco: solo la prima lettera. */
    public function utenteMascherato(): string
    {
        $u = (string) $this->utente;

        return $u === '' ? '—' : mb_substr($u, 0, 1).'•••••';
    }
}
