<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Sincronizzazione;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScadaTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-di-prova-lungo-almeno-trentadue-caratteri';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $variabili = [];
        for ($i = 1; $i <= 18; $i++) {
            $variabili[] = ['nome' => 'POMPA_P'.$i.'_STATO', 'valore' => '3', 'tipo' => 'Int16', 'accesso' => 'R', 'qualita' => 'Good'];
        }
        $variabili[] = ['nome' => 'Ossigeno', 'valore' => '', 'tipo' => 'String', 'accesso' => 'R/W', 'qualita' => 'Bad - Communication Error', 'nota' => 'dalle 12:02'];
        app(Sincronizzazione::class)->applica([
            'progetto' => 'salpa', 'nome' => 'Salpa', 'ordine' => 1, 'fasi' => [['rete', 'Rete']], 'eventi' => [],
            'macchine' => [
                ['codice' => 'm05', 'num' => '05', 'nome' => 'Separatore NH3', 'fornitore' => 'CEA',
                    'certificazione' => ['tappe' => [['id' => 't3', 'titolo' => 'Collegata allo SCADA', 'stato' => 'ok']]],
                    'scada' => [
                        'visto' => '2026-10-16', 'fonte' => '[S16]', 'stato' => 'vivo',
                        'connessione' => 'OPC UA "Nh3" — CPU Siemens 25CPU1',
                        'sintesi' => 'Dati vivi, orologio del PLC al 2014.',
                        'variabili' => $variabili,
                        'storico' => 'Solo la valvola EOV28 nel gruppo OEE.',
                        'pagine' => [['nome' => 'Gestione Ammoniaca.gdfx', 'data' => '2026-09-24']],
                        'schermate' => [['file' => 'nh3-05.jpg', 'titolo' => 'Pagina Impianto Nh3', 'data' => '2026-10-16 13:55'], ['file' => 'mancante.jpg', 'titolo' => 'Non caricata']],
                        'domande' => ['Rimettere l\'ora del PLC'],
                    ]],
                ['codice' => 'm04', 'num' => '04', 'nome' => 'Impianto cottura Boule'],
            ],
            'sistemi' => [['codice' => 's1', 'nome' => 'SCADA Edica', 'tipo' => 'scada', 'schermate' => [['file' => 'homepage.jpg', 'titolo' => 'Homepage SCADA']]]],
        ]);
        app(Sincronizzazione::class)->applica([
            'progetto' => 'rolli', 'nome' => 'Rolli', 'ordine' => 2, 'eventi' => [],
            'macchine' => [['codice' => 'r04', 'num' => '04', 'nome' => 'Soffianti Rolli', 'scada' => ['stato' => 'preparato', 'connessione' => 'Modbus Ingeco']]],
        ]);
    }

    public function test_api_schermate_richiede_il_token(): void
    {
        $this->postJson('/api/schermate', [])->assertStatus(401);
        $this->getJson('/api/schermate?progetto=salpa')->assertStatus(401);
    }

    public function test_api_schermate_codice_inesistente_422(): void
    {
        $this->withToken(self::TOKEN)->post('/api/schermate', [
            'progetto' => 'salpa', 'codice' => 'm99', 'file' => UploadedFile::fake()->image('x.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
        $this->withToken(self::TOKEN)->post('/api/schermate', [
            'progetto' => 'nessuno', 'codice' => 'm05', 'file' => UploadedFile::fake()->image('x.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_upload_elenco_e_file_servito_solo_autenticati(): void
    {
        $this->withToken(self::TOKEN)->post('/api/schermate', [
            'progetto' => 'salpa', 'codice' => 'm05', 'file' => UploadedFile::fake()->image('nh3-05.jpg', 640, 360),
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['ok' => true, 'percorso' => 'schermate/salpa/m05/nh3-05.jpg']);
        // idempotente: lo stesso nome sovrascrive
        $this->withToken(self::TOKEN)->post('/api/schermate', [
            'progetto' => 'salpa', 'codice' => 'm05', 'file' => UploadedFile::fake()->image('nh3-05.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();
        // anche per un sistema
        $this->withToken(self::TOKEN)->post('/api/schermate', [
            'progetto' => 'salpa', 'codice' => 's1', 'file' => UploadedFile::fake()->image('home page (1).png'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['percorso' => 'schermate/salpa/s1/home_page__1_.png']);
        Storage::disk('local')->assertExists('schermate/salpa/m05/nh3-05.jpg');

        $this->withToken(self::TOKEN)->getJson('/api/schermate?progetto=salpa')->assertOk()
            ->assertJsonPath('schermate.m05', ['nh3-05.jpg'])
            ->assertJsonPath('schermate.s1', ['home_page__1_.png']);

        $this->get('/salpa/schermate/m05/nh3-05.jpg')->assertRedirect('/login');
        $utente = User::factory()->create();
        $this->actingAs($utente)->get('/salpa/schermate/m05/nh3-05.jpg')->assertOk();
        $this->actingAs($utente)->get('/salpa/schermate/m05/manca.jpg')->assertNotFound();
        $this->actingAs($utente)->get('/salpa/schermate/m05/..%2F..%2F.env')->assertNotFound();
    }

    public function test_blocco_sullo_scada_nella_pagina_progetto(): void
    {
        Storage::disk('local')->put('schermate/salpa/m05/nh3-05.jpg', 'x');
        $this->actingAs(User::factory()->create())->get('/salpa/progetti/m05')->assertOk()
            ->assertSee('Sullo SCADA')
            ->assertSee('dati vivi')
            ->assertSee('CPU Siemens 25CPU1')
            ->assertSee('POMPA_P1_STATO')
            ->assertSee('mostra tutte (19)')
            ->assertSee('Bad - Communication Error')
            ->assertSee('Solo la valvola EOV28')
            ->assertSee('Gestione Ammoniaca.gdfx')
            ->assertSee('/salpa/schermate/m05/nh3-05.jpg')
            ->assertSee('schermata non ancora caricata')
            ->assertSee('Rimettere l&#039;ora del PLC', false);
    }

    public function test_pagina_scada_separata_per_progetto_e_righe_brevi(): void
    {
        $utente = User::factory()->create();
        $this->actingAs($utente)->get('/salpa/scada')->assertOk()
            ->assertSee('Sullo SCADA · Salpa')
            ->assertSee('Separatore NH3')
            ->assertSee('19 variabili')
            ->assertSee('Homepage SCADA', false)
            ->assertSee('Nessun dato SCADA per questa macchina.')
            ->assertDontSee('Soffianti Rolli');
        $this->actingAs($utente)->get('/rolli/scada')->assertOk()
            ->assertSee('Soffianti Rolli')->assertSee('tag preparati, vuoti')->assertDontSee('Separatore NH3');

        $this->actingAs($utente)->get('/salpa')->assertOk()->assertSee('SCADA 16/10:')->assertSee('/salpa/progetti/m05#scada');
        $this->actingAs($utente)->get('/salpa/certificazione')->assertOk()->assertSee('SCADA 16/10:');
        $this->actingAs($utente)->get('/salpa/sistemi/s1')->assertOk()->assertSee('Schermate dal PC');
    }
}
