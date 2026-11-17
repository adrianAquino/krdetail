@extends('layouts.guest')

@section('title', 'Redefinir Senha')
@section('header_title', 'Criar Nova Senha')
@section('header_subtitle', 'Defina sua nova senha de acesso ao sistema')

@section('content')
<form method="POST" action="{{ route('password.update', [], false) }}" class="space-y-5">
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    <x-input
        type="email"
        name="email"
        label="E-mail"
        placeholder="seuemail@exemplo.com"
        value="{{ old('email', $email) }}"
        maxlength="100"
        required
    />

    <x-input
        type="password"
        name="password"
        label="Nova Senha (mín. 8 caracteres)"
        placeholder="••••••••"
        minlength="8"
        maxlength="64"
        required
        autofocus
    />

    <x-input
        type="password"
        name="password_confirmation"
        label="Confirmar Nova Senha"
        placeholder="••••••••"
        minlength="8"
        maxlength="64"
        required
    />

    <div>
        <x-button type="submit" variant="primary" class="w-full justify-center py-2.5 shadow-lg shadow-primary/20">
            Redefinir Minha Senha
        </x-button>
    </div>

    <div class="pt-4 border-t border-border/60 text-center">
        <a href="{{ route('login') }}" class="text-xs font-semibold text-primary hover:underline transition-colors">
            Voltar para o Login
        </a>
    </div>
</form>
@endsection
