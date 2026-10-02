<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\Project;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PannelloTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-di-prova-lungo-almeno-trentadue-caratteri';

    private function progetto(array $cambi = []): array
    {
        return array_replace_recursive([
            'progetto' => 'rolli',
            'nome' => 'Rolli',
            'ordine' => 2,
            'info' => ['mail' => '02/10/2026 10:59', 'scadenza' => '2026-11-30', 'contatori' => [['tipo' => 'domande', 'etichetta' => 'domande aperte']]],
            'fasi' => [['rete', 'Rete'], ['opcua', 'Server OPC UA']],
            'macchine' => [
                ['codice' => 'r00', 'ordine' => 0, 'num' => '—', 'nome' => 'Domande generali', 'escludiDaiConteggi' => true, 'fasi' => ['rete' => 'na'],
                    'chiedere' => [['id' => 'scada', 'chi' => 'Sarmati', 'cosa' => 'SCADA dedicato?', 'fatto' => false]]],
                ['codice' => 'r01', 'ordine' => 1, 'num' => '01', 'nome' => 'Imbustatrice COMEK', 'fasi' => ['rete' => 'no', 'opcua' => 'rischio'],
                    'note' => [['testo' => 'Investimento 2023', 'fonte' => 'Layout']], 'chiedere' => []],
            ],
            'eventi' => [
                ['id' => 'e20261002-1056', 'data' => '2026-10-02', 'ora' => '10:56', 'tipo' => 'mail', 'chi' => 'Sarmati', 'macchine' => ['r00'], 'testo' => 'Sopralluogo martedì'],
            ],
        ], $cambi);
    }

    private function sync(array $dati)
    {
        return $this->withToken(self::TOKEN)->postJson('/api/sync', $dati);
    }

    public function test_senza_token_la_sincronizzazione_e_rifiutata(): void
    {
        $this->postJson('/api/sync', $this->progetto())->assertStatus(401);
        $this->withToken('sbagliato')->getJson('/api/spunte')->assertStatus(401);
    }

    public function test_sincronizzazione_crea_progetto_macchine_domande_eventi(): void
    {
        $this->sync($this->progetto())->assertOk()->assertJson(['ok' => true, 'macchine' => 2, 'domande' => 1, 'eventi' => 1]);

        $p = Project::where('slug', 'rolli')->firstOrFail();
        $this->assertSame('02/10/2026 10:59', $p->info('mail'));
        $this->assertSame(2, $p->machines()->count());
        $this->assertSame('rischio', Machine::where('codice', 'r01')->first()->fase('opcua'));
        $this->assertSame('2026-10-02 10:56', $p->events()->first()->chiave);
    }

    public function test_cio_che_sparisce_dal_file_sparisce_dal_pannello(): void
    {
        $this->sync($this->progetto());
        $dati = $this->progetto();
        unset($dati['macchine'][1]);
        $dati['eventi'] = [];
        $this->sync($dati)->assertOk();

        $this->assertSame(['r00'], Machine::pluck('codice')->all());
        $this->assertSame(0, Project::first()->events()->count());
    }

    public function test_la_spunta_dal_browser_vince_sul_file_e_si_legge_da_api_spunte(): void
    {
        $this->sync($this->progetto());
        $q = Question::where('chiave', 'scada')->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->postJson("/domande/{$q->id}", ['fatto' => true])->assertOk();

        // il file dice ancora "non fatto": la spunta del web resta
        $this->sync($this->progetto())->assertOk();
        $this->assertTrue($q->fresh()->fatto);

        $this->withToken(self::TOKEN)->getJson('/api/spunte')->assertOk()
            ->assertJsonPath('spunte.0.progetto', 'rolli')
            ->assertJsonPath('spunte.0.macchina', 'r00')
            ->assertJsonPath('spunte.0.id', 'scada')
            ->assertJsonPath('spunte.0.fatto', true);
    }

    public function test_progetto_non_valido_risponde_422(): void
    {
        $this->sync(['progetto' => 'NO VALIDO', 'macchine' => []])->assertStatus(422);
        $this->sync($this->progetto(['macchine' => [['codice' => 'con spazi']]]))->assertStatus(422);
    }

    public function test_il_pannello_richiede_login_e_mostra_il_progetto(): void
    {
        $this->sync($this->progetto());
        $this->get('/rolli')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())->get('/rolli')->assertOk()
            ->assertSee('Imbustatrice COMEK')
            ->assertSee('SCADA dedicato?')
            ->assertSee('Sopralluogo martedì');
        $this->get('/')->assertRedirect('/rolli');
    }

    public function test_striscia_ultimi_aggiornamenti_dal_pc(): void
    {
        $this->sync($this->progetto());
        $this->postJson('/api/stato', ['controllo' => '2026-10-02 14:00'])->assertStatus(401);
        $this->withToken(self::TOKEN)->postJson('/api/stato', [
            'controllo' => '2026-10-02 14:00', 'mail' => '2026-10-02 11:20', 'rolling' => '2026-10-02 14:00', 'rollingVersione' => 'v19',
        ])->assertOk();

        $this->actingAs(User::factory()->create())->get('/rolli')->assertOk()
            ->assertSee('Ultimo controllo PC')
            ->assertSee('02/10 11:20')
            ->assertSee('v19');
    }

    public function test_login(): void
    {
        $u = User::factory()->create(['email' => 'f@omniasoft.it', 'password' => 'segreta-123']);
        $this->post('/login', ['email' => 'f@omniasoft.it', 'password' => 'sbagliata'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'f@omniasoft.it', 'password' => 'segreta-123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
    }
}
