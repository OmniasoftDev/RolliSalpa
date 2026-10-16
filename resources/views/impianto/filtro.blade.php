<label class="pj-filtro">Macchina
    <select data-filtro-macchina>
        <option value="">tutte</option>
        @foreach ($macchine as $m)<option value="{{ $m['codice'] }}">{{ $m['num'] }} {{ $m['nome'] }}</option>@endforeach
    </select>
</label>
