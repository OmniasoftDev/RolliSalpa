{{-- Gantt del workflow (23/10/2026): $g da Pubblico::gantt(). Una riga per macchina con le tappe come
     segmenti consecutivi, rombi per gli appuntamenti, riga "Progetto" con le fasi del planning senza macchine.
     La matrice scorre in orizzontale dentro il riquadro, con la colonna delle macchine fissa. --}}
@php $st = \App\Services\MappaProgetto::STATI; @endphp
<section class="pb-riquadro pb-gantt" aria-label="Diagramma di Gantt del lavoro">
    <div class="gt-titolo">
        <h2>Il lavoro nel tempo</h2>
        <span class="pb-visto">Settimane dal {{ $g['settimane'][0]['etichetta'] ?? '' }} alla scadenza del {{ $g['scadenzaTesto'] }} · tocca una riga per aprire la macchina</span>
    </div>
    <div class="gt-scroll">
        <div class="gt">
            <div class="gt-testa">
                <div class="gt-etichetta gt-angolo">Macchina</div>
                <div class="gt-asse">
                    <div class="gt-mesi">@foreach ($g['mesi'] as $ms)<span style="left: {{ $ms['x'] }}%; width: {{ $ms['w'] }}%">{{ $ms['nome'] }}</span>@endforeach</div>
                    <div class="gt-settimane">@foreach ($g['settimane'] as $w)<span style="left: {{ $w['x'] }}%; width: {{ $w['w'] }}%">{{ $w['etichetta'] }}</span>@endforeach</div>
                </div>
            </div>
            <div class="gt-corpo">
                @if ($g['progetto'])
                    <div class="gt-riga gt-riga-progetto" style="--corsie: {{ $g['corsie'] }}">
                        <div class="gt-etichetta"><b>Progetto</b><small>fasi comuni</small></div>
                        <div class="gt-traccia">
                            @foreach ($g['progetto'] as $b)
                                <span class="gt-seg gt-fase pl-{{ $b['stato'] }}" style="left: {{ $b['x'] }}%; width: {{ $b['w'] }}%; top: calc({{ $b['corsia'] }} * var(--h-corsia) + 4px)" title="{{ $b['testo'] }}" data-info="{{ $b['testo'] }}" tabindex="0">{{ $b['etichetta'] }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
                @foreach ($g['righe'] as $r)
                    <a class="gt-riga" href="#m-{{ $r['codice'] }}" data-card="m-{{ $r['codice'] }}">
                        <span class="gt-etichetta"><span class="gt-num">{{ $r['num'] }}</span><span class="gt-nome">{{ $r['nome'] }}</span></span>
                        <span class="gt-traccia">
                            @forelse ($r['segmenti'] as $s)
                                <span class="gt-seg st-{{ $s['stato'] }} {{ $s['ritardo'] ? 'ritardo' : '' }}" style="left: {{ $s['x'] }}%; width: {{ $s['w'] }}%" title="{{ $s['testo'] }}" data-info="{{ $s['testo'] }}">{{ $s['etichetta'] }}</span>
                            @empty
                                <span class="gt-vuota">tappe non ancora pianificate</span>
                            @endforelse
                            @foreach ($r['rombi'] as $ap)
                                <span class="gt-rombo" style="left: {{ $ap['x'] }}%" title="{{ $ap['testo'] }}" data-info="{{ $ap['testo'] }}"></span>
                            @endforeach
                        </span>
                    </a>
                @endforeach
                <div class="gt-linee" aria-hidden="true">
                    @foreach ($g['settimane'] as $w)<i class="gt-griglia" style="left: {{ $w['x'] }}%"></i>@endforeach
                    @if ($g['oggi'] !== null)<i class="gt-oggi" style="left: {{ $g['oggi'] }}%"><span>oggi {{ $g['oggiTesto'] }}</span></i>@endif
                    <i class="gt-scadenza" style="left: {{ $g['scadenza'] }}%"><span>scadenza {{ $g['scadenzaTesto'] }}</span></i>
                </div>
            </div>
        </div>
    </div>
    <p class="gt-info" id="gt-info" aria-live="polite">Passa o tocca un segmento per vedere tappa, stato e data.</p>
    <div class="legend gt-legenda">
        @foreach (['ok', 'corso', 'attesa', 'rischio', 'verificare', 'no'] as $k)<span><i class="gt-leg st-{{ $k }}"></i>{{ $k === 'no' ? 'Non iniziata / futura' : $st[$k][1] }}</span>@endforeach
        <span><i class="gt-leg st-corso ritardo"></i>Data prevista superata</span>
        <span><i class="gt-leg-rombo"></i>Appuntamento</span>
        <span><i class="gt-leg-linea oggi"></i>Oggi</span>
        <span><i class="gt-leg-linea scad"></i>Scadenza</span>
        @if ($g['progetto'])<span class="gt-leg-sep">Fasi comuni:</span>@foreach (['fatto' => 'fatta', 'corso' => 'in corso', 'fisso' => 'data fissata', 'previsto' => 'prevista', 'rischio' => 'a rischio'] as $k => $l)<span><i class="gt-leg pl-{{ $k }}"></i>{{ $l }}</span>@endforeach @endif
    </div>
</section>
<script>
(() => {
    const info = document.getElementById('gt-info');
    const g = document.querySelector('.pb-gantt');
    if (!info || !g) return;
    const mostra = el => { info.textContent = el.dataset.info; };
    g.addEventListener('mouseover', e => { const el = e.target.closest('[data-info]'); if (el) mostra(el); });
    g.addEventListener('focusin', e => { const el = e.target.closest('[data-info]'); if (el) mostra(el); });
    g.addEventListener('click', e => {
        const el = e.target.closest('[data-info]');
        // su telefono il tocco su un segmento mostra il dettaglio; il tocco sul resto della riga apre la macchina
        if (el && window.matchMedia('(hover: none)').matches) {
            e.preventDefault();
            mostra(el);
            return;
        }
        const riga = e.target.closest('a.gt-riga');
        if (!riga) return;
        const card = document.getElementById(riga.dataset.card);
        if (card && card.hidden) {
            const tutte = document.querySelector('#pb-filtro [data-f="tutte"]');
            if (tutte) tutte.click();
        }
    });
})();
</script>
