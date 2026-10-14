<?php

namespace Tests\Feature;

use App\Models\Credenziale;
use App\Models\CredenzialeAccesso;
use App\Models\User;
use App\Services\Sincronizzazione;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CredenzialiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-di-prova-lungo-almeno-trentadue-caratteri';

    private const SEGRETO = 'Pw-SegretA-9381!';

    protected function setUp(): void
    {
        parent::setUp();
        app(Sincronizzazione::class)->applica([
            'progetto' => 'salpa', 'nome' => 'Salpa', 'ordine' => 1, 'eventi' => [],
            'macchine' => [['codice' => 'm01', 'num' => '01', 'nome' => 'Surgelatore Gyro']],
            'sistemi' => [['codice' => 's1', 'nome' => 'SCADA Edica (Salpa)', 'tipo' => 'scada']],
        ]);
        app(Sincronizzazione::class)->applica([
            'progetto' => 'rolli', 'nome' => 'Rolli', 'ordine' => 2, 'eventi' => [],
            'macchine' => [['codice' => 'r01', 'num' => '01', 'nome' => 'Imbustatrice Rolli']],
            'sistemi' => [['codice' => 's1', 'nome' => 'Data Collector Rolli', 'tipo' => 'datacollector']],
        ]);
    }

    private function crea(string $slug = 'salpa', array $dati = []): int
    {
        return $this->postJson('/'.$slug.'/credenziali', $dati + [
            'oggetto_tipo' => 'sistema', 'oggetto_codice' => 's1',
            'etichetta' => 'Utente PC SCADA Salpa', 'indirizzo' => '192.168.11.20',
            'utente' => 'omniasoft', 'segreto' => self::SEGRETO, 'note' => 'sola lettura, nota riservata', 'fonte' => 'Mail 14/10 Merlotti',
        ])->assertOk()->json('id');
    }

    public function test_creazione_cifrata_e_nascosta_nella_pagina(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->crea();

        // nel DB c'e' solo il testo cifrato
        $riga = DB::table('credenziali')->where('id', $id)->first();
        $this->assertStringNotContainsString(self::SEGRETO, (string) $riga->segreto);
        $this->assertStringNotContainsString('omniasoft', (string) $riga->utente);
        $this->assertStringNotContainsString('nota riservata', (string) $riga->note);
        $this->assertSame(self::SEGRETO, Credenziale::findOrFail($id)->segreto);
        $this->assertArrayNotHasKey('segreto', Credenziale::findOrFail($id)->toArray());

        // nella pagina: etichetta, indirizzo e utente mascherato, mai i valori
        $this->get('/salpa/sistemi/s1')->assertOk()
            ->assertSee('Credenziali (1)')
            ->assertSee('Utente PC SCADA Salpa')
            ->assertSee('192.168.11.20')
            ->assertSee('o•••••')
            ->assertDontSee(self::SEGRETO)
            ->assertDontSee('nota riservata')
            ->assertDontSee('>omniasoft<', false)
            ->assertSee('creata');
        $this->assertSame(1, CredenzialeAccesso::where('credenziale_id', $id)->where('azione', 'creazione')->count());
    }

    public function test_mostra_solo_dopo_la_conferma_della_password(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->crea();

        $this->postJson('/credenziali/'.$id.'/mostra')->assertStatus(423)->assertJson(['conferma' => true])->assertDontSee(self::SEGRETO);
        $this->assertSame(0, CredenzialeAccesso::where('azione', 'mostra')->count());

        $this->postJson('/credenziali/conferma', ['password' => 'sbagliata'])->assertStatus(422);
        $this->postJson('/credenziali/conferma', ['password' => 'password'])->assertOk()->assertSessionHas('auth.password_confirmed_at');

        $r = $this->withSession(['auth.password_confirmed_at' => time()])->postJson('/credenziali/'.$id.'/mostra')->assertOk()
            ->assertJson(['utente' => 'omniasoft', 'segreto' => self::SEGRETO, 'note' => 'sola lettura, nota riservata']);
        $this->assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
        $this->assertSame(1, CredenzialeAccesso::where('credenziale_id', $id)->where('azione', 'mostra')->count());

        // conferma scaduta (piu' di 15 minuti fa)
        $this->withSession(['auth.password_confirmed_at' => time() - 1000])->postJson('/credenziali/'.$id.'/mostra')->assertStatus(423);
    }

    public function test_modifica_ed_eliminazione_con_registro(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->crea();

        $this->putJson('/credenziali/'.$id, ['etichetta' => 'Utente SCADA lettura', 'utente' => 'omnia2', 'segreto' => 'nuova', 'note' => ''])->assertOk();
        $c = Credenziale::findOrFail($id);
        $this->assertSame(['Utente SCADA lettura', 'omnia2', 'nuova', null], [$c->etichetta, $c->utente, $c->segreto, $c->note]);

        $this->deleteJson('/credenziali/'.$id)->assertOk();
        $this->assertSame(0, Credenziale::count());
        $this->assertSame(1, CredenzialeAccesso::where('azione', 'eliminazione')->whereNull('credenziale_id')->where('nota', 'like', '%Utente SCADA lettura%')->count());
        $this->assertSame(3, CredenzialeAccesso::count());
    }

    public function test_credenziale_della_macchina_nella_pagina_progetto(): void
    {
        $this->actingAs(User::factory()->create());
        $this->crea('salpa', ['oggetto_tipo' => 'macchina', 'oggetto_codice' => 'm01', 'etichetta' => 'HMI surgelatore']);
        $this->get('/salpa/progetti/m01')->assertOk()->assertSee('Credenziali (1)')->assertSee('HMI surgelatore')->assertDontSee(self::SEGRETO);
        $this->get('/salpa/sistemi/s1')->assertOk()->assertSee('Credenziali (0)')->assertDontSee('HMI surgelatore');

        $this->postJson('/salpa/credenziali', ['oggetto_tipo' => 'macchina', 'oggetto_codice' => 's1', 'etichetta' => 'x'])->assertStatus(422);
        $this->postJson('/salpa/credenziali', ['oggetto_tipo' => 'sistema', 'oggetto_codice' => 's1', 'etichetta' => ''])->assertStatus(422);
    }

    public function test_progetti_separati_e_niente_valori_nelle_api(): void
    {
        $this->actingAs(User::factory()->create());
        $this->crea('rolli', ['etichetta' => 'Router Rolli WAN']);
        $this->crea('salpa');

        $this->get('/salpa/sistemi/s1')->assertOk()->assertSee('Credenziali (1)')->assertDontSee('Router Rolli WAN');
        $this->get('/rolli/sistemi/s1')->assertOk()->assertSee('Router Rolli WAN')->assertDontSee('Utente PC SCADA Salpa');
        // una macchina di Rolli non si usa da Salpa
        $this->postJson('/salpa/credenziali', ['oggetto_tipo' => 'macchina', 'oggetto_codice' => 'r01', 'etichetta' => 'x'])->assertStatus(422);

        // il PC non vede mai i valori
        foreach (['/api/spunte', '/api/appunti'] as $api) {
            $r = $this->withToken(self::TOKEN)->getJson($api)->assertOk();
            $this->assertStringNotContainsString(self::SEGRETO, $r->getContent());
            $this->assertStringNotContainsString('nota riservata', $r->getContent());
        }
    }

    public function test_serve_il_login(): void
    {
        $this->postJson('/salpa/credenziali', ['oggetto_tipo' => 'sistema', 'oggetto_codice' => 's1', 'etichetta' => 'x'])->assertStatus(401);
        $this->postJson('/credenziali/1/mostra')->assertStatus(401);
        $this->assertSame(0, Credenziale::count());
    }
}
