<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Sincronizzazione;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Miglioramenti del 16/10/2026: matrice requisiti, domande dallo SCADA, eta' dei dati, badge SCADA nel quadro. */
class MiglioramentiScadaTest extends TestCase
{
    use RefreshDatabase;

    private function file(array $info = []): array
    {
        $tutti = fn (string $s) => collect(range(1, 13))->mapWithKeys(fn ($i) => ['R'.$i => ['stato' => $s, 'come' => 'come '.$i]])->all();

        $r05 = $tutti('corso');
        $r05['R1'] = ['stato' => 'ok', 'come' => 'ricetta scritta dallo SCADA', 'evidenza' => ['file' => 'nh3-05.jpg']];
        $r05['R2'] = ['stato' => 'na', 'come' => 'non applicabile'];
        $r05['R9'] = ['stato' => 'ok', 'come' => 'area Nh3', 'evidenza' => ['testo' => 'AlarmWorX64, 20 allarmi']];
        $r05['R12'] = ['stato' => 'no', 'come' => 'nessuno storico'];
        $r09 = $tutti('ok');
        $r09['R12'] = ['stato' => 'no', 'come' => 'nessuno storico'];
        $r09['R13'] = ['stato' => 'rischio', 'come' => 'nessun gestionale'];

        return [
            'progetto' => 'salpa', 'nome' => 'Salpa', 'ordine' => 1,
            'info' => $info + [
                'contatori' => [['tipo' => 'fase', 'fase' => 'scada', 'etichetta' => 'su SCADA']],
            ],
            'fasi' => [['rete', 'Rete'], ['scada', 'Su SCADA']],
            'eventi' => [],
            'persone' => [['id' => 'p2', 'nome' => 'Roberto Merlotti', 'azienda' => 'Salpa', 'contatti' => 'roberto.merlotti@salparoseto.it']],
            'compitiProposti' => [
                ['id' => 'c65', 'persona' => 'p2', 'testo' => 'Far leggere MES_TO_PLC e PLC_TO_MES dei forni', 'macchine' => ['m05'], 'fonte' => 'SCADA visto da dentro 16/10', 'scadenza' => '2026-10-20'],
                ['id' => 'c10', 'persona' => 'p2', 'testo' => 'Compito che non c\'entra', 'fonte' => 'Mail 01/10'],
            ],
            'macchine' => [
                ['codice' => 'm05', 'num' => '05', 'nome' => 'Separatore NH3', 'fasi' => ['rete' => 'ok', 'scada' => 'corso'],
                    'progetto' => ['requisiti' => $r05,
                        'oggi' => ['aggiornato' => '2026-10-16', 'fonte' => '[S16]', 'nodi' => [['id' => 'plc', 'nome' => 'PLC 25CPU1', 'tipo' => 'plc', 'stato' => 'ok'], ['id' => 'g-scada', 'nome' => 'SCADA', 'tipo' => 'scada', 'stato' => 'ok']],
                            'collegamenti' => [['id' => 'k1', 'da' => 'plc', 'a' => 'g-scada', 'titolo' => 'Dati', 'stato' => 'ok']]]],
                    'scada' => ['visto' => '2026-10-16', 'fonte' => '[S16]', 'stato' => 'vivo', 'connessione' => 'OPC UA Nh3', 'domande' => ['Orologio del PLC al 2014: chi lo corregge?']]],
                ['codice' => 'm07', 'num' => '07', 'nome' => 'Forno 2', 'fasi' => ['scada' => 'no'],
                    'scada' => ['visto' => '2026-10-16', 'stato' => 'collegato', 'connessione' => 'OPC UA Polin_forno2', 'domande' => ['Variabili di MES_TO_PLC?']]],
                ['codice' => 'm09', 'num' => '09', 'nome' => 'Imbustatrice COMEK', 'fasi' => ['scada' => 'ok'], 'progetto' => ['requisiti' => $r09]],
                ['codice' => 'r00', 'num' => '—', 'nome' => 'Domande generali', 'escludiDaiConteggi' => true],
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-16 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_matrice_requisiti(): void
    {
        app(Sincronizzazione::class)->applica($this->file());
        $this->get('/salpa/requisiti')->assertRedirect('/login');

        $r = $this->actingAs(User::factory()->create())->get('/salpa/requisiti')->assertOk();
        $r->assertSee('Requisiti R1–R13 · Salpa')
            ->assertSee('Separatore NH3')->assertSee('Imbustatrice COMEK')->assertDontSee('Domande generali')
            ->assertSee('rq-cella s-ok', false)->assertSee('rq-cella s-na', false)->assertSee('rq-cella s-rischio', false)
            ->assertSee('ricetta scritta dallo SCADA')
            ->assertSee('/salpa/schermate/m05/nh3-05.jpg', false)
            ->assertSee('AlarmWorX64, 20 allarmi')
            ->assertSee('📎')
            // 3 macchine contate (05, 07 senza requisiti = tutti "manca", 09).
            // R12 e R13: 0 macchine ok su 3 applicabili → i piu' scoperti
            ->assertSee('Più scoperti:</b> R12 e R13: 0 macchine ok', false)
            ->assertSee('<td class="rq-tot rq-zero">0/3</td>', false)
            // R2 non applicabile per la 05: 1 ok (09) su 2 applicabili (07, 09)
            ->assertSee('<td class="rq-tot ">1/2</td>', false)
            // 05: ok R1, R9 su 12 applicabili → 17%; 09: 11 ok su 13 → 85%; 07: 0%
            ->assertSee('17%')->assertSee('85%')->assertSee('0%')
            ->assertSee(route('progetti.show', ['salpa', 'm05']), false);
    }

    public function test_domande_dallo_scada_e_bozza(): void
    {
        config(['services.anthropic.key' => '']);
        app(Sincronizzazione::class)->applica($this->file());
        $utente = User::factory()->create();

        // senza destinatario: le domande ci sono ma niente pulsante
        $r = $this->actingAs($utente)->get('/salpa/scada')->assertOk();
        $r->assertSee('Domande dallo SCADA')
            ->assertSee('Orologio del PLC al 2014: chi lo corregge?')->assertSee('Variabili di MES_TO_PLC?')
            ->assertSee('Far leggere MES_TO_PLC e PLC_TO_MES dei forni')->assertSee('c65')
            ->assertDontSee('Compito che non c\'entra')
            ->assertDontSee('Prepara bozza mail per')
            ->assertSee('info.destinatarioScada');
        $this->actingAs($utente)->postJson('/salpa/scada/bozza')->assertStatus(422);

        // con il destinatario della rubrica: pulsante e bozza (senza chiave API: 503, nessuna chiamata esterna)
        app(Sincronizzazione::class)->applica($this->file(['destinatarioScada' => 'p2']));
        $this->actingAs($utente)->get('/salpa/scada')->assertOk()->assertSee('Prepara bozza mail per Roberto Merlotti');
        $risposta = $this->actingAs($utente)->postJson('/salpa/scada/bozza')->assertStatus(503);
        $this->assertStringContainsString('ANTHROPIC_API_KEY', (string) $risposta->json('errore'));

        // un destinatario che non e' nella rubrica non vale
        app(Sincronizzazione::class)->applica($this->file(['destinatarioScada' => 'p99']));
        $this->actingAs($utente)->get('/salpa/scada')->assertDontSee('Prepara bozza mail per');
    }

    public function test_eta_dei_dati_scada(): void
    {
        app(Sincronizzazione::class)->applica($this->file());
        $utente = User::factory()->create();

        $this->actingAs($utente)->get('/salpa/scada')->assertOk()->assertSee('visto il 16/10 · oggi')->assertDontSee('dati da rivedere alla prossima visita');

        Carbon::setTestNow('2026-10-17 09:00:00');
        $this->actingAs($utente)->get('/salpa/scada')->assertSee('· ieri');

        Carbon::setTestNow('2026-10-30 09:00:00');
        $this->actingAs($utente)->get('/salpa/scada')->assertSee('14 giorni fa')->assertSee('dati da rivedere alla prossima visita')->assertSee('sc-vecchio', false);
        $this->actingAs($utente)->get('/salpa/progetti/m05')->assertOk()->assertSee('pj-vecchio', false)->assertSee('dati da rivedere alla prossima visita');
        $this->actingAs($utente)->get('/salpa/progetti/m05/mappa?vista=oggi')->assertOk()->assertSee('pj-vecchio', false);
        $this->actingAs($utente)->get('/salpa/impianto')->assertOk()->assertSee('pj-vecchio', false)->assertSee('14 giorni fa');
        // la vista Obiettivo non diventa grigia
        $this->actingAs($utente)->get('/salpa/progetti/m05?vista=obiettivo')->assertOk()->assertDontSee('pj-mappa-card pj-vecchio', false);
    }

    public function test_quadro_badge_scada_e_contatore_dati_vivi(): void
    {
        app(Sincronizzazione::class)->applica($this->file());
        $r = $this->actingAs(User::factory()->create())->get('/salpa')->assertOk();
        // contatore: solo la 05 ha dati vivi, su 3 macchine contate
        $r->assertSee('<b>1/3</b><span>con dati vivi</span>', false)
            ->assertSee('sc-badge sc-mini sc-vivo', false)
            ->assertSee('sc-badge sc-mini sc-collegato', false)
            ->assertSee('Su SCADA (visto dentro lo SCADA)');
    }

    public function test_quadro_senza_dati_scada_resta_come_prima(): void
    {
        $f = $this->file();
        foreach ($f['macchine'] as &$m) {
            unset($m['scada']);
        }
        app(Sincronizzazione::class)->applica($f);
        $r = $this->actingAs(User::factory()->create())->get('/salpa')->assertOk();
        // la 09 ha la fase scada "ok": contatore sulle fasi, etichetta del file
        $r->assertSee('<b>1/3</b><span>su SCADA</span>', false)->assertDontSee('sc-mini', false);
    }
}
