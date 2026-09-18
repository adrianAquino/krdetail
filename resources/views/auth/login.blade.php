@extends('layouts.guest')

@section('title', 'Entrar')
@section('header_title', 'Painel de Gestão')
@section('header_subtitle', 'Insira suas credenciais de acesso')

@section('content')
<form method="POST" action="{{ route('login.post', [], false) }}" class="space-y-5">
    @csrf

    <x-input 
        type="email" 
        name="email" 
        label="E-mail" 
        placeholder="seu.email@krausdetail.com.br" 
        value="{{ old('email', 'admin@krausdetail.com.br') }}" 
        required 
        autofocus 
    />

    <x-input 
        type="password" 
        name="password" 
        label="Senha" 
        placeholder="••••••••" 
        value="password" 
        required 
    />

    <div class="flex items-center justify-between text-xs">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="remember" class="rounded border-border text-primary focus:ring-primary bg-secondary/50">
            <span class="text-muted-foreground">Lembrar de mim</span>
        </label>

        <a href="#" class="text-primary hover:underline font-medium">
            Esqueceu a senha?
        </a>
    </div>

    <div>
        <x-button type="submit" variant="primary" class="w-full justify-center py-2.5 shadow-lg shadow-primary/20">
            Entrar no Sistema
        </x-button>
    </div>

    <div class="pt-4 border-t border-border/60 text-center">
        <p class="text-xs text-muted-foreground">
            Acesso para clientes? 
            <a href="{{ route('dashboard') }}" class="text-primary hover:underline font-medium">Entrar como Demonstração</a>
        </p>
    </div>
</form>
@endsection