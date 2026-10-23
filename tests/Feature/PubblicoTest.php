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
                    'ip' => '192.168.11.109', 'protocollo' => 'OPC UA, chiarito con Calabretta [Mail 16/10 Calabretta]', 'rete' => 'OK — porta DI-11',
                    'referenti' => 'REFERENTE-CIVETTA',
                    'chiedere' => [['id' => 'q1', 'chi' => 'Edica', 'cosa' => 'CHIEDERE-CIVETTA']],
                    'scada' => ['visto' => '2026-10-16', 'stato' => 'vivo', 'connessione' => 'OPC UA opc.tcp://10.1.2.3:4840',
                        'fonte' => 'SCADA-FONTE-CIVETTA', 'sintesi' => 'SCADA-SINTESI-CIVETTA', 'storico' => 'Nessun tag nello storico.',
                        'variabili' => [['nome' => 'VARIABILE_CIVETTA', 'valore' => '42', 'tipo' => 'Float', 'accesso' => 'R/W', 'qualita' => 'Good', 'nota' => 'VAR-NOTA-CIVETTA']],
                        'pagine' => [['nome' => 'Gestione_Ammoniaca.gdfx', 'data' => '2026-09-24', 'nota' => 'PAGINA-NOTA-CIVETTA']],
                        'schermate' => [['file' => 'SCHERMATA-CIVETTA.png', 'titolo' => 'SCHERMATA-TITOLO-CIVETTA']],
                        'domande' => ['DOMANDA-SCADA-CIVETTA']],
                    'progetto' => [
                        'requisiti' => ['R9' => ['stato' => 'ok', 'come' => 'COME-CIVETTA', 'evidenza' => ['testo' => 'EVIDENZA-CIVETTA']]],
                        'sintesi' => 'PROGETTO-SINTESI-CIVETTA',
                        'anagrafica' => [['voce' => 'Controllore', 'valore' => 'Siemens S7-1500 visto da Andrea Calabretta', 'fonte' => 'ANAGRAFICA-FONTE-CIVETTA']],
                        'nodi' => [
                            ['id' => 'n1', 'nome' => 'PLC macchina', 'tipo' => 'plc', 'ip' => '192.168.11.124', 'modello' => 'CPU 1214C', 'stato' => 'ok', 'dettaglio' => 'NODO-DETTAGLIO-CIVETTA', 'fonte' => 'NODO-FONTE-CIVETTA'],
                            ['id' => 'n2', 'nome' => 'SCADA Edica', 'tipo' => 'scada', 'stato' => 'attesa'],
                        ],
                        'collegamenti' => [['id' => 'k1', 'da' => 'n1', 'a' => 'n2', 'verso' => 'uno', 'titolo' => 'Dati di processo DB390', 'protocollo' => 'OPC UA porta 4840',
                            'dati' => ['stati macchina', 'allarmi'], 'stato' => 'corso', 'dettaglio' => 'COLL-DETTAGLIO-CIVETTA', 'nota' => 'COLL-NOTA-CIVETTA', 'fonte' => 'COLL-FONTE-CIVETTA']],
                        'uscite' => [['dato' => 'Stato macchina', 'dettaglio' => 'USCITA-DETTAGLIO-CIVETTA', 'variabile' => 'DB390.Stato', 'req' => ['R8'], 'stato' => 'attesa', 'fonte' => 'USCITA-FONTE-CIVETTA']],
                        'ingressi' => [['dato' => 'Ricetta di produzione', 'da' => 'SCADA Edica', 'dettaglio' => 'INGRESSO-DETTAGLIO-CIVETTA', 'variabile' => 'DB394.Req_Recipe', 'req' => ['R1', 'R13'], 'stato' => 'verificare']],
                    ],
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
            'persone' => [
                ['id' => 'p1', 'nome' => 'PERSONA-CIVETTA', 'azienda' => 'Edica', 'contatti' => 'CONTATTI-CIVETTA'],
                ['id' => 'p5', 'nome' => 'Andrea Calabretta', 'azienda' => 'Edica'],
                ['id' => 'p10', 'nome' => 'Renato Voce', 'azienda' => 'JBT'],
                ['id' => 'p19', 'nome' => 'Ing. Marco Romano', 'azienda' => 'Certificatore esterno'],
            ],
            'sistemi' => [['codice' => 's1', 'nome' => 'SCADA Edica', 'tipo' => 'scada', 'credenziali' => [['oggetto' => 'CRED-CIVETTA', 'stato' => 'richiesto', 'nota' => 'password123']]]],
            'planning' => [
                ['id' => 'pl8', 'titolo' => 'Verifica R1–R13 delle macchine collegate', 'dal' => '2026-11-02', 'al' => '2026-11-06', 'macchine' => [], 'stato' => 'previsto',
                    'fonte' => 'PLANNING-FONTE-CIVETTA', 'dettaglio' => 'PLANNING-DETTAGLIO-CIVETTA'],
                ['id' => 'pl2', 'titolo' => 'PLANNING-MACCHINA-CIVETTA', 'dal' => '2026-10-14', 'al' => '2026-10-14', 'macchine' => ['m05'], 'stato' => 'fatto'],
            ],
            'appuntamenti' => [
                ['id' => 'ap1', 'titolo' => 'Prove CEA con Renato Voce e Ing. Romano', 'inizio' => '2026-10-27T09:30:00+01:00', 'fine' => '2026-10-27T11:00:00+01:00',
                    'macchine' => ['m05'], 'stato' => 'accettato', 'dettaglio' => 'APP-DETTAGLIO-CIVETTA', 'fonte' => 'APP-FONTE-CIVETTA'],
                ['id' => 'ap2', 'titolo' => 'APPUNTAMENTO-PROPOSTO-CIVETTA', 'inizio' => '2026-10-28T09:30:00+01:00', 'fine' => '2026-10-28T10:00:00+01:00', 'macchine' => ['m05']],
            ],
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
        foreach (['NOTA-CIVETTA', 'Civetta]', 'FONTE-CIVETTA', 'NOTA-TAPPA-CIVETTA', '192.168.10.55', 'Mail 16/10',
            'SINTESI-CIVETTA', 'RISCHIO-CIVETTA', 'MANCA-CIVETTA', 'PROSSIMO-CIVETTA', 'Calabretta', 'Francesco', 'Romano', 'Renato',
            'DOMANDA-SCADA-CIVETTA', 'COME-CIVETTA', 'EVIDENZA-CIVETTA', 'DETTAGLIO-CIVETTA', 'SCHERMATA', 'REFERENTE-CIVETTA', 'CHIEDERE-CIVETTA',
            'CRED-CIVETTA', 'password123', 'CONTATTI-CIVETTA', 'PLANNING-MACCHINA-CIVETTA', 'APPUNTAMENTO-PROPOSTO-CIVETTA',
            'PERSONA-CIVETTA', 'DECISIONE-CIVETTA', 'EVENTO-CIVETTA', 'Esci', '/logout'] as $civetta) {
            $risposta->assertDontSee($civetta, false);
        }
    }

    public function test_dati_tecnici_con_ip_e_variabili_senza_nomi(): void
    {
        $this->get(Pubblico::url($this->salpa()))->assertOk()
            ->assertSee('Dati tecnici')
            ->assertSee('192.168.11.109')
            ->assertSee('OPC UA, chiarito con Edica')
            ->assertSee('OK — porta DI-11')
            ->assertSee('Controllore')
            ->assertSee('Siemens S7-1500 visto da Edica')
            ->assertSee('opc.tcp://10.1.2.3:4840')
            ->assertSee('Nessun tag nello storico.')
            ->assertSee('VARIABILE_CIVETTA')
            ->assertSee('Float')
            ->assertSee('R/W')
            ->assertSee('Good')
            ->assertSee('Gestione_Ammoniaca.gdfx')
            ->assertSee('24/09/2026')
            ->assertSee('Stato macchina')
            ->assertSee('DB390.Stato')
            ->assertSee('Ricetta di produzione')
            ->assertSee('DB394.Req_Recipe')
            ->assertSee('PLC macchina')
            ->assertSee('192.168.11.124')
            ->assertSee('CPU 1214C')
            ->assertSee('Dati di processo DB390')
            ->assertSee('OPC UA porta 4840')
            ->assertSee('stati macchina, allarmi');
    }

    public function test_gantt_con_tappe_appuntamenti_e_fasi_comuni(): void
    {
        $this->get(Pubblico::url($this->salpa()))->assertOk()
            ->assertSee('Il lavoro nel tempo')
            ->assertSee('28/09')
            ->assertSee('ottobre')
            ->assertSee('href="#m-m05"', false)
            ->assertSee('T1 Rete e IP: Fatto / funziona — fatta il 07/10/2026')
            ->assertSee('T3 Collegata allo SCADA')
            ->assertSee('gt-seg st-ok', false)
            ->assertSee('gt-scadenza', false)
            ->assertSee('Prove CEA con JBT e Certificatore esterno')
            ->assertSee('Verifica R1–R13 delle macchine collegate')
            ->assertSee('Progetto');
    }

    public function test_anonimizza_sostituisce_i_nomi_con_l_azienda(): void
    {
        $a = new \App\Services\Anonimizza([
            ['nome' => 'M. Sarmati', 'azienda' => 'Salpa'],
            ['nome' => 'Luca Maria Marocchi', 'azienda' => 'Salpa'],
            ['nome' => 'IT Salpa', 'azienda' => 'Salpa'],
            ['nome' => 'Ezio Di Carmine', 'azienda' => 'Edica'],
            ['nome' => 'Claudio Santarelli', 'azienda' => 'Edica (programmatore SCADA) [I]'],
            ['nome' => 'Renato Voce', 'azienda' => 'JBT'],
            ['nome' => 'CFT (F. Dioni, M. Gonizzi)', 'azienda' => 'CFT'],
            ['nome' => 'CEA (referente tecnico da chiedere)', 'azienda' => 'CEA'],
            ['nome' => 'Ing. Marco Romano', 'azienda' => 'Certificatore esterno'],
        ]);
        $this->assertSame('Chiesto a Salpa e a Salpa', $a->testo('Chiesto a SARMATI e a M. Sarmati'));
        $this->assertSame('Salpa ha detto', $a->testo('Luca Maria Marocchi ha detto'));
        $this->assertSame('rete IT Salpa', $a->testo('rete IT Salpa'));
        $this->assertSame('visto con Edica', $a->testo('visto con Di Carmine'));
        $this->assertSame('Edica', $a->testo('Santarelli [Mail 09/10 Santarelli]'));
        $this->assertSame('una voce del menu, JBT', $a->testo('una voce del menu, Voce'));
        $this->assertSame('CFT e CFT', $a->testo('Dioni e gonizzi'));
        $this->assertSame('Certificatore esterno verifica', $a->testo('Ing. Romano verifica'));
        $this->assertSame('Romanov e CEA', $a->testo('Romanov e CEA'));
        $this->assertSame('a, b', $a->testo(['a', 'b']));
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
