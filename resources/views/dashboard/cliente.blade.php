<x-layouts.app title="Meu Painel">
    <x-header
        :title="'Olá, ' . ($cliente->nome ?? auth()->user()->name)"
        subtitle="Acompanhe em tempo real o andamento dos seus agendamentos, serviços e o histórico dos seus veículos."
    >
        <x-slot:actions>
            <x-button href="{{ route('veiculos.create') }}" variant="outline" icon="car" size="md">
                Cadastrar Veículo
            </x-button>
            <x-button href="{{ route('agendamentos.create') }}" icon="plus" size="md">
                Novo Agendamento
            </x-button>
        </x-slot:actions>
    </x-header>

    <!-- Cards de Resumo do Cliente -->
    <div class="grid gap-4 sm:grid-cols-3 mb-8">
        <x-card class="relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Meus Veículos</p>
                    <p class="mt-2 text-3xl font-bold text-foreground">{{ $meusVeiculos->count() }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Cadastrados na sua conta</p>
                </div>
                <div class="rounded-xl p-3 bg-primary/10 text-primary">
                    <x-icon name="car" class="h-6 w-6" />
                </div>
            </div>
        </x-card>

        <x-card class="relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Agendamentos Ativos</p>
                    <p class="mt-2 text-3xl font-bold text-warning">{{ $agendamentosAtivos->count() }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Programados ou em andamento</p>
                </div>
                <div class="rounded-xl p-3 bg-warning/10 text-warning">
                    <x-icon name="calendar" class="h-6 w-6" />
                </div>
            </div>
        </x-card>

        <x-card class="relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Serviços Concluídos</p>
                    <p class="mt-2 text-3xl font-bold text-success">{{ $totalConcluidos }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Atendimentos finalizados</p>
                </div>
                <div class="rounded-xl p-3 bg-success/10 text-success">
                    <x-icon name="check-circle" class="h-6 w-6" />
                </div>
            </div>
        </x-card>
    </div>

    <div class="grid gap-6 lg:grid-cols-3 mb-8">
        <!-- Andamento dos Agendamentos e Serviços (2 colunas) -->
        <div class="lg:col-span-2 space-y-6">
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                                <x-icon name="clock" class="h-4 w-4" />
                            </div>
                            <h3 class="text-base font-semibold text-foreground">Andamento dos Agendamentos e Serviços</h3>
                        </div>
                        <x-button href="{{ route('agendamentos.index') }}" variant="ghost" size="sm" icon="chevron-right">
                            Ver todos
                        </x-button>
                    </div>
                </x-slot:header>

                <div class="space-y-4">
                    @forelse($agendamentosAtivos as $ag)
                        @php
                            $os = $ag->ordemServico;
                            $tarefas = $os ? $os->tarefas : collect();
                            $totalTarefas = $tarefas->count();
                            $tarefasConcluidas = $tarefas->where('status', 'concluida')->count();
                            $progresso = $totalTarefas > 0 ? round(($tarefasConcluidas / $totalTarefas) * 100) : ($ag->status === 'concluido' ? 100 : 0);
                        @endphp
                        <div class="rounded-xl border border-border bg-secondary/20 p-4 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-bold text-sm text-foreground">
                                        {{ \Carbon\Carbon::parse($ag->data_inicio)->format('d/m/Y \à\s H:i') }}
                                    </span>
                                    <x-badge-status :status="$ag->status" />
                                    @if($os)
                                        <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-primary/10 text-primary border border-primary/20">
                                            {{ $os->numero }}
                                        </span>
                                    @endif
                                </div>
                                <span class="text-sm font-extrabold text-primary">
                                    R$ {{ number_format((float) $ag->valor_total, 2, ',', '.') }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <x-icon name="car" class="w-4 h-4 text-primary" />
                                <span class="font-semibold text-foreground">{{ $ag->veiculo?->marca }} {{ $ag->veiculo?->modelo }}</span>
                                <span class="font-mono px-1.5 py-0.5 rounded bg-secondary border border-border text-foreground">{{ $ag->veiculo?->placa }}</span>
                            </div>

                            @if($os && $totalTarefas > 0)
                                <div class="pt-2 border-t border-border/60 space-y-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-muted-foreground font-medium">Progresso da Execução ({{ $tarefasConcluidas }}/{{ $totalTarefas }} etapas concluídas)</span>
                                        <span class="font-bold text-primary">{{ $progresso }}%</span>
                                    </div>
                                    <div class="w-full bg-secondary h-2 rounded-full overflow-hidden">
                                        <div class="bg-primary h-full rounded-full transition-all duration-500" style="width: {{ $progresso }}%"></div>
                                    </div>

                                    <div class="grid gap-2 sm:grid-cols-2 pt-1">
                                        @foreach($tarefas as $tarefa)
                                            <div class="flex items-center justify-between rounded-lg bg-card/80 border border-border px-3 py-2 text-xs">
                                                <span class="font-medium text-foreground truncate pr-2">{{ $tarefa->titulo }}</span>
                                                <x-badge-status :status="$tarefa->status" />
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="flex flex-wrap gap-1.5 pt-1">
                                    @foreach($ag->servicos as $servico)
                                        <span class="inline-flex items-center gap-1 text-xs px-2.5 py-0.5 rounded-md bg-primary/10 text-primary border border-primary/20">
                                            <x-icon name="wrench" class="w-3 h-3" />
                                            {{ $servico->nome }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="flex justify-end pt-2 border-t border-border/50">
                                <x-button href="{{ route('agendamentos.show', $ag->id) }}" variant="ghost" size="sm" icon="eye">
                                    Ver Detalhes do Agendamento
                                </x-button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-muted-foreground text-sm">
                            Você não possui agendamentos em andamento no momento.
                            <div class="mt-3">
                                <x-button href="{{ route('agendamentos.create') }}" size="sm" icon="plus">
                                    Agendar um Serviço
                                </x-button>
                            </div>
                        </div>
                    @endforelse
                </div>
            </x-card>

            <!-- Histórico de Serviços -->
            <x-card>
                <x-slot:header>
                    <div class="flex items-center gap-2">
                        <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                            <x-icon name="clipboard-list" class="h-4 w-4" />
                        </div>
                        <h3 class="text-base font-semibold text-foreground">Histórico de Serviços Realizados</h3>
                    </div>
                </x-slot:header>

                <div class="space-y-3">
                    @forelse($historicoServicos as $hist)
                        <a href="{{ route('agendamentos.show', $hist->id) }}" class="flex items-center justify-between rounded-xl border border-border bg-secondary/20 p-3.5 hover:bg-secondary/40 transition-colors">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-sm text-foreground">
                                        {{ $hist->veiculo?->marca }} {{ $hist->veiculo?->modelo }} ({{ $hist->veiculo?->placa }})
                                    </span>
                                    <x-badge-status :status="$hist->status" />
                                </div>
                                <p class="text-xs text-primary/90 font-medium mt-1">
                                    {{ $hist->servicos_nomes ?: 'Serviços realizados' }}
                                </p>
                                <p class="text-[11px] text-muted-foreground mt-0.5">
                                    Realizado em {{ \Carbon\Carbon::parse($hist->data_inicio)->format('d/m/Y \à\s H:i') }}
                                </p>
                            </div>
                            <div class="text-right shrink-0 ml-3">
                                <span class="text-sm font-bold text-foreground">R$ {{ number_format((float) $hist->valor_total, 2, ',', '.') }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="text-center py-6 text-muted-foreground text-sm">
                            Nenhum histórico de serviço finalizado até o momento.
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>

        <!-- Meus Veículos (1 coluna) -->
        <div>
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                                <x-icon name="car" class="h-4 w-4" />
                            </div>
                            <h3 class="text-base font-semibold text-foreground">Meus Veículos</h3>
                        </div>
                        <x-button href="{{ route('veiculos.create') }}" variant="ghost" size="sm" icon="plus">
                            Novo
                        </x-button>
                    </div>
                </x-slot:header>

                <div class="space-y-3">
                    @forelse($meusVeiculos as $veiculo)
                        <a href="{{ route('veiculos.show', $veiculo->id) }}" class="block rounded-xl border border-border bg-secondary/20 p-3.5 hover:border-primary/40 transition-colors">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-sm text-foreground">{{ $veiculo->marca }} {{ $veiculo->modelo }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $veiculo->cor ?? 'Cor N/D' }} {{ $veiculo->ano ? '• ' . $veiculo->ano : '' }}</p>
                                </div>
                                <span class="font-mono text-xs font-bold px-2.5 py-1 rounded bg-secondary text-primary border border-border">
                                    {{ $veiculo->placa }}
                                </span>
                            </div>
                            <div class="mt-2 pt-2 border-t border-border/50 flex items-center justify-between text-[11px] text-muted-foreground">
                                <span>{{ $veiculo->agendamentos_count }} agendamento(s) no histórico</span>
                                <span class="text-primary font-semibold">Ver ficha &rarr;</span>
                            </div>
                        </a>
                    @empty
                        <div class="text-center py-8 text-muted-foreground text-sm">
                            Você ainda não cadastrou nenhum veículo.
                            <div class="mt-3">
                                <x-button href="{{ route('veiculos.create') }}" size="sm" icon="plus">
                                    Cadastrar Meu Veículo
                                </x-button>
                            </div>
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
</x-layouts.app>

