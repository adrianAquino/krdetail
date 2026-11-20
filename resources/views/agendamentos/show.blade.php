@php
    /** @var \App\Models\Agendamento $agendamento */
    $isCliente = auth()->check() && auth()->user()->isCliente();
    $agendamentoObj = isset($agendamento) && $agendamento instanceof \App\Models\Agendamento
        ? $agendamento
        : new \App\Models\Agendamento();

    /** @var \App\Models\Cliente|null $clienteObj */
    $clienteObj = $agendamentoObj->cliente ?? null;

    /** @var \App\Models\Veiculo|null $veiculoObj */
    $veiculoObj = $agendamentoObj->veiculo ?? null;

    /** @var \App\Models\OrdemServico|null $osObj */
    $osObj = $agendamentoObj->ordemServico ?? null;

    $servicosItens = $agendamentoObj->agendamentosServicos ?? $agendamentoObj->servicos_itens ?? [];
    $totalValor = collect($servicosItens)->sum(fn($i) => (float) data_get($i, 'valor_servico', data_get($i, 'preco_base', 0)));
    $totalMinutosServicos = collect($servicosItens)->sum(fn($i) => (int) data_get($i, 'tempo_estimado_minutos', data_get($i, 'servico.tempo_estimado_minutos', 0)));
    $gargaloMinutos = \App\Models\Agendamento::GARGALO_MINUTOS;
    $totalMinutosComGargalo = $totalMinutosServicos > 0 ? $totalMinutosServicos + $gargaloMinutos : 0;

    $tempoServicosFormatado = \App\Models\Agendamento::formatarMinutos((int) $totalMinutosServicos);
    $tempoTotalFormatado = \App\Models\Agendamento::formatarMinutos((int) $totalMinutosComGargalo);

    /** @var \Illuminate\Support\Collection<int, \App\Models\TarefaOrdemServico> $tarefas */
    $tarefas = $osObj && $osObj->tarefas ? $osObj->tarefas : collect();
    $totalTarefas = $tarefas->count();
    $tarefasConcluidas = $tarefas->where('status', 'concluida')->count();
    $progressoOs = $totalTarefas > 0 ? (int) round(($tarefasConcluidas / $totalTarefas) * 100) : ($agendamentoObj?->status === 'concluido' ? 100 : 0);

    $temTarefaIniciada = $tarefas->contains(fn ($t) => in_array(data_get($t, 'status'), ['em_andamento', 'concluida'], true));
    $podeCancelar = $agendamentoObj
        && in_array($agendamentoObj?->status, ['agendado', 'confirmado'], true)
        && !$temTarefaIniciada
        && (!$osObj || !in_array($osObj?->status, ['em_andamento', 'concluida'], true));

    $dataConclusaoReal = $agendamentoObj?->data_conclusao ?? $osObj?->data_fim ?? null;
@endphp

<x-layouts.app :title="'Agendamento #' . ($agendamentoObj?->id ?? 1)">
    <x-header
        :title="'Agendamento #' . str_pad((string)($agendamentoObj?->id ?? 1), 5, '0', STR_PAD_LEFT)"
        :subtitle="'Registrado em ' . \Carbon\Carbon::parse($agendamentoObj?->created_at ?? now())->format('d/m/Y \à\s H:i')"
    >
        <x-slot:actions>
            @if(!$isCliente)
                @if($osObj)
                    <x-button href="{{ route('ordens-servico.show', $osObj?->id ?? 0) }}" variant="primary" size="sm" icon="clipboard-list">
                        Acessar Ordem de Serviço ({{ $osObj?->numero ?? '' }})
                    </x-button>
                @elseif(!in_array($agendamentoObj?->status, ['cancelado', 'concluido'], true))
                    <form method="POST" action="{{ route('ordens-servico.store') }}" class="inline">
                        @csrf
                        <input type="hidden" name="agendamento_id" value="{{ $agendamentoObj?->id ?? 1 }}" />
                        <x-button type="submit" variant="primary" size="sm" icon="clipboard-list">
                            Gerar Ordem de Serviço
                        </x-button>
                    </form>
                @endif

                @if(!in_array($agendamentoObj?->status, ['concluido', 'cancelado'], true))
                    <x-button href="{{ route('agendamentos.edit', $agendamentoObj?->id ?? 1) }}" variant="secondary" size="sm" icon="pencil">
                        Editar
                    </x-button>
                @endif
            @endif

            @if($podeCancelar && $agendamentoObj)
                <form method="POST"
                      action="{{ route('agendamentos.cancelar', $agendamentoObj?->id ?? 0) }}"
                      class="inline"
                      onsubmit="return confirm('Tem certeza que deseja cancelar este agendamento?')">
                    @csrf
                    @method('PATCH')
                    <x-button type="submit" variant="outline" size="sm" icon="ban" class="text-rose-400 border-rose-500/30 hover:bg-rose-500/10">
                        Cancelar Agendamento
                    </x-button>
                </form>
            @endif

            <x-button href="{{ route('agendamentos.index') }}" variant="ghost" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Coluna Esquerda: Status, Cliente e Veículo -->
        <div class="space-y-6">
            <!-- Status & Linha do Tempo -->
            <x-card title="Situação & Linha do Tempo">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground uppercase font-semibold">Status</span>
                        <x-badge-status :status="$agendamentoObj?->status ?? 'agendado'" />
                    </div>

                    <div class="space-y-2.5 pb-3 border-b border-border/60 text-xs">
                        <div class="p-2.5 rounded-lg bg-secondary/30 border border-border/60">
                            <span class="text-muted-foreground block">Registrado em</span>
                            <span class="font-semibold text-foreground text-sm">
                                {{ \Carbon\Carbon::parse($agendamentoObj?->created_at ?? now())->format('d/m/Y \à\s H:i') }}
                            </span>
                        </div>

                        <div class="p-2.5 rounded-lg bg-secondary/30 border border-border/60">
                            <span class="text-muted-foreground block">Horário Agendado (Entrada)</span>
                            <span class="font-bold text-primary text-sm flex items-center gap-1.5 mt-0.5">
                                <x-icon name="clock" class="w-4 h-4" />
                                {{ \Carbon\Carbon::parse($agendamentoObj?->data_inicio ?? now())->format('d/m/Y \à\s H:i') }}
                            </span>
                        </div>

                        <div class="p-2.5 rounded-lg bg-secondary/30 border border-border/60">
                            <span class="text-muted-foreground block">Previsão Estimada de Conclusão (+30min)</span>
                            <span class="font-semibold text-foreground text-sm">
                                {{ $agendamentoObj?->data_fim ? \Carbon\Carbon::parse($agendamentoObj?->data_fim)->format('d/m/Y \à\s H:i') : 'Não calculada' }}
                            </span>
                        </div>

                        <div class="p-2.5 rounded-lg bg-secondary/30 border border-border/60">
                            <span class="text-muted-foreground block">Abertura da Ordem de Serviço</span>
                            <span class="font-semibold text-foreground text-sm">
                                {{ $osObj?->data_inicio ? \Carbon\Carbon::parse($osObj?->data_inicio)->format('d/m/Y \à\s H:i') : 'Aguardando abertura' }}
                            </span>
                        </div>

                        <div class="p-2.5 rounded-lg {{ $dataConclusaoReal ? 'bg-success/10 border-success/30' : 'bg-secondary/30 border-border/60' }} border">
                            <span class="text-muted-foreground block">Conclusão Real (Fechamento da OS)</span>
                            <span class="font-semibold {{ $dataConclusaoReal ? 'text-success' : 'text-foreground' }} text-sm">
                                {{ $dataConclusaoReal ? \Carbon\Carbon::parse($dataConclusaoReal)->format('d/m/Y \à\s H:i') : 'Aguardando conclusão' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Canal de Origem:</span>
                        <span class="font-semibold text-foreground px-2 py-0.5 rounded bg-secondary border border-border">
                            {{ $agendamentoObj?->origem ?? 'Direto' }}
                        </span>
                    </div>

                    @if(!empty($agendamentoObj?->observacoes))
                        <div class="pt-2">
                            <span class="text-xs text-muted-foreground font-semibold block mb-1">Observações:</span>
                            <p class="text-xs text-muted-foreground italic bg-secondary/30 p-2.5 rounded-lg border border-border">
                                "{{ $agendamentoObj?->observacoes }}"
                            </p>
                        </div>
                    @endif
                </div>
            </x-card>

            @if(!$isCliente)
                <!-- Card Cliente -->
                <x-card title="Cliente">
                    @if($clienteObj)
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-bold text-sm">
                                {{ mb_strtoupper(mb_substr($clienteObj?->nome ?? '', 0, 2, 'UTF-8'), 'UTF-8') }}
                            </div>
                            <div>
                                <a href="{{ route('clientes.show', $clienteObj?->id ?? 0) }}" class="font-semibold text-foreground hover:text-primary transition-colors text-sm">
                                    {{ $clienteObj?->nome }}
                                </a>
                                <p class="text-xs text-muted-foreground">{{ $clienteObj?->telefone }}</p>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-border/60 text-xs space-y-1 text-muted-foreground">
                            <p><strong class="text-foreground">CPF:</strong> {{ $clienteObj?->cpf_cnpj ?? 'Não informado' }}</p>
                            <p><strong class="text-foreground">E-mail:</strong> {{ $clienteObj?->email ?? 'Não informado' }}</p>
                        </div>
                    @else
                        <p class="text-xs text-muted-foreground">Nenhum cliente vinculado.</p>
                    @endif
                </x-card>
            @endif

            <!-- Card Veículo -->
            <x-card title="Veículo Selecionado">
                @if($veiculoObj)
                    <div class="p-3 rounded-lg bg-secondary/50 border border-border mb-3 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-sm text-foreground">{{ $veiculoObj?->marca }} {{ $veiculoObj?->modelo }}</p>
                            <p class="text-xs text-muted-foreground">{{ $veiculoObj?->cor ?? 'Cor N/D' }}</p>
                        </div>
                        <span class="font-mono text-sm font-extrabold px-2.5 py-1 rounded bg-secondary text-primary border border-border">
                            {{ $veiculoObj?->placa }}
                        </span>
                    </div>

                    <x-button href="{{ route('veiculos.show', $veiculoObj?->id ?? 0) }}" variant="outline" size="sm" class="w-full" icon="car">
                        Ver Histórico do Veículo
                    </x-button>
                @else
                    <p class="text-xs text-muted-foreground">Nenhum veículo vinculado.</p>
                @endif
            </x-card>
        </div>

        <!-- Coluna Direita: Serviços Contratados e Andamento da OS -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Serviços Contratados -->
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                                <x-icon name="wrench" class="h-4 w-4" />
                            </div>
                            <h3 class="text-base font-semibold text-foreground">Serviços Contratados</h3>
                        </div>
                        <span class="text-xs text-muted-foreground">Valores registrados no agendamento</span>
                    </div>
                </x-slot:header>

                <div class="space-y-3">
                    @forelse($servicosItens as $item)
                        @php
                            $nomeServico = data_get($item, 'servico.nome', data_get($item, 'nome', 'Serviço'));
                            $descricaoServico = data_get($item, 'servico.descricao', data_get($item, 'descricao', ''));
                            $tempoEstimado = (int) data_get($item, 'tempo_estimado_minutos', data_get($item, 'servico.tempo_estimado_minutos', 60));
                            $valorServico = (float) data_get($item, 'valor_servico', data_get($item, 'preco_base', 0));
                            $horasItem = floor($tempoEstimado / 60);
                            $minutosItem = $tempoEstimado % 60;
                            $tempoItemFormatado = $horasItem > 0 ? "{$horasItem}h " . ($minutosItem > 0 ? "{$minutosItem}min" : '') : "{$minutosItem}min";
                        @endphp
                        <div class="flex items-start justify-between p-3.5 rounded-xl border border-border bg-secondary/20">
                            <div>
                                <h4 class="font-bold text-sm text-foreground">{{ $nomeServico }}</h4>
                                @if(!empty($descricaoServico))
                                    <p class="text-xs text-muted-foreground mt-0.5">{{ $descricaoServico }}</p>
                                @endif
                                <div class="flex items-center gap-1.5 text-xs text-muted-foreground mt-1">
                                    <x-icon name="clock" class="w-3.5 h-3.5" />
                                    <span>Tempo estimado: {{ $tempoItemFormatado }}</span>
                                </div>
                            </div>

                            <div class="text-right shrink-0 ml-4">
                                <span class="text-base font-extrabold text-primary">
                                    R$ {{ number_format($valorServico, 2, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-muted-foreground py-4 text-center">Nenhum serviço registrado neste agendamento.</p>
                    @endforelse
                </div>

                <div class="mt-5 pt-4 border-t border-border flex flex-wrap items-center justify-between gap-4">
                    <div class="space-y-0.5">
                        <span class="text-xs text-muted-foreground block">
                            Tempo dos Serviços: <strong class="text-foreground">{{ $tempoServicosFormatado }}</strong>
                            @if($totalMinutosServicos > 0)
                                + <strong class="text-amber-400">30min gargalo</strong>
                            @endif
                        </span>
                        <span class="font-bold text-foreground text-sm block">Tempo Total Estimado: {{ $tempoTotalFormatado }}</span>
                    </div>

                    <div class="text-right">
                        <span class="text-xs text-muted-foreground block">Valor Total Contratado</span>
                        <span class="text-2xl font-black text-primary">
                            R$ {{ number_format($totalValor, 2, ',', '.') }}
                        </span>
                    </div>
                </div>
            </x-card>

            <!-- Vínculo e Andamento da Ordem de Serviço -->
            <x-card title="Andamento dos Serviços (Ordem de Serviço)">
                @if($osObj)
                    <div class="space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 rounded-xl border border-primary/30 bg-primary/5">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-lg bg-primary/10 text-primary">
                                    <x-icon name="clipboard-list" class="w-6 h-6" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-sm font-bold text-foreground">{{ $osObj?->numero }}</span>
                                        <x-badge-status :status="$osObj?->status ?? 'aberta'" />
                                    </div>
                                    <p class="text-xs text-muted-foreground mt-0.5">
                                        {{ $tarefasConcluidas }} de {{ $totalTarefas }} etapa(s) concluída(s) ({{ $progressoOs }}%)
                                    </p>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5 text-[11px] text-muted-foreground mt-1">
                                        <span><strong>Abertura da OS:</strong> {{ $osObj?->data_inicio ? \Carbon\Carbon::parse($osObj?->data_inicio)->format('d/m/Y H:i') : '-' }}</span>
                                        <span><strong>Fechamento da OS:</strong> {{ $osObj?->data_fim ? \Carbon\Carbon::parse($osObj?->data_fim)->format('d/m/Y H:i') : 'Em aberto' }}</span>
                                    </div>
                                </div>
                            </div>

                            @if(!$isCliente)
                                <x-button href="{{ route('ordens-servico.show', $osObj?->id ?? 0) }}" variant="primary" size="md" icon="eye">
                                    Abrir OS
                                </x-button>
                            @endif
                        </div>

                        @if($totalTarefas > 0)
                            <div class="space-y-2">
                                <div class="w-full bg-secondary h-2 rounded-full overflow-hidden">
                                    <div class="bg-primary h-full rounded-full transition-all duration-500" @style(['width: ' . $progressoOs . '%'])></div>
                                </div>

                                <div class="space-y-2.5 pt-2">
                                    @foreach($tarefas as $tarefa)
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 rounded-xl border border-border bg-secondary/20 p-3">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-semibold text-sm text-foreground">{{ data_get($tarefa, 'titulo', 'Tarefa') }}</span>
                                                    <x-badge-status :status="data_get($tarefa, 'status', 'pendente')" />
                                                </div>
                                                <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground mt-1">
                                                    @if(data_get($tarefa, 'funcionario'))
                                                        <span>Responsável: <strong class="text-foreground">{{ data_get($tarefa, 'funcionario.nome') }}</strong></span>
                                                    @endif
                                                    <span><strong>Abertura da Tarefa:</strong> {{ data_get($tarefa, 'data_inicio') ? \Carbon\Carbon::parse(data_get($tarefa, 'data_inicio'))->format('d/m/Y H:i') : 'Não iniciada' }}</span>
                                                    <span><strong>Encerramento da Tarefa:</strong> {{ data_get($tarefa, 'data_conclusao') ? \Carbon\Carbon::parse(data_get($tarefa, 'data_conclusao'))->format('d/m/Y H:i') : 'Aguardando conclusão' }}</span>
                                                </div>
                                                @if(!empty(data_get($tarefa, 'observacoes')))
                                                    <p class="text-xs text-muted-foreground italic mt-1">"{{ data_get($tarefa, 'observacoes') }}"</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-6">
                        <x-icon name="clock" class="w-8 h-8 text-muted-foreground mx-auto mb-2" />
                        @if($isCliente)
                            <p class="text-sm font-semibold text-foreground">Aguardando Início na Oficina</p>
                            <p class="text-xs text-muted-foreground mt-1 max-w-md mx-auto">
                                Assim que seu veículo der entrada e a Ordem de Serviço for aberta pela equipe, você poderá acompanhar cada etapa do serviço em tempo real aqui.
                            </p>
                        @elseif(!in_array($agendamentoObj?->status, ['cancelado', 'concluido'], true))
                            <p class="text-sm font-semibold text-foreground">Ordem de Serviço ainda não gerada</p>
                            <p class="text-xs text-muted-foreground mt-1 max-w-md mx-auto">
                                Ao abrir a Ordem de Serviço, as tarefas dos serviços contratados serão geradas automaticamente para os funcionários da oficina.
                            </p>
                            <form method="POST" action="{{ route('ordens-servico.store') }}" class="mt-4">
                                @csrf
                                <input type="hidden" name="agendamento_id" value="{{ $agendamentoObj?->id ?? 1 }}" />
                                <x-button type="submit" variant="primary" size="md" icon="plus">
                                    Gerar Ordem de Serviço Agora
                                </x-button>
                            </form>
                        @else
                            <p class="text-sm font-semibold text-foreground">Nenhuma Ordem de Serviço gerada</p>
                            <p class="text-xs text-muted-foreground mt-1 max-w-md mx-auto">
                                Este agendamento está {{ $agendamentoObj?->status === 'cancelado' ? 'cancelado' : 'concluído' }} e não permite mais abertura de Ordem de Serviço.
                            </p>
                        @endif
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
