@php
    $isCliente = auth()->check() && auth()->user()->isCliente();
    $clienteUnico = $isCliente ? $clientes->first() : null;

    // Mapeamento cliente -> veículos para o script JS de dependência (apenas campos mínimos)
    $clientesVeiculosMap = collect($clientes)->mapWithKeys(function ($cli) {
        $veiculosMin = collect($cli->veiculos ?? [])->map(fn ($v) => [
            'id' => $v->id,
            'placa' => $v->placa,
            'marca' => $v->marca,
            'modelo' => $v->modelo,
        ])->values()->all();

        return [$cli->id => $veiculosMin];
    })->all();
@endphp

<x-layouts.app title="Novo Agendamento">
    <x-header
        title="Novo Agendamento"
        :subtitle="$isCliente ? 'Escolha um dos seus veículos, a data desejada e os serviços de estética automotiva.' : 'Selecione o cliente, o veículo correspondente e os serviços a serem contratados.'"
    >
        <x-slot:actions>
            <x-button href="{{ route('agendamentos.index') }}" variant="outline" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    <form
        method="POST"
        action="{{ route('agendamentos.store') }}"
        id="form-agendamento"
        data-clientes-veiculos="{{ json_encode($clientesVeiculosMap) }}"
        data-target-veiculo-pre="{{ (string) old('veiculo_id', (string) request('veiculo_id', '')) }}"
        data-rota-veiculos-create="{{ route('veiculos.create') }}"
    >
        @csrf

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Coluna Principal (2 cols): Cliente, Veículo, Horário, Serviços -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Seção 1: Cliente e Veículo -->
                <x-card :title="$isCliente ? 'Meu Veículo' : 'Cliente & Veículo'" :description="$isCliente ? 'Selecione qual dos seus veículos receberá o atendimento' : 'Selecione o cliente para visualizar apenas os veículos pertencentes a ele'">
                    <div class="space-y-4">
                        @if($isCliente && $clienteUnico)
                            <input type="hidden" name="cliente_id" id="cliente_select" value="{{ $clienteUnico?->id }}" />
                        @else
                            <div>
                                <x-select
                                    name="cliente_id"
                                    id="cliente_select"
                                    label="Cliente Responsável"
                                    required
                                >
                                    @foreach($clientes as $cli)
                                        <option value="{{ $cli->id }}" {{ old('cliente_id', request('cliente_id')) == $cli->id ? 'selected' : '' }}>
                                            {{ $cli->nome }} — {{ $cli->telefone }}
                                        </option>
                                    @endforeach
                                </x-select>
                            </div>
                        @endif

                        <div>
                            <label for="veiculo_select" class="block text-xs font-medium text-foreground mb-1.5">
                                {{ $isCliente ? 'Selecione seu Veículo' : 'Veículo do Cliente' }} <span class="text-destructive">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    name="veiculo_id"
                                    id="veiculo_select"
                                    required
                                    class="block w-full rounded-lg bg-input/40 border border-border focus:border-ring focus:ring-ring/40 px-3 py-2 pr-8 text-sm text-foreground focus:outline-none focus:ring-2 transition-all appearance-none cursor-pointer"
                                >
                                    <option value="">Selecione um veículo</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-2.5 pointer-events-none text-muted-foreground">
                                    <x-icon name="chevron-down" class="w-4 h-4" />
                                </div>
                            </div>
                            <p id="veiculo_aviso" class="text-[11px] text-muted-foreground mt-1">
                                {{ $isCliente ? 'Não encontrou seu veículo? Você pode cadastrar um novo.' : 'O veículo deve obrigatoriamente pertencer ao cliente selecionado.' }}
                            </p>
                        </div>
                    </div>
                </x-card>

                <!-- Seção 2: Data, Horário e Origem -->
                <x-card title="Programação do Atendimento">
                    @php
                        $padraoInicio = now()->addDay()->isSunday()
                            ? now()->addDays(2)->setHour(9)->setMinute(0)
                            : now()->addDay()->setHour(9)->setMinute(0);
                    @endphp
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input
                                type="datetime-local"
                                name="data_inicio"
                                id="data_inicio"
                                label="Data e Horário Desejados"
                                min="{{ now()->format('Y-m-d\TH:i') }}"
                                :value="old('data_inicio', $padraoInicio->format('Y-m-d\TH:i'))"
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
                                label="Previsão Estimada de Conclusão (+30min de margem)"
                                :value="old('data_hora_estimado_conclusao')"
                                hint="Calculado automaticamente: soma dos serviços + 30 min de gargalo operacional."
                                readonly
                                class="cursor-not-allowed opacity-80"
                            />
                        </div>

                        @if(!$isCliente)
                            <div>
                                <x-select name="origem" label="Canal de Origem do Agendamento">
                                    @php $origemAtual = old('origem', 'WhatsApp'); @endphp
                                    <option value="WhatsApp" {{ $origemAtual === 'WhatsApp' ? 'selected' : '' }}>WhatsApp</option>
                                    <option value="Telefone" {{ $origemAtual === 'Telefone' ? 'selected' : '' }}>Telefone</option>
                                    <option value="Balcão" {{ in_array($origemAtual, ['Balcão', 'Presencial'], true) ? 'selected' : '' }}>Presencial / Balcão</option>
                                    <option value="Site" {{ $origemAtual === 'Site' ? 'selected' : '' }}>Site / Formulário</option>
                                    <option value="Outro" {{ $origemAtual === 'Outro' ? 'selected' : '' }}>Outro</option>
                                </x-select>
                            </div>

                            <div>
                                <x-select name="status" label="Status Inicial">
                                    @php $statusInicial = old('status', 'agendado'); @endphp
                                    <option value="agendado" {{ $statusInicial === 'agendado' ? 'selected' : '' }}>Agendado</option>
                                    <option value="confirmado" {{ $statusInicial === 'confirmado' ? 'selected' : '' }}>Confirmado</option>
                                    <option value="em_andamento" {{ $statusInicial === 'em_andamento' ? 'selected' : '' }}>Em Andamento</option>
                                </x-select>
                            </div>
                        @endif

                        <div class="sm:col-span-2">
                            <x-textarea
                                name="observacoes"
                                label="Observações do Agendamento"
                                placeholder="Recomendações especiais, detalhes sobre o veículo ou preferência de horário..."
                                :value="old('observacoes')"
                                maxlength="500"
                                rows="2"
                            />
                        </div>
                    </div>
                </x-card>

                <!-- Seção 3: Seleção de Serviços Contratados (com cálculo dinâmico) -->
                <x-card title="Serviços Desejados" description="Selecione um ou mais procedimentos para o atendimento">
                    <div class="space-y-3" id="servicos-container">
                        @foreach($servicos as $servico)
                            @php
                                $horas = floor($servico->tempo_estimado_minutos / 60);
                                $minutos = $servico->tempo_estimado_minutos % 60;
                                $tempoFormatado = $horas > 0 ? "{$horas}h " . ($minutos > 0 ? "{$minutos}min" : '') : "{$minutos}min";
                            @endphp
                            <label
                                class="flex items-start gap-3 p-3.5 rounded-xl border border-border bg-secondary/20 hover:bg-secondary/40 cursor-pointer transition-all service-checkbox-label"
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
                                    {{ old('servicos.' . $servico->id . '.servico_id') ? 'checked' : '' }}
                                    class="mt-1 h-5 w-5 rounded border-border bg-input text-primary focus:ring-primary service-checkbox cursor-pointer"
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

            <!-- Coluna Lateral (1 col): Resumo em Tempo Real e Finalização -->
            <div class="space-y-6">
                <!-- Resumo dos Serviços Selecionados -->
                <x-card class="sticky top-6">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                                <x-icon name="clipboard-list" class="h-4 w-4" />
                            </div>
                            <h3 class="text-base font-semibold text-foreground">Resumo Estimado</h3>
                        </div>
                    </x-slot:header>

                    <div class="space-y-4">
                        <div id="resumo-servicos-lista" class="space-y-2 text-xs">
                            <p class="text-muted-foreground italic text-center py-4">
                                Nenhum serviço selecionado ainda.
                            </p>
                        </div>

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
                            <x-button type="submit" variant="primary" size="lg" class="w-full shadow-md" icon="check">
                                Confirmar Agendamento
                            </x-button>

                            <x-button href="{{ route('agendamentos.index') }}" variant="ghost" size="sm" class="w-full mt-2">
                                Cancelar
                            </x-button>
                        </div>
                    </div>
                </x-card>
            </div>
        </div>
    </form>

    <!-- Script de Dependência Cliente -> Veículos e Totalizador de Serviços -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const formCfg = document.getElementById('form-agendamento')?.dataset || {};
            const clientesVeiculos = JSON.parse(formCfg.clientesVeiculos || '{}');
            const clienteSelect = document.getElementById('cliente_select');
            const veiculoSelect = document.getElementById('veiculo_select');
            const veiculoAviso = document.getElementById('veiculo_aviso');
            const targetVeiculoPre = formCfg.targetVeiculoPre || '';
            const rotaVeiculosCreate = formCfg.rotaVeiculosCreate || '';

            function renderAvisoVeiculo(semVeiculos, clienteId) {
                if (!veiculoAviso) return;
                veiculoAviso.replaceChildren();
                const link = document.createElement('a');
                link.className = 'text-primary underline';
                if (semVeiculos) {
                    veiculoAviso.appendChild(document.createTextNode('Nenhum veículo cadastrado. '));
                    link.href = rotaVeiculosCreate + (clienteId ? ('?cliente_id=' + encodeURIComponent(clienteId)) : '');
                    link.textContent = 'Cadastrar veículo agora';
                    veiculoAviso.appendChild(link);
                } else {
                    veiculoAviso.appendChild(document.createTextNode('Não encontrou seu veículo? '));
                    link.href = rotaVeiculosCreate;
                    link.textContent = 'Cadastrar novo veículo';
                    veiculoAviso.appendChild(link);
                    veiculoAviso.appendChild(document.createTextNode('.'));
                }
            }

            function atualizarVeiculos() {
                if (!clienteSelect || !veiculoSelect) return;
                const clienteId = clienteSelect.value;
                const veiculos = clientesVeiculos[clienteId] || [];

                veiculoSelect.replaceChildren();

                if (veiculos.length === 0) {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = 'Nenhum veículo cadastrado';
                    veiculoSelect.appendChild(opt);
                    renderAvisoVeiculo(true, clienteId);
                } else {
                    const optDefault = document.createElement('option');
                    optDefault.value = '';
                    optDefault.textContent = 'Selecione o veículo (' + veiculos.length + ' disponível(is))';
                    veiculoSelect.appendChild(optDefault);

                    veiculos.forEach(v => {
                        const opt = document.createElement('option');
                        opt.value = String(v.id);
                        opt.textContent = `${v.placa} — ${v.marca} ${v.modelo}`;
                        if (targetVeiculoPre && String(targetVeiculoPre) === String(v.id)) {
                            opt.selected = true;
                        }
                        veiculoSelect.appendChild(opt);
                    });

                    if (veiculos.length === 1 && !targetVeiculoPre) {
                        veiculoSelect.value = String(veiculos[0].id);
                    }

                    renderAvisoVeiculo(false, clienteId);
                }
            }

            if (clienteSelect) {
                clienteSelect.addEventListener('change', atualizarVeiculos);
                atualizarVeiculos();
            }

            // Totalizador Dinâmico de Serviços e Cálculo Automático da Previsão (+30 min de gargalo)
            const GARGALO_MINUTOS = 30;
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
                    emptyP.textContent = 'Nenhum serviço selecionado ainda.';
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

                    if (dtInicio < limiteMinimo) {
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
