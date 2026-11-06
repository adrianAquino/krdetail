<!DOCTYPE html>
<html lang="pt-BR" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title . ' - KR Detail' : 'KR Detail - Sistema de Gestão de Estética Automotiva' }}
    </title>
    <meta name="description" content="Sistema de gestão e agendamento para estética automotiva profissional">

    <script>
        (function () {
            const savedTheme = localStorage.getItem('krdetail-theme') || 'dark';
            if (savedTheme === 'light') {
                document.documentElement.classList.remove('dark');
            } else {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>

<body
    class="font-sans antialiased bg-background text-foreground min-h-screen selection:bg-primary/20 selection:text-primary transition-colors duration-200">
    <!-- Link de Acessibilidade para Pular Navegação (Teclado / Leitores de Tela) -->
    <a href="#conteudo-principal" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-xl focus:bg-primary focus:px-4 focus:py-2.5 focus:text-xs focus:font-bold focus:text-primary-foreground focus:shadow-lg">
        Pular para o conteúdo principal
    </a>

    <!-- Sidebar Navigation -->
    <x-sidebar />

    <!-- Main Content Area -->
    <main id="conteudo-principal" tabindex="-1" class="min-h-screen bg-background pt-16 lg:pl-64 lg:pt-0 flex flex-col focus:outline-none">
        <div class="flex-1 p-4 lg:p-8 max-w-7xl w-full mx-auto">
            <!-- Flash Session Feedback -->
            @if(session('success'))
                <div class="mb-6">
                    <x-alert type="success" :title="session('success')">
                        Ação realizada com sucesso.
                    </x-alert>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6">
                    <x-alert type="error" :title="session('error')">
                        Por favor, verifique os dados informados e tente novamente.
                    </x-alert>
                </div>
            @endif

            @if(session('warning'))
                <div class="mb-6">
                    <x-alert type="warning" :title="session('warning')" />
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6">
                    <x-alert type="error" title="Existem erros no formulário:">
                        <ul class="list-disc list-inside space-y-1 text-xs mt-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                </div>
            @endif

            <!-- Content Slot -->
            {{ $slot ?? '' }}
            @yield('content')
        </div>

        <!-- Footer -->
        <footer class="border-t border-border/50 py-4 px-6 text-center text-xs text-muted-foreground mt-auto">
            <p>&copy; {{ date('Y') }} KR Detail - Gestão de Estética Automotiva. Todos os direitos reservados.</p>
        </footer>
    </main>

    <!-- Recursos de Acessibilidade: VLibras + Leitor de Voz (pt-BR) -->
    <x-vlibras />

    <!-- Global Modal Helpers -->
    <script>
        window.openModal = function (id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
            }
        };

        window.closeModal = function (id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }
        };

        // Fechar modal com tecla ESC ou clicando fora
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('[data-modal]').forEach(modal => {
                    if (!modal.classList.contains('hidden')) {
                        window.closeModal(modal.id);
                    }
                });
            }
        });

        document.addEventListener('click', function (event) {
            if (event.target.hasAttribute('data-modal')) {
                window.closeModal(event.target.id);
            }
        });
    </script>

    @stack('scripts')
</body>

</html>
