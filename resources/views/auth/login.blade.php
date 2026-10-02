@extends('layouts.app')

@section('titolo', 'Accesso · Rolli Salpa 4.0')

@section('contenuto')
    <form class="login" method="post" action="{{ url('/login') }}">
        @csrf
        <h1>Rolli Salpa · 4.0</h1>
        @error('email')<p class="errore">{{ $message }}</p>@enderror
        <label for="email">Email
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        </label>
        <label for="password">Password
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </label>
        <label class="riga" for="ricordami"><input id="ricordami" name="ricordami" type="checkbox" value="1"> Resta collegato</label>
        <button type="submit">Entra</button>
    </form>
@endsection
