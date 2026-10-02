<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un controllo orario del PC (automazione/controllo-orario.ps1). */
class Controllo extends Model
{
    protected $table = 'controlli';

    protected $fillable = ['codice', 'inizio', 'fine', 'esito', 'outlook', 'mail_finestra', 'mail_nuove', 'appunti', 'passi', 'firma_pc', 'firma_server', 'quadra'];

    protected function casts(): array
    {
        return ['inizio' => 'datetime', 'fine' => 'datetime', 'passi' => 'array', 'quadra' => 'boolean'];
    }
}
