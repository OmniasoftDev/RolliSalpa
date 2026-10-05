<?php

namespace Tests\Feature;

use App\Models\Appuntamento;
use App\Models\Compito;
use App\Models\Persona;
use App\Models\Project;
use App\Models\User;
use App\Services\BozzaMail;
use App\Services\LavoroFrancesco;
use App\Services\Sincronizzazione;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LavoroTest extends TestCase
{
    use RefreshDatabase;

    private Project $progetto;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 10:00'); // lunedi'
        app(Sincronizzazione::class)->applica([
            'progetto' => 'salpa', 'nome' => 'Salpa', 'fasi' => [['rete', 'Rete']],
            'macchine' => [
                ['codice' => 'm01', 'num' => '01', 'nome' => 'Surgelatore Gyro', 'chiedere' => [
                    ['id' => 'q1', 'chi' => 'Andrea Uberti (JBT)', 'cosa' => 'Protocollo verso lo SCADA?', 'fatto' => false],
                ]],
                ['codice' => 'm04', 'num' => '04', 'nome' => 'Impianto cottura Boule', 'chiedere' => [
                    ['id' => 'q2', 'chi' => 'CFT (tramite Merlotti)', 'cosa' => 'Verbale di collaudo', 'fatto' => false],
                    ['id' => 'q3', 'chi' => 'Merlotti', 'cosa' => 'Esito call del 03/09', 'fatto' => true],
                ]],
            ],
            'eventi' => [
                ['id' => 'e20261004-1427', 'data' => '2026-10-04', 'ora' => '14:27', 'tipo' => 'mail', 'chi' => 'Uberti', 'macchine' => ['m01'], 'testo' => 'Le date arrivano domani'],
            ],
            'persone' => [
                ['id' => 'p0', 'nome' => 'Francesco Guerrieri', 'azienda' => 'Omniasoft', 'gruppo' => 'Omniasoft', 'contatti' => 'info@omniasoft.it'],
                ['id' => 'p2', 'nome' => 'Roberto Merlotti', 'azienda' => 'Salpa', 'gruppo' => 'Salpa', 'contatti' => 'roberto.merlotti@salparoseto.it'],
                ['id' => 'p4', 'nome' => 'IT Salpa', 'azienda' => 'Salpa', 'gruppo' => 'Salpa'],
                ['id' => 'p5', 'nome' => 'Andrea Calabretta', 'azienda' => 'Edica', 'gruppo' => 'Edica', 'contatti' => 'andrea.calabretta@edica.it'],
                ['id' => 'p9', 'nome' => 'Andrea Uberti', 'azienda' => 'JBT', 'gruppo' => 'Fornitori', 'contatti' => 'Tel. 0123 / andrea.uberti@jbtmarel.com'],
            ],
            'decisioni' => [['id' => 'd2', 'testo' => 'Protocollo dei surgelatori JBT', 'fonte' => 'Spec PRoLINK', 'macchine' => ['m01'], 'data' => '2026-10-01', 'fatta' => false]],
        ]);
        $this->progetto = Project::where('slug', 'salpa')->firstOrFail();
        $base = ['project_id' => $this->progetto->id, 'stato' => 'aperto', 'macchine' => []];
        Compito::create($base + ['persona' => 'p0', 'testo' => 'Preparare la relazione settimanale', 'scadenza' => '2026-10-09', 'assegnato_il' => '2026-10-03']);
        Compito::create($base + ['persona' => 'p5', 'testo' => 'Fissare il giorno del giro', 'scadenza' => '2026-10-02', 'assegnato_il' => '2026-10-01']);
        Compito::create($base + ['persona' => 'p2', 'testo' => 'Sollecitare CFT', 'macchine' => ['m04'], 'assegnato_il' => '2026-10-04']);
    }

    public function test_la_pagina_mostra_il_lavoro_di_francesco_e_il_calendario(): void
    {
        $this->actingAs(User::factory()->create())->get('/salpa/lavoro')->assertOk()
            ->assertSee('Il mio lavoro · Salpa')
            ->assertSee('Preparare la relazione settimanale')
            ->assertSee('Protocollo dei surgelatori JBT')
            ->assertSee('Sollecitare Andrea Calabretta: Fissare il giorno del giro')
            ->assertSee('Verificare Roberto Merlotti: Sollecitare CFT')
            ->assertSee('Protocollo verso lo SCADA?')
            ->assertDontSee('Esito call del 03/09')
            // si accetta con data e ora; nessun link diretto a Google Calendar
            ->assertSee('data-accetta', false)
            ->assertSee('type="time"', false)
            ->assertDontSee('calendar.google.com', false);

        // dal quadro si arriva alla pagina
        $this->get('/salpa')->assertOk()->assertSee('Il mio lavoro');
    }

    public function test_accettare_una_voce_del_calendario_la_manda_al_pc(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/salpa/lavoro/appuntamento', ['chiave' => 'c12', 'titolo' => 'Preparare la relazione settimanale', 'data' => '2026-10-09', 'ora' => '10:15'])
            ->assertOk()->assertJson(['quando' => 'ven 09/10 10:15–10:45']);

        $a = Appuntamento::where('codice', 'cal-c12')->firstOrFail();
        $this->assertSame(['accettato', 'lavoro'], [$a->stato, $a->origine]);

        $spunte = $this->withToken('token-di-prova-lungo-almeno-trentadue-caratteri')->getJson('/api/spunte')->assertOk()->json('appuntamenti');
        $this->assertSame('cal-c12', $spunte[0]['id']);
        $this->assertSame('2026-10-09T10:15:00+02:00', $spunte[0]['inizio']);
        $this->assertSame('2026-10-09T10:45:00+02:00', $spunte[0]['fine']);

        // riaccettata con un'altra ora: stessa voce, orario aggiornato
        $this->postJson('/salpa/lavoro/appuntamento', ['chiave' => 'c12', 'titolo' => 'Preparare la relazione settimanale', 'data' => '2026-10-09', 'ora' => '15:00'])->assertOk();
        $this->assertSame(1, Appuntamento::count());
        $this->assertSame('15:00', Appuntamento::first()->inizio->format('H:i'));
    }

    public function test_appuntamenti_proposti_dal_pc_si_accettano_o_rifiutano(): void
    {
        $file = fn (array $appuntamenti) => app(Sincronizzazione::class)->applica(['progetto' => 'salpa', 'nome' => 'Salpa', 'macchine' => [], 'eventi' => [], 'appuntamenti' => $appuntamenti]);
        $file([
            ['id' => 'ap1', 'titolo' => 'Salpa - Attivazione 4.0 tunnel JBT', 'inizio' => '2026-10-14T10:00:00+02:00', 'fine' => '2026-10-14T12:30:00+02:00', 'luogo' => 'Microsoft Teams', 'fonte' => 'Invito 05/10 Merlotti', 'macchine' => ['m01']],
            ['id' => 'ap2', 'titolo' => 'Salpa - Call Polin', 'inizio' => '2026-10-15T11:00:00+02:00', 'fine' => '2026-10-15T11:30:00+02:00'],
        ]);
        $this->actingAs(User::factory()->create())->get('/salpa/lavoro')->assertOk()
            ->assertSee('Salpa - Attivazione 4.0 tunnel JBT')->assertSee('mer 14/10 10:00–12:30');

        $jbt = Appuntamento::where('codice', 'ap1')->firstOrFail();
        // accettato spostando la fine; la call Polin rifiutata
        $this->postJson('/appuntamenti/'.$jbt->id, ['stato' => 'accettato', 'data' => '2026-10-14', 'inizio' => '10:00', 'fine' => '12:00'])->assertOk();
        $this->postJson('/appuntamenti/'.Appuntamento::where('codice', 'ap2')->value('id'), ['stato' => 'rifiutato'])->assertOk();
        $this->postJson('/appuntamenti/'.$jbt->id, ['stato' => 'accettato', 'data' => '2026-10-14', 'inizio' => '12:00', 'fine' => '10:00'])->assertStatus(422);

        // il file non cambia piu' le scelte di Francesco, e un proposto sparito dal file sparisce
        $file([['id' => 'ap1', 'titolo' => 'Salpa - JBT (cambiato dal PC)', 'inizio' => '2026-10-14T08:00:00+02:00', 'fine' => '2026-10-14T09:00:00+02:00']]);
        $jbt->refresh();
        $this->assertSame(['accettato', '12:00'], [$jbt->stato, $jbt->fine->format('H:i')]);
        $this->assertSame('rifiutato', Appuntamento::where('codice', 'ap2')->value('stato'));

        $spunte = collect($this->withToken('token-di-prova-lungo-almeno-trentadue-caratteri')->getJson('/api/spunte')->json('appuntamenti'))->keyBy('id');
        $this->assertSame('accettato', $spunte['ap1']['stato']);
        $this->assertSame('2026-10-14T12:00:00+02:00', $spunte['ap1']['fine']);
        $this->assertSame('rifiutato', $spunte['ap2']['stato']);
    }

    public function test_appuntamento_accettato_per_mail_arriva_gia_accettato_dal_file(): void
    {
        app(Sincronizzazione::class)->applica(['progetto' => 'salpa', 'nome' => 'Salpa', 'macchine' => [], 'eventi' => [], 'appuntamenti' => [
            ['id' => 'ap3', 'titolo' => 'Salpa - Riunione', 'inizio' => '2026-10-07T09:45:00+02:00', 'fine' => '2026-10-07T10:15:00+02:00', 'stato' => 'accettato'],
            ['id' => 'ap4', 'titolo' => 'Senza fine', 'inizio' => '2026-10-07T09:45:00+02:00'],
        ]]);
        $this->assertSame('accettato', Appuntamento::where('codice', 'ap3')->value('stato'));
        $this->assertNull(Appuntamento::where('codice', 'ap4')->first());
    }

    public function test_date_proposte_dal_calendario(): void
    {
        $voci = app(LavoroFrancesco::class)->per($this->progetto)['calendario']->keyBy('titolo');

        // scadenza futura: quel giorno; scaduta: oggi; senza scadenza: una settimana dopo l'assegnazione (domenica 11 -> lunedi' 12)
        $this->assertSame('2026-10-09', $voci['Preparare la relazione settimanale']['data']->format('Y-m-d'));
        $this->assertSame('2026-10-05', $voci['Sollecitare Andrea Calabretta: Fissare il giorno del giro']['data']->format('Y-m-d'));
        $this->assertSame('2026-10-12', $voci['Verificare Roberto Merlotti: Sollecitare CFT']['data']->format('Y-m-d'));
        $this->assertSame('2026-10-05', $voci['Decidere: Protocollo dei surgelatori JBT']['data']->format('Y-m-d'));
    }

    public function test_destinatario_delle_domande_dal_campo_chi(): void
    {
        $lavoro = app(LavoroFrancesco::class);
        $persone = Persona::all()->keyBy('codice');

        $this->assertSame('p9', $lavoro->personaDaChi('Andrea Uberti (JBT)', $persone)?->codice);
        $this->assertSame('p2', $lavoro->personaDaChi('CFT (tramite Merlotti)', $persone)?->codice);
        $this->assertSame('p2', $lavoro->personaDaChi('Merlotti / IT Salpa', $persone)?->codice);
        $this->assertSame('p5', $lavoro->personaDaChi('Edica', $persone)?->codice);
        $this->assertNull($lavoro->personaDaChi('Deloitte', $persone));
        $this->assertSame('andrea.uberti@jbtmarel.com', LavoroFrancesco::mailDi($persone['p9']));
    }

    public function test_bozza_scritta_da_claude_con_indirizzo_dalla_rubrica(): void
    {
        $compito = Compito::where('persona', 'p5')->firstOrFail();
        $this->mock(BozzaMail::class, function ($mock) {
            $mock->shouldReceive('scrivi')->once()->withArgs(function ($progetto, $persona, $scopo, $voci, $eventi, $nota) {
                return $persona->codice === 'p5' && $scopo === 'sollecito' && $voci[0]['testo'] === 'Fissare il giorno del giro'
                    && $voci[0]['scaduto'] === true && $voci[1]['macchine'] === '01 Surgelatore Gyro' && $nota === 'Domani sono in sede';
            })->andReturn(['oggetto' => 'Giro sulle macchine Salpa', 'corpo' => "Ciao Andrea,\n...\nFrancesco Guerrieri"]);
        });
        $domanda = \App\Models\Question::where('chiave', 'q1')->firstOrFail();

        $this->actingAs(User::factory()->create())->postJson('/salpa/lavoro/bozza', [
            'persona' => 'p5', 'scopo' => 'sollecito', 'compiti' => [$compito->id], 'domande' => [$domanda->id], 'nota' => 'Domani sono in sede',
        ])->assertOk()
            ->assertJsonPath('oggetto', 'Giro sulle macchine Salpa')
            ->assertJsonPath('a', 'andrea.calabretta@edica.it');
    }

    public function test_bozza_senza_chiave_o_senza_punti(): void
    {
        config(['services.anthropic.key' => '']);
        $this->actingAs(User::factory()->create());

        $this->postJson('/salpa/lavoro/bozza', ['scopo' => 'richiesta'])->assertStatus(422);
        $this->postJson('/salpa/lavoro/bozza', ['scopo' => 'richiesta', 'nota' => 'Aggiornamento sul giro'])
            ->assertStatus(503)->assertJsonPath('errore', 'Bozze non disponibili: manca ANTHROPIC_API_KEY nel .env del server.');
    }

    public function test_separa_oggetto_e_corpo(): void
    {
        $this->assertSame(['oggetto' => 'Tabelle forni', 'corpo' => "Ciao,\ngrazie"], BozzaMail::separa("Oggetto: Tabelle forni\n\nCiao,\ngrazie"));
        $this->assertSame(['oggetto' => '', 'corpo' => 'Solo testo'], BozzaMail::separa('Solo testo'));
    }
}
