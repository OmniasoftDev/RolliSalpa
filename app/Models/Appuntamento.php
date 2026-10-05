<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Appuntamento da mettere nel calendario Google Salpa-Rolli solo se Francesco lo accetta:
 * proposto dal PC (invito o mail, codice "apN") o nato dal calendario proposto ("cal-...").
 */
class Appuntamento extends Model
{
    public const STATI = [
        'proposto' => 'Da accettare',
        'accettato' => 'Accettato',
        'rifiutato' => 'Rifiutato',
    ];

    protected $table = 'appuntamenti';

    protected $fillable = ['project_id', 'codice', 'origine', 'titolo', 'dettaglio', 'luogo', 'fonte', 'macchine', 'inizio', 'fine', 'stato', 'deciso_il'];

    protected function casts(): array
    {
        return [
            'macchine' => 'array',
            'inizio' => 'datetime',
            'fine' => 'datetime',
            'deciso_il' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** "mer 07/10 9:45–10:15" */
    public function quando(): string
    {
        $giorni = ['dom', 'lun', 'mar', 'mer', 'gio', 'ven', 'sab'];

        return $giorni[$this->inizio->dayOfWeek].' '.$this->inizio->format('d/m G:i').'–'.$this->fine->format('G:i')
            .($this->fine->isSameDay($this->inizio) ? '' : ' del '.$this->fine->format('d/m'));
    }
}
