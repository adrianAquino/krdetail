@php
    $usuarioLogado = \Illuminate\Support\Facades\Auth::user();
    $isCliente = $usuarioLogado && $usuarioLogado->isCliente();

    if ($isCliente) {
        $menuItems = [
            ['route' => 'dashboard', 'label' => 'Meu Painel', 'icon' => 'layout-dashboard', 'active' => request()->routeIs('dashboard*')],
            ['route' => 'veiculos.index', 'label' => 'Meus Veículos', 'icon' => 'car', 'active' => request()->routeIs('veiculos*')],
            ['route' => 'agendamentos.index', 'label' => 'Meus Agendamentos', 'icon' => 'calendar', 'active' => request()->routeIs('agendamentos*')],
        ];
    } else {
        $menuItems = [
            ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'active' => request()->routeIs('dashboard*')],
            ['route' => 'clientes.index', 'label' => 'Clientes', 'icon' => 'users', 'active' => request()->routeIs('clientes*')],
            ['route' => 'veiculos.index', 'label' => 'Veículos', 'icon' => 'car', 'active' => request()->routeIs('veiculos*')],
            ['route' => 'servicos.index', 'label' => 'Serviços', 'icon' => 'wrench', 'active' => request()->routeIs('servicos*')],
            ['route' => 'agendamentos.index', 'label' => 'Agendamentos', 'icon' => 'calendar', 'active' => request()->routeIs('agendamentos*')],
            ['route' => 'ordens-servico.index', 'label' => 'Ordens de Serviço', 'icon' => 'clipboard-list', 'active' => request()->routeIs('ordens-servico*')],
            ['route' => 'estoque.index', 'label' => 'Estoque', 'icon' => 'package', 'active' => request()->routeIs('estoque*') || request()->routeIs('produtos*')],
            ['route' => 'relatorios.index', 'label' => 'Relatórios', 'icon' => 'bar-chart-3', 'active' => request()->routeIs('relatorios*')],
        ];
    }

    $nomeUsuario = $usuarioLogado?->name ?? 'Usuário';
    $emailUsuario = $usuarioLogado?->email ?? '';
    $configUrl = \Illuminate\Support\Facades\Route::has('configuracoes.index') ? route('configuracoes.index') : '#';
    $configActive = request()->routeIs('configuracoes*');
@endphp

<!-- Mobile Header -->
<header class="fixed left-0 right-0 top-0 z-40 flex h-16 items-center justify-between border-b border-border bg-background px-4 lg:hidden">
    <div class="flex items-center gap-3">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary shadow-xs">
            <x-icon name="sparkles" class="h-5 w-5 text-primary-foreground" />
        </div>
        <div>
            <span class="text-base font-bold text-foreground tracking-tight">Kraus Detail</span>
            <p class="text-[10px] text-muted-foreground uppercase tracking-wider font-medium">
                {{ $isCliente ? 'Portal do Cliente' : 'Estética Automotiva' }}
            </p>
        </div>
    </div>
    <button
        type="button"
        id="mobile-menu-toggle"
        class="p-2 rounded-lg text-muted-foreground hover:text-foreground hover:bg-secondary transition-colors"
        aria-label="Abrir menu"
    >
        <x-icon name="menu" class="h-6 w-6" id="menu-icon-open" />
        <x-icon name="x" class="h-6 w-6 hidden" id="menu-icon-close" />
    </button>
</header>

<!-- Mobile Overlay -->
<div id="mobile-sidebar-backdrop" class="fixed inset-0 z-45 bg-background/80 backdrop-blur-xs hidden lg:hidden transition-opacity"></div>

<!-- Sidebar Container -->
<aside
    id="sidebar"
    class="fixed left-0 top-0 z-50 h-screen w-64 -translate-x-full border-r border-border bg-sidebar transition-transform duration-200 ease-in-out lg:translate-x-0 lg:z-30 flex flex-col"
>
    <!-- Brand Header -->
    <div class="flex items-center gap-3 border-b border-sidebar-border px-6 py-5">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary shadow-sm">
            <x-icon name="sparkles" class="h-6 w-6 text-primary-foreground" />
        </div>
        <div class="overflow-hidden">
            <h1 class="text-lg font-bold text-sidebar-foreground truncate tracking-tight">Kraus Detail</h1>
            <p class="text-xs text-muted-foreground truncate">
                {{ $isCliente ? 'Portal do Cliente' : 'Estética Automotiva' }}
            </p>
        </div>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        @foreach($menuItems as $item)
            @php
                $url = \Illuminate\Support\Facades\Route::has($item['route']) ? route($item['route']) : '#';
            @endphp
            <a
                href="{{ $url }}"
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all {{ $item['active'] ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-foreground' }}"
            >
                <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                <span class="truncate">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <!-- User Menu & Theme Toggle Footer -->
    <div class="relative border-t border-sidebar-border p-3 space-y-1.5" id="user-menu-container">
        <!-- Botão de Alternar Tema (Claro / Escuro) -->
        <button
            type="button"
            id="theme-toggle-btn"
            class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-xs font-medium text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-foreground cursor-pointer"
            title="Alternar entre tema claro e escuro"
        >
            <span class="flex items-center gap-2.5">
                <x-icon name="sun" id="theme-icon-sun" class="h-4 w-4 text-primary hidden" />
                <x-icon name="moon" id="theme-icon-moon" class="h-4 w-4 text-primary" />
                <span id="theme-label-text">Tema Escuro</span>
            </span>
            <span class="text-[10px] px-2 py-0.5 rounded-full bg-secondary text-foreground border border-border">
                Alternar
            </span>
        </button>

        <!-- Dropdown Menu (abre acima do botão) -->
        <div
            id="user-menu-dropdown"
            class="hidden absolute bottom-full left-3 right-3 mb-2 rounded-xl border border-border bg-card shadow-lg p-1.5 z-50 space-y-1"
        >
            @if(!$isCliente)
                <a
                    href="{{ $configUrl }}"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $configActive ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-secondary hover:text-foreground' }}"
                >
                    <x-icon name="settings" class="h-4 w-4 shrink-0" />
                    <span>Configurações</span>
                </a>

                <div class="border-t border-border/60 my-1"></div>
            @endif

            <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('logout') ? route('logout') : '#' }}" class="w-full">
                @csrf
                <button
                    type="submit"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive cursor-pointer"
                >
                    <x-icon name="log-out" class="h-4 w-4 shrink-0" />
                    <span>Sair</span>
                </button>
            </form>
        </div>

        <!-- Botão do Usuário Logado -->
        <button
            type="button"
            id="user-menu-button"
            class="flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left transition-colors hover:bg-sidebar-accent cursor-pointer border border-transparent hover:border-border"
            aria-expanded="false"
        >
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary border border-primary/30">
                    <x-icon name="user" class="h-4 w-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-sidebar-foreground truncate">{{ $nomeUsuario }}</p>
                    @if($emailUsuario)
                        <p class="text-[11px] text-muted-foreground truncate">{{ $emailUsuario }}</p>
                    @endif
                </div>
            </div>
            <x-icon name="chevron-down" id="user-menu-chevron" class="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200 rotate-180" />
        </button>
    </div>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.getElementById('mobile-menu-toggle');
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('mobile-sidebar-backdrop');
        const iconOpen = document.getElementById('menu-icon-open');
        const iconClose = document.getElementById('menu-icon-close');

        function toggleMenu() {
            const isClosed = sidebar.classList.contains('-translate-x-full');
            if (isClosed) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
                iconOpen.classList.add('hidden');
                iconClose.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
                iconOpen.classList.remove('hidden');
                iconClose.classList.add('hidden');
            }
        }

        if (toggle && sidebar && backdrop) {
            toggle.addEventListener('click', toggleMenu);
            backdrop.addEventListener('click', toggleMenu);
        }

        // Alternância de Tema (Claro / Escuro)
        const themeToggleBtn = document.getElementById('theme-toggle-btn');
        const themeIconSun = document.getElementById('theme-icon-sun');
        const themeIconMoon = document.getElementById('theme-icon-moon');
        const themeLabelText = document.getElementById('theme-label-text');

        function syncThemeUI() {
            const isDark = document.documentElement.classList.contains('dark');
            if (themeIconSun && themeIconMoon && themeLabelText) {
                themeIconSun.classList.toggle('hidden', isDark);
                themeIconMoon.classList.toggle('hidden', !isDark);
                themeLabelText.textContent = isDark ? 'Tema Escuro' : 'Tema Claro';
            }
        }

        syncThemeUI();

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function () {
                const isDark = document.documentElement.classList.toggle('dark');
                localStorage.setItem('krdetail-theme', isDark ? 'dark' : 'light');
                syncThemeUI();
            });
        }

        // Menu do Usuário Logado no Rodapé da Sidebar
        const userMenuBtn = document.getElementById('user-menu-button');
        const userMenuDropdown = document.getElementById('user-menu-dropdown');
        const userMenuContainer = document.getElementById('user-menu-container');
        const userMenuChevron = document.getElementById('user-menu-chevron');

        if (userMenuBtn && userMenuDropdown) {
            userMenuBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                const isHidden = userMenuDropdown.classList.contains('hidden');
                userMenuDropdown.classList.toggle('hidden', !isHidden);
                userMenuBtn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
                if (userMenuChevron) {
                    userMenuChevron.classList.toggle('rotate-180', !isHidden);
                }
            });

            document.addEventListener('click', function (e) {
                if (userMenuContainer && !userMenuContainer.contains(e.target)) {
                    userMenuDropdown.classList.add('hidden');
                    userMenuBtn.setAttribute('aria-expanded', 'false');
                    if (userMenuChevron) {
                        userMenuChevron.classList.add('rotate-180');
                    }
                }
            });
        }
    });
</script>
