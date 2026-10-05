<?php

namespace Tests\Feature;

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
            ->assertSee('calendar.google.com/calendar/render?action=TEMPLATE', false)
            // a orario preciso (9:00-9:30, ora di Roma), nel calendario Salpa-Rolli: mai eventi di tutto il giorno
            ->assertSee('20261009T090000%2F20261009T093000', false)
            ->assertSee('ctz=Europe%2FRome', false)
            ->assertSee('src=15d308d04bcc0b634523e7a3fe5ea816fc2ddfb6a69286adc4e2ae30ad027361%40group.calendar.google.com', false)
            ->assertSee('type="time"', false);

        // dal quadro si arriva alla pagina
        $this->get('/salpa')->assertOk()->assertSee('Il mio lavoro');
    }

    public function test_link_calendario_con_ora_scelta(): void
    {
        $link = LavoroFrancesco::linkCalendario('Giro macchine', \Illuminate\Support\Carbon::parse('2026-10-07'), 'Fonte: invito', '10:15');

        $this->assertStringContainsString('dates=20261007T101500%2F20261007T104500', $link);
        $this->assertStringContainsString('ctz=Europe%2FRome', $link);
        $this->assertStringNotContainsString('dates=20261007%2F20261008', $link);
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
