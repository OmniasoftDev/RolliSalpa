<?php

namespace Tests\Feature;

use App\Models\Controllo;
use App\Models\MailRegistro;
use App\Models\User;
use App\Services\Registro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RegistroTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-di-prova-lungo-almeno-trentadue-caratteri';

    private function mail(string $codice, string $ricevuta, array $extra = []): array
    {
        // $extra per primo: con + vince la chiave dell'array di sinistra
        return $extra + ['codice' => $codice, 'ricevuta' => $ricevuta, 'cartella' => 'Posta in arrivo', 'da' => 'Roberto Merlotti <roberto.merlotti@salparoseto.it>',
            'a' => 'info', 'oggetto' => 'ROLLI | Analisi investimenti 4.0', 'testo' => 'Con Francesco verifichiamo', 'allegati' => [], 'inviata' => false,
            'elaborata' => null, 'trovata' => '2026-10-02 17:00:01'];
    }

    private function pacchetto(array $mail, array $codici, string $controllo = '2026-10-02 17:00:01'): array
    {
        return [
            'controlli' => [['codice' => $controllo, 'inizio' => $controllo, 'fine' => '2026-10-02 17:01:30', 'esito' => 'ok',
                'outlook' => 'aperto, online', 'mailFinestra' => count($codici), 'mailNuove' => count($mail), 'appunti' => 0,
                'passi' => [['passo' => 'mail', 'esito' => count($mail).' nuove']], 'firma' => Registro::impronta($codici)]],
            'mail' => $mail,
            'finestra' => ['dal' => '2026-09-18 00:00:00', 'codici' => $codici, 'firma' => Registro::impronta($codici)],
        ];
    }

    public function test_quadra_quando_il_sito_ha_tutte_le_mail_del_pc(): void
    {
        $a = str_repeat('a', 40);
        $b = str_repeat('b', 40);
        $this->withToken(self::TOKEN)->postJson('/api/controlli', $this->pacchetto([$this->mail($a, '2026-10-02 15:16:00'), $this->mail($b, '2026-10-02 16:07:00', ['inviata' => true])], [$a, $b]))
            ->assertOk()->assertJsonPath('quadra', true)->assertJsonPath('conteggio', 2)->assertJsonPath('firma', Registro::impronta([$b, $a]));

        // controllo successivo: nessuna mail nuova, solo i codici; la b ora e' elaborata e il testo non si perde
        $this->withToken(self::TOKEN)->postJson('/api/controlli', $this->pacchetto([$this->mail($b, '2026-10-02 16:07:00', ['elaborata' => '2026-10-02 18:00:40']) ], [$a, $b], '2026-10-02 18:00:01'))
            ->assertOk()->assertJsonPath('quadra', true);
        $mb = MailRegistro::where('codice', $b)->first();
        $this->assertNotNull($mb->elaborata_at);
        $this->assertSame('Con Francesco verifichiamo', $mb->testo);
        $this->assertSame(2, Controllo::count());
        $this->assertTrue(Controllo::where('codice', '2026-10-02 18:00:01')->first()->quadra);
    }

    public function test_non_quadra_se_il_pc_ha_una_mail_che_il_sito_non_ha(): void
    {
        $a = str_repeat('a', 40);
        $c = str_repeat('c', 40);
        $this->withToken(self::TOKEN)->postJson('/api/controlli', $this->pacchetto([$this->mail($a, '2026-10-02 15:16:00')], [$a, $c]))
            ->assertOk()->assertJsonPath('quadra', false)->assertJsonPath('mancanti', [$c]);
        $this->assertFalse(Controllo::first()->quadra);
    }

    public function test_impronta_incoerente_rifiutata(): void
    {
        $a = str_repeat('a', 40);
        $p = $this->pacchetto([$this->mail($a, '2026-10-02 15:16:00')], [$a]);
        $p['finestra']['firma'] = str_repeat('0', 64);
        $this->withToken(self::TOKEN)->postJson('/api/controlli', $p)->assertStatus(422);
        $this->assertSame(0, MailRegistro::count());
    }

    public function test_senza_token_rifiutato(): void
    {
        $this->postJson('/api/controlli', ['controlli' => []])->assertStatus(401);
    }

    public function test_griglia_segna_gli_orari_mancanti(): void
    {
        Carbon::setTestNow('2026-10-02 12:40:00'); // venerdi'
        Controllo::create(['codice' => '2026-10-02 08:00:02', 'inizio' => '2026-10-02 08:00:02', 'esito' => 'ok', 'quadra' => true]);
        Controllo::create(['codice' => '2026-10-02 09:00:01', 'inizio' => '2026-10-02 09:00:01', 'esito' => 'ok', 'quadra' => true]);
        // 10:00 mancante; 11:00 partito in ritardo alle 11:20 (PC acceso tardi): vale per le 11
        Controllo::create(['codice' => '2026-10-02 11:20:00', 'inizio' => '2026-10-02 11:20:00', 'esito' => 'avvisi', 'quadra' => true]);
        Controllo::create(['codice' => '2026-10-02 12:00:01', 'inizio' => '2026-10-02 12:00:01', 'esito' => 'ok', 'quadra' => false]);

        $oggi = collect(app(Registro::class)->griglia(1)->first()['orari'])->pluck('stato', 'ora');
        $this->assertSame('ok', $oggi['08:00']);
        $this->assertSame('manca', $oggi['10:00']);
        $this->assertSame('avvisi', $oggi['11:00']);
        $this->assertSame('errore', $oggi['12:00']);
        $this->assertSame('futuro', $oggi['13:00']);

        $problemi = implode("\n", app(Registro::class)->problemi());
        $this->assertStringContainsString('02/10 10:00', $problemi);
        $this->assertStringContainsString('non quadra', $problemi);
        // l'ultimo controllo e' delle 12:00 e sono le 12:40: il sito non e' aggiornato
        $this->assertStringContainsString('Nessun controllo da', $problemi);
        Carbon::setTestNow();
    }

    public function test_pagina_registro_e_mail_letta(): void
    {
        $a = str_repeat('a', 40);
        $this->withToken(self::TOKEN)->postJson('/api/controlli', $this->pacchetto([$this->mail($a, '2026-10-02 15:16:00')], [$a]))->assertOk();
        $this->withoutToken()->actingAs(User::factory()->create());

        $this->get('/registro')->assertOk()->assertSee('Mail da leggere')->assertSee('ROLLI | Analisi investimenti 4.0')->assertSee('Con Francesco verifichiamo');
        $m = MailRegistro::first();
        $this->postJson("/mail/{$m->id}/vista", ['vista' => true])->assertOk();
        $this->assertNotNull($m->fresh()->vista_at);

        // un nuovo invio dal PC non rimette la mail tra le da leggere
        $this->withToken(self::TOKEN)->postJson('/api/controlli', $this->pacchetto([$this->mail($a, '2026-10-02 15:16:00', ['elaborata' => '2026-10-02 18:00:00'])], [$a], '2026-10-02 18:00:01'))->assertOk();
        $this->assertNotNull($m->fresh()->vista_at);
    }
}
