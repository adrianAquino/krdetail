@php
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
@endphp

<!-- Mobile Header -->
<header class="fixed left-0 right-0 top-0 z-40 flex h-16 items-center justify-between border-b border-border bg-background px-4 lg:hidden">
    <div class="flex items-center gap-3">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary shadow-xs">
            <x-icon name="sparkles" class="h-5 w-5 text-primary-foreground" />
        </div>
        <div>
            <span class="text-base font-bold text-foreground tracking-tight">Kraus Detail</span>
            <p class="text-[10px] text-muted-foreground uppercase tracking-wider font-medium">Estética Automotiva</p>
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
            <p class="text-xs text-muted-foreground truncate">Estética Automotiva</p>
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

    <!-- Footer Links -->
    <div class="border-t border-sidebar-border p-3 space-y-1">
        @php
            $configUrl = \Illuminate\Support\Facades\Route::has('configuracoes.index') ? route('configuracoes.index') : '#';
            $configActive = request()->routeIs('configuracoes*');
        @endphp
        <a
            href="{{ $configUrl }}"
            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ $configActive ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-foreground' }}"
        >
            <x-icon name="settings" class="h-5 w-5 shrink-0" />
            <span>Configurações</span>
        </a>

        <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('logout') ? route('logout') : '#' }}" class="w-full">
            @csrf
            <button
                type="submit"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive cursor-pointer"
            >
                <x-icon name="log-out" class="h-5 w-5 shrink-0" />
                <span>Sair</span>
            </button>
        </form>
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
    });
</script>