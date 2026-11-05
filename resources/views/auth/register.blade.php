@extends('layouts.guest')

@section('title', 'Criar Conta de Cliente')
@section('header_title', 'Portal do Cliente')
@section('header_subtitle', 'Crie sua conta para agendar serviços e acompanhar seus veículos')

@section('content')
<form method="POST" action="{{ route('register.post', [], false) }}" class="space-y-4">
    @csrf

    <div class="rounded-xl border border-primary/25 bg-primary/10 p-3 text-xs text-muted-foreground leading-relaxed">
        <span class="font-semibold text-primary block mb-0.5">Já é cliente da oficina?</span>
        Informe o <strong class="text-foreground">mesmo e-mail</strong> utilizado no seu cadastro presencial para vincularmos automaticamente seus veículos e histórico de serviços.
    </div>

    <x-input
        type="text"
        name="name"
        label="Nome Completo"
        placeholder="Ex: João da Silva"
        value="{{ old('name') }}"
        required
        autofocus
    />

    <x-input
        type="email"
        name="email"
        label="E-mail"
        placeholder="seuemail@exemplo.com"
        value="{{ old('email') }}"
        required
    />

    <div class="grid gap-4 sm:grid-cols-2">
        <x-input
            type="text"
            name="telefone"
            label="Telefone / WhatsApp"
            placeholder="(11) 99999-9999"
            value="{{ old('telefone') }}"
            required
        />

        <x-input
            type="text"
            name="cpf_cnpj"
            label="CPF ou CNPJ (Opcional)"
            placeholder="000.000.000-00"
            value="{{ old('cpf_cnpj') }}"
        />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-input
            type="password"
            name="password"
            label="Senha"
            placeholder="Mínimo 8 caracteres"
            required
        />

        <x-input
            type="password"
            name="password_confirmation"
            label="Confirmar Senha"
            placeholder="Repita sua senha"
            required
        />
    </div>

    <div class="pt-2">
        <x-button type="submit" variant="primary" class="w-full justify-center py-2.5 shadow-lg shadow-primary/20">
            Cadastrar e Acessar Portal
        </x-button>
    </div>

    <div class="pt-4 border-t border-border/60 text-center">
        <p class="text-xs text-muted-foreground">
            Já possui uma conta?
            <a href="{{ route('login') }}" class="text-primary hover:underline font-medium">Fazer login</a>
        </p>
    </div>
</form>
@endsection

