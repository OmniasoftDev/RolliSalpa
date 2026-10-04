<?php

namespace Tests\Feature;

use App\Models\Compito;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompitiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-di-prova-lungo-almeno-trentadue-caratteri';

    private function progetto(string $testoProposta = 'Mandare la tabella variabili dei forni'): array
    {
        return [
            'progetto' => 'salpa', 'nome' => 'Salpa', 'fasi' => [['rete', 'Rete']],
            'macchine' => [
                ['codice' => 'm07', 'num' => '07', 'nome' => 'Forno Grigliatura 2', 'chiedere' => []],
                ['codice' => 'm08', 'num' => '08', 'nome' => 'Forno Grigliatura 3', 'chiedere' => []],
            ],
            'eventi' => [],
            'persone' => [
                ['id' => 'p2', 'nome' => 'Roberto Merlotti', 'azienda' => 'Salpa', 'gruppo' => 'Salpa', 'ruolo' => 'Coordinatore fornitori 4.0'],
                ['id' => 'p12', 'nome' => 'Dimitri Zinelli', 'azienda' => 'Polin', 'gruppo' => 'Fornitori', 'ruolo' => 'Resp. automazione', 'macchine' => ['m07', 'm08'], 'contatti' => 'dimitri.zinelli@polin.it'],
            ],
            'compitiProposti' => [
                ['id' => 'c1', 'persona' => 'p12', 'testo' => $testoProposta, 'macchine' => ['m07', 'm08'], 'fonte' => 'Mail 01/10 Zinelli', 'scadenza' => '2026-10-09'],
                // proposta senza scadenza su una macchina: la scheda macchina del quadro deve reggere
                ['id' => 'c2', 'persona' => 'p2', 'testo' => 'Sollecitare il fornitore', 'macchine' => ['m08']],
            ],
        ];
    }

    public function test_rubrica_e_proposte_arrivano_dal_pc(): void
    {
        $this->withToken(self::TOKEN)->postJson('/api/sync', $this->progetto())->assertOk()
            ->assertJsonPath('persone', 2)->assertJsonPath('compitiProposti', 2);

        $this->withoutToken()->actingAs(User::factory()->create())->get('/salpa/compiti')->assertOk()
            ->assertSee('Dimitri Zinelli')
            ->assertSee('dimitri.zinelli@polin.it')
            ->assertSee('Da confermare')
            ->assertSee('Mandare la tabella variabili dei forni');

        // il quadro segnala la proposta nel blocco "Da guardare"
        $this->get('/salpa')->assertOk()->assertSee('da confermare')->assertSee('Sollecitare il fornitore');
    }

    public function test_confermato_dal_web_il_file_non_lo_cambia_piu(): void
    {
        $this->withToken(self::TOKEN)->postJson('/api/sync', $this->progetto())->assertOk();
        $this->withoutToken()->actingAs(User::factory()->create());

        $c = Compito::where('codice', 'c1')->firstOrFail();
        $this->postJson("/compiti/{$c->id}", ['stato' => 'aperto', 'persona' => 'p2', 'scadenza' => '2026-10-07'])->assertOk();
        $c->refresh();
        $this->assertSame('aperto', $c->stato);
        $this->assertSame('p2', $c->persona);
        $this->assertNotNull($c->assegnato_il);

        // il PC rimanda la proposta con un altro testo, o la toglie: il compito confermato resta com'e'
        $this->withToken(self::TOKEN)->postJson('/api/sync', $this->progetto('Testo cambiato'))->assertOk();
        $this->assertSame('Mandare la tabella variabili dei forni', $c->fresh()->testo);
        $senza = $this->progetto();
        $senza['compitiProposti'] = [];
        $this->withToken(self::TOKEN)->postJson('/api/sync', $senza)->assertOk();
        $this->assertNotNull($c->fresh());

        $this->withoutToken()->postJson("/compiti/{$c->id}", ['stato' => 'fatto', 'esito' => 'Tabella arrivata'])->assertOk();
        $this->assertNotNull($c->fresh()->chiuso_il);

        $this->withToken(self::TOKEN)->getJson('/api/spunte')->assertOk()
            ->assertJsonPath('compiti.0.id', 'c1')
            ->assertJsonPath('compiti.0.stato', 'fatto')
            ->assertJsonPath('compiti.0.persona', 'p2')
            ->assertJsonPath('compiti.0.esito', 'Tabella arrivata');
    }

    public function test_proposta_non_toccata_si_aggiorna_o_sparisce_col_file(): void
    {
        $this->withToken(self::TOKEN)->postJson('/api/sync', $this->progetto())->assertOk();
        $this->withToken(self::TOKEN)->postJson('/api/sync', $this->progetto('Testo corretto'))->assertOk();
        $this->assertSame('Testo corretto', Compito::where('codice', 'c1')->value('testo'));

        $senza = $this->progetto();
        $senza['compitiProposti'] = [];
        $this->withToken(self::TOKEN)->postJson('/api/sync', $senza)->assertOk();
        $this->assertSame(0, Compito::count());
    }

    public function test_nuovo_compito_dal_web_e_scadenza_passata(): void
    {
        $this->withToken(self::TOKEN)->postJson('/api/sync', $this->progetto())->assertOk();
        $this->withoutToken()->actingAs(User::factory()->create());

        $this->postJson('/salpa/compiti', ['persona' => 'p12', 'testo' => 'Chiudere il tema protocollo', 'macchine' => ['m07'], 'scadenza' => '2020-01-01'])->assertOk();
        $this->postJson('/salpa/compiti', ['persona' => 'nessuno', 'testo' => 'x'])->assertStatus(422);
        $this->postJson('/salpa/compiti', ['persona' => 'p12', 'testo' => 'x', 'macchine' => ['m99']])->assertStatus(422);

        $c = Compito::where('testo', 'Chiudere il tema protocollo')->firstOrFail();
        $this->assertSame('aperto', $c->stato);
        $this->assertTrue($c->scaduto());

        $this->get('/salpa/compiti')->assertOk()->assertSee('scaduto il 01/01')->assertSee('box-persona stato-rischio', false)->assertSee('1 scaduti');
        $this->get('/salpa')->assertOk()->assertSee('Chiudere il tema protocollo');
        $this->withToken(self::TOKEN)->getJson('/api/spunte')->assertOk()->assertJsonPath('compiti.0.id', 'w'.$c->id);
    }
}
