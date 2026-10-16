<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\User;
use App\Services\MappaProgetto;
use App\Services\Sincronizzazione;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Mappe Oggi | Obiettivo 4.0, schermo intero e Mappa impianto (Francesco, 16/10/2026). */
class MappeTest extends TestCase
{
    use RefreshDatabase;

    private function oggi(string $plc, string $stato): array
    {
        return [
            'aggiornato' => '2026-10-16', 'fonte' => '[S16]',
            'nodi' => [
                ['id' => 'g-fda', 'nome' => "Filo d'Arianna", 'tipo' => 'gestionale', 'stato' => 'no'],
                ['id' => 'g-dc', 'nome' => 'Data Collector', 'tipo' => 'datacollector', 'stato' => 'verificare'],
                ['id' => 'g-scada', 'nome' => 'SCADA Edica .170', 'tipo' => 'scada', 'stato' => 'ok'],
                ['id' => 'g-hh', 'nome' => 'Hyper Historian', 'tipo' => 'storico', 'stato' => 'corso'],
                ['id' => 'o1', 'nome' => $plc, 'tipo' => 'plc', 'stato' => $stato, 'ip' => '192.168.11.109', 'modello' => 'Siemens 25CPU1',
                    'variabili' => ['POMPA_P1_STATO', 'LIVELLO_Allarme_H_Attivo'], 'schermata' => 'nh3-05.jpg'],
                ['id' => 'o2', 'nome' => 'Router eWON', 'tipo' => 'rete', 'stato' => 'ok'],
                ['id' => 'o3', 'nome' => 'Misura produzione Edica', 'tipo' => 'dispositivo', 'stato' => 'ok'],
            ],
            'collegamenti' => [
                ['id' => 'k1', 'da' => 'o1', 'a' => 'g-scada', 'titolo' => 'Lettura OPC UA', 'protocollo' => 'OPC UA', 'stato' => $stato, 'variabili' => ['POMPA_P1_STATO'], 'dettaglio' => 'qualità Good'],
                ['id' => 'k2', 'da' => 'g-scada', 'a' => 'g-fda', 'titolo' => 'Verso il gestionale', 'stato' => 'no'],
                ['id' => 'k3', 'da' => 'g-dc', 'a' => 'g-scada', 'titolo' => 'Passa dal DC?', 'stato' => 'verificare'],
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        app(Sincronizzazione::class)->applica([
            'progetto' => 'salpa', 'nome' => 'Salpa', 'ordine' => 1, 'eventi' => [],
            'macchine' => [
                ['codice' => 'm05', 'num' => '05', 'nome' => 'Separatore NH3', 'progetto' => [
                    'sintesi' => 'NH3 vivo',
                    'nodi' => [['id' => 'n1', 'nome' => 'Gestionale WMS', 'tipo' => 'gestionale', 'stato' => 'no'], ['id' => 'n2', 'nome' => 'PLC vecchia scala', 'tipo' => 'plc', 'livello' => 2, 'stato' => 'ok']],
                    'collegamenti' => [['id' => 'k1', 'da' => 'n1', 'a' => 'n2', 'titolo' => 'Ricetta dal gestionale', 'stato' => 'no']],
                    'oggi' => $this->oggi('PLC NH3 25CPU1', 'ok'),
                ]],
                ['codice' => 'm09', 'num' => '09', 'nome' => 'Imbustatrice Comek', 'progetto' => ['sintesi' => 'Comek', 'oggi' => $this->oggi('PLC B&R Comek', 'corso')]],
                ['codice' => 'm10', 'num' => '10', 'nome' => 'Rulliera', 'progetto' => ['sintesi' => 'Rulliera senza oggi',
                    'nodi' => [['id' => 'n1', 'nome' => 'PLC rulliera', 'tipo' => 'plc']]]],
            ],
        ]);
    }

    public function test_pagina_macchina_vista_oggi_e_obiettivo(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/salpa/progetti/m05')->assertOk()
            ->assertSee('Obiettivo 4.0')
            ->assertSee('sullo SCADA: ', false)->assertSee('visto il 16/10', false)
            ->assertSee('PLC NH3 25CPU1')
            ->assertSee('Data Collector')
            ->assertSee('i dati passano davvero')
            ->assertSee('da verificare')
            ->assertSee('192.168.11.109')
            ->assertSee('POMPA_P1_STATO')
            ->assertSee('/salpa/schermate/m05/nh3-05.jpg', false)
            ->assertSee('/salpa/progetti/m05/mappa?vista=oggi', false)
            ->assertDontSee('Ricetta dal gestionale');

        $this->get('/salpa/progetti/m05?vista=obiettivo')->assertOk()
            ->assertSee('Ricetta dal gestionale')
            ->assertDontSee('PLC NH3 25CPU1');

        // senza vista oggi si apre l'obiettivo e "Oggi" non e' cliccabile
        $this->get('/salpa/progetti/m10?vista=oggi')->assertOk()->assertSee('PLC rulliera')->assertSee('Vista non ancora compilata');
    }

    public function test_mappa_a_schermo_intero(): void
    {
        $this->actingAs(User::factory()->create())->get('/salpa/progetti/m05/mappa?vista=oggi')->assertOk()
            ->assertSee('05 Separatore NH3')
            ->assertSee('Torna alla pagina')
            ->assertSee('schermo-mappa')
            ->assertSee('PLC NH3 25CPU1')
            ->assertSee('js/mappa.js', false);
        $this->get('/salpa/progetti/m99/mappa')->assertNotFound();
        $this->get('/salpa/impianto/mappa?vista=oggi')->assertOk()->assertSee('Mappa impianto · Salpa')->assertSee('data-filtro-macchina', false);
    }

    public function test_mappa_impianto_fonde_i_sistemi_condivisi(): void
    {
        $mappa = app(MappaProgetto::class);
        $macchine = Machine::orderBy('ordine')->get()->map(fn ($m) => ['codice' => $m->codice, 'num' => (string) $m->dato('num'), 'nome' => (string) $m->dato('nome'), 'progetto' => $mappa->progetto($m)])->all();
        $m = $mappa->impianto($macchine, 'oggi');
        $ids = array_column($m['nodi'], 'id');

        $this->assertSame(1, count(array_filter($ids, fn ($i) => $i === 'g-scada')));
        $this->assertContains('m05:o1', $ids);
        $this->assertContains('m09:o1', $ids);
        $nodi = collect($m['nodi'])->keyBy('id');
        $this->assertSame(['m05', 'm09'], $nodi['g-scada']['macchine']);
        $this->assertSame('05 · PLC NH3 25CPU1', $nodi['m05:o1']['nome']);
        // gestionale sopra il Data Collector, sopra lo SCADA, sopra la rete, sopra il PLC
        $this->assertTrue($nodi['g-fda']['y'] < $nodi['g-dc']['y'] && $nodi['g-dc']['y'] < $nodi['g-scada']['y']
            && $nodi['g-scada']['y'] < $nodi['m05:o2']['y'] && $nodi['m05:o2']['y'] < $nodi['m05:o1']['y']);
        // le due macchine in colonne diverse
        $this->assertNotSame($nodi['m05:o1']['x'], $nodi['m09:o1']['x']);
        // collegamenti di tutte e due le macchine, con il codice della macchina
        $this->assertCount(6, $m['collegamenti']);
        $this->assertSame(['m05', 'm09'], array_values(array_unique(array_column($m['collegamenti'], 'codice'))));
        // la rulliera non ha vista oggi: non ha colonna
        $this->assertSame(['m05', 'm09'], array_column($m['colonne'], 'codice'));

        // livello numerico nella vecchia scala (2 = controllo macchina)
        $p = $mappa->progetto(Machine::where('codice', 'm05')->firstOrFail());
        $ob = collect($mappa->mappa($mappa->grafo($p, 'obiettivo'))['nodi'])->keyBy('id');
        $this->assertTrue($ob['n1']['y'] < $ob['n2']['y']);
        $this->assertSame(4, MappaProgetto::livello(['tipo' => 'gestionale', 'livello' => 2]));
        $this->assertSame(1, MappaProgetto::livello(['tipo' => 'datacollector']));
    }

    public function test_pagina_impianto(): void
    {
        $this->actingAs(User::factory()->create())->get('/salpa/impianto')->assertOk()
            ->assertSee('Mappa impianto · Salpa')
            ->assertSee('05 · PLC NH3 25CPU1')
            ->assertSee('09 · PLC B&amp;R Comek', false)
            ->assertSee('Usato da 2 macchine')
            ->assertSee('Senza mappa in questa vista')
            ->assertSee('/salpa/impianto/mappa?vista=oggi', false);
        $this->get('/salpa/impianto?vista=obiettivo')->assertOk()->assertSee('Gestionale WMS')->assertSee('PLC rulliera');
        $this->get('/salpa')->assertOk()->assertSee('Mappa impianto');
    }
}
