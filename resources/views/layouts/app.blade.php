<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titolo', 'Rolli Salpa 4.0')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Source+Sans+3:wght@400;600&family=JetBrains+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="{{ asset('css/pannello.css') }}?v={{ filemtime(public_path('css/pannello.css')) }}">
</head>
<body class="@yield('classe')">
@auth
    <nav class="topbar">
        <span class="brand">Rolli Salpa · Industria 4.0</span>
        @isset($progetti)
            <div class="tabs">
                @foreach ($progetti as $p)
                    <a href="{{ route('pannello', $p->slug) }}" @if (isset($progetto) && $progetto->slug === $p->slug) aria-current="page" @endif>{{ $p->nome }}</a>
                @endforeach
                <a href="{{ route('registro') }}" @if (request()->routeIs('registro')) aria-current="page" @endif>Registro</a>
            </div>
            @isset($progetto)
                <div class="tabs sotto">
                    <a href="{{ route('pannello', $progetto->slug) }}" @if (request()->routeIs('pannello')) aria-current="page" @endif>Quadro</a>
                    <a href="{{ route('lavoro', $progetto->slug) }}" @if (request()->routeIs('lavoro')) aria-current="page" @endif>Il mio lavoro</a>
                    <a href="{{ route('compiti', $progetto->slug) }}" @if (request()->routeIs('compiti')) aria-current="page" @endif>Persone e compiti</a>
                    <a href="{{ route('chat', $progetto->slug) }}" @if (request()->routeIs('chat')) aria-current="page" @endif>Chat con Claude</a>
                </div>
            @endisset
        @endisset
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="esci">Esci</button>
        </form>
    </nav>
@endauth
@yield('contenuto')
@stack('script')
</body>
</html>
