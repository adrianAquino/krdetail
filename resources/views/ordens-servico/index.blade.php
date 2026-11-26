<x-layouts.app title="Ordens de Serviço">
    <x-header
        title="Ordens de Serviço"
        subtitle="Acompanhamento operacional da oficina, progresso de tarefas por serviço e execução pelos funcionários."
    />

    <x-admin-empresa-filter
        :action="route('ordens-servico.index')"
        :empresas="$empresasFiltro ?? collect()"
        :empresaSelecionadaId="$empresaFiltroId ?? null"
        :preserve="['busca' => request('busca'), 'status' => request('status')]"
        label="Empresa nas Ordens de Serviço"
    />

    <!-- Filters Bar -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" action="{{ route('ordens-servico.index') }}" class="flex-1 max-w-md flex items-center gap-2">
            @if(($isAdmin ?? false) && !empty($empresaFiltroId))
                <input type="hidden" name="empresa_id" value="{{ $empresaFiltroId }}" />
            @endif
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}" />
            @endif
            <x-input
                name="busca"
                value="{{ request('busca') }}"
                maxlength="100"
                placeholder="Buscar por número da OS, cliente ou placa..."
                icon="search"
            />
            @if(request('busca') || request('status'))
                <x-button href="{{ route('ordens-servico.index', ($isAdmin ?? false) && !empty($empresaFiltroId) ? ['empresa_id' => $empresaFiltroId] : []) }}" variant="ghost" size="sm">
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
                    href="{{ route('ordens-servico.index', array_merge(request()->except('page'), ['status' => $key])) }}"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium border whitespace-nowrap transition-colors {{ $currentStatus === $key ? 'bg-primary/15 border-primary text-primary font-semibold' : 'bg-secondary/40 border-border text-muted-foreground hover:text-foreground' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>



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

            @forelse($ordensServico as $os)
                @php
                    $progressoPorcentagem = $os->tarefas_total > 0 ? round(($os->tarefas_concluidas / $os->tarefas_total) * 100) : 0;
                @endphp
                <tr class="hover:bg-secondary/25 transition-colors">
                    <td class="py-3.5 px-4">
                        <a href="{{ route('ordens-servico.show', $os->id) }}" class="font-mono text-sm font-bold text-foreground hover:text-primary transition-colors flex items-center gap-1.5">
                            <x-icon name="clipboard-list" class="w-4 h-4 text-primary" />
                            {{ $os->numero }}
                        </a>
                        @if(($isAdmin ?? false) && empty($empresaFiltroId) && $os->agendamento?->empresa)
                            <span class="inline-block mt-0.5 text-[10px] font-medium px-1.5 py-0.5 rounded bg-secondary text-muted-foreground border border-border">
                                {{ $os->agendamento->empresa->nome_fantasia }}
                            </span>
                        @endif
                        <p class="text-[11px] text-muted-foreground mt-0.5">
                            Aberta em: {{ \Carbon\Carbon::parse($os->data_inicio)->format('d/m/Y H:i') }}
                        </p>
                        @if($os->data_fim)
                            <p class="text-[11px] text-success mt-0.5 font-medium">
                                Fechada em: {{ \Carbon\Carbon::parse($os->data_fim)->format('d/m/Y H:i') }}
                            </p>
                        @endif
                    </td>

                    <td class="py-3.5 px-4">
                        <p class="font-semibold text-foreground text-sm">{{ $os->agendamento?->cliente?->nome ?? 'Cliente não informado' }}</p>
                        <p class="text-xs text-muted-foreground flex items-center gap-1.5 mt-0.5">
                            <span class="font-mono text-[11px] bg-secondary px-1.5 py-0.2 rounded border border-border text-foreground">
                                {{ $os->agendamento?->veiculo?->placa ?? '-' }}
                            </span>
                            <span>{{ $os->agendamento?->veiculo?->marca ?? '' }} {{ $os->agendamento?->veiculo?->modelo ?? '' }}</span>
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
                                    @style(['width: ' . $progressoPorcentagem . '%'])
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
        @if(method_exists($ordensServico, 'hasPages') && $ordensServico->hasPages())
            <div class="p-4 border-t border-border">
                {{ $ordensServico->links() }}
            </div>
        @endif
    </x-card>
</x-layouts.app>