@php
    $usuarioAtual = auth()->user();
    $isCliente = $usuarioAtual && $usuarioAtual->isCliente();
    $possuiEsteticaVinculada = $isCliente ? $usuarioAtual->possuiEsteticaVinculada() : true;
    $viewMode = request('view') === 'calendario' ? 'calendario' : 'lista';
@endphp

<x-layouts.app :title="$isCliente ? 'Meus Agendamentos' : 'Agendamentos'">
    <x-header
        :title="$isCliente ? 'Meus Agendamentos' : 'Agendamentos'"
        :subtitle="$isCliente ? 'Acompanhe o andamento dos seus agendamentos, o progresso dos serviços e consulte seu histórico.' : 'Organização do fluxo de entrada de veículos, agendamento de serviços e acompanhamento de status.'"
    >
        <x-slot:actions>
            <div class="inline-flex items-center rounded-lg border border-border bg-secondary/40 p-1 gap-1" role="group" aria-label="Modo de visualização">
                <button
                    type="button"
                    id="btn-view-lista"
                    aria-pressed="{{ $viewMode === 'lista' ? 'true' : 'false' }}"
                    class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold transition-all cursor-pointer {{ $viewMode === 'lista' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground hover:bg-secondary/60' }}"
                >
                    <x-icon name="list" class="w-3.5 h-3.5" />
                    <span>Lista</span>
                </button>
                <button
                    type="button"
                    id="btn-view-calendario"
                    aria-pressed="{{ $viewMode === 'calendario' ? 'true' : 'false' }}"
                    class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold transition-all cursor-pointer {{ $viewMode === 'calendario' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground hover:bg-secondary/60' }}"
                >
                    <x-icon name="calendar" class="w-3.5 h-3.5" />
                    <span>Calendário</span>
                </button>
            </div>

            @if(!$isCliente || $possuiEsteticaVinculada)
                <x-button href="{{ route('agendamentos.create', ($isAdmin ?? false) && !empty($empresaFiltroId) ? ['empresa_id' => $empresaFiltroId] : []) }}" icon="plus" size="md">
                    Novo Agendamento
                </x-button>
            @endif
        </x-slot:actions>
    </x-header>

    @if(!$isCliente)
        <x-admin-empresa-filter
            :action="route('agendamentos.index')"
            :empresas="$empresasFiltro ?? collect()"
            :empresaSelecionadaId="$empresaFiltroId ?? null"
            :preserve="['busca' => request('busca'), 'status' => request('status'), 'view' => $viewMode]"
            label="Empresa nos Agendamentos"
        />
    @endif

    @if($isCliente && !$possuiEsteticaVinculada)
        <div class="mb-6">
            <x-alert type="warning" title="Sem estética vinculada para novos agendamentos">
                Você ainda não está vinculado a nenhuma estética automotiva no momento. Seus agendamentos e serviços anteriores permanecem disponíveis para consulta abaixo, e você pode cadastrar seus veículos normalmente, mas novos agendamentos só são liberados após uma estética vincular seu cadastro.
            </x-alert>
        </div>
    @endif

    <!-- Filters Bar -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" action="{{ route('agendamentos.index') }}" class="flex-1 max-w-md flex items-center gap-2">
            <input type="hidden" name="view" value="{{ $viewMode }}" class="js-agendamentos-view-input" />
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
                :placeholder="$isCliente ? 'Buscar por placa ou veículo...' : 'Buscar por cliente, placa ou veículo...'"
                icon="search"
            />
            @if(request('busca') || request('status'))
                <x-button
                    href="{{ route('agendamentos.index', array_filter(['empresa_id' => (($isAdmin ?? false) && !empty($empresaFiltroId)) ? $empresaFiltroId : null, 'view' => $viewMode], fn ($v) => $v !== null && $v !== '')) }}"
                    variant="ghost"
                    size="sm"
                    class="js-agendamentos-preserve-view"
                >
                    Limpar
                </x-button>
            @endif
        </form>

        <div class="flex flex-wrap items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
            @php
                $statusList = [
                    '' => 'Todos',
                    'agendado' => 'Agendados',
                    'confirmado' => 'Confirmados',
                    'em_andamento' => 'Em Andamento',
                    'concluido' => 'Concluídos',
                    'cancelado' => 'Cancelados',
                ];
                $currentStatus = request('status', '');
            @endphp
            @foreach($statusList as $key => $label)
                <a
                    href="{{ route('agendamentos.index', array_merge(request()->except('page'), ['status' => $key, 'view' => $viewMode])) }}"
                    class="js-agendamentos-preserve-view px-3 py-1.5 rounded-lg text-xs font-medium border whitespace-nowrap transition-colors {{ $currentStatus === $key ? 'bg-primary/15 border-primary text-primary font-semibold' : 'bg-secondary/40 border-border text-muted-foreground hover:text-foreground' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div id="agendamentos-lista-wrapper" class="{{ $viewMode === 'calendario' ? 'hidden' : '' }}">
    <div class="space-y-3">
        @forelse($agendamentos as $ag)
            @php
                $servicosList = $ag->servicos ? $ag->servicos->pluck('nome') : [];
                $valorTotal = $ag->valor_total ?? 0;
                $osObj = $ag->ordemServico ?? null;
                $tarefas = $osObj ? $osObj->tarefas : collect();
                $totalTarefas = $tarefas->count();
                $tarefasConcluidas = $tarefas->where('status', 'concluida')->count();
            @endphp
            <x-card class="p-4 hover:border-primary/40 transition-all">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <!-- Horário e Status -->
                    <div class="flex items-start gap-4">
                        <div class="flex flex-col items-center justify-center h-16 w-16 shrink-0 rounded-xl bg-secondary/70 border border-border p-2 text-center">
                            <span class="text-xs text-muted-foreground uppercase font-semibold">
                                {{ \Carbon\Carbon::parse($ag->data_inicio)->translatedFormat('M') }}
                            </span>
                            <span class="text-lg font-extrabold text-foreground leading-none mt-0.5">
                                {{ \Carbon\Carbon::parse($ag->data_inicio)->format('d') }}
                            </span>
                            <span class="text-[10px] text-primary font-bold mt-1">
                                {{ \Carbon\Carbon::parse($ag->data_inicio)->format('H:i') }}
                            </span>
                        </div>

                        <div>
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="font-bold text-base text-foreground">
                                    {{ \Carbon\Carbon::parse($ag->data_inicio)->format('H:i') }}
                                    @if($ag->data_fim)
                                        - {{ \Carbon\Carbon::parse($ag->data_fim)->format('H:i') }} (est.)
                                    @endif
                                </span>
                                @if($ag->data_conclusao)
                                    <span class="text-[11px] font-medium text-success bg-success/10 px-2 py-0.5 rounded border border-success/25">
                                        Concluído em {{ \Carbon\Carbon::parse($ag->data_conclusao)->format('d/m H:i') }}
                                    </span>
                                @endif
                                <x-badge-status :status="$ag->status" />
                                @if(($isCliente || (($isAdmin ?? false) && empty($empresaFiltroId))) && $ag->empresa)
                                    <span class="text-[11px] font-medium text-muted-foreground bg-secondary px-2 py-0.5 rounded border border-border">
                                        {{ $ag->empresa->nome_fantasia }}
                                    </span>
                                @endif
                                @if(!empty($ag->origem))
                                    <span class="text-[11px] text-muted-foreground bg-secondary px-2 py-0.5 rounded border border-border">
                                        Via {{ $ag->origem }}
                                    </span>
                                @endif
                                @if($osObj && $totalTarefas > 0)
                                    <span class="text-[11px] font-semibold text-primary bg-primary/10 px-2 py-0.5 rounded border border-primary/25">
                                        OS {{ $osObj?->numero }} • {{ $tarefasConcluidas }}/{{ $totalTarefas }} etapas concluídas
                                    </span>
                                @endif
                            </div>

                            <!-- Cliente e Veículo -->
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-foreground">
                                @if(!$isCliente && $ag->cliente)
                                    <a href="{{ route('clientes.show', $ag->cliente?->id ?? 0) }}" class="font-semibold hover:text-primary transition-colors flex items-center gap-1.5">
                                        <x-icon name="user" class="w-3.5 h-3.5 text-muted-foreground" />
                                        {{ $ag->cliente?->nome }}
                                    </a>
                                    <span class="text-muted-foreground">•</span>
                                @endif
                                @if($ag->veiculo)
                                    <a href="{{ route('veiculos.show', $ag->veiculo?->id ?? 0) }}" class="text-muted-foreground hover:text-foreground transition-colors flex items-center gap-1.5">
                                        <x-icon name="car" class="w-3.5 h-3.5 text-primary" />
                                        <span>{{ $ag->veiculo?->marca }} {{ $ag->veiculo?->modelo }}</span>
                                        <span class="font-mono text-xs font-bold px-1.5 py-0.2 bg-secondary rounded border border-border text-foreground">
                                            {{ $ag->veiculo?->placa }}
                                        </span>
                                    </a>
                                @endif
                            </div>

                            <!-- Serviços Contratados -->
                            <div class="mt-2.5 flex flex-wrap gap-1.5">
                                @foreach($servicosList as $servicoNome)
                                    <span class="inline-flex items-center gap-1 text-xs px-2.5 py-0.5 rounded-md bg-primary/10 text-primary border border-primary/20">
                                        <x-icon name="wrench" class="w-3 h-3" />
                                        {{ $servicoNome }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Resumo Financeiro e Ações -->
                    <div class="flex items-center justify-between lg:justify-end gap-4 pt-3 lg:pt-0 border-t lg:border-t-0 border-border/60">
                        <div class="lg:text-right">
                            <span class="text-xs text-muted-foreground block">Total Estimado</span>
                            <span class="text-lg font-extrabold text-foreground">
                                R$ {{ number_format($valorTotal, 2, ',', '.') }}
                            </span>
                            @if(isset($ag->tempo_total))
                                <span class="text-[11px] text-muted-foreground block">{{ $ag->tempo_total }}</span>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            @if($isCliente)
                                <x-button href="{{ route('agendamentos.show', $ag->id) }}" variant="secondary" size="sm" icon="eye">
                                    Acompanhar Serviço
                                </x-button>
                            @else
                                @if($ag->status === 'agendado')
                                    <form method="POST" action="{{ route('agendamentos.confirmar', $ag->id) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <x-button
                                            type="submit"
                                            variant="outline"
                                            size="sm"
                                            icon="check-circle"
                                            title="Confirmar Agendamento"
                                            class="bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/40 hover:bg-emerald-500/25 font-semibold"
                                        >
                                            Confirmar
                                        </x-button>
                                    </form>
                                @endif

                                @if($osObj)
                                    <x-button href="{{ route('ordens-servico.show', $osObj->id) }}" variant="secondary" size="sm" icon="clipboard-list">
                                        Ver OS
                                    </x-button>
                                @elseif(!in_array($ag->status, ['cancelado', 'concluido'], true))
                                    <form method="POST" action="{{ route('ordens-servico.store') }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="agendamento_id" value="{{ $ag->id }}" />
                                        <x-button type="submit" variant="primary" size="sm" icon="clipboard-list">
                                            Abrir OS
                                        </x-button>
                                    </form>
                                @endif

                                <x-button href="{{ route('agendamentos.show', $ag->id) }}" variant="ghost" size="icon-sm" title="Ver Detalhes">
                                    <x-icon name="eye" class="w-4 h-4" />
                                </x-button>

                                @if(!in_array($ag->status, ['concluido', 'cancelado'], true))
                                    <x-button href="{{ route('agendamentos.edit', $ag->id) }}" variant="ghost" size="icon-sm" title="Editar">
                                        <x-icon name="pencil" class="w-4 h-4" />
                                    </x-button>
                                @endif

                                <form method="POST" action="{{ route('agendamentos.destroy', $ag->id) }}" onsubmit="return confirm('Tem certeza que deseja excluir este agendamento?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors cursor-pointer" title="Excluir Agendamento">
                                        <x-icon name="trash-2" class="w-4 h-4" />
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </x-card>
        @empty
            <x-card>
                <div class="text-center py-12">
                    <x-icon name="calendar" class="w-10 h-10 mx-auto text-muted-foreground mb-3" />
                    <h4 class="text-base font-semibold text-foreground">Nenhum agendamento encontrado</h4>
                    <p class="text-xs text-muted-foreground mt-1">Nenhum agendamento corresponde aos filtros selecionados.</p>
                    @if(!$isCliente || $possuiEsteticaVinculada)
                        <div class="mt-4">
                            <x-button href="{{ route('agendamentos.create', ($isAdmin ?? false) && !empty($empresaFiltroId) ? ['empresa_id' => $empresaFiltroId] : []) }}" icon="plus">
                                Novo Agendamento
                            </x-button>
                        </div>
                    @endif
                </div>
            </x-card>
        @endforelse
    </div>

    @if(method_exists($agendamentos, 'hasPages') && $agendamentos->hasPages())
        <div class="mt-6">
            {{ $agendamentos->links() }}
        </div>
    @endif
    </div>

    <div id="agendamentos-calendario-wrapper" class="{{ $viewMode === 'calendario' ? '' : 'hidden' }}">
        <x-card class="p-4 sm:p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between pb-4 mb-4 border-b border-border/70">
                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#3b82f6]"></span> Agendado
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#10b981]"></span> Confirmado
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#f59e0b]"></span> Em Andamento
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#64748b]"></span> Concluído
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#ef4444]"></span> Cancelado
                    </span>
                    @if($isCliente)
                        <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                            <span class="w-2.5 h-2.5 rounded border border-destructive/60 bg-destructive/20"></span> Horário lotado
                        </span>
                    @endif
                </div>

                <p class="text-[11px] text-muted-foreground">
                    Clique em um agendamento para ver detalhes{{ (!$isCliente || $possuiEsteticaVinculada) ? ' ou em um horário livre para agendar.' : '.' }}
                </p>
            </div>

            <div id="calendario-feedback" class="hidden mb-3 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs font-medium text-amber-400"></div>

            <div
                id="agendamentos-calendario-root"
                class="krdetail-calendar"
                data-eventos-url="{{ route('agendamentos.eventos') }}"
                data-create-url="{{ route('agendamentos.create') }}"
                data-can-create="{{ (!$isCliente || $possuiEsteticaVinculada) ? '1' : '0' }}"
                data-status="{{ request('status', '') }}"
                data-busca="{{ request('busca', '') }}"
                data-empresa-id="{{ $empresaFiltroId ?? '' }}"
                data-is-cliente="{{ $isCliente ? '1' : '0' }}"
                data-initial-view-mode="{{ $viewMode }}"
            ></div>
        </x-card>
    </div>
</x-layouts.app>
