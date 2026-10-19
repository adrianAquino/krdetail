<x-layouts.app title="Agendamentos">
    <x-header
        title="Agendamentos"
        subtitle="Organização do fluxo de entrada de veículos, agendamento de serviços e acompanhamento de status."
    >
        <x-slot:actions>
            <x-button href="{{ route('agendamentos.create') }}" icon="plus" size="md">
                Novo Agendamento
            </x-button>
        </x-slot:actions>
    </x-header>

    <!-- Filters Bar -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" action="{{ route('agendamentos.index') }}" class="flex-1 max-w-md flex items-center gap-2">
            <x-input
                name="busca"
                value="{{ request('busca') }}"
                placeholder="Buscar por cliente, placa ou veículo..."
                icon="search"
            />
            @if(request('busca') || request('status'))
                <x-button href="{{ route('agendamentos.index') }}" variant="ghost" size="sm">
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
                    href="{{ route('agendamentos.index', array_merge(request()->query(), ['status' => $key])) }}"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium border whitespace-nowrap transition-colors {{ $currentStatus === $key ? 'bg-primary/15 border-primary text-primary font-semibold' : 'bg-secondary/40 border-border text-muted-foreground hover:text-foreground' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @php
        $agendamentosDemo = $agendamentos ?? [
            (object)[
                'id' => 1,
                'data_inicio' => now()->setHour(9)->setMinute(0),
                'data_fim' => now()->setHour(13)->setMinute(0),
                'status' => 'em_andamento',
                'origem' => 'WhatsApp',
                'cliente' => (object)['id' => 1, 'nome' => 'Lucas Guimarães', 'telefone' => '(11) 98765-4321'],
                'veiculo' => (object)['id' => 1, 'marca' => 'BMW', 'modelo' => '320i M Sport', 'placa' => 'BRA2E19'],
                'servicos_nomes' => ['Polimento Técnico Comercial', 'Proteção de Pintura Selante'],
                'valor_total' => 850.00,
                'tempo_total' => '4h 00min',
                'ordem_servico' => (object)['id' => 15, 'numero' => 'OS-00015', 'status' => 'em_andamento']
            ],
            (object)[
                'id' => 2,
                'data_inicio' => now()->setHour(14)->setMinute(30),
                'data_fim' => now()->setHour(17)->setMinute(30),
                'status' => 'confirmado',
                'origem' => 'Presencial',
                'cliente' => (object)['id' => 2, 'nome' => 'Marina Silveira', 'telefone' => '(11) 97123-8890'],
                'veiculo' => (object)['id' => 2, 'marca' => 'Audi', 'modelo' => 'Q3 Black Edition', 'placa' => 'KRX8A42'],
                'servicos_nomes' => ['Higienização Interna Completa + Ozônio'],
                'valor_total' => 380.00,
                'tempo_total' => '3h 00min',
                'ordem_servico' => null
            ],
            (object)[
                'id' => 3,
                'data_inicio' => now()->addDay()->setHour(10)->setMinute(0),
                'data_fim' => now()->addDay()->setHour(16)->setMinute(0),
                'status' => 'agendado',
                'origem' => 'Site',
                'cliente' => (object)['id' => 3, 'nome' => 'Roberto Medeiros', 'telefone' => '(11) 99345-1234'],
                'veiculo' => (object)['id' => 3, 'marca' => 'Porsche', 'modelo' => 'Macan GTS', 'placa' => 'GTS9F99'],
                'servicos_nomes' => ['Vitrificação Cerâmica 9H (Pintura)', 'Lavagem Detalhada Premium'],
                'valor_total' => 1380.00,
                'tempo_total' => '6h 00min',
                'ordem_servico' => null
            ],
            (object)[
                'id' => 4,
                'data_inicio' => now()->subDays(2)->setHour(8)->setMinute(30),
                'data_fim' => now()->subDays(2)->setHour(12)->setMinute(0),
                'status' => 'concluido',
                'origem' => 'Telefone',
                'cliente' => (object)['id' => 4, 'nome' => 'Juliana Ferreira', 'telefone' => '(11) 98456-7890'],
                'veiculo' => (object)['id' => 4, 'marca' => 'Volvo', 'modelo' => 'XC60 Recharge', 'placa' => 'VOL6C30'],
                'servicos_nomes' => ['Descontaminação Ferrosa + Selante Sintético'],
                'valor_total' => 490.00,
                'tempo_total' => '3h 30min',
                'ordem_servico' => (object)['id' => 14, 'numero' => 'OS-00014', 'status' => 'concluida']
            ],
        ];
    @endphp

    <div class="space-y-3">
        @forelse($agendamentosDemo as $ag)
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
                                        - {{ \Carbon\Carbon::parse($ag->data_fim)->format('H:i') }}
                                    @endif
                                </span>
                                <x-badge-status :status="$ag->status" />
                                @if(!empty($ag->origem))
                                    <span class="text-[11px] text-muted-foreground bg-secondary px-2 py-0.5 rounded border border-border">
                                        Via {{ $ag->origem }}
                                    </span>
                                @endif
                            </div>

                            <!-- Cliente e Veículo -->
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-foreground">
                                <a href="{{ route('clientes.show', $ag->cliente->id) }}" class="font-semibold hover:text-primary transition-colors flex items-center gap-1.5">
                                    <x-icon name="user" class="w-3.5 h-3.5 text-muted-foreground" />
                                    {{ $ag->cliente->nome }}
                                </a>
                                <span class="text-muted-foreground">•</span>
                                <a href="{{ route('veiculos.show', $ag->veiculo->id) }}" class="text-muted-foreground hover:text-foreground transition-colors flex items-center gap-1.5">
                                    <x-icon name="car" class="w-3.5 h-3.5 text-primary" />
                                    <span>{{ $ag->veiculo->marca }} {{ $ag->veiculo->modelo }}</span>
                                    <span class="font-mono text-xs font-bold px-1.5 py-0.2 bg-secondary rounded border border-border text-foreground">
                                        {{ $ag->veiculo->placa }}
                                    </span>
                                </a>
                            </div>

                            <!-- Serviços Contratados -->
                            @php
                                $servicosList = $ag->servicos_nomes ?? ($ag->servicos ? $ag->servicos->pluck('nome') : []);
                                $valorTotal = $ag->valor_total ?? ($ag->servicos ? $ag->servicos->sum(fn($s) => $s->pivot->valor_servico ?? $s->preco_base) : 0);
                                $osObj = $ag->ordem_servico ?? $ag->ordemServico ?? null;
                            @endphp
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
                            @if($osObj)
                                <x-button href="{{ route('ordens-servico.show', $osObj->id) }}" variant="secondary" size="sm" icon="clipboard-list">
                                    Ver OS
                                </x-button>
                            @else
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

                            <x-button href="{{ route('agendamentos.edit', $ag->id) }}" variant="ghost" size="icon-sm" title="Editar">
                                <x-icon name="pencil" class="w-4 h-4" />
                            </x-button>
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
                    <div class="mt-4">
                        <x-button href="{{ route('agendamentos.create') }}" icon="plus">
                            Novo Agendamento
                        </x-button>
                    </div>
                </div>
            </x-card>
        @endforelse
    </div>
</x-layouts.app>