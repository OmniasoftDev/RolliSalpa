<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro della cassaforte: ogni "Mostra", creazione, modifica ed eliminazione. Righe mai cancellate. */
class CredenzialeAccesso extends Model
{
    public const AZIONI = ['mostra' => 'mostrata', 'creazione' => 'creata', 'modifica' => 'modificata', 'eliminazione' => 'eliminata'];

    public const UPDATED_AT = null;

    protected $table = 'credenziali_accessi';

    protected $fillable = ['credenziale_id', 'user_id', 'azione', 'nota', 'ip', 'user_agent'];

    public function credenziale(): BelongsTo
    {
        return $this->belongsTo(Credenziale::class, 'credenziale_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
