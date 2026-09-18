<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Acesso ao Sistema') - Kraus Detail</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-foreground antialiased min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8 selection:bg-primary selection:text-primary-foreground relative overflow-x-hidden">
    <!-- Efeito de brilho de fundo (radial gradient) -->
    <div class="fixed inset-0 pointer-events-none -z-10 flex items-center justify-center opacity-30">
        <div class="w-[600px] h-[600px] bg-primary/20 rounded-full blur-[140px]"></div>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <!-- Logo -->
        <a href="/" class="inline-flex items-center gap-3 group">
            <div class="w-12 h-12 rounded-xl bg-primary/10 border border-primary/25 flex items-center justify-center text-primary shadow-[0_0_25px_rgba(16,185,129,0.25)] group-hover:scale-105 transition-transform duration-300">
                <x-icon name="sparkles" class="w-6 h-6" />
            </div>
            <div class="text-left">
                <span class="text-xl font-bold tracking-tight text-foreground block">
                    KRAUS <span class="text-primary font-black">DETAIL</span>
                </span>
                <span class="text-[10px] text-muted-foreground tracking-widest uppercase block -mt-1 font-medium">
                    Estética Automotiva Pro
                </span>
            </div>
        </a>

        <h2 class="mt-6 text-center text-xl font-bold tracking-tight text-foreground">
            @yield('header_title', 'Acesse sua conta')
        </h2>
        <p class="mt-1 text-center text-xs text-muted-foreground">
            @yield('header_subtitle', 'Gestão integrada de estética automotiva')
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4">
        @if(session('success'))
            <div class="mb-4">
                <x-alert type="success" :message="session('success')" dismissible />
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4">
                <x-alert type="error" :message="session('error')" dismissible />
            </div>
        @endif

        <div class="bg-card border border-border py-8 px-6 sm:px-10 shadow-2xl rounded-2xl relative">
            @yield('content')
        </div>

        <p class="text-center text-xs text-muted-foreground mt-8">
            &copy; {{ date('Y') }} Kraus Detail. Todos os direitos reservados.
        </p>
    </div>
</body>
</html>