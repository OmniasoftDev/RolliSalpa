<?php

namespace Tests\Feature;

use App\Models\Decisione;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DaGuardareTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-di-prova-lungo-almeno-trentadue-caratteri';

    private function progetto(bool $decisioneFatta = false): array
    {
        return [
            'progetto' => 'rolli', 'nome' => 'Rolli', 'fasi' => [['rete', 'Rete']],
            'macchine' => [['codice' => 'r06', 'num' => '06', 'nome' => 'Taglio ad acqua 2ª linea', 'chiedere' => []]],
            'eventi' => [
                ['id' => 'e1', 'data' => '2026-10-02', 'ora' => '14:13', 'tipo' => 'mail', 'chi' => 'Deloitte', 'testo' => '[auto] Lo SCADA può restare tra i 4.0', 'daVedere' => true],
                ['id' => 'e2', 'data' => '2026-10-02', 'ora' => '15:01', 'tipo' => 'rolling', 'chi' => 'Merlotti', 'testo' => '[auto] Rolling v20: IP della 06', 'daVedere' => true],
                ['id' => 'e3', 'data' => '2026-10-01', 'ora' => '09:00', 'tipo' => 'nota', 'chi' => 'Francesco', 'testo' => 'Vecchia nota'],
            ],
            'decisioni' => [
                ['id' => 'd1', 'testo' => 'SCADA Edica o SCADA Omniasoft per Rolli?', 'fonte' => 'Mail 02/10 Deloitte', 'macchine' => ['r06'], 'data' => '2026-10-02', 'fatta' => $decisioneFatta],
            ],
        ];
    }

    public function test_decisioni_novita_e_mail_escluse_in_cima_alla_pagina(): void
    {
        $this->withToken(self::TOKEN)->postJson('/api/sync', $this->progetto())->assertOk()->assertJsonPath('decisioni', 1);
        $this->withToken(self::TOKEN)->postJson('/api/stato', [
            'controllo' => '2026-10-02 15:00',
            'escluse' => [['quando' => '02/10 14:13', 'da' => 'vcantacessi@deloitte.it', 'oggetto' => 'ROLLI | Analisi investimenti 4.0']],
        ])->assertOk();

        $this->withoutToken()->actingAs(User::factory()->create())->get('/rolli')->assertOk()
            ->assertSee('Decisioni da prendere')
            ->assertSee('SCADA Edica o SCADA Omniasoft per Rolli?')
            ->assertSee('Lo SCADA può restare tra i 4.0')
            ->assertSee('rolling cambiato')
            ->assertDontSee('[auto]')
            ->assertSee('vcantacessi@deloitte.it');
    }

    public function test_visto_e_decisione_dal_web_tornano_al_pc(): void
    {
        $this->withToken(self::TOKEN)->postJson('/api/sync', $this->progetto())->assertOk();
        $this->withoutToken()->actingAs(User::factory()->create());

        $e1 = Event::where('codice', 'e1')->firstOrFail();
        $this->postJson("/eventi/{$e1->id}/visto", ['visto' => true])->assertOk();
        $this->assertFalse($e1->fresh()->nonVisto());
        $this->postJson('/rolli/eventi/visti')->assertOk()->assertJsonPath('viste', 1);

        // la sincronizzazione successiva non riapre le novita' gia' viste
        $this->withToken(self::TOKEN)->postJson('/api/sync', $this->progetto())->assertOk();
        $this->assertSame(0, Event::where('da_vedere', true)->whereNull('visto_at')->count());

        $d = Decisione::firstOrFail();
        $this->withoutToken()->postJson("/decisioni/{$d->id}", ['fatta' => true])->assertOk();
        $this->withToken(self::TOKEN)->postJson('/api/sync', $this->progetto(false))->assertOk();
        $this->assertTrue($d->fresh()->fatta);

        $this->withToken(self::TOKEN)->getJson('/api/spunte')->assertOk()
            ->assertJsonPath('decisioni.0.id', 'd1')
            ->assertJsonPath('decisioni.0.fatta', true);
    }
}
