<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\Project;
use App\Models\User;
use App\Services\Pubblico;
use App\Services\Sincronizzazione;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PubblicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(Sincronizzazione::class)->applica([
            'progetto' => 'salpa', 'nome' => 'Salpa', 'ordine' => 1,
            'info' => ['scadenza' => '2099-11-30', 'pubblico' => ['attivo' => true, 'versione' => 1, 'aggiornato' => '2026-10-23', 'sintesi' => 'Sei macchine su dieci collegate allo SCADA.']],
            'fasi' => [['rete', 'Rete']],
            'macchine' => [
                ['codice' => 'm05', 'num' => '05', 'nome' => 'Separatore NH3', 'fornitore' => 'CEA — referente Mario Rossi (tel. 333)',
                    'note' => [['testo' => 'NOTA-CIVETTA interna', 'fonte' => '[Mail 16/10 Civetta]']],
                    'certificazione' => [
                        'aggiornato' => '2026-10-16',
                        'sintesi' => 'SINTESI-CIVETTA interna',
                        'rischio' => 'RISCHIO-CIVETTA interno',
                        'tappe' => [
                            ['id' => 't1', 'titolo' => 'Rete e IP', 'stato' => 'ok', 'data' => '2026-10-07', 'nota' => 'IP 192.168.10.55', 'fonte' => '[FONTE-CIVETTA]'],
                            ['id' => 't3', 'titolo' => 'Collegata allo SCADA', 'stato' => 'rischio', 'data' => '2026-10-16', 'nota' => 'NOTA-TAPPA-CIVETTA'],
                            ['id' => 't6', 'titolo' => 'Verifica Omniasoft a video', 'stato' => 'no', 'previsto' => '2099-10-28'],
                        ],
                        'manca' => [['testo' => 'MANCA-CIVETTA da non mostrare', 'chi' => 'Edica (Calabretta)', 'entro' => '2020-01-10']],
                        'prossimo' => ['testo' => 'PROSSIMO-CIVETTA interno', 'chi' => 'Francesco', 'entro' => '2099-10-20'],
                    ],
                    'scada' => ['visto' => '2026-10-16', 'stato' => 'vivo', 'connessione' => 'OPC UA opc.tcp://10.1.2.3:4840',
                        'variabili' => [['nome' => 'VARIABILE_CIVETTA', 'valore' => '42']], 'domande' => ['DOMANDA-SCADA-CIVETTA']],
                    'progetto' => ['requisiti' => ['R9' => ['stato' => 'ok', 'come' => 'COME-CIVETTA', 'evidenza' => ['testo' => 'EVIDENZA-CIVETTA']]]],
                    'pubblico' => [
                        'stato' => 'Collegata allo SCADA, si attendono le variabili di processo.',
                        'prossimo' => ['testo' => 'Verifica a video dei dati', 'azienda' => 'Edica', 'entro' => '2099-10-28'],
                        'manca' => [
                            ['testo' => 'Elenco variabili dal fornitore', 'azienda' => 'CEA', 'entro' => '2020-01-10'],
                            ['testo' => 'Ora corretta sul PLC', 'azienda' => 'Edica', 'entro' => '2099-12-31'],
                        ],
                        'dati' => 'Raccolti 8 dati su 13.',
                    ]],
                ['codice' => 'm04', 'num' => '04', 'nome' => 'Impianto cottura Boule', 'fornitore' => 'CFT (via Sertech)'],
                ['codice' => 'm00', 'num' => '—', 'nome' => 'Domande generali', 'escludiDaiConteggi' => true],
            ],
            'persone' => [['id' => 'p1', 'nome' => 'PERSONA-CIVETTA', 'azienda' => 'Edica']],
            'decisioni' => [['id' => 'd1', 'testo' => 'DECISIONE-CIVETTA']],
            'eventi' => [['id' => 'e1', 'data' => '2026-10-16', 'tipo' => 'mail', 'testo' => 'EVENTO-CIVETTA']],
        ]);
        app(Sincronizzazione::class)->applica([
            'progetto' => 'rolli', 'nome' => 'Rolli', 'ordine' => 2, 'eventi' => [],
            'macchine' => [['codice' => 'r01', 'num' => '01', 'nome' => 'Depuratore Rolli']],
        ]);
    }

    private function salpa(): Project
    {
        return Project::where('slug', 'salpa')->first();
    }

    public function test_la_sincronizzazione_conserva_i_campi_pubblici(): void
    {
        $this->assertTrue(Pubblico::attivo($this->salpa()));
        $this->assertSame('2026-10-23', $this->salpa()->info['pubblico']['aggiornato']);
        $this->assertSame('Raccolti 8 dati su 13.', Machine::where('codice', 'm05')->first()->dato('pubblico')['dati']);
    }

    public function test_chiave_giusta_mostra_la_pagina_con_noindex(): void
    {
        $chiave = substr(hash_hmac('sha256', 'pubblico|salpa|1', config('app.key')), 0, 20);
        $this->assertSame($chiave, Pubblico::chiave($this->salpa()));

        $this->get('/salpa/avanzamento/'.$chiave)->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('Industria 4.0 Salpa')
            ->assertSee('stato avanzamento')
            ->assertSee('23/10/2026')
            ->assertSee('Sei macchine su dieci collegate allo SCADA.')
            ->assertSee('Separatore NH3')
            ->assertSee('Impianto cottura Boule')
            ->assertDontSee('Domande generali')
            // fornitore: solo il nome dell'azienda
            ->assertSee('CEA')
            ->assertSee('CFT')
            ->assertDontSee('Mario Rossi')
            ->assertDontSee('Sertech')
            // tappe con titolo e date
            ->assertSee('Collegata allo SCADA')
            ->assertSee('07/10')
            ->assertSee('prev. 28/10')
            // SCADA: stato e data
            ->assertSee('dati vivi')
            ->assertSee('visto il 16/10')
            // testi pubblici
            ->assertSee('Collegata allo SCADA, si attendono le variabili di processo.')
            ->assertSee('Verifica a video dei dati')
            ->assertSee('Elenco variabili dal fornitore')
            ->assertSee('Ora corretta sul PLC')
            ->assertSee('Raccolti 8 dati su 13.')
            ->assertSee('R9');
    }

    public function test_la_pagina_non_mostra_dati_interni(): void
    {
        $risposta = $this->get(Pubblico::url($this->salpa()))->assertOk();
        foreach (['NOTA-CIVETTA', 'Civetta]', 'FONTE-CIVETTA', 'NOTA-TAPPA-CIVETTA', '192.168.10.55', '10.1.2.3', 'opc.tcp',
            'SINTESI-CIVETTA', 'RISCHIO-CIVETTA', 'MANCA-CIVETTA', 'PROSSIMO-CIVETTA', 'Calabretta', 'Francesco',
            'VARIABILE_CIVETTA', 'DOMANDA-SCADA-CIVETTA', 'COME-CIVETTA', 'EVIDENZA-CIVETTA',
            'PERSONA-CIVETTA', 'DECISIONE-CIVETTA', 'EVENTO-CIVETTA', 'Esci', '/logout'] as $civetta) {
            $risposta->assertDontSee($civetta, false);
        }
    }

    public function test_chiave_sbagliata_404(): void
    {
        $this->get('/salpa/avanzamento/00000000000000000000')->assertNotFound();
        $this->get('/salpa/avanzamento/abc')->assertNotFound();
    }

    public function test_nuova_versione_cambia_il_link(): void
    {
        $vecchia = Pubblico::chiave($this->salpa());
        $p = $this->salpa();
        $p->update(['info' => array_replace_recursive($p->info, ['pubblico' => ['versione' => 2]])]);
        $this->get('/salpa/avanzamento/'.$vecchia)->assertNotFound();
        $this->get(Pubblico::url($p->fresh()))->assertOk();
    }

    public function test_progetto_non_attivo_404(): void
    {
        $rolli = Project::where('slug', 'rolli')->first();
        $this->assertFalse(Pubblico::attivo($rolli));
        $this->get('/rolli/avanzamento/'.Pubblico::chiave($rolli))->assertNotFound();

        $p = $this->salpa();
        $chiave = Pubblico::chiave($p);
        $p->update(['info' => array_replace_recursive($p->info, ['pubblico' => ['attivo' => false]])]);
        $this->get('/salpa/avanzamento/'.$chiave)->assertNotFound();

        $this->get('/inesistente/avanzamento/'.$chiave)->assertNotFound();
    }

    public function test_link_pubblico_nel_quadro(): void
    {
        $url = Pubblico::url($this->salpa());
        $this->actingAs(User::factory()->create())->get('/salpa')->assertOk()
            ->assertSee('Link pubblico')
            ->assertSee($url, false);
        $this->get('/rolli')->assertOk()->assertSee('Link pubblico')->assertSee('Non attiva');
    }
}
