@php
    $decisione = $m->ruolo === 'assistant' && preg_match('/Decisione da registrare:\s*(.+)$/is', $m->testo, $x) ? trim($x[1]) : null;
@endphp
<div class="msg {{ $m->ruolo === 'user' ? 'msg-io' : 'msg-claude' }}">
    <div class="meta">{{ $m->ruolo === 'user' ? 'Tu' : 'Claude' }} · {{ $m->created_at->format('d/m H:i') }}</div>
    <div class="testo-appunto">{{ $m->testo }}</div>
    @if ($m->ruolo === 'assistant')
        <button type="button" class="btn btn-sec" data-apri-appunto="" data-tipo="decisione" data-testo="{{ $decisione ?? $m->testo }}">Registra come decisione</button>
    @endif
</div>
