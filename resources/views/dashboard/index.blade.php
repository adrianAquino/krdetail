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
                    <p class="mt-2 text-3xl font-bold text-foreground">{{ $totalClientes }}</p>
                    <p class="mt-1 text-xs text-muted-foreground flex items-center gap-1">
                        <span class="{{ $variacaoClientesPercent >= 0 ? 'text-success' : 'text-destructive' }} font-medium">
                            {{ $variacaoClientesPercent >= 0 ? '+' : '' }}{{ $variacaoClientesPercent }}%
                        </span>
                        em relação ao mês anterior
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
                    <p class="mt-2 text-3xl font-bold text-foreground">{{ $totalVeiculos }}</p>
                    <p class="mt-1 text-xs text-muted-foreground flex items-center gap-1">
                        <span class="text-success font-medium">{{ $percentVeiculosAtivos }}%</span> com histórico de serviços
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
                    <p class="mt-2 text-3xl font-bold text-foreground">{{ $agendamentosHojeCount }}</p>
                    <p class="mt-1 text-xs text-muted-foreground flex items-center gap-1">
                        <span class="text-warning font-medium">{{ $agendamentosPendentesHoje }}</span> aguardando início
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
                    <p class="mt-2 text-3xl font-bold text-primary">R$ {{ number_format($receitaMes, 2, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-muted-foreground flex items-center gap-1">
                        <span class="{{ $variacaoReceitaPercent >= 0 ? 'text-success' : 'text-destructive' }} font-medium">
                            {{ $variacaoReceitaPercent >= 0 ? '+' : '' }}{{ $variacaoReceitaPercent }}%
                        </span>
                        em relação ao mês anterior
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

            <div class="space-y-3">
                @forelse($proximosAgendamentos as $ag)
                    @php
                        $horario = $ag->data_inicio ? \Carbon\Carbon::parse($ag->data_inicio)->format('H:i') : '--:--';
                        $dataFormatada = $ag->data_inicio ? \Carbon\Carbon::parse($ag->data_inicio)->format('d/m') : '--/--';
                        $servicosNomes = $ag->servicos->pluck('nome')->join(' + ') ?: 'Serviço agendado';
                    @endphp
                    <a href="{{ route('agendamentos.show', $ag->id) }}" class="flex items-center justify-between rounded-xl border border-border bg-secondary/20 p-3.5 hover:bg-secondary/40 transition-colors">
                        <div class="flex items-start gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary font-bold text-xs">
                                {{ $horario }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="font-semibold text-sm text-foreground">{{ $ag->cliente?->nome ?? 'Cliente' }}</p>
                                    <x-badge-status :status="$ag->status" />
                                </div>
                                <p class="text-xs text-muted-foreground mt-0.5 flex items-center gap-1.5">
                                    <span class="font-mono text-foreground/80 bg-secondary px-1.5 py-0.2 rounded text-[11px]">{{ $ag->veiculo?->placa ?? '-' }}</span>
                                    <span>{{ $ag->veiculo?->marca ?? '' }} {{ $ag->veiculo?->modelo ?? '' }}</span>
                                </p>
                                <p class="text-xs text-primary/90 mt-1 font-medium">{{ $servicosNomes }}</p>
                            </div>
                        </div>

                        <div class="text-right shrink-0 ml-3">
                            <span class="text-sm font-bold text-foreground">R$ {{ number_format((float) $ag->valor_total, 2, ',', '.') }}</span>
                            <p class="text-[11px] text-muted-foreground">{{ $dataFormatada }}</p>
                        </div>
                    </a>
                @empty
                    <div class="text-center py-8 text-muted-foreground text-sm">
                        Nenhum agendamento programado a partir de hoje.
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

            <div class="space-y-3">
                @forelse($produtosEstoqueBaixo as $prod)
                    @php
                        $porcentagem = ((float) $prod->quantidade_estoque / max((float) $prod->estoque_minimo, 1)) * 100;
                        $critico = $porcentagem <= 40;
                        $volAtual = rtrim(rtrim(number_format((float) $prod->quantidade_estoque, 3, ',', '.'), '0'), ',');
                        $volMin = rtrim(rtrim(number_format((float) $prod->estoque_minimo, 3, ',', '.'), '0'), ',');
                    @endphp
                    <a href="{{ route('produtos.show', $prod->id) }}" class="flex items-center justify-between rounded-xl border {{ $critico ? 'border-destructive/30 bg-destructive/5' : 'border-warning/30 bg-warning/5' }} p-3.5 hover:opacity-90 transition-opacity">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 rounded-lg p-2 {{ $critico ? 'bg-destructive/15 text-destructive' : 'bg-warning/15 text-warning' }}">
                                <x-icon name="package" class="h-4 w-4" />
                            </div>
                            <div>
                                <p class="font-semibold text-sm text-foreground">{{ $prod->nome }}</p>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    Mínimo: <span class="font-medium text-foreground">{{ $prod->unidades_minimas }} un. ({{ $volMin }} {{ $prod->unidade_medida }})</span>
                                </p>
                            </div>
                        </div>

                        <div class="text-right">
                            <p class="font-bold text-sm {{ $critico ? 'text-destructive' : 'text-warning' }}">
                                {{ $prod->unidades_estoque }} un. ({{ $volAtual }} {{ $prod->unidade_medida }})
                            </p>
                            <span class="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded {{ $critico ? 'bg-destructive/20 text-destructive' : 'bg-warning/20 text-warning' }}">
                                {{ $critico ? 'Crítico' : 'Baixo' }}
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="text-center py-8 text-muted-foreground text-sm">
                        Todos os produtos estão com níveis de estoque adequados.
                    </div>
                @endforelse
            </div>
        </x-card>
    </div>

    <!-- Bottom Section: Serviços Mais Realizados & Resumo Operacional -->
    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Serviços Mais Realizados (2 colunas) -->
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
                    $maxExecucoes = max((int) $servicosPopulares->max('agendamentos_servicos_count'), 1);
                @endphp

                <div class="space-y-4">
                    @forelse($servicosPopulares as $index => $servico)
                        @php
                            $execucoes = (int) $servico->agendamentos_servicos_count;
                            $percent = $execucoes > 0 ? round(($execucoes / $maxExecucoes) * 100) : 0;
                            $horas = intdiv((int) $servico->tempo_estimado_minutos, 60);
                            $minutos = ((int) $servico->tempo_estimado_minutos) % 60;
                            $tempoFormatado = $horas > 0
                                ? sprintf('%dh %02dmin', $horas, $minutos)
                                : sprintf('%dmin', $minutos);
                        @endphp
                        <div class="rounded-xl bg-secondary/20 p-3.5 border border-border/50">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary/15 text-xs font-bold text-primary">
                                        {{ $index + 1 }}
                                    </span>
                                    <div>
                                        <p class="font-semibold text-sm text-foreground">{{ $servico->nome }}</p>
                                        <div class="flex items-center gap-2 text-xs text-muted-foreground mt-0.5">
                                            <span>R$ {{ number_format((float) $servico->preco_base, 2, ',', '.') }}</span>
                                            <span>•</span>
                                            <span>{{ $tempoFormatado }}</span>
                                        </div>
                                    </div>
                                </div>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-secondary text-foreground border border-border">
                                    {{ $execucoes }} {{ $execucoes === 1 ? 'execução' : 'execuções' }}
                                </span>
                            </div>
                            <div class="w-full bg-secondary h-1.5 rounded-full overflow-hidden">
                                <div class="bg-primary h-full rounded-full transition-all duration-500" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-muted-foreground text-sm">
                            Nenhum serviço ativo cadastrado no catálogo.
                        </div>
                    @endforelse
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
                            <span class="font-bold text-warning text-base">{{ $osEmAndamento }}</span>
                        </div>
                        <p class="text-xs text-muted-foreground">Veículos na oficina em execução de tarefas</p>
                    </div>

                    <div class="p-4 rounded-xl bg-secondary/30 border border-border">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="text-muted-foreground">Tarefas Concluídas Hoje</span>
                            <span class="font-bold text-success text-base">{{ $tarefasConcluidasHoje }}</span>
                        </div>
                        <p class="text-xs text-muted-foreground">Receitas lançadas automaticamente</p>
                    </div>

                    <div class="p-4 rounded-xl bg-secondary/30 border border-border">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="text-muted-foreground">Tarefas Pendentes / Em Execução</span>
                            <span class="font-bold text-foreground text-base">{{ $tarefasPendentes }}</span>
                        </div>
                        <p class="text-xs text-muted-foreground">Aguardando início ou conclusão pelos funcionários</p>
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
