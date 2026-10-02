@php
    $quando = fn ($s) => $s ? \Illuminate\Support\Carbon::parse($s)->format('d/m H:i') : '–';
    $ultimo = isset($statoPc['controllo']) ? \Illuminate\Support\Carbon::parse($statoPc['controllo']) : null;
    // In orario di lavoro (lun-ven 8-21) il PC controlla ogni ora: oltre 2 ore di silenzio c'e' qualcosa che non va.
    $oraLavoro = now()->isWeekday() && now()->hour >= 8 && now()->hour < 21;
    $fermo = $oraLavoro && (! $ultimo || $ultimo->lt(now()->subHours(2)));
@endphp
<div class="aggiornamenti {{ $fermo ? 'fermo' : '' }}" role="status">
    <span><b>Ultimo controllo PC</b> {{ $quando($statoPc['controllo'] ?? null) }}@if ($fermo) — fermo da più di 2 ore: PC spento o Outlook chiuso?@endif</span>
    <span><b>Mail lette fino al</b> {{ $quando($statoPc['mail'] ?? null) }}</span>
    <span><b>Rolling</b> {{ $statoPc['rollingVersione'] ?? '' }} controllato {{ $quando($statoPc['rolling'] ?? null) }}</span>
    <span><b>Appunti</b> ultimo elaborato {{ $quando($appuntiElaborati) }}@if ($appuntiInAttesa) · <em>{{ $appuntiInAttesa }} da elaborare</em>@endif</span>
</div>
