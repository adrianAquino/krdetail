<x-layouts.app :title="'Editar Agendamento #' . ($agendamento->id ?? 1)">
    <x-header
        :title="'Editar Agendamento #' . ($agendamento->id ?? 1)"
        subtitle="Atualize o horário, status ou serviços vinculados a este agendamento."
    >
        <x-slot:actions>
            <x-button href="{{ route('agendamentos.show', $agendamento->id ?? 1) }}" variant="outline" size="sm" icon="eye">
                Ver Detalhes
            </x-button>
            <x-button href="{{ route('agendamentos.index') }}" variant="ghost" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    @php
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
                if ($itemContratado->servico && !in_array($itemContratado->servico_id, $idsAtivos, true)) {
                    $servicosAtivos->push($itemContratado->servico);
                    $idsAtivos[] = $itemContratado->servico_id;
                }
            }
        }

        $possuiOS = isset($agendamentoObj->ordemServico) && $agendamentoObj->ordemServico !== null;
        $valorOriginalInicio = \Carbon\Carbon::parse($agendamentoObj->data_inicio)->format('Y-m-d\TH:i');
        $minDataInicio = \Carbon\Carbon::parse($agendamentoObj->data_inicio)->lt(now())
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
        if (!empty($agendamentoObj->cliente_id) && isset($agendamentoObj->veiculo) && $agendamentoObj->veiculo) {
            $veiculosDoCliente = collect($clientesVeiculosMap[$agendamentoObj->cliente_id] ?? []);
            if (!$veiculosDoCliente->contains('id', $agendamentoObj->veiculo->id)) {
                $veiculosDoCliente->push([
                    'id' => $agendamentoObj->veiculo->id,
                    'placa' => $agendamentoObj->veiculo->placa,
                    'marca' => $agendamentoObj->veiculo->marca,
                    'modelo' => $agendamentoObj->veiculo->modelo . ' (Transferido)',
                ]);
                $clientesVeiculosMap[$agendamentoObj->cliente_id] = $veiculosDoCliente->values()->all();
            }
        }
    @endphp

    @if($possuiOS)
        <div class="mb-6">
            <x-alert type="info" title="Ordem de Serviço Vinculada ({{ $agendamentoObj->ordemServico->numero }}):">
                Como este agendamento já possui Ordem de Serviço gerada, o cliente, o veículo e os serviços contratados estão travados para manter a integridade operacional.
            </x-alert>
        </div>
    @endif

    <form method="POST" action="{{ route('agendamentos.update', $agendamentoObj->id) }}" id="form-agendamento">
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
                                    <option value="{{ $cli->id }}" {{ old('cliente_id', $agendamentoObj->cliente_id) == $cli->id ? 'selected' : '' }}>
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
                        </div>

                        <div>
                            <x-input
                                type="datetime-local"
                                name="data_hora_estimado_conclusao"
                                id="data_fim"
                                label="Previsão Estimada de Conclusão (+30min)"
                                :value="$agendamentoObj->data_fim ? \Carbon\Carbon::parse($agendamentoObj->data_fim)->format('Y-m-d\TH:i') : ''"
                                hint="Calculado automaticamente: soma dos serviços + 30 min de gargalo operacional."
                                readonly
                                class="cursor-not-allowed opacity-80"
                            />
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

                            <x-button href="{{ route('agendamentos.show', $agendamentoObj->id) }}" variant="ghost" size="sm" class="w-full mt-2">
                                Cancelar
                            </x-button>
                        </div>
                    </div>
                </x-card>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const clientesVeiculos = @json($clientesVeiculosMap);
            const clienteSelect = document.getElementById('cliente_select');
            const veiculoSelect = document.getElementById('veiculo_select');
            const statusSelect = document.getElementById('status_select');
            const targetVeiculoPre = @json((string) old('veiculo_id', (string) ($agendamentoObj->veiculo_id ?? '')));

            function atualizarVeiculos() {
                if (!clienteSelect || !veiculoSelect) return;
                const clienteId = clienteSelect.value;
                const veiculos = clientesVeiculos[clienteId] || [];

                veiculoSelect.replaceChildren();

                veiculos.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = String(v.id);
                    opt.textContent = `${v.placa} — ${v.marca} ${v.modelo}`;
                    if (targetVeiculoPre && String(targetVeiculoPre) === String(v.id)) {
                        opt.selected = true;
                    }
                    veiculoSelect.appendChild(opt);
                });
            }

            if (clienteSelect) {
                clienteSelect.addEventListener('change', atualizarVeiculos);
                atualizarVeiculos();
            }

            const GARGALO_MINUTOS = 30;
            const valorOriginalInicio = @json($valorOriginalInicio);
            const checkboxes = document.querySelectorAll('.service-checkbox');
            const listaResumo = document.getElementById('resumo-servicos-lista');
            const resumoValorTotal = document.getElementById('resumo-valor-total');
            const resumoTempoServicos = document.getElementById('resumo-tempo-servicos');
            const resumoGargalo = document.getElementById('resumo-gargalo');
            const resumoTempoTotal = document.getElementById('resumo-tempo-total');
            const resumoDataFim = document.getElementById('resumo-data-fim');
            const formAgendamento = document.getElementById('form-agendamento');
            const inputDataInicio = document.getElementById('data_inicio');
            const inputDataFim = document.getElementById('data_fim');
            const erroDataInicio = document.getElementById('erro_data_inicio');

            let totalMinutosComGargaloAtual = 0;

            function formatarDuracao(minutosTotais) {
                if (minutosTotais <= 0) return '0min';
                const horas = Math.floor(minutosTotais / 60);
                const minutos = minutosTotais % 60;
                if (horas > 0) {
                    return minutos > 0 ? `${horas}h ${minutos}min` : `${horas}h 00min`;
                }
                return `${minutos}min`;
            }

            function atualizarPrevisaoConclusao() {
                if (!inputDataInicio || !inputDataInicio.value || totalMinutosComGargaloAtual <= 0) {
                    if (inputDataFim) inputDataFim.value = '';
                    if (resumoDataFim) resumoDataFim.textContent = '--/--/---- --:--';
                    return;
                }

                const inicio = new Date(inputDataInicio.value);
                if (isNaN(inicio.getTime())) {
                    if (inputDataFim) inputDataFim.value = '';
                    if (resumoDataFim) resumoDataFim.textContent = '--/--/---- --:--';
                    return;
                }

                const fim = new Date(inicio.getTime() + totalMinutosComGargaloAtual * 60000);
                const pad = n => String(n).padStart(2, '0');

                const valorLocal = `${fim.getFullYear()}-${pad(fim.getMonth() + 1)}-${pad(fim.getDate())}T${pad(fim.getHours())}:${pad(fim.getMinutes())}`;
                const valorExibicao = `${pad(fim.getDate())}/${pad(fim.getMonth() + 1)}/${fim.getFullYear()} às ${pad(fim.getHours())}:${pad(fim.getMinutes())}`;

                if (inputDataFim) inputDataFim.value = valorLocal;
                if (resumoDataFim) resumoDataFim.textContent = valorExibicao;
            }

            function atualizarResumoServicos() {
                let totalValor = 0;
                let totalMinutosServicos = 0;
                const selecionados = [];

                checkboxes.forEach(cb => {
                    const label = cb.closest('.service-checkbox-label');
                    if (cb.checked) {
                        label.classList.add('border-primary', 'bg-primary/10');
                        const preco = parseFloat(cb.dataset.preco) || 0;
                        const tempo = parseInt(cb.dataset.tempo) || 0;
                        totalValor += preco;
                        totalMinutosServicos += tempo;
                        selecionados.push({
                            nome: cb.dataset.nome,
                            preco: preco,
                            tempoStr: cb.dataset.tempoStr
                        });
                    } else {
                        label.classList.remove('border-primary', 'bg-primary/10');
                    }
                });

                listaResumo.replaceChildren();
                if (selecionados.length === 0) {
                    const emptyP = document.createElement('p');
                    emptyP.className = 'text-muted-foreground italic text-center py-4';
                    emptyP.textContent = 'Nenhum serviço selecionado.';
                    listaResumo.appendChild(emptyP);
                    totalMinutosComGargaloAtual = 0;
                } else {
                    selecionados.forEach(s => {
                        const row = document.createElement('div');
                        row.className = 'flex items-center justify-between py-1.5 border-b border-border/50';

                        const info = document.createElement('div');
                        const nomeSpan = document.createElement('span');
                        nomeSpan.className = 'font-medium text-foreground';
                        nomeSpan.textContent = s.nome;
                        const tempoSpan = document.createElement('span');
                        tempoSpan.className = 'text-[10px] text-muted-foreground block';
                        tempoSpan.textContent = s.tempoStr;
                        info.appendChild(nomeSpan);
                        info.appendChild(tempoSpan);

                        const valorSpan = document.createElement('span');
                        valorSpan.className = 'font-bold text-foreground';
                        valorSpan.textContent = `R$ ${s.preco.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

                        row.appendChild(info);
                        row.appendChild(valorSpan);
                        listaResumo.appendChild(row);
                    });
                    totalMinutosComGargaloAtual = totalMinutosServicos + GARGALO_MINUTOS;
                }

                if (resumoTempoServicos) resumoTempoServicos.textContent = formatarDuracao(totalMinutosServicos);
                if (resumoGargalo) resumoGargalo.textContent = selecionados.length > 0 ? `+${GARGALO_MINUTOS}min` : '0min';
                resumoTempoTotal.textContent = formatarDuracao(totalMinutosComGargaloAtual);
                resumoValorTotal.textContent = `R$ ${totalValor.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

                atualizarPrevisaoConclusao();
            }

            checkboxes.forEach(cb => cb.addEventListener('change', atualizarResumoServicos));
            atualizarResumoServicos();

            function validarDatas() {
                if (statusSelect && statusSelect.value === 'cancelado') {
                    erroDataInicio?.classList.add('hidden');
                    inputDataInicio?.classList.remove('border-destructive');
                    inputDataInicio?.setCustomValidity('');
                    atualizarPrevisaoConclusao();
                    return true;
                }

                let valido = true;
                const agora = new Date();
                agora.setSeconds(0, 0);
                const limiteMinimo = new Date(agora.getTime() - 60000);

                if (inputDataInicio && inputDataInicio.value) {
                    const dtInicio = new Date(inputDataInicio.value);
                    const hora = dtInicio.getHours();
                    const minuto = dtInicio.getMinutes();
                    const domingo = dtInicio.getDay() === 0;
                    const foraExpediente = hora < 8 || hora > 18 || (hora === 18 && minuto > 0);
                    const alterado = inputDataInicio.value !== valorOriginalInicio;

                    if (alterado && dtInicio < limiteMinimo) {
                        if (erroDataInicio) {
                            erroDataInicio.textContent = 'A data e o horário não podem ser inferiores à data e horário atuais.';
                            erroDataInicio.classList.remove('hidden');
                        }
                        inputDataInicio.classList.add('border-destructive');
                        inputDataInicio.setCustomValidity('A data e o horário não podem ser inferiores à data e horário atuais.');
                        valido = false;
                    } else if (domingo) {
                        if (erroDataInicio) {
                            erroDataInicio.textContent = 'A oficina não realiza atendimentos aos domingos. Escolha entre segunda e sábado.';
                            erroDataInicio.classList.remove('hidden');
                        }
                        inputDataInicio.classList.add('border-destructive');
                        inputDataInicio.setCustomValidity('A oficina não realiza atendimentos aos domingos.');
                        valido = false;
                    } else if (foraExpediente) {
                        if (erroDataInicio) {
                            erroDataInicio.textContent = 'Horário fora do expediente comercial (08:00 às 18:00).';
                            erroDataInicio.classList.remove('hidden');
                        }
                        inputDataInicio.classList.add('border-destructive');
                        inputDataInicio.setCustomValidity('Horário fora do expediente comercial (08:00 às 18:00).');
                        valido = false;
                    } else {
                        erroDataInicio?.classList.add('hidden');
                        inputDataInicio.classList.remove('border-destructive');
                        inputDataInicio.setCustomValidity('');
                    }
                }

                atualizarPrevisaoConclusao();
                return valido;
            }

            if (inputDataInicio) {
                inputDataInicio.addEventListener('change', validarDatas);
                inputDataInicio.addEventListener('input', validarDatas);
            }
            if (statusSelect) {
                statusSelect.addEventListener('change', validarDatas);
            }
            if (formAgendamento) {
                formAgendamento.addEventListener('submit', function (e) {
                    if (!validarDatas()) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</x-layouts.app>