<?php

namespace Tests\Feature;

use App\Models\Appunto;
use App\Models\User;
use App\Services\Sincronizzazione;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppuntiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-di-prova-lungo-almeno-trentadue-caratteri';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        app(Sincronizzazione::class)->applica([
            'progetto' => 'rolli', 'nome' => 'Rolli', 'fasi' => [['rete', 'Rete']],
            'macchine' => [['codice' => 'r01', 'num' => '01', 'nome' => 'Imbustatrice COMEK', 'chiedere' => []]],
            'eventi' => [],
        ]);
    }

    public function test_appunto_con_foto_dal_sito_poi_scaricato_ed_elaborato_dal_pc(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Francesco']))
            ->post('/rolli/appunti', [
                'tipo' => 'sopralluogo',
                'testo' => "Targa COMEK: PLC B&R X20, porta Ethernet libera.",
                'macchine' => ['r01'],
                'allegati' => [UploadedFile::fake()->image('targa.jpg', 800, 600)],
            ], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['ok' => true]);

        $a = Appunto::with('allegati')->firstOrFail();
        $this->assertSame(['r01'], $a->macchine);
        Storage::disk('local')->assertExists($a->allegati[0]->percorso);

        // il pannello lo mostra, "da elaborare"
        $this->get('/rolli')->assertOk()->assertSee('PLC B&amp;R X20', false)->assertSee('da elaborare');

        // il PC lo scarica con il token
        $this->withToken(self::TOKEN)->getJson('/api/appunti')->assertOk()
            ->assertJsonPath('appunti.0.progetto', 'rolli')
            ->assertJsonPath('appunti.0.tipo', 'sopralluogo')
            ->assertJsonPath('appunti.0.macchine.0', 'r01')
            ->assertJsonPath('appunti.0.autore', 'Francesco')
            ->assertJsonPath('appunti.0.allegati.0.nome', 'targa.jpg');
        $this->withToken(self::TOKEN)->get('/api/allegati/'.$a->allegati[0]->id)->assertOk();

        // e lo segna elaborato: non torna piu' e non si puo' cancellare
        $this->withToken(self::TOKEN)->postJson('/api/appunti/elaborati', ['id' => [$a->id]])->assertJson(['elaborati' => 1]);
        $this->withToken(self::TOKEN)->getJson('/api/appunti')->assertJsonCount(0, 'appunti');
        $this->deleteJson('/appunti/'.$a->id)->assertStatus(422);
    }

    public function test_appunto_dal_menu_di_ogni_pagina(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['/rolli/lavoro', '/rolli/workflow', '/rolli/compiti'] as $pagina) {
            $this->get($pagina)->assertOk()->assertSee('/rolli?appunto', false)->assertSee('+ Appunto');
        }
        // sul Quadro il link apre il modulo senza ricaricare
        $this->assertMatchesRegularExpression('/class="nuovo-appunto"\s+data-apri-appunto=""/', $this->get('/rolli')->assertOk()->getContent());
    }

    public function test_validazione_e_cancellazione(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/rolli/appunti', ['tipo' => 'nota', 'testo' => ''])->assertStatus(422);
        $this->postJson('/rolli/appunti', ['tipo' => 'nota', 'testo' => 'x', 'macchine' => ['m99']])->assertStatus(422);
        $this->postJson('/rolli/appunti', ['tipo' => 'nota', 'testo' => 'x', 'allegati' => [UploadedFile::fake()->create('virus.exe', 10)]])->assertStatus(422);

        $this->postJson('/rolli/appunti', ['tipo' => 'decisione', 'testo' => 'Preventivo dopo il sopralluogo'])->assertOk();
        $a = Appunto::firstOrFail();
        $this->deleteJson('/appunti/'.$a->id)->assertOk();
        $this->assertSame(0, Appunto::count());
    }

    public function test_api_appunti_e_allegati_richiedono_il_token(): void
    {
        $this->getJson('/api/appunti')->assertStatus(401);
        $this->postJson('/api/appunti/elaborati', ['id' => [1]])->assertStatus(401);
        $this->get('/allegati/1')->assertRedirect('/login');
    }
}
