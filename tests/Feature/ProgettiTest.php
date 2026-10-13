<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\User;
use App\Services\MappaProgetto;
use App\Services\Sincronizzazione;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgettiTest extends TestCase
{
    use RefreshDatabase;

    private function progettoRulliera(): array
    {
        return [
            'aggiornato' => '2026-10-13',
            'sintesi' => 'Ingressi al 100% dal gestionale di magazzino, mai a mano.',
            'principi' => ['I dati in ingresso arrivano dal gestionale (Francesco, 13/10)'],
            'anagrafica' => [['voce' => 'Costruttore', 'valore' => 'LOGISS (commessa 5688)', 'fonte' => '[M25]']],
            'attori' => [
                ['id' => 'a1', 'ruolo' => 'Software PLC 4.0', 'chi' => 'Sertech Elettronica', 'persone' => ['p14'], 'stato' => 'corso', 'nota' => 'consegna metà novembre'],
                ['id' => 'a2', 'ruolo' => 'Gestionale di magazzino', 'chi' => 'Ibimec? da confermare', 'stato' => 'verificare'],
            ],
            'nodi' => [
                ['id' => 'n1', 'nome' => 'Gestionale di magazzino (WMS)', 'tipo' => 'gestionale', 'attore' => 'a2', 'stato' => 'verificare'],
                ['id' => 'n2', 'nome' => 'SCADA Edica', 'tipo' => 'scada', 'stato' => 'corso'],
                ['id' => 'n3', 'nome' => 'PLC rulliera', 'tipo' => 'plc', 'attore' => 'a1', 'stato' => 'inventato'],
            ],
            'collegamenti' => [
                ['id' => 'k1', 'da' => 'n1', 'a' => 'n3', 'titolo' => 'Richieste di carico pallet', 'dati' => ['codice pallet', 'destinazione'], 'protocollo' => 'OPC UA', 'stato' => 'verificare'],
                ['id' => 'k2', 'da' => 'n3', 'a' => 'n2', 'verso' => 'entrambi', 'titolo' => 'Stato, allarmi, contatori', 'stato' => 'corso'],
                ['id' => 'k3', 'da' => 'n2', 'a' => 'n3', 'titolo' => 'Programma di lavoro', 'stato' => 'no'],
                ['id' => 'k9', 'da' => 'n1', 'a' => 'nX', 'titolo' => 'Verso un nodo che non esiste'],
            ],
            'uscite' => [['dato' => 'Stato macchina', 'variabile' => 'STS.int_LineStatus', 'req' => ['R8'], 'stato' => 'corso']],
            'ingressi' => [['dato' => 'Pallet in arrivo', 'da' => 'Gestionale (WMS)', 'req' => ['R1', 'R13'], 'stato' => 'verificare']],
            'bidirezionale' => ['stato' => 'verificare', 'testo' => 'Istruzione dal gestionale e esito di ritorno'],
            'requisiti' => ['R8' => ['stato' => 'ok', 'come' => 'LineStatus'], 'R5' => ['stato' => 'na', 'come' => 'non richiesto']],
            'strade' => [['id' => 'A', 'titolo' => 'Scambio WMS-PLC già attivo', 'stato' => 'valutare', 'collegamenti' => ['k1']]],
            'aperti' => [['testo' => 'Cosa sono le ubicazioni 777, 7, 6', 'chi' => 'Colantonio', 'quando' => '14/10']],
            'avanzamento' => [['testo' => 'Call con Sertech', 'fatto' => true, 'data' => '2026-10-12'], ['testo' => 'Perizia', 'fatto' => false]],
            'documenti' => [['titolo' => 'Progetto 4.0 rulliera', 'percorso' => 'progetti-4.0/10-rulliera.md']],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        app(Sincronizzazione::class)->applica([
            'progetto' => 'salpa', 'nome' => 'Salpa', 'ordine' => 1,
            'fasi' => [['rete', 'Rete'], ['scada', 'Su SCADA']],
            'macchine' => [
                ['codice' => 'm01', 'num' => '01', 'nome' => 'Surgelatore Gyro', 'fornitore' => 'JBT', 'fasi' => ['rete' => 'ok']],
                ['codice' => 'm10', 'num' => '10', 'nome' => 'Rulliera cella', 'fornitore' => 'LOGISS / Sertech', 'fasi' => ['rete' => 'ok', 'scada' => 'no'], 'progetto' => $this->progettoRulliera(),
                    'chiedere' => [['id' => 'q1', 'chi' => 'Merlotti', 'cosa' => 'IP sulla sottorete 13']]],
            ],
            'eventi' => [],
            'persone' => [['id' => 'p14', 'nome' => 'Stefano Ruaro', 'azienda' => 'Sertech', 'contatti' => 'stefano.ruaro@sertech.it']],
        ]);
        app(Sincronizzazione::class)->applica([
            'progetto' => 'rolli', 'nome' => 'Rolli', 'ordine' => 2, 'eventi' => [],
            'macchine' => [['codice' => 'r01', 'num' => '01', 'nome' => 'Imbustatrice Rolli', 'progetto' => ['sintesi' => 'Solo Rolli']]],
        ]);
    }

    public function test_elenco_dei_progetti_per_macchina(): void
    {
        $this->actingAs(User::factory()->create())->get('/salpa/progetti')->assertOk()
            ->assertSee('Progetti 4.0 · Salpa')
            ->assertSee('Rulliera cella')
            ->assertSee('Ingressi al 100% dal gestionale di magazzino')
            ->assertSee('1/2 passi')
            ->assertSee('Progetto non ancora impostato')
            ->assertSee('/salpa/progetti/m10', false)
            ->assertDontSee('Imbustatrice Rolli');

        // dal menu e dalla scheda macchina del Quadro
        $this->get('/salpa')->assertOk()->assertSee('/salpa/progetti', false)->assertSee('Progetto 4.0 e mappa');
    }

    public function test_pagina_della_macchina_con_mappa_attori_dati_e_requisiti(): void
    {
        $this->actingAs(User::factory()->create())->get('/salpa/progetti/m10')->assertOk()
            ->assertSee('Rulliera cella · progetto 4.0')
            ->assertSee('Mappa delle connessioni')
            ->assertSee('Gestionale di magazzino (WMS)')
            ->assertSee('Richieste di carico pallet')
            ->assertSee('codice pallet')
            ->assertDontSee('Verso un nodo che non esiste')
            ->assertSee('Sertech Elettronica')
            ->assertSee('Stefano Ruaro')
            ->assertSee('mailto:stefano.ruaro@sertech.it', false)
            ->assertSee('STS.int_LineStatus')
            ->assertSee('Bidirezionalità con il gestionale')
            ->assertSee('Scambio WMS-PLC già attivo')
            ->assertSee('Cosa sono le ubicazioni 777, 7, 6')
            ->assertSee('IP sulla sottorete 13')
            ->assertSee('progetti-4.0/10-rulliera.md')
            ->assertSee('marker-start="url(#fr-corso)"', false)
            ->assertSee('Surgelatore Gyro'); // salto alla macchina prima

        $this->get('/salpa/progetti/m01')->assertOk()->assertSee('non è ancora impostato');
        // le macchine di Rolli non si aprono da Salpa
        $this->get('/salpa/progetti/r01')->assertNotFound();
        $this->get('/rolli/progetti/r01')->assertOk()->assertSee('Solo Rolli')->assertDontSee('Rulliera cella');
    }

    public function test_disposizione_della_mappa(): void
    {
        $mappa = app(MappaProgetto::class);
        $p = $mappa->progetto(Machine::where('codice', 'm10')->firstOrFail());
        $m = $mappa->mappa($p);
        $nodi = collect($m['nodi'])->keyBy('id');

        // gestionale sopra lo SCADA, SCADA sopra il PLC
        $this->assertTrue($nodi['n1']['y'] < $nodi['n2']['y'] && $nodi['n2']['y'] < $nodi['n3']['y']);
        // stato sconosciuto -> "no"; il collegamento verso un nodo inesistente non c'e'
        $this->assertSame('no', $nodi['n3']['stato']);
        $this->assertSame(['k1', 'k2', 'k3'], array_column($m['collegamenti'], 'id'));
        // due collegamenti tra gli stessi nodi non si sovrappongono
        $k = collect($m['collegamenti'])->keyBy('id');
        $this->assertNotSame($k['k2']['path'], $k['k3']['path']);
        $this->assertTrue($k['k2']['entrambi']);
        $this->assertSame([1, 2], $mappa->avanzamento($p));
        $copertura = $mappa->copertura($p, $mappa->requisiti([]));
        $this->assertSame([1, 1, 11], [$copertura['ok'], $copertura['na'], $copertura['no']]);
    }
}
