<?php

namespace Tests\Feature;

use App\Models\ChatMessaggio;
use App\Models\Project;
use App\Models\User;
use App\Services\ChatClaude;
use App\Services\Sincronizzazione;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(Sincronizzazione::class)->applica([
            'progetto' => 'rolli', 'nome' => 'Rolli', 'fasi' => [['rete', 'Rete']],
            'macchine' => [['codice' => 'r06', 'num' => '06', 'nome' => 'Taglio ad acqua 2ª linea', 'chiedere' => []]],
            'eventi' => [],
        ]);
    }

    public function test_senza_chiave_la_chat_lo_dice_e_salva_la_domanda(): void
    {
        config(['services.anthropic.key' => '']);
        $this->actingAs(User::factory()->create());

        $this->get('/rolli/chat')->assertOk()->assertSee('Chat non ancora attiva');
        $this->postJson('/rolli/chat', ['testo' => 'Cosa manca per la 06?'])
            ->assertStatus(503)->assertJsonPath('errore', 'Chat non configurata: manca ANTHROPIC_API_KEY nel .env del server.');
        $this->assertSame(1, ChatMessaggio::where('ruolo', 'user')->count());
    }

    public function test_risposta_e_pulsante_registra_come_decisione(): void
    {
        $this->mock(ChatClaude::class, function ($mock) {
            $mock->shouldReceive('rispondi')->once()->andReturnUsing(fn (Project $p, $uid) => ChatMessaggio::create([
                'project_id' => $p->id, 'user_id' => $uid, 'ruolo' => 'assistant',
                'testo' => "Manca il verbale di collaudo.\nDecisione da registrare: chiedere a Movinox il verbale della 06.",
            ]));
        });
        $this->actingAs(User::factory()->create());

        $this->postJson('/rolli/chat', ['testo' => 'Cosa manca per la 06?'])->assertOk()
            ->assertJsonPath('domanda.ruolo', 'user')
            ->assertJsonPath('risposta.ruolo', 'assistant');

        $this->get('/rolli/chat')->assertOk()
            ->assertSee('Cosa manca per la 06?')
            ->assertSee('Registra come decisione')
            ->assertSee('data-testo="chiedere a Movinox il verbale della 06."', false)
            ->assertSee('Chat con Claude');
    }

    public function test_errore_api_mostra_il_messaggio_vero(): void
    {
        config(['services.anthropic.key' => 'sk-ant-finta']);
        $this->mock(ChatClaude::class, function ($mock) {
            $req = new \GuzzleHttp\Psr7\Request('POST', 'https://api.anthropic.com/v1/messages');
            $res = new \GuzzleHttp\Psr7\Response(400, [], json_encode(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'Your credit balance is too low']]));
            $mock->shouldReceive('rispondi')->andThrow(\Anthropic\Core\Exceptions\APIStatusException::from($req, $res));
        });
        $this->actingAs(User::factory()->create());

        $this->postJson('/rolli/chat', ['testo' => 'Ciao'])->assertStatus(502)
            ->assertJsonPath('errore', "Errore dell'API di Claude: Your credit balance is too low");
    }

    public function test_la_chat_richiede_login(): void
    {
        $this->get('/rolli/chat')->assertRedirect('/login');
        $this->postJson('/rolli/chat', ['testo' => 'x'])->assertStatus(401);
    }
}
