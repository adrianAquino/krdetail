<x-layouts.app :title="'Editar Agendamento #' . ($agendamento?->id ?? 1)">
    <x-header
        :title="'Editar Agendamento #' . ($agendamento?->id ?? 1)"
        subtitle="Atualize o horário, status ou serviços vinculados a este agendamento."
    >
        <x-slot:actions>
            <x-button href="{{ route('agendamentos.show', $agendamento?->id ?? 1) }}" variant="outline" size="sm" icon="eye">
                Ver Detalhes
            </x-button>
            <x-button href="{{ route('agendamentos.index') }}" variant="ghost" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    @php
        /** @var \App\Models\Agendamento|object $agendamentoObj */
        $agendamentoObj = $agendamento ?? (object)[
            'id' => 1,
            'cliente_id' => 1,
            'veiculo_id' => 1,
            'data_inicio' => now()->addDay()->setHour(9)->setMinute(0),
            'data_fim' => now()->addDay()->setHour(13)->setMinute(0),
            'status' => 'confirmado',
            'origem' => 'WhatsApp',
            'observacoes' => 'Cliente pontual, aguardará no local.',
            'servicos_ids' => [1, 4]
        ];

        $clientesDemo = $clientes ?? [
            (object)[
                'id' => 1,
                'nome' => 'Lucas Guimarães',
                'telefone' => '(11) 98765-4321',
                'veiculos' => [
                    (object)['id' => 1, 'placa' => 'BRA2E19', 'marca' => 'BMW', 'modelo' => '320i M Sport'],
                    (object)['id' => 5, 'placa' => 'TOY4X99', 'marca' => 'Toyota', 'modelo' => 'Corolla Cross'],
                ]
            ],
            (object)[
                'id' => 2,
                'nome' => 'Marina Silveira',
                'telefone' => '(11) 97123-8890',
                'veiculos' => [
                    (object)['id' => 2, 'placa' => 'KRX8A42', 'marca' => 'Audi', 'modelo' => 'Q3 Black Edition'],
                ]
            ],
        ];

        $servicosAtivos = collect($servicos ?? []);
        // Preserva serviços inativos já contratados no agendamento para não sumirem na edição
        if (isset($agendamentoObj->agendamentosServicos)) {
            $idsAtivos = $servicosAtivos->pluck('id')->all();
            foreach ($agendamentoObj->agendamentosServicos as $itemContratado) {
                if (data_get($itemContratado, 'servico') && !in_array(data_get($itemContratado, 'servico_id'), $idsAtivos, true)) {
                    $servicosAtivos->push(data_get($itemContratado, 'servico'));
                    $idsAtivos[] = data_get($itemContratado, 'servico_id');
                }
            }
        }

        $possuiOS = !empty($agendamentoObj?->ordemServico);
        $valorOriginalInicio = \Carbon\Carbon::parse($agendamentoObj?->data_inicio ?? now())->format('Y-m-d\TH:i');
        $minDataInicio = \Carbon\Carbon::parse($agendamentoObj?->data_inicio ?? now())->lt(now())
            ? $valorOriginalInicio
            : now()->format('Y-m-d\TH:i');

        $servicosContratadosIds = collect(old('servicos'))->pluck('servico_id')->filter()->all();
        if (empty($servicosContratadosIds)) {
            $servicosContratadosIds = isset($agendamentoObj->agendamentosServicos)
                ? collect($agendamentoObj->agendamentosServicos)->pluck('servico_id')->all()
                : ($agendamentoObj->servicos_ids ?? []);
        }

        $clientesVeiculosMap = collect($clientesDemo)->mapWithKeys(function ($cli) {
            $lista = collect($cli->veiculos ?? [])->map(fn ($v) => [
                'id' => $v->id,
                'placa' => $v->placa,
                'marca' => $v->marca,
                'modelo' => $v->modelo,
            ])->values()->all();
            return [$cli->id => $lista];
        })->all();

        // Caso o veículo histórico do agendamento tenha sido transferido para outro proprietário
        if (!empty($agendamentoObj?->cliente_id) && isset($agendamentoObj->veiculo) && $agendamentoObj->veiculo) {
            $veiculosDoCliente = collect($clientesVeiculosMap[$agendamentoObj->cliente_id] ?? []);
            if (!$veiculosDoCliente->contains('id', $agendamentoObj->veiculo?->id)) {
                $veiculosDoCliente->push([
                    'id' => $agendamentoObj->veiculo?->id,
                    'placa' => $agendamentoObj->veiculo?->placa,
                    'marca' => $agendamentoObj->veiculo?->marca,
                    'modelo' => ($agendamentoObj->veiculo?->modelo ?? '') . ' (Transferido)',
                ]);
                $clientesVeiculosMap[$agendamentoObj->cliente_id] = $veiculosDoCliente->values()->all();
            }
        }
    @endphp

    @if($possuiOS)
        <div class="mb-6">
            <x-alert type="info" title="Ordem de Serviço Vinculada ({{ $agendamentoObj?->ordemServico?->numero }}):">
                Como este agendamento já possui Ordem de Serviço gerada, o cliente, o veículo e os serviços contratados estão travados para manter a integridade operacional.
            </x-alert>
        </div>
    @endif

    @if(auth()->check() && auth()->user()->isAdministrador() && ($agendamentoObj?->empresa ?? null))
        <div class="mb-5 rounded-xl border border-primary/25 bg-primary/5 px-4 py-2.5 text-xs text-muted-foreground flex items-center gap-2">
            <x-icon name="briefcase" class="w-4 h-4 text-primary shrink-0" />
            <span>Agendamento pertencente à empresa: <strong class="text-foreground">{{ $agendamentoObj->empresa->nome_fantasia }}</strong></span>
        </div>
    @endif

    <!-- Alerta dinâmico quando um veículo é cadastrado pelo modal -->
    <div id="alerta-veiculo-criado" class="mb-6 hidden">
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-400 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 text-xs font-semibold">
                <x-icon name="check-circle" class="w-5 h-5 text-emerald-500 shrink-0" />
                <span id="alerta-veiculo-criado-texto">Veículo cadastrado e selecionado com sucesso!</span>
            </div>
            <button type="button" onclick="document.getElementById('alerta-veiculo-criado').classList.add('hidden')" class="text-emerald-400/70 hover:text-emerald-400 cursor-pointer">
                <x-icon name="x" class="w-4 h-4" />
            </button>
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('agendamentos.update', $agendamentoObj?->id ?? 1) }}"
        id="form-agendamento"
        data-clientes-veiculos="{{ json_encode($clientesVeiculosMap) }}"
        data-target-veiculo-pre="{{ (string) old('veiculo_id', (string) ($agendamentoObj->veiculo_id ?? '')) }}"
        data-valor-original-inicio="{{ $valorOriginalInicio }}"
        data-duracao-os-fixa="{{ $possuiOS ? (int) ($agendamentoObj->minutos_totais_com_gargalo ?? 90) : 0 }}"
    >
        @csrf
        @method('PUT')

        @if($possuiOS)
            <input type="hidden" name="cliente_id" value="{{ $agendamentoObj->cliente_id }}" />
            <input type="hidden" name="veiculo_id" value="{{ $agendamentoObj->veiculo_id }}" />
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <!-- Cliente & Veículo -->
                <x-card title="Cliente & Veículo">
                    <div class="space-y-4">
                        <div>
                            <x-select name="{{ $possuiOS ? 'cliente_id_display' : 'cliente_id' }}" id="cliente_select" label="Cliente Responsável" required :disabled="$possuiOS">
                                @foreach($clientesDemo as $cli)
                                    <option value="{{ $cli->id }}" data-nome="{{ $cli->nome }}" {{ old('cliente_id', $agendamentoObj->cliente_id) == $cli->id ? 'selected' : '' }}>
                                        {{ $cli->nome }} — {{ $cli->telefone }}
                                    </option>
                                @endforeach
                            </x-select>
                        </div>

                        <div>
                            <label for="veiculo_select" class="block text-xs font-medium text-foreground mb-1.5">
                                Veículo do Cliente <span class="text-destructive">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    name="{{ $possuiOS ? 'veiculo_id_display' : 'veiculo_id' }}"
                                    id="veiculo_select"
                                    required
                                    {{ $possuiOS ? 'disabled' : '' }}
                                    class="block w-full rounded-lg bg-input/40 border border-border focus:border-ring focus:ring-ring/40 px-3 py-2 pr-8 text-sm text-foreground focus:outline-none focus:ring-2 transition-all appearance-none cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
                                >
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-2.5 pointer-events-none text-muted-foreground">
                                    <x-icon name="chevron-down" class="w-4 h-4" />
                                </div>
                            </div>
                            @if(!$possuiOS)
                                <p id="veiculo_aviso" class="text-[11px] text-muted-foreground mt-1"></p>
                            @endif
                        </div>
                    </div>
                </x-card>

                <!-- Horário e Status -->
                <x-card title="Programação & Status">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input
                                type="datetime-local"
                                name="data_inicio"
                                id="data_inicio"
                                label="Data e Horário de Início"
                                min="{{ $minDataInicio }}"
                                :value="old('data_inicio', $valorOriginalInicio)"
                                hint="Atendimento de Segunda a Sábado, das 08:00 às 18:00."
                                required
                            />
                            <p id="erro_data_inicio" class="text-xs text-destructive mt-1 font-medium hidden">
                                A data e o horário devem ser futuros e dentro do expediente (Seg-Sáb, 08:00 às 18:00).
                            </p>
                            <div id="aviso_ultrapassa_expediente" class="hidden mt-2 rounded-lg border border-amber-500/30 bg-amber-500/10 p-2.5 text-[11px] text-amber-300 space-y-1.5">
                                <p>Os serviços selecionados ultrapassam o expediente das 18:00 neste horário.</p>
                                <button type="button" onclick="const c=document.getElementById('deixar_veiculo'); if(c){c.checked=true; c.dispatchEvent(new Event('change'));}" class="font-bold underline text-amber-200 cursor-pointer">
                                    Ativar &quot;Deixar veículo na estética&quot; para concluir no próximo dia útil
                                </button>
                            </div>
                        </div>

                        <div>
                            <x-input
                                type="datetime-local"
                                name="data_hora_estimado_conclusao"
                                id="data_fim"
                                label="Previsão Estimada de Conclusão (+30min)"
                                :value="$agendamentoObj->data_fim ? \Carbon\Carbon::parse($agendamentoObj->data_fim)->format('Y-m-d\TH:i') : ''"
                                hint="Calculado automaticamente: soma dos serviços + 30 min e eventual pernoite."
                                readonly
                                class="cursor-not-allowed opacity-80"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            @php $deixarVeiculoAtual = (bool) old('deixar_veiculo', $agendamentoObj->deixar_veiculo ?? false); @endphp
                            <label for="deixar_veiculo" class="flex items-start gap-3 p-3.5 rounded-xl border border-border bg-secondary/25 hover:bg-secondary/40 cursor-pointer transition-colors">
                                <input
                                    type="checkbox"
                                    name="deixar_veiculo"
                                    id="deixar_veiculo"
                                    value="1"
                                    {{ $deixarVeiculoAtual ? 'checked' : '' }}
                                    class="mt-0.5 h-4 w-4 rounded border-border bg-input text-primary focus:ring-primary cursor-pointer"
                                />
                                <div class="text-xs">
                                    <span class="font-semibold text-foreground block">Deixar o veículo na estética (Pernoite / Continuação no próximo dia útil)</span>
                                    <span class="text-muted-foreground">Marque esta opção quando a duração dos serviços ultrapassar as 18:00 do dia selecionado; a execução continua às 08:00 do próximo dia útil.</span>
                                </div>
                            </label>
                        </div>

                        <div>
                            <x-select name="status" id="status_select" label="Status Atual">
                                @php $statusSelecionado = old('status', $agendamentoObj->status); @endphp
                                <option value="agendado" {{ $statusSelecionado === 'agendado' ? 'selected' : '' }}>Agendado</option>
                                <option value="confirmado" {{ $statusSelecionado === 'confirmado' ? 'selected' : '' }}>Confirmado</option>
                                <option value="em_andamento" {{ $statusSelecionado === 'em_andamento' ? 'selected' : '' }}>Em Andamento</option>
                                <option value="concluido" {{ $statusSelecionado === 'concluido' ? 'selected' : '' }}>Concluído</option>
                                <option value="cancelado" {{ $statusSelecionado === 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                            </x-select>
                        </div>

                        <div>
                            <x-select name="origem" label="Canal de Origem">
                                @php
                                    $origemSelecionada = old('origem', $agendamentoObj->origem);
                                    $origensPadrao = ['WhatsApp', 'Telefone', 'Presencial', 'Balcão', 'Site', 'Outro'];
                                @endphp
                                <option value="WhatsApp" {{ $origemSelecionada === 'WhatsApp' ? 'selected' : '' }}>WhatsApp</option>
                                <option value="Telefone" {{ $origemSelecionada === 'Telefone' ? 'selected' : '' }}>Telefone</option>
                                <option value="Presencial" {{ $origemSelecionada === 'Presencial' ? 'selected' : '' }}>Presencial</option>
                                <option value="Balcão" {{ $origemSelecionada === 'Balcão' ? 'selected' : '' }}>Balcão</option>
                                <option value="Site" {{ $origemSelecionada === 'Site' ? 'selected' : '' }}>Site</option>
                                <option value="Outro" {{ $origemSelecionada === 'Outro' ? 'selected' : '' }}>Outro</option>
                                @if(!empty($origemSelecionada) && !in_array($origemSelecionada, $origensPadrao, true))
                                    <option value="{{ $origemSelecionada }}" selected>{{ $origemSelecionada }}</option>
                                @endif
                            </x-select>
                        </div>

                        <div class="sm:col-span-2">
                            <x-textarea
                                name="observacoes"
                                label="Observações"
                                :value="old('observacoes', $agendamentoObj->observacoes ?? '')"
                                maxlength="500"
                                rows="2"
                            />
                        </div>
                    </div>
                </x-card>

                <!-- Serviços Contratados -->
                <x-card title="Serviços Contratados">
                    <div class="space-y-3" id="servicos-container">
                        @foreach($servicosAtivos as $servico)
                            @php
                                $isContratado = in_array($servico->id, $servicosContratadosIds);
                                $horas = floor($servico->tempo_estimado_minutos / 60);
                                $minutos = $servico->tempo_estimado_minutos % 60;
                                $tempoFormatado = $horas > 0 ? "{$horas}h " . ($minutos > 0 ? "{$minutos}min" : '') : "{$minutos}min";
                            @endphp
                            <label
                                class="flex items-start gap-3 p-3.5 rounded-xl border border-border bg-secondary/20 hover:bg-secondary/40 cursor-pointer transition-all service-checkbox-label {{ $isContratado ? 'border-primary bg-primary/10' : '' }}"
                                for="servico_{{ $servico->id }}"
                            >
                                <input
                                    type="checkbox"
                                    name="servicos[{{ $servico->id }}][servico_id]"
                                    value="{{ $servico->id }}"
                                    id="servico_{{ $servico->id }}"
                                    data-nome="{{ $servico->nome }}"
                                    data-preco="{{ $servico->preco_base }}"
                                    data-tempo="{{ $servico->tempo_estimado_minutos }}"
                                    data-tempo-str="{{ $tempoFormatado }}"
                                    {{ $isContratado ? 'checked' : '' }}
                                    {{ $possuiOS ? 'disabled' : '' }}
                                    class="mt-1 h-5 w-5 rounded border-border bg-input text-primary focus:ring-primary service-checkbox cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
                                />

                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <p class="font-bold text-sm text-foreground">{{ $servico->nome }}</p>
                                        <span class="font-extrabold text-sm text-primary">
                                            R$ {{ number_format($servico->preco_base, 2, ',', '.') }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-muted-foreground mt-0.5">{{ $servico->descricao }}</p>
                                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground mt-1.5 font-medium">
                                        <x-icon name="clock" class="w-3.5 h-3.5" />
                                        <span>Duração estimada: {{ $tempoFormatado }}</span>
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </x-card>
            </div>

            <!-- Resumo e Ações -->
            <div class="space-y-6">
                <x-card class="sticky top-6">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                                <x-icon name="clipboard-list" class="h-4 w-4" />
                            </div>
                            <h3 class="text-base font-semibold text-foreground">Resumo do Atendimento</h3>
                        </div>
                    </x-slot:header>

                    <div class="space-y-4">
                        <div id="resumo-servicos-lista" class="space-y-2 text-xs"></div>

                        <div class="pt-4 border-t border-border space-y-2">
                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Tempo dos Serviços:</span>
                                <span id="resumo-tempo-servicos" class="font-medium text-foreground">0min</span>
                            </div>

                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Margem Operacional (Gargalo):</span>
                                <span id="resumo-gargalo" class="font-medium text-amber-400">+30min</span>
                            </div>

                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Tempo Total Estimado:</span>
                                <span id="resumo-tempo-total" class="font-semibold text-foreground">0min</span>
                            </div>

                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Previsão de Conclusão:</span>
                                <span id="resumo-data-fim" class="font-semibold text-primary">--/--/---- --:--</span>
                            </div>

                            <div class="flex items-center justify-between text-sm pt-2 border-t border-border/50">
                                <span class="font-bold text-foreground">Total Estimado:</span>
                                <span id="resumo-valor-total" class="text-xl font-extrabold text-primary">R$ 0,00</span>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-border/60">
                            <x-button type="submit" variant="primary" size="lg" class="w-full shadow-md" icon="save">
                                Salvar Alterações
                            </x-button>

                            <x-button href="{{ route('agendamentos.show', $agendamentoObj?->id ?? 1) }}" variant="ghost" size="sm" class="w-full mt-2">
                                Cancelar
                            </x-button>
                        </div>
                    </div>
                </x-card>
            </div>
        </div>
    </form>

    @include('agendamentos.partials._modais_e_script', [
        'podeCadastrarVeiculo' => !$possuiOS,
    ])
</x-layouts.app>