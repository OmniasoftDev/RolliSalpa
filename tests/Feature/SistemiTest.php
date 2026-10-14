<?php

namespace Tests\Feature;

use App\Models\Appunto;
use App\Models\Project;
use App\Models\User;
use App\Services\Sincronizzazione;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SistemiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        app(Sincronizzazione::class)->applica([
            'progetto' => 'salpa', 'nome' => 'Salpa', 'ordine' => 1, 'eventi' => [],
            'planning' => [['id' => 'pl1', 'titolo' => 'Prova', 'dal' => '2026-10-12', 'al' => '2026-10-16']],
            'macchine' => [
                ['codice' => 'm01', 'num' => '01', 'nome' => 'Surgelatore Gyro', 'fornitore' => 'JBT'],
                ['codice' => 'm10', 'num' => '10', 'nome' => 'Rulliera cella'],
            ],
            'sistemi' => [
                [
                    'codice' => 's1', 'nome' => 'SCADA Edica (Salpa)', 'tipo' => 'scada', 'proprieta' => 'terzi',
                    'gestore' => 'Edica (Calabretta)', 'sintesi' => 'Lo SCADA su cui la perizia verifica le macchine.', 'aggiornato' => '2026-10-14',
                    'anagrafica' => [['voce' => 'PC SCADA', 'valore' => 'nome e IP da chiedere', 'fonte' => '[G07]']],
                    'collegamenti' => [
                        ['macchine' => ['m01'], 'testo' => 'OPC UA su X2 porta 4840', 'stato' => 'corso', 'fonte' => '[WhatsApp 14/10 Uberti]'],
                        ['macchine' => ['m10'], 'testo' => 'rulliera non raggiungibile', 'stato' => 'rischio'],
                    ],
                    'contenuti' => [['titolo' => 'Pagina sinottico surgelatori', 'testo' => 'temperature e stati', 'fonte' => 'screenshot 16/10']],
                    'foto' => [['titolo' => 'Sinottico', 'percorso' => 'C:\\apps\\rolli-salpa\\sistemi\\foto\\s1-sinottico.png', 'data' => '2026-10-16']],
                    'credenziali' => [['oggetto' => 'Utente SCADA di consultazione', 'stato' => 'richiesto a Merlotti', 'nota' => 'sola lettura']],
                    'aperti' => [['testo' => 'Versione e licenza dello SCADA', 'chi' => 'Edica', 'quando' => '16/10']],
                    'documenti' => [['titolo' => 'Scheda SCADA', 'percorso' => 'sistemi/salpa-s1-scada.md']],
                ],
                ['codice' => 's4', 'nome' => 'Rete 4.0', 'tipo' => 'rete'],
                ['nome' => 'senza codice: si salta'],
            ],
        ]);
        app(Sincronizzazione::class)->applica([
            'progetto' => 'rolli', 'nome' => 'Rolli', 'ordine' => 2, 'eventi' => [],
            'macchine' => [['codice' => 'r01', 'num' => '01', 'nome' => 'Imbustatrice Rolli']],
            'sistemi' => [['codice' => 's1', 'nome' => 'Data Collector Rolli', 'tipo' => 'datacollector', 'proprieta' => 'da decidere']],
        ]);
    }

    public function test_i_sistemi_stanno_in_info_insieme_al_planning(): void
    {
        $info = Project::where('slug', 'salpa')->firstOrFail()->info;
        $this->assertCount(3, $info['sistemi']);
        $this->assertSame('pl1', $info['planning'][0]['id']);
        $this->assertSame(['s1', 's4'], array_column(Project::where('slug', 'salpa')->firstOrFail()->sistemi(), 'codice'));
    }

    public function test_elenco_dei_sistemi(): void
    {
        $this->actingAs(User::factory()->create())->get('/salpa/sistemi')->assertOk()
            ->assertSee('Sistemi · Salpa')
            ->assertSee('SCADA Edica (Salpa)')
            ->assertSee('Lo SCADA su cui la perizia verifica le macchine.')
            ->assertSee('Edica (Calabretta)')
            ->assertSee('2 collegamenti')
            ->assertSee('1 da chiarire')
            ->assertSee('/salpa/sistemi/s1', false)
            ->assertDontSee('Data Collector Rolli');

        // voce nel menu
        $this->get('/salpa')->assertOk()->assertSee('/salpa/sistemi', false);
        $this->get('/rolli/sistemi')->assertOk()->assertSee('Data Collector Rolli')->assertDontSee('SCADA Edica');
    }

    public function test_dettaglio_del_sistema_con_link_alle_macchine(): void
    {
        $this->actingAs(User::factory()->create())->get('/salpa/sistemi/s1')->assertOk()
            ->assertSee('SCADA Edica (Salpa)')
            ->assertSee('nome e IP da chiedere')
            ->assertSee('/salpa/progetti/m01', false)
            ->assertSee('Surgelatore Gyro')
            ->assertSee('OPC UA su X2 porta 4840')
            ->assertSee('lamp s-corso', false)
            ->assertSee('lamp s-rischio', false)
            ->assertSee('Pagina sinottico surgelatori')
            ->assertSee('in archivio sul PC:')
            ->assertSee('s1-sinottico.png')
            ->assertSee('Versione e licenza dello SCADA')
            ->assertSee('sistemi/salpa-s1-scada.md')
            ->assertSee('Note dal PC')
            ->assertSee('Utente SCADA di consultazione')
            ->assertSee('Credenziali (0)')
            ->assertSee('+ Aggiungi foto/screenshot')
            ->assertSee('data-apri-appunto="s1"', false);

        $this->get('/salpa/sistemi/s9')->assertNotFound();
        $this->get('/rolli/sistemi/s1')->assertOk()->assertSee('Data Collector Rolli')->assertDontSee('Surgelatore Gyro');
    }

    public function test_galleria_con_le_foto_caricate_dal_sito(): void
    {
        $this->actingAs(User::factory()->create());
        // il modulo appunti offre i sistemi e il tipo screenshot
        $this->get('/salpa')->assertOk()->assertSee('id="am-s1"', false)->assertSee('Screenshot');

        $this->post('/salpa/appunti', [
            'tipo' => 'screenshot',
            'testo' => 'Sinottico surgelatori su SCADA',
            'macchine' => ['s1'],
            'allegati' => [UploadedFile::fake()->image('sinottico.png', 800, 600)],
        ], ['Accept' => 'application/json'])->assertOk();

        $a = Appunto::with('allegati')->firstOrFail();
        $this->assertSame(['s1'], $a->macchine);
        $this->get('/salpa/sistemi/s1')->assertOk()
            ->assertSee(route('allegati.file', $a->allegati[0]), false)
            ->assertSee('Sinottico surgelatori su SCADA');
        $this->get('/salpa/sistemi/s4')->assertOk()->assertDontSee(route('allegati.file', $a->allegati[0]), false);

        // un codice che non e' ne' macchina ne' sistema del progetto non passa (s1 di Rolli non vale per Salpa: qui s9)
        $this->postJson('/salpa/appunti', ['tipo' => 'nota', 'testo' => 'x', 'macchine' => ['s9']])->assertStatus(422);
        $this->postJson('/salpa/appunti', ['tipo' => 'nota', 'testo' => 'x', 'macchine' => ['s4', 'm01']])->assertOk();
    }
}
