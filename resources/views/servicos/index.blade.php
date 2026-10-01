<x-layouts.app title="Serviços">
    <x-header
        title="Catálogo de Serviços"
        subtitle="Gerencie os serviços de estética automotiva, valores base e tempos estimados de execução."
    >
        <x-slot:actions>
            <x-button href="{{ route('servicos.create') }}" icon="plus" size="md">
                Novo Serviço
            </x-button>
        </x-slot:actions>
    </x-header>

    <!-- Search & Status Filter -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" action="{{ route('servicos.index') }}" class="flex-1 max-w-md flex items-center gap-2">
            <x-input
                name="busca"
                value="{{ request('busca') }}"
                placeholder="Buscar por nome ou descrição..."
                icon="search"
            />
            @if(request('busca'))
                <x-button href="{{ route('servicos.index') }}" variant="ghost" size="sm">
                    Limpar
                </x-button>
            @endif
        </form>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('servicos.index') }}"
                class="px-3 py-1.5 rounded-lg text-xs font-medium border {{ !request('status') ? 'bg-primary/10 border-primary text-primary' : 'bg-secondary/40 border-border text-muted-foreground hover:text-foreground' }}"
            >
                Todos
            </a>
            <a
                href="{{ route('servicos.index', ['status' => 'ativo']) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-medium border {{ request('status') === 'ativo' ? 'bg-primary/10 border-primary text-primary' : 'bg-secondary/40 border-border text-muted-foreground hover:text-foreground' }}"
            >
                Ativos
            </a>
            <a
                href="{{ route('servicos.index', ['status' => 'inativo']) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-medium border {{ request('status') === 'inativo' ? 'bg-primary/10 border-primary text-primary' : 'bg-secondary/40 border-border text-muted-foreground hover:text-foreground' }}"
            >
                Inativos
            </a>
        </div>
    </div>

    @php
        $servicosDemo = $servicos ?? [
            (object)[
                'id' => 1,
                'nome' => 'Polimento Técnico Comercial',
                'descricao' => 'Correção de pintura em 2 etapas com remoção de até 80% dos micro-riscos (swirls) e aplicação de selante sintético de 6 meses.',
                'preco_base' => 650.00,
                'tempo_estimado_minutos' => 240,
                'ativo' => true,
            ],
            (object)[
                'id' => 2,
                'nome' => 'Vitrificação Cerâmica 9H (Pintura)',
                'descricao' => 'Proteção nanocerâmica de altíssima dureza com garantia de 3 anos, repelência a água e sujeira e brilho vitrificado profundo.',
                'preco_base' => 1200.00,
                'tempo_estimado_minutos' => 360,
                'ativo' => true,
            ],
            (object)[
                'id' => 3,
                'nome' => 'Higienização Interna Completa + Ozônio',
                'descricao' => 'Limpeza detalhada de estofados, carpetes, teto, painel e dutos de ventilação com oxi-sanitização antimicrobiana.',
                'preco_base' => 380.00,
                'tempo_estimado_minutos' => 210,
                'ativo' => true,
            ],
            (object)[
                'id' => 4,
                'nome' => 'Lavagem Detalhada Premium',
                'descricao' => 'Lavagem técnica dos dois baldes com shampoo com pH neutro, descontaminação ferrosa, limpeza minuciosa de caixas de roda e secagem com ar quente.',
                'preco_base' => 180.00,
                'tempo_estimado_minutos' => 90,
                'ativo' => true,
            ],
            (object)[
                'id' => 5,
                'nome' => 'Limpeza e Condicionamento de Couro',
                'descricao' => 'Higienização a vapor com sabão específico para couro e hidratação com nutrientes que evitam ressecamento e rachaduras.',
                'preco_base' => 220.00,
                'tempo_estimado_minutos' => 75,
                'ativo' => true,
            ],
            (object)[
                'id' => 6,
                'nome' => 'Polimento de Faróis com Proteção UV',
                'descricao' => 'Restauração da transparência de lentes de policarbonato amareladas ou foscas com aplicação de verniz protetor contra raios ultravioleta.',
                'preco_base' => 150.00,
                'tempo_estimado_minutos' => 60,
                'ativo' => false, // Exemplo inativo
            ],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($servicosDemo as $servico)
            @php
                $horas = floor(($servico->tempo_estimado_minutos ?? 0) / 60);
                $minutos = ($servico->tempo_estimado_minutos ?? 0) % 60;
                $tempoFormatado = $horas > 0 ? "{$horas}h " . ($minutos > 0 ? "{$minutos}min" : '') : "{$minutos}min";
            @endphp
            <x-card class="{{ !$servico->ativo ? 'opacity-65 border-border/60' : 'hover:border-primary/50' }}">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <x-icon name="wrench" class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-base text-foreground leading-tight">{{ $servico->nome }}</h3>
                            <div class="mt-1">
                                <x-badge-status :status="$servico->ativo ? 'ativo' : 'inativo'" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-1">
                        <x-button href="{{ route('servicos.edit', $servico->id) }}" variant="ghost" size="icon-sm" title="Editar">
                            <x-icon name="pencil" class="w-4 h-4" />
                        </x-button>
                    </div>
                </div>

                <p class="text-xs text-muted-foreground line-clamp-3 mb-4 min-h-[3rem] leading-relaxed">
                    {{ $servico->descricao ?? 'Sem descrição detalhada cadastrada.' }}
                </p>

                <div class="flex items-center justify-between pt-3 border-t border-border/60">
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground font-medium">
                        <x-icon name="clock" class="w-4 h-4 text-muted-foreground" />
                        <span>{{ $tempoFormatado }}</span>
                    </div>

                    <div class="flex items-baseline gap-0.5 text-lg font-extrabold text-primary">
                        <span class="text-xs font-semibold">R$</span>
                        <span>{{ number_format($servico->preco_base, 2, ',', '.') }}</span>
                    </div>
                </div>
            </x-card>
        @empty
            <div class="col-span-3">
                <x-card>
                    <div class="text-center py-12">
                        <x-icon name="wrench" class="w-10 h-10 mx-auto text-muted-foreground mb-3" />
                        <h4 class="text-base font-semibold text-foreground">Nenhum serviço encontrado</h4>
                        <p class="text-xs text-muted-foreground mt-1">Cadastre novos serviços para disponibilizá-los em agendamentos.</p>
                        <div class="mt-4">
                            <x-button href="{{ route('servicos.create') }}" icon="plus">
                                Cadastrar Serviço
                            </x-button>
                        </div>
                    </div>
                </x-card>
            </div>
        @endforelse
    </div>
</x-layouts.app>