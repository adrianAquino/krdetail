<x-layouts.app title="Ordens de Serviço">
    <x-header
        title="Ordens de Serviço"
        subtitle="Acompanhamento operacional da oficina, progresso de tarefas por serviço e execução pelos funcionários."
    />

    <!-- Filters Bar -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" action="{{ route('ordens-servico.index') }}" class="flex-1 max-w-md flex items-center gap-2">
            <x-input
                name="busca"
                value="{{ request('busca') }}"
                placeholder="Buscar por número da OS, cliente ou placa..."
                icon="search"
            />
            @if(request('busca') || request('status'))
                <x-button href="{{ route('ordens-servico.index') }}" variant="ghost" size="sm">
                    Limpar
                </x-button>
            @endif
        </form>

        <div class="flex flex-wrap items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
            @php
                $statusList = [
                    '' => 'Todas',
                    'aberta' => 'Abertas',
                    'em_andamento' => 'Em Andamento',
                    'concluida' => 'Concluídas',
                    'cancelada' => 'Canceladas',
                ];
                $currentStatus = request('status', '');
            @endphp
            @foreach($statusList as $key => $label)
                <a
                    href="{{ route('ordens-servico.index', array_merge(request()->query(), ['status' => $key])) }}"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium border whitespace-nowrap transition-colors {{ $currentStatus === $key ? 'bg-primary/15 border-primary text-primary font-semibold' : 'bg-secondary/40 border-border text-muted-foreground hover:text-foreground' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @php
        $ordensDemo = $ordensServico ?? [
            (object)[
                'id' => 15,
                'numero' => 'OS-00015',
                'data_inicio' => now()->setHour(9)->setMinute(0),
                'data_fim' => null,
                'status' => 'em_andamento',
                'tarefas_concluidas' => 2,
                'tarefas_total' => 3,
                'agendamento' => (object)[
                    'id' => 1,
                    'cliente' => (object)['nome' => 'Lucas Guimarães', 'telefone' => '(11) 98765-4321'],
                    'veiculo' => (object)['marca' => 'BMW', 'modelo' => '320i M Sport', 'placa' => 'BRA2E19'],
                ],
                'servicos_nomes' => 'Polimento Técnico + Vitrificação + Higienização'
            ],
            (object)[
                'id' => 14,
                'numero' => 'OS-00014',
                'data_inicio' => now()->subDays(2)->setHour(8)->setMinute(30),
                'data_fim' => now()->subDays(2)->setHour(12)->setMinute(0),
                'status' => 'concluida',
                'tarefas_concluidas' => 2,
                'tarefas_total' => 2,
                'agendamento' => (object)[
                    'id' => 4,
                    'cliente' => (object)['nome' => 'Juliana Ferreira', 'telefone' => '(11) 98456-7890'],
                    'veiculo' => (object)['marca' => 'Volvo', 'modelo' => 'XC60 Recharge', 'placa' => 'VOL6C30'],
                ],
                'servicos_nomes' => 'Descontaminação Ferrosa + Selante Sintético'
            ],
            (object)[
                'id' => 16,
                'numero' => 'OS-00016',
                'data_inicio' => now()->setHour(14)->setMinute(0),
                'data_fim' => null,
                'status' => 'aberta',
                'tarefas_concluidas' => 0,
                'tarefas_total' => 2,
                'agendamento' => (object)[
                    'id' => 2,
                    'cliente' => (object)['nome' => 'Marina Silveira', 'telefone' => '(11) 97123-8890'],
                    'veiculo' => (object)['marca' => 'Audi', 'modelo' => 'Q3 Black Edition', 'placa' => 'KRX8A42'],
                ],
                'servicos_nomes' => 'Higienização Interna Completa + Ozônio'
            ],
        ];
    @endphp

    <x-card class="p-0">
        <x-table>
            <x-slot:header>
                <tr>
                    <th class="py-3.5 px-4">Ordem de Serviço</th>
                    <th class="py-3.5 px-4">Cliente & Veículo</th>
                    <th class="py-3.5 px-4">Serviços Vinculados</th>
                    <th class="py-3.5 px-4">Progresso de Tarefas</th>
                    <th class="py-3.5 px-4 text-center">Status</th>
                    <th class="py-3.5 px-4 text-right">Ação</th>
                </tr>
            </x-slot:header>

            @forelse($ordensDemo as $os)
                @php
                    $progressoPorcentagem = $os->tarefas_total > 0 ? round(($os->tarefas_concluidas / $os->tarefas_total) * 100) : 0;
                @endphp
                <tr class="hover:bg-secondary/25 transition-colors">
                    <td class="py-3.5 px-4">
                        <a href="{{ route('ordens-servico.show', $os->id) }}" class="font-mono text-sm font-bold text-foreground hover:text-primary transition-colors flex items-center gap-1.5">
                            <x-icon name="clipboard-list" class="w-4 h-4 text-primary" />
                            {{ $os->numero }}
                        </a>
                        <p class="text-[11px] text-muted-foreground mt-0.5">
                            Aberta em {{ \Carbon\Carbon::parse($os->data_inicio)->format('d/m/Y H:i') }}
                        </p>
                    </td>

                    <td class="py-3.5 px-4">
                        <p class="font-semibold text-foreground text-sm">{{ $os->agendamento->cliente->nome }}</p>
                        <p class="text-xs text-muted-foreground flex items-center gap-1.5 mt-0.5">
                            <span class="font-mono text-[11px] bg-secondary px-1.5 py-0.2 rounded border border-border text-foreground">
                                {{ $os->agendamento->veiculo->placa }}
                            </span>
                            <span>{{ $os->agendamento->veiculo->marca }} {{ $os->agendamento->veiculo->modelo }}</span>
                        </p>
                    </td>

                    <td class="py-3.5 px-4 max-w-xs">
                        <p class="text-xs text-foreground truncate font-medium">{{ $os->servicos_nomes }}</p>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Tarefas automáticas geradas</p>
                    </td>

                    <td class="py-3.5 px-4 w-44">
                        <div class="space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-foreground">{{ $os->tarefas_concluidas }} de {{ $os->tarefas_total }} tarefas</span>
                                <span class="font-bold text-primary">{{ $progressoPorcentagem }}%</span>
                            </div>
                            <div class="w-full bg-secondary h-2 rounded-full overflow-hidden border border-border/40">
                                <div
                                    class="h-full rounded-full transition-all duration-500 {{ $progressoPorcentagem == 100 ? 'bg-success' : 'bg-primary' }}"
                                    style="width: {{ $progressoPorcentagem }}%"
                                ></div>
                            </div>
                        </div>
                    </td>

                    <td class="py-3.5 px-4 text-center">
                        <x-badge-status :status="$os->status" />
                    </td>

                    <td class="py-3.5 px-4 text-right">
                        <x-button href="{{ route('ordens-servico.show', $os->id) }}" variant="secondary" size="sm" icon="eye">
                            Acompanhar
                        </x-button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="py-8 text-center text-muted-foreground text-sm">
                        Nenhuma Ordem de Serviço encontrada.
                    </td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
</x-layouts.app>