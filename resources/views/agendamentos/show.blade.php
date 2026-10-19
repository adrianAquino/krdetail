@php
    $agendamentoObj = $agendamento ?? null;
    $osObj = $agendamentoObj?->ordemServico ?? $agendamentoObj?->ordem_servico ?? null;
    $servicosItens = $agendamentoObj?->agendamentosServicos ?? $agendamentoObj?->servicos_itens ?? [];
    $totalValor = collect($servicosItens)->sum(fn($i) => $i->valor_servico ?? $i->preco_base ?? 0);
    $totalMinutos = collect($servicosItens)->sum(fn($i) => $i->tempo_estimado_minutos ?? 0);
    $totalHoras = floor($totalMinutos / 60);
    $minutosRestantes = $totalMinutos % 60;
    $tempoTotalFormatado = $totalHoras > 0 ? "{$totalHoras}h " . ($minutosRestantes > 0 ? "{$minutosRestantes}min" : '') : "{$minutosRestantes}min";
@endphp

<x-layouts.app :title="'Agendamento #' . ($agendamentoObj?->id ?? 1)">
    <x-header
        :title="'Agendamento #' . str_pad($agendamentoObj?->id ?? 1, 5, '0', STR_PAD_LEFT)"
        :subtitle="'Criado em ' . \Carbon\Carbon::parse($agendamentoObj?->created_at ?? now())->format('d/m/Y \à\s H:i')"
    >
        <x-slot:actions>
            @if($osObj)
                <x-button href="{{ route('ordens-servico.show', $osObj->id) }}" variant="primary" size="sm" icon="clipboard-list">
                    Acessar Ordem de Serviço ({{ $osObj->numero }})
                </x-button>
            @else
                <form method="POST" action="{{ route('ordens-servico.store') }}" class="inline">
                    @csrf
                    <input type="hidden" name="agendamento_id" value="{{ $agendamentoObj?->id ?? 1 }}" />
                    <x-button type="submit" variant="primary" size="sm" icon="clipboard-list">
                        Gerar Ordem de Serviço
                    </x-button>
                </form>
            @endif

            <x-button href="{{ route('agendamentos.edit', $agendamentoObj?->id ?? 1) }}" variant="secondary" size="sm" icon="pencil">
                Editar
            </x-button>

            <x-button href="{{ route('agendamentos.index') }}" variant="ghost" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Coluna Esquerda: Status, Cliente e Veículo -->
        <div class="space-y-6">
            <!-- Status & Datas -->
            <x-card title="Situação do Agendamento">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground uppercase font-semibold">Status</span>
                        <x-badge-status :status="$agendamentoObj?->status ?? 'agendado'" />
                    </div>

                    <div class="pb-3 border-b border-border/60">
                        <span class="text-xs text-muted-foreground uppercase font-semibold block mb-1">Horário Agendado</span>
                        <p class="font-bold text-base text-foreground flex items-center gap-2">
                            <x-icon name="clock" class="w-4 h-4 text-primary" />
                            {{ \Carbon\Carbon::parse($agendamentoObj?->data_inicio ?? now())->format('d/m/Y \à\s H:i') }}
                        </p>
                        @if($agendamentoObj?->data_fim)
                            <p class="text-xs text-muted-foreground mt-1 ml-6">
                                Previsão de entrega: {{ \Carbon\Carbon::parse($agendamentoObj->data_fim)->format('d/m/Y H:i') }}
                            </p>
                        @endif
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
                                "{{ $agendamentoObj->observacoes }}"
                            </p>
                        </div>
                    @endif
                </div>
            </x-card>

            <!-- Card Cliente -->
            <x-card title="Cliente">
                @if($agendamentoObj?->cliente)
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-bold text-sm">
                            {{ strtoupper(substr($agendamentoObj->cliente->nome, 0, 2)) }}
                        </div>
                        <div>
                            <a href="{{ route('clientes.show', $agendamentoObj->cliente->id) }}" class="font-semibold text-foreground hover:text-primary transition-colors text-sm">
                                {{ $agendamentoObj->cliente->nome }}
                            </a>
                            <p class="text-xs text-muted-foreground">{{ $agendamentoObj->cliente->telefone }}</p>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-border/60 text-xs space-y-1 text-muted-foreground">
                        <p><strong class="text-foreground">CPF:</strong> {{ $agendamentoObj->cliente->cpf_cnpj ?? 'Não informado' }}</p>
                        <p><strong class="text-foreground">E-mail:</strong> {{ $agendamentoObj->cliente->email ?? 'Não informado' }}</p>
                    </div>
                @else
                    <p class="text-xs text-muted-foreground">Nenhum cliente vinculado.</p>
                @endif
            </x-card>

            <!-- Card Veículo -->
            <x-card title="Veículo Selecionado">
                @if($agendamentoObj?->veiculo)
                    <div class="p-3 rounded-lg bg-secondary/50 border border-border mb-3 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-sm text-foreground">{{ $agendamentoObj->veiculo->marca }} {{ $agendamentoObj->veiculo->modelo }}</p>
                            <p class="text-xs text-muted-foreground">{{ $agendamentoObj->veiculo->cor ?? 'Cor N/D' }}</p>
                        </div>
                        <span class="font-mono text-sm font-extrabold px-2.5 py-1 rounded bg-secondary text-primary border border-border">
                            {{ $agendamentoObj->veiculo->placa }}
                        </span>
                    </div>

                    <x-button href="{{ route('veiculos.show', $agendamentoObj->veiculo->id) }}" variant="outline" size="sm" class="w-full" icon="car">
                        Ver Histórico do Veículo
                    </x-button>
                @else
                    <p class="text-xs text-muted-foreground">Nenhum veículo vinculado.</p>
                @endif
            </x-card>
        </div>

        <!-- Coluna Direita: Serviços Contratados e Vínculo de OS -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Serviços Contratados (com preservação de AgendamentoServico) -->
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
                            $servicoModel = $item->servico ?? $item;
                            $tempoEstimado = $item->tempo_estimado_minutos ?? $servicoModel->tempo_estimado_minutos ?? 60;
                            $valorServico = $item->valor_servico ?? $servicoModel->preco_base ?? 0;
                            $horasItem = floor($tempoEstimado / 60);
                            $minutosItem = $tempoEstimado % 60;
                            $tempoItemFormatado = $horasItem > 0 ? "{$horasItem}h " . ($minutosItem > 0 ? "{$minutosItem}min" : '') : "{$minutosItem}min";
                        @endphp
                        <div class="flex items-start justify-between p-3.5 rounded-xl border border-border bg-secondary/20">
                            <div>
                                <h4 class="font-bold text-sm text-foreground">{{ $servicoModel->nome ?? 'Serviço' }}</h4>
                                <p class="text-xs text-muted-foreground mt-0.5">{{ $servicoModel->descricao ?? '' }}</p>
                                <div class="flex items-center gap-1.5 text-xs text-muted-foreground mt-1">
                                    <x-icon name="clock" class="w-3.5 h-3.5" />
                                    <span>Tempo contratado: {{ $tempoItemFormatado }}</span>
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

                <div class="mt-5 pt-4 border-t border-border flex items-center justify-between">
                    <div>
                        <span class="text-xs text-muted-foreground block">Tempo Total Estimado</span>
                        <span class="font-bold text-foreground text-sm">{{ $tempoTotalFormatado }}</span>
                    </div>

                    <div class="text-right">
                        <span class="text-xs text-muted-foreground block">Valor Total Contratado</span>
                        <span class="text-2xl font-black text-primary">
                            R$ {{ number_format($totalValor, 2, ',', '.') }}
                        </span>
                    </div>
                </div>
            </x-card>

            <!-- Vínculo com Ordem de Serviço -->
            <x-card title="Ordem de Serviço (Acompanhamento Operacional)">
                @if($osObj)
                    <div class="flex items-center justify-between p-4 rounded-xl border border-primary/30 bg-primary/5">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-primary/10 text-primary">
                                <x-icon name="clipboard-list" class="w-6 h-6" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-sm font-bold text-foreground">{{ $osObj->numero }}</span>
                                    <x-badge-status :status="$osObj->status" />
                                </div>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    As tarefas operacionais e produtos utilizados estão sendo executados nesta OS.
                                </p>
                            </div>
                        </div>

                        <x-button href="{{ route('ordens-servico.show', $osObj->id) }}" variant="primary" size="md" icon="eye">
                            Abrir OS
                        </x-button>
                    </div>
                @else
                    <div class="text-center py-6">
                        <x-icon name="clipboard-list" class="w-8 h-8 text-muted-foreground mx-auto mb-2" />
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
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>