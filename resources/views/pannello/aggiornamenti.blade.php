@php
    $quando = fn ($s) => $s ? \Illuminate\Support\Carbon::parse($s)->format('d/m H:i') : '–';
    $ultimo = isset($statoPc['controllo']) ? \Illuminate\Support\Carbon::parse($statoPc['controllo']) : null;
    // In orario di lavoro (lun-ven 8-21) il PC controlla ogni 5 minuti: oltre 15 minuti di silenzio c'e' qualcosa che non va.
    $oraLavoro = now()->isWeekday() && now()->hour >= 8 && now()->hour < 21;
    $fermo = $oraLavoro && (! $ultimo || $ultimo->lt(now()->subMinutes(\App\Services\Registro::MARGINE_MINUTI)));
    $mailDaLeggere = \App\Models\MailRegistro::whereNull('vista_at')->count();
    $ultimoRegistrato = \App\Models\Controllo::orderByDesc('inizio')->first();
    $nonQuadra = $ultimoRegistrato && $ultimoRegistrato->quadra !== true;
@endphp
<div class="aggiornamenti {{ $fermo || $nonQuadra ? 'fermo' : '' }}" role="status">
    <span><b>Ultimo controllo PC</b> {{ $quando($statoPc['controllo'] ?? null) }}@if ($fermo) — fermo da più di 15 minuti: PC spento o Outlook chiuso?@endif</span>
    <span><b>Mail lette fino al</b> {{ $quando($statoPc['mail'] ?? null) }}</span>
    <span><b>Registro</b> <a href="{{ route('registro') }}">@if ($mailDaLeggere)<em>{{ $mailDaLeggere }} mail da leggere</em>@else mail tutte lette @endif · @if (! $ultimoRegistrato) nessun controllo registrato @elseif ($nonQuadra) <em>non quadra</em> @else quadra @endif</a></span>
    <span><b>Rolling</b> {{ $statoPc['rollingVersione'] ?? '' }} controllato {{ $quando($statoPc['rolling'] ?? null) }}</span>
    <span><b>Appunti</b> ultimo elaborato {{ $quando($appuntiElaborati) }}@if ($appuntiInAttesa) · <em>{{ $appuntiInAttesa }} da elaborare</em>@endif</span>
</div>
