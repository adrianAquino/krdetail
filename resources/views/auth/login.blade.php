@extends('layouts.guest')

@section('title', 'Entrar')
@section('header_title', 'Painel de Acesso')
@section('header_subtitle', 'Insira suas credenciais para acessar o sistema')

@section('content')
<form method="POST" action="{{ route('login.post', [], false) }}" class="space-y-5">
    @csrf

    <x-input 
        type="email" 
        name="email" 
        label="E-mail" 
        placeholder="Insira seu e-mail" 
        value="{{ old('email') }}" 
        required 
        autofocus 
    />

    <x-input 
        type="password" 
        name="password" 
        label="Senha" 
        placeholder="Insira sua senha" 
        value="{{ old('password') }}" 
        required 
    />

    <div class="flex items-center justify-between text-xs">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="remember" class="rounded border-border text-primary focus:ring-primary bg-secondary/50">
            <span class="text-muted-foreground">Lembrar de mim</span>
        </label>
    </div>

    <div>
        <x-button type="submit" variant="primary" class="w-full justify-center py-2.5 shadow-lg shadow-primary/20">
            Entrar no Sistema
        </x-button>
    </div>

    <div class="pt-4 border-t border-border/60 text-center space-y-2">
        <p class="text-xs text-muted-foreground">
            É cliente e ainda não possui conta de acesso?
        </p>
        <a href="{{ route('register') }}" class="inline-flex items-center justify-center w-full rounded-xl border border-primary/30 bg-primary/10 px-4 py-2 text-xs font-semibold text-primary hover:bg-primary/20 transition-colors">
            Criar minha conta de cliente
        </a>
    </div>
</form>
@endsection
