<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Certificazione;
use App\Services\Sincronizzazione;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificazioneTest extends TestCase
{
    use RefreshDatabase;

    private function certificazioneNh3(): array
    {
        return [
            'aggiornato' => '2026-10-16',
            'sintesi' => 'Dati vivi in OPC UA sullo SCADA Edica, orologio del PLC al 2014.',
            'tappe' => [
                ['id' => 't1', 'titolo' => 'Rete e IP', 'stato' => 'ok', 'data' => '2026-10-07', 'fonte' => '[B07]'],
                ['id' => 't2', 'titolo' => 'Variabili dal fornitore', 'stato' => 'ok', 'data' => '2026-10-16'],
                ['id' => 't3', 'titolo' => 'Collegata allo SCADA', 'stato' => 'ok', 'data' => '2026-10-16', 'nota' => 'Interfaccia server_1, qualità Good', 'fonte' => '[S16]'],
                ['id' => 't4', 'titolo' => 'Dati R1–R13 sullo SCADA', 'stato' => 'rischio', 'data' => '2026-10-16', 'nota' => 'timestamp 2014'],
                ['id' => 't5', 'titolo' => 'Ricetta dal gestionale', 'stato' => 'na'],
                ['id' => 't6', 'titolo' => 'Verifica Omniasoft a video', 'stato' => 'no', 'previsto' => '2026-10-28'],
                ['id' => 't7', 'titolo' => 'Perizia Ing. Romano', 'stato' => 'no', 'previsto' => '2026-11-20'],
                ['id' => 't8', 'titolo' => 'Certificata', 'stato' => 'inventato'],
            ],
            'manca' => [
                ['testo' => 'Rimettere l\'ora giusta sul PLC', 'chi' => 'Edica (Calabretta)', 'entro' => '2020-01-10', 'tappa' => 't4'],
                ['testo' => 'Decidere il pressostato', 'chi' => 'Salpa', 'entro' => '2099-12-31'],
            ],
            'prossimo' => ['testo' => 'Chiedere a Edica l\'ora del PLC', 'chi' => 'Francesco', 'entro' => '2099-10-20'],
            'fineprevista' => '2026-11-20',
            'rischio' => 'Orologio PLC sbagliato: storico inutilizzabile',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        app(Sincronizzazione::class)->applica([
            'progetto' => 'salpa', 'nome' => 'Salpa', 'ordine' => 1,
            'info' => ['scadenza' => '2099-11-30'],
            'fasi' => [['rete', 'Rete']],
            'macchine' => [
                ['codice' => 'm05', 'num' => '05', 'nome' => 'Separatore NH3', 'fornitore' => 'CEA', 'certificazione' => $this->certificazioneNh3(),
                    'progetto' => ['requisiti' => ['R9' => ['stato' => 'ok', 'come' => 'allarmi livello']]]],
                ['codice' => 'm04', 'num' => '04', 'nome' => 'Impianto cottura Boule', 'fornitore' => 'CFT'],
            ],
            'eventi' => [],
        ]);
        app(Sincronizzazione::class)->applica([
            'progetto' => 'rolli', 'nome' => 'Rolli', 'ordine' => 2, 'eventi' => [],
            'macchine' => [
                ['codice' => 'r00', 'num' => '—', 'nome' => 'Domande generali', 'escludiDaiConteggi' => true],
                ['codice' => 'r04', 'num' => '04', 'nome' => 'Soffianti Rolli', 'certificazione' => ['tappe' => [['id' => 'x1', 'titolo' => 'Offerta', 'stato' => 'corso']]]],
            ],
        ]);
    }

    public function test_pagina_con_tappe_date_e_cosa_manca(): void
    {
        $this->actingAs(User::factory()->create())->get('/salpa/certificazione')->assertOk()
            ->assertSee('Certificazione 4.0 · Salpa')
            ->assertSee('Separatore NH3')
            ->assertSee('Collegata allo SCADA')
            ->assertSee('16/10')
            ->assertSee('prev. 28/10')
            ->assertSee('Interfaccia server_1, qualità Good')
            ->assertSee('Rimettere l&#039;ora giusta sul PLC', false)
            ->assertSee('Edica (Calabretta)')
            ->assertSee('Orologio PLC sbagliato')
            ->assertSee('fine prevista 20/11/2026')
            ->assertSee('Scheda certificazione non ancora compilata')
            ->assertSee('Impianto cottura Boule')
            ->assertDontSee('Soffianti Rolli')
            ->assertDontSee('Domande generali');

        $this->get('/rolli/certificazione')->assertOk()->assertSee('Soffianti Rolli')->assertSee('Offerta')->assertDontSee('Domande generali');
    }

    public function test_percentuale_e_filtri(): void
    {
        // t1..t3 ok = 3; t4 rischio, t6, t7 no, t8 (stato sconosciuto -> no) = 0; t5 na escluso: 3/7 = 43%
        $scheda = app(Certificazione::class)->scheda(\App\Models\Machine::where('codice', 'm05')->first());
        $this->assertSame(43, $scheda['percentuale']);
        $this->assertSame(2, $scheda['ultimaOk']);
        $this->assertTrue($scheda['manca'][0]['scaduto']);
        $this->assertFalse($scheda['manca'][1]['scaduto']);

        $this->actingAs(User::factory()->create())->get('/salpa/certificazione?filtro=rischio')->assertOk()
            ->assertSee('Separatore NH3')->assertDontSee('Impianto cottura Boule');
        $this->get('/salpa/certificazione?filtro=senzadati')->assertOk()
            ->assertSee('Impianto cottura Boule')->assertDontSee('Separatore NH3');
    }

    public function test_link_dal_menu_dal_quadro_e_dal_progetto(): void
    {
        $this->actingAs(User::factory()->create())->get('/salpa')->assertOk()
            ->assertSee('/salpa/certificazione', false)
            ->assertSee('/salpa/certificazione#c-m05', false);
        $this->get('/salpa/progetti/m05')->assertOk()->assertSee('/salpa/certificazione#c-m05', false);
    }

    public function test_serve_il_login(): void
    {
        $this->get('/salpa/certificazione')->assertRedirect('/login');
    }
}
