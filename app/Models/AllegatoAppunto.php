<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllegatoAppunto extends Model
{
    protected $table = 'appunti_allegati';

    protected $fillable = ['appunto_id', 'nome', 'percorso', 'mime', 'dimensione'];

    public function appunto(): BelongsTo
    {
        return $this->belongsTo(Appunto::class, 'appunto_id');
    }

    public function immagine(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }
}
