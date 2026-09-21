<x-layouts.app title="Dashboard">
    <!-- Header -->
    <x-header
        title="Dashboard"
        :subtitle="now()->translatedFormat('l, d \d\e F \d\e Y')"
    >
        <x-slot:actions>
            <x-button href="{{ route('agendamentos.create') }}" icon="plus" size="md">
                Novo Agendamento
            </x-button>
        </x-slot:actions>
    </x-header>

    <!-- Top KPI Cards Grid -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <!-- Card 1: Clientes -->
        <x-card class="relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Clientes Cadastrados</p>
                    <p class="mt-2 text-3xl font-bold text-foreground">{{ $totalClientes ?? 48 }}</p>
                    <p class="mt-1 text-xs text-muted-foreground flex items-center gap-1">
                        <span class="text-success font-medium">+12%</span> em relação ao mês anterior
                    </p>
                </div>
                <div class="rounded-xl p-3 bg-chart-1/10 text-chart-1">
                    <x-icon name="users" class="h-6 w-6" />
                </div>
            </div>
        </x-card>

        <!-- Card 2: Veículos -->
        <x-card class="relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Veículos Registrados</p>
                    <p class="mt-2 text-3xl font-bold text-foreground">{{ $totalVeiculos ?? 64 }}</p>
                    <p class="mt-1 text-xs text-muted-foreground flex items-center gap-1">
                        <span class="text-success font-medium">92%</span> com histórico ativo
                    </p>
                </div>
                <div class="rounded-xl p-3 bg-chart-2/10 text-chart-2">
                    <x-icon name="car" class="h-6 w-6" />
                </div>
            </div>
        </x-card>

        <!-- Card 3: Agendamentos Hoje -->
        <x-card class="relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Agendamentos Hoje</p>
                    <p class="mt-2 text-3xl font-bold text-foreground">{{ $agendamentosHojeCount ?? 6 }}</p>
                    <p class="mt-1 text-xs text-muted-foreground flex items-center gap-1">
                        <span class="text-warning font-medium">{{ $agendamentosPendentesHoje ?? 2 }}</span> aguardando início
                    </p>
                </div>
                <div class="rounded-xl p-3 bg-chart-3/10 text-chart-3">
                    <x-icon name="calendar" class="h-6 w-6" />
                </div>
            </div>
        </x-card>

        <!-- Card 4: Receita do Mês -->
        <x-card class="relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Receitas do Mês</p>
                    <p class="mt-2 text-3xl font-bold text-primary">R$ {{ number_format($receitaMes ?? 18450.00, 2, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-muted-foreground flex items-center gap-1">
                        Lançamentos de tarefas concluídas
                    </p>
                </div>
                <div class="rounded-xl p-3 bg-primary/10 text-primary">
                    <x-icon name="dollar-sign" class="h-6 w-6" />
                </div>
            </div>
        </x-card>
    </div>

    <!-- Middle Grid: Próximos Agendamentos & Alertas de Estoque -->
    <div class="grid gap-6 lg:grid-cols-2 mb-8">
        <!-- Próximos Agendamentos -->
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-2">
                        <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                            <x-icon name="clock" class="h-4 w-4" />
                        </div>
                        <h3 class="text-base font-semibold text-foreground">Próximos Agendamentos</h3>
                    </div>
                    <x-button href="{{ route('agendamentos.index') }}" variant="ghost" size="sm" icon="chevron-right">
                        Ver todos
                    </x-button>
                </div>
            </x-slot:header>

            @php
                $agendamentosDemo = $proximosAgendamentos ?? [
                    (object)[
                        'id' => 1,
                        'horario' => '09:00',
                        'data' => now()->format('d/m'),
                        'cliente' => (object)['nome' => 'Lucas Guimarães', 'telefone' => '(11) 98765-4321'],
                        'veiculo' => (object)['marca' => 'BMW', 'modelo' => '320i M Sport', 'placa' => 'BRA2E19'],
                        'servicos_nomes' => 'Polimento Técnico + Vitrificação',
                        'status' => 'em_andamento',
                        'valor_total' => 1250.00
                    ],
                    (object)[
                        'id' => 2,
                        'horario' => '11:30',
                        'data' => now()->format('d/m'),
                        'cliente' => (object)['nome' => 'Marina Silveira', 'telefone' => '(11) 97123-8890'],
                        'veiculo' => (object)['marca' => 'Audi', 'modelo' => 'Q3 Black Edition', 'placa' => 'KRX8A42'],
                        'servicos_nomes' => 'Higienização Interna Completa + Oxi-Sanitização',
                        'status' => 'confirmado',
                        'valor_total' => 480.00
                    ],
                    (object)[
                        'id' => 3,
                        'horario' => '14:00',
                        'data' => now()->format('d/m'),
                        'cliente' => (object)['nome' => 'Roberto Medeiros', 'telefone' => '(11) 99345-1234'],
                        'veiculo' => (object)['marca' => 'Porsche', 'modelo' => 'Macan GTS', 'placa' => 'GTS9F99'],
                        'servicos_nomes' => 'Lavagem Detalhada + Proteção de Pintura',
                        'status' => 'agendado',
                        'valor_total' => 350.00
                    ],
                    (object)[
                        'id' => 4,
                        'horario' => '16:30',
                        'data' => now()->format('d/m'),
                        'cliente' => (object)['nome' => 'Juliana Ferreira', 'telefone' => '(11) 98456-7890'],
                        'veiculo' => (object)['marca' => 'Volvo', 'modelo' => 'XC60 Recharge', 'placa' => 'VOL6C30'],
                        'servicos_nomes' => 'Descontaminação Ferrosa + Selante Cerâmico',
                        'status' => 'agendado',
                        'valor_total' => 520.00
                    ],
                ];
            @endphp

            <div class="space-y-3">
                @forelse($agendamentosDemo as $ag)
                    <div class="flex items-center justify-between rounded-xl border border-border bg-secondary/20 p-3.5 hover:bg-secondary/40 transition-colors">
                        <div class="flex items-start gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary font-bold text-xs">
                                {{ $ag->horario }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="font-semibold text-sm text-foreground">{{ $ag->cliente->nome }}</p>
                                    <x-badge-status :status="$ag->status" />
                                </div>
                                <p class="text-xs text-muted-foreground mt-0.5 flex items-center gap-1.5">
                                    <span class="font-mono text-foreground/80 bg-secondary px-1.5 py-0.2 rounded text-[11px]">{{ $ag->veiculo->placa }}</span>
                                    <span>{{ $ag->veiculo->marca }} {{ $ag->veiculo->modelo }}</span>
                                </p>
                                <p class="text-xs text-primary/90 mt-1 font-medium">{{ $ag->servicos_nomes }}</p>
                            </div>
                        </div>

                        <div class="text-right shrink-0 ml-3">
                            <span class="text-sm font-bold text-foreground">R$ {{ number_format($ag->valor_total, 2, ',', '.') }}</span>
                            <p class="text-[11px] text-muted-foreground">{{ $ag->data }}</p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-muted-foreground text-sm">
                        Nenhum agendamento programado para hoje.
                    </div>
                @endforelse
            </div>
        </x-card>

        <!-- Alertas de Estoque Baixo -->
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-2">
                        <div class="p-1.5 rounded-lg bg-destructive/10 text-destructive">
                            <x-icon name="alert-triangle" class="h-4 w-4" />
                        </div>
                        <h3 class="text-base font-semibold text-foreground">Alertas de Estoque</h3>
                    </div>
                    <x-button href="{{ route('estoque.index') }}" variant="ghost" size="sm" icon="chevron-right">
                        Gerenciar Estoque
                    </x-button>
                </div>
            </x-slot:header>

            @php
                $produtosAlertaDemo = $produtosBaixoEstoque ?? [
                    (object)[
                        'id' => 1,
                        'nome' => 'Composto Polidor Médio V40',
                        'unidade_medida' => 'L',
                        'quantidade_estoque' => 0.800,
                        'estoque_minimo' => 2.000,
                        'custo_unitario' => 120.00
                    ],
                    (object)[
                        'id' => 2,
                        'nome' => 'Vitrificador de Pintura 9H 50ml',
                        'unidade_medida' => 'un',
                        'quantidade_estoque' => 1.000,
                        'estoque_minimo' => 4.000,
                        'custo_unitario' => 240.00
                    ],
                    (object)[
                        'id' => 3,
                        'nome' => 'Desengordurante IPA Prep',
                        'unidade_medida' => 'L',
                        'quantidade_estoque' => 1.200,
                        'estoque_minimo' => 3.000,
                        'custo_unitario' => 45.00
                    ],
                    (object)[
                        'id' => 4,
                        'nome' => 'Boina de Lã Híbrida 5 Pol',
                        'unidade_medida' => 'un',
                        'quantidade_estoque' => 2.000,
                        'estoque_minimo' => 6.000,
                        'custo_unitario' => 58.00
                    ],
                ];
            @endphp

            <div class="space-y-3">
                @forelse($produtosAlertaDemo as $prod)
                    @php
                        $porcentagem = ($prod->quantidade_estoque / max($prod->estoque_minimo, 1)) * 100;
                        $critico = $porcentagem <= 40;
                    @endphp
                    <div class="flex items-center justify-between rounded-xl border {{ $critico ? 'border-destructive/30 bg-destructive/5' : 'border-warning/30 bg-warning/5' }} p-3.5">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 rounded-lg p-2 {{ $critico ? 'bg-destructive/15 text-destructive' : 'bg-warning/15 text-warning' }}">
                                <x-icon name="package" class="h-4 w-4" />
                            </div>
                            <div>
                                <p class="font-semibold text-sm text-foreground">{{ $prod->nome }}</p>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    Estoque Mínimo: <span class="font-medium text-foreground">{{ $prod->estoque_minimo }} {{ $prod->unidade_medida }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="text-right">
                            <p class="font-bold text-sm {{ $critico ? 'text-destructive' : 'text-warning' }}">
                                {{ $prod->quantidade_estoque }} {{ $prod->unidade_medida }}
                            </p>
                            <span class="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded {{ $critico ? 'bg-destructive/20 text-destructive' : 'bg-warning/20 text-warning' }}">
                                {{ $critico ? 'Crítico' : 'Baixo' }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-muted-foreground text-sm">
                        Todos os produtos estão com níveis de estoque adequados.
                    </div>
                @endforelse
            </div>
        </x-card>
    </div>

    <!-- Bottom Section: Serviços Mais Populares & Resumo Operacional -->
    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Serviços Populares (2 colunas) -->
        <div class="lg:col-span-2">
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                                <x-icon name="trending-up" class="h-4 w-4" />
                            </div>
                            <h3 class="text-base font-semibold text-foreground">Serviços Mais Realizados</h3>
                        </div>
                        <x-button href="{{ route('servicos.index') }}" variant="ghost" size="sm" icon="chevron-right">
                            Ver Catálogo
                        </x-button>
                    </div>
                </x-slot:header>

                @php
                    $servicosPopularesDemo = [
                        ['nome' => 'Polimento Técnico Comercial', 'preco' => 650.00, 'tempo' => '4h 00min', 'realizacoes' => 38, 'percent' => 85],
                        ['nome' => 'Higienização Detalhada + Ozônio', 'preco' => 380.00, 'tempo' => '3h 30min', 'realizacoes' => 31, 'percent' => 70],
                        ['nome' => 'Vitrificação Cerâmica de Pintura 3 Anos', 'preco' => 1200.00, 'tempo' => '6h 00min', 'realizacoes' => 24, 'percent' => 55],
                        ['nome' => 'Lavagem Detalhada Premium', 'preco' => 180.00, 'tempo' => '1h 30min', 'realizacoes' => 52, 'percent' => 95],
                        ['nome' => 'Revitalização e Proteção de Plásticos', 'preco' => 120.00, 'tempo' => '1h 00min', 'realizacoes' => 19, 'percent' => 40],
                    ];
                @endphp

                <div class="space-y-4">
                    @foreach($servicosPopularesDemo as $index => $servico)
                        <div class="rounded-xl bg-secondary/20 p-3.5 border border-border/50">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary/15 text-xs font-bold text-primary">
                                        {{ $index + 1 }}
                                    </span>
                                    <div>
                                        <p class="font-semibold text-sm text-foreground">{{ $servico['nome'] }}</p>
                                        <div class="flex items-center gap-2 text-xs text-muted-foreground mt-0.5">
                                            <span>R$ {{ number_format($servico['preco'], 2, ',', '.') }}</span>
                                            <span>•</span>
                                            <span>{{ $servico['tempo'] }}</span>
                                        </div>
                                    </div>
                                </div>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-secondary text-foreground border border-border">
                                    {{ $servico['realizacoes'] }} execuções
                                </span>
                            </div>
                            <div class="w-full bg-secondary h-1.5 rounded-full overflow-hidden">
                                <div class="bg-primary h-full rounded-full transition-all duration-500" style="width: {{ $servico['percent'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>

        <!-- Resumo Operacional de Ordens de Serviço (1 coluna) -->
        <div>
            <x-card>
                <x-slot:header>
                    <div class="flex items-center gap-2">
                        <div class="p-1.5 rounded-lg bg-chart-1/10 text-chart-1">
                            <x-icon name="clipboard-list" class="h-4 w-4" />
                        </div>
                        <h3 class="text-base font-semibold text-foreground">Ordens de Serviço</h3>
                    </div>
                </x-slot:header>

                <div class="space-y-4">
                    <div class="p-4 rounded-xl bg-secondary/30 border border-border">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="text-muted-foreground">OS em Andamento</span>
                            <span class="font-bold text-warning text-base">4</span>
                        </div>
                        <p class="text-xs text-muted-foreground">Veículos na oficina em execução de tarefas</p>
                    </div>

                    <div class="p-4 rounded-xl bg-secondary/30 border border-border">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="text-muted-foreground">Tarefas Concluídas Hoje</span>
                            <span class="font-bold text-success text-base">9</span>
                        </div>
                        <p class="text-xs text-muted-foreground">Receitas lançadas automaticamente</p>
                    </div>

                    <div class="p-4 rounded-xl bg-secondary/30 border border-border">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="text-muted-foreground">Tarefas Pendentes</span>
                            <span class="font-bold text-foreground text-base">3</span>
                        </div>
                        <p class="text-xs text-muted-foreground">Aguardando início pelos funcionários</p>
                    </div>

                    <div class="pt-2">
                        <x-button href="{{ route('ordens-servico.index') }}" variant="secondary" size="md" class="w-full" icon="clipboard-list">
                            Acompanhar Operação
                        </x-button>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</x-layouts.app>