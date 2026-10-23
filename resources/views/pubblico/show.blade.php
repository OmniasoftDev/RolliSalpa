{{-- Area pubblica "stato avanzamento" (23/10/2026): pagina standalone, senza menu dell'area riservata.
     Riceve solo dati gia' filtrati da App\Services\Pubblico: niente note, fonti, IP, persone. --}}
@php
    $gm = fn ($d) => $d ? $d->format('d/m') : '';
    $nMacchine = count($macchine);
    $legendaTappe = ['ok', 'corso', 'verificare', 'attesa', 'rischio', 'no', 'na'];
    $legendaReq = ['ok', 'corso', 'attesa', 'verificare', 'rischio', 'no', 'na'];
@endphp
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Industria 4.0 {{ $testata['nome'] }} — stato avanzamento</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Source+Sans+3:wght@400;600&family=JetBrains+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="{{ asset('css/pannello.css') }}?v={{ filemtime(public_path('css/pannello.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/pubblico.css') }}?v={{ filemtime(public_path('css/pubblico.css')) }}">
</head>
<body class="p-{{ $slug }} pb-body">
<div class="wrap pb-pagina">
    <header class="pb-testa">
        <div class="pb-marchio"><span class="pb-logo" aria-hidden="true">O</span>Omniasoft · coordinamento Industria 4.0</div>
        <div class="pb-testa-riga">
            <div class="pb-titolo">
                <h1>Industria 4.0 {{ $testata['nome'] }} <span>— stato avanzamento</span></h1>
                @if ($testata['aggiornato'] !== '')<p class="pb-agg">Aggiornato al <b>{{ $testata['aggiornato'] }}</b></p>@endif
                @if ($testata['sintesi'] !== '')<p class="pb-sintesi">{{ $testata['sintesi'] }}</p>@endif
            </div>
            <div class="gauges">
                @if ($testata['giorni'] !== null)
                    <div class="gauge {{ $testata['giorni'] < 15 ? 'alert' : '' }}"><b>{{ max(0, $testata['giorni']) }}</b><span>giorni al {{ $testata['scadenza']->format('d/m/Y') }}</span></div>
                @endif
                <div class="gauge"><b>{{ $testata['media'] }}%</b><span>avanzamento medio</span></div>
                <div class="gauge"><b>{{ $nMacchine }}</b><span>macchine</span></div>
                <div class="gauge {{ $testata['inRitardo'] ? 'alert' : '' }}"><b>{{ $testata['inRitardo'] }}</b><span>in ritardo</span></div>
            </div>
        </div>
    </header>

    @if ($riepilogo)
        <section class="pb-riquadro" aria-label="Macchine che hanno completato ogni tappa">
            <h2>Tappe completate</h2>
            <div class="cert-riepilogo pb-riepilogo">
                @foreach ($riepilogo as $t)
                    <div class="cert-colonna" title="{{ $t['titolo'] }}: {{ $t['ok'] }} macchine su {{ $t['totale'] }}">
                        <div class="cert-colonna-barra"><span style="height: {{ $t['totale'] ? round($t['ok'] / $t['totale'] * 100) : 0 }}%"></span></div>
                        <b>{{ $t['ok'] }}<small>/{{ $t['totale'] }}</small></b>
                        <span>{{ $t['titolo'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="pb-strumenti">
        <div class="filters" id="pb-filtro" role="group" aria-label="Filtro">
            <button type="button" class="btn" data-f="tutte" aria-pressed="true">Tutte ({{ $nMacchine }})</button>
            <button type="button" class="btn btn-sec" data-f="ritardo" aria-pressed="false">In ritardo ({{ $testata['inRitardo'] }})</button>
        </div>
        <a class="pb-vai-legenda" href="#legenda">Come leggere la pagina</a>
    </div>

    <div class="pb-griglia" id="pb-griglia">
        @forelse ($macchine as $m)
            <article class="pb-card {{ $m['ritardo'] ? 'in-ritardo' : '' }} {{ $m['bloccata'] ? 'bloccata' : '' }} {{ $m['compilata'] ? '' : 'vuota' }}" id="m-{{ $m['codice'] }}" data-ritardo="{{ $m['ritardo'] ? 1 : 0 }}">
                <div class="pb-card-testa">
                    <span class="pb-num">{{ $m['num'] }}</span>
                    <div class="pb-nome">
                        <h2>{{ $m['nome'] }}</h2>
                        @if ($m['fornitore'] !== '')<span class="pb-forn">{{ $m['fornitore'] }}</span>@endif
                    </div>
                    @if ($m['compilata'])
                        <div class="pb-pct">
                            <b>{{ $m['percentuale'] }}%</b>
                            <div class="pj-barra" role="img" aria-label="Avanzamento {{ $m['percentuale'] }} per cento"><span style="width: {{ $m['percentuale'] }}%"></span></div>
                        </div>
                    @endif
                </div>

                @if ($m['ritardo'] || $m['bloccata'])
                    <div class="pb-segnali">
                        @if ($m['bloccata'])<span class="pb-segnale bloccata">Tappa bloccata</span>@endif
                        @if ($m['ritardo'])<span class="pb-segnale ritardo">In ritardo</span>@endif
                    </div>
                @endif

                @if ($m['stato'] !== '')<p class="pb-stato">{{ $m['stato'] }}</p>@endif

                @if ($m['tappe'])
                    <ol class="pb-tappe" style="--n: {{ count($m['tappe']) }}">
                        @foreach ($m['tappe'] as $i => $t)
                            <li class="pb-tappa {{ $i <= $m['ultimaOk'] ? 'fatta' : '' }} {{ $i < $m['ultimaOk'] ? 'linea-piena' : '' }}" title="{{ $t['titolo'] }}: {{ $stati[$t['stato']][1] }}">
                                <i class="cert-pallino s-{{ $t['stato'] }}" aria-hidden="true">{{ $stati[$t['stato']][0] }}</i>
                                <span class="pb-tappa-titolo">{{ $t['titolo'] }}<span class="pb-sr"> — {{ $stati[$t['stato']][1] }}</span></span>
                                @if ($t['data'])
                                    <span class="pb-data">{{ $gm($t['data']) }}</span>
                                @elseif ($t['previsto'])
                                    <span class="pb-data prevista {{ $t['inRitardo'] ? 'scaduto' : '' }}">prev. {{ $gm($t['previsto']) }}</span>
                                @else
                                    <span class="pb-data prevista">—</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @elseif (! $m['compilata'])
                    <p class="pb-vuota">Tappe non ancora pianificate.</p>
                @endif

                <div class="pb-righe">
                    <div class="pb-riga">
                        <span class="pb-etichetta">SCADA</span>
                        @if ($m['scada'])
                            <span class="sc-badge {{ $m['scada']['classe'] }}">{{ $m['scada']['etichetta'] }}</span>
                            @if ($m['scada']['visto'] !== '')<span class="pb-visto">visto il {{ $m['scada']['visto'] }}</span>@endif
                        @else
                            <span class="pb-visto">non ancora verificato</span>
                        @endif
                    </div>
                    <div class="pb-riga">
                        <span class="pb-etichetta">Requisiti</span>
                        <span class="pb-req" aria-label="Requisiti R1–R13">
                            @foreach ($m['requisiti'] as $rq => $st)
                                <span class="rq-cella-leg pb-chip s-{{ $st }}" title="{{ $rq }} {{ $requisiti[$rq] ?? '' }}: {{ $stati[$st][1] }}">{{ $rq }}</span>
                            @endforeach
                        </span>
                    </div>
                    @if ($m['dati'] !== '')
                        <div class="pb-riga"><span class="pb-etichetta">Raccolta dati</span><span>{{ $m['dati'] }}</span></div>
                    @endif
                </div>

                @if ($m['prossimo'] || $m['manca'])
                    <div class="pb-piede">
                        @if ($m['prossimo'])
                            <div class="pb-prossimo">
                                <b>Prossimo passo</b>
                                <span>{{ $m['prossimo']['testo'] }}</span>
                                @if ($m['prossimo']['azienda'] !== '' || $m['prossimo']['entro'])
                                    <span class="pb-chi {{ $m['prossimo']['scaduto'] ? 'scaduto' : '' }}">{{ $m['prossimo']['azienda'] }}@if ($m['prossimo']['azienda'] !== '' && $m['prossimo']['entro']) · @endif @if ($m['prossimo']['entro'])entro {{ $m['prossimo']['entro']->format('d/m') }}@endif</span>
                                @endif
                            </div>
                        @endif
                        @if ($m['manca'])
                            <div class="pb-manca">
                                <b>Cosa manca</b>
                                <ul>
                                    @foreach ($m['manca'] as $x)
                                        <li class="{{ $x['scaduto'] ? 'scaduto' : '' }}">{{ $x['testo'] }}
                                            @if ($x['azienda'] !== '' || $x['entro'])
                                                <span class="pb-chi">{{ $x['azienda'] }}@if ($x['azienda'] !== '' && $x['entro']) · @endif @if ($x['entro'])entro {{ $x['entro']->format('d/m') }}@if ($x['scaduto']) (scaduto)@endif @endif</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif
            </article>
        @empty
            <p class="empty">Nessuna macchina da mostrare.</p>
        @endforelse
        <p class="empty pb-nessuna" hidden>Nessuna macchina in ritardo.</p>
    </div>

    <section class="pb-riquadro pb-legenda" id="legenda" aria-label="Legenda">
        <h2>Come leggere la pagina</h2>
        <div class="pb-legenda-griglia">
            <div>
                <h3>Tappe</h3>
                <p>Il percorso di ogni macchina fino alla certificazione 4.0. Sotto ogni tappa la data in cui è stata fatta, oppure la data prevista («prev.», in rosso se superata). La percentuale conta le tappe fatte (quelle in corso o da verificare valgono metà).</p>
                <div class="legend">@foreach ($legendaTappe as $k)<span><i class="cert-pallino s-{{ $k }}"></i>{{ $stati[$k][1] }}</span>@endforeach</div>
            </div>
            <div>
                <h3>SCADA</h3>
                <p>Come si vede la macchina nello SCADA dello stabilimento, alla data dell'ultima verifica.</p>
                <div class="legend">@foreach (\App\Services\Scada::STATI as $k => $s)<span><span class="sc-badge {{ $s[1] }}">{{ \App\Services\Scada::BREVI[$k] }}</span> {{ $s[0] }}</span>@endforeach</div>
            </div>
            <div>
                <h3>Requisiti R1–R13</h3>
                <p>Lo stato di ogni requisito richiesto per la perizia.</p>
                <div class="legend">@foreach ($legendaReq as $k)<span><span class="rq-cella-leg pb-chip s-{{ $k }}">R</span>{{ $stati[$k][1] }}</span>@endforeach</div>
                <ol class="pb-req-nomi">
                    @foreach ($requisiti as $rq => $nome)<li><b>{{ $rq }}</b> {{ $nome }}</li>@endforeach
                </ol>
            </div>
        </div>
    </section>

    <footer class="pb-piede-pagina">
        Pagina in sola lettura curata da Omniasoft per il coordinamento del progetto Industria 4.0 {{ $testata['nome'] }}.
        @if ($testata['aggiornato'] !== '') Dati aggiornati al {{ $testata['aggiornato'] }}.@endif
    </footer>
</div>
<script>
(() => {
    const filtro = document.getElementById('pb-filtro');
    if (!filtro) return;
    const card = [...document.querySelectorAll('#pb-griglia .pb-card')];
    const nessuna = document.querySelector('.pb-nessuna');
    filtro.addEventListener('click', e => {
        const b = e.target.closest('button[data-f]');
        if (!b) return;
        filtro.querySelectorAll('button').forEach(x => {
            const on = x === b;
            x.setAttribute('aria-pressed', on ? 'true' : 'false');
            x.classList.toggle('btn-sec', !on);
        });
        const soloRitardo = b.dataset.f === 'ritardo';
        let visibili = 0;
        card.forEach(c => {
            const vedi = !soloRitardo || c.dataset.ritardo === '1';
            c.hidden = !vedi;
            visibili += vedi ? 1 : 0;
        });
        if (nessuna) nessuna.hidden = visibili > 0;
    });
})();
</script>
</body>
</html>
