@extends('layouts.guest')

@section('title', 'Recuperar Senha')
@section('header_title', 'Esqueceu sua senha?')
@section('header_subtitle', 'Informe seu e-mail cadastrado e enviaremos um link seguro para redefinição')

@section('content')
<form method="POST" action="{{ route('password.email', [], false) }}" class="space-y-5">
    @csrf

    <x-input
        type="email"
        name="email"
        label="E-mail Cadastrado"
        placeholder="seuemail@exemplo.com"
        value="{{ old('email') }}"
        maxlength="100"
        required
        autofocus
    />

    <div>
        <x-button type="submit" variant="primary" class="w-full justify-center py-2.5 shadow-lg shadow-primary/20">
            Enviar Link de Recuperação
        </x-button>
    </div>

    <div class="pt-4 border-t border-border/60 text-center space-y-2">
        <p class="text-xs text-muted-foreground">
            Lembrou da sua senha?
        </p>
        <a href="{{ route('login') }}" class="inline-flex items-center justify-center w-full rounded-xl border border-border bg-secondary/40 px-4 py-2 text-xs font-semibold text-foreground hover:bg-secondary transition-colors">
            Voltar para o Login
        </a>
    </div>
</form>
@endsection
