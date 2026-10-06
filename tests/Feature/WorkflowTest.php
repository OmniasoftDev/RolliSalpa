<?php

namespace Tests\Feature;

use App\Models\Compito;
use App\Models\Project;
use App\Models\User;
use App\Services\Sincronizzazione;
use App\Services\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-di-prova-lungo-almeno-trentadue-caratteri';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-06 10:00'); // martedi'
        app(Sincronizzazione::class)->applica([
            'progetto' => 'salpa', 'nome' => 'Salpa', 'info' => ['scadenza' => '2026-11-30'],
            'macchine' => [
                ['codice' => 'm01', 'num' => '01', 'nome' => 'Surgelatore Gyro'],
                ['codice' => 'm09', 'num' => '09', 'nome' => 'Imbustatrice Block Frozen'],
            ],
            'eventi' => [
                ['id' => 'e20261005-1453', 'data' => '2026-10-05', 'ora' => '14:53', 'tipo' => 'mail', 'chi' => 'Merlotti', 'macchine' => ['m09'], 'testo' => 'Comek è tutto funzionante e testato'],
                ['id' => 'e20261001-1200', 'data' => '2026-10-01', 'ora' => '12:00', 'tipo' => 'decisione', 'testo' => 'Non e\' una mail'],
            ],
            'persone' => [['id' => 'p5', 'nome' => 'Andrea Calabretta', 'azienda' => 'Edica']],
            'planning' => [
                ['id' => 'pl1', 'titolo' => 'Attivazione tunnel JBT', 'dal' => '2026-10-12', 'al' => '2026-10-16', 'macchine' => ['m01'], 'stato' => 'fisso', 'fonte' => 'Invito 05/10 Merlotti'],
                ['id' => 'pl2', 'titolo' => 'Verifica requisiti di tutte le macchine', 'dal' => '2026-11-09', 'al' => '2026-11-13'],
                ['id' => 'pl3', 'titolo' => 'Fase senza date'],
            ],
            'appuntamenti' => [
                ['id' => 'ap1', 'titolo' => 'Salpa - Giro macchine con Calabretta', 'inizio' => '2026-10-07T10:15:00+02:00', 'fine' => '2026-10-07T12:30:00+02:00', 'macchine' => ['m01', 'm09'], 'stato' => 'accettato'],
                ['id' => 'ap2', 'titolo' => 'Salpa - Call Polin', 'inizio' => '2026-10-15T11:00:00+02:00', 'fine' => '2026-10-15T11:30:00+02:00'],
            ],
        ]);
        app(Sincronizzazione::class)->applica(['progetto' => 'rolli', 'nome' => 'Rolli', 'macchine' => [], 'eventi' => []]);
        $salpa = Project::where('slug', 'salpa')->firstOrFail();
        Compito::create(['project_id' => $salpa->id, 'persona' => 'p5', 'testo' => 'Mostrare cosa legge lo SCADA dalla COMEK', 'macchine' => ['m09'], 'scadenza' => '2026-10-07', 'stato' => 'aperto', 'assegnato_il' => '2026-10-05']);
        Compito::create(['project_id' => $salpa->id, 'persona' => 'p5', 'testo' => 'Compito chiuso', 'scadenza' => '2026-10-08', 'stato' => 'fatto']);
    }

    private function calendarioDalPc(array $eventi): void
    {
        $this->withToken(self::TOKEN)->postJson('/api/stato', ['controllo' => '2026-10-06 10:05', 'calendario' => ['letto' => '2026-10-06 10:05', 'eventi' => $eventi]])->assertOk();
    }

    public function test_la_pagina_mostra_fasi_appuntamenti_google_scadenze_e_mail(): void
    {
        $this->calendarioDalPc([
            ['progetto' => 'salpa', 'uid' => 'abc@google.com', 'titolo' => 'Preparazione giro macchine', 'inizio' => '2026-10-06T15:00:00+02:00', 'fine' => '2026-10-06T18:00:00+02:00'],
            // lo stesso del giro gia' accettato sul pannello: non si ripete
            ['progetto' => 'salpa', 'uid' => 'giro@google.com', 'titolo' => 'Salpa Giro Macchine', 'inizio' => '2026-10-07T10:15:00+02:00', 'fine' => '2026-10-07T12:30:00+02:00'],
            ['progetto' => 'rolli', 'uid' => 'r@google.com', 'titolo' => 'Sopralluogo Rolli', 'inizio' => '2026-10-08T09:00:00+02:00', 'fine' => '2026-10-08T10:00:00+02:00'],
        ]);

        $this->actingAs(User::factory()->create())->get('/salpa/workflow')->assertOk()
            ->assertSee('Workflow · Salpa')
            ->assertSee('Ottobre 2026')
            ->assertSee('Attivazione tunnel JBT')
            ->assertSee('Salpa - Giro macchine con Calabretta')
            ->assertSee('Salpa - Call Polin')
            ->assertSee('Preparazione giro macchine')
            ->assertDontSee('Salpa Giro Macchine')
            ->assertDontSee('Sopralluogo Rolli')
            ->assertSee('Andrea Calabretta: Mostrare cosa legge lo SCADA dalla COMEK')
            ->assertDontSee('Compito chiuso')
            ->assertSee('Comek è tutto funzionante e testato')
            ->assertDontSee('Non e\' una mail')
            ->assertDontSee('Fase senza date')
            ->assertSee('Timeline per macchina')
            ->assertSee('/salpa?m=m09', false)
            ->assertSee('letto dal PC il 06/10 10:05');

        // gli eventi Google di Rolli stanno solo in Rolli
        $this->get('/rolli/workflow')->assertOk()->assertSee('Sopralluogo Rolli')->assertDontSee('Preparazione giro macchine');
        // dal menu del progetto
        $this->get('/salpa')->assertOk()->assertSee('/salpa/workflow', false);
    }

    public function test_voci_e_intervallo_della_timeline(): void
    {
        $progetto = Project::where('slug', 'salpa')->firstOrFail();
        $workflow = app(Workflow::class);
        $voci = $workflow->voci($progetto);

        $this->assertEquals(['fase' => 2, 'appuntamento' => 2, 'scadenza' => 1, 'mail' => 1], $voci->countBy('tipo')->all());
        $fase = $voci->firstWhere('chiave', 'f-pl1');
        $this->assertSame(['2026-10-12', '2026-10-16', 'fisso', ['m01']], [$fase['inizio']->format('Y-m-d'), $fase['fine']->format('Y-m-d'), $fase['stato'], $fase['macchine']]);
        $this->assertSame('10:15–12:30', $voci->firstWhere('chiave', 'a-'.$progetto->appuntamenti()->where('codice', 'ap1')->value('id'))['ora']);

        // da lunedi' della settimana scorsa alla domenica della settimana della scadenza (lun 30/11)
        [$dal, $al] = $workflow->intervallo($progetto, $voci);
        $this->assertSame(['2026-09-28', '2026-12-06'], [$dal->format('Y-m-d'), $al->format('Y-m-d')]);
    }

    public function test_il_mese_si_sceglie_e_lo_stato_senza_calendario_non_cancella_gli_eventi_google(): void
    {
        $this->calendarioDalPc([['progetto' => 'salpa', 'uid' => 'x', 'titolo' => 'Evento di novembre', 'inizio' => '2026-11-10T09:00:00+01:00', 'fine' => '2026-11-10T10:00:00+01:00']]);
        // un /api/stato senza il campo calendario (es. invio a mano) lascia gli eventi letti prima
        $this->withToken(self::TOKEN)->postJson('/api/stato', ['controllo' => '2026-10-06 10:10'])->assertOk();

        $this->actingAs(User::factory()->create())->get('/salpa/workflow?mese=2026-11')->assertOk()
            ->assertSee('Novembre 2026')->assertSee('Evento di novembre')->assertSee('Verifica requisiti di tutte le macchine');
        $this->get('/salpa/workflow?mese=sbagliato')->assertOk()->assertSee('Ottobre 2026');
    }
}
