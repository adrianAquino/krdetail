<x-layouts.app title="Novo Agendamento">
    <x-header
        title="Novo Agendamento"
        subtitle="Selecione o cliente, o veículo correspondente e os serviços a serem contratados."
    >
        <x-slot:actions>
            <x-button href="{{ route('agendamentos.index') }}" variant="outline" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    @php
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
            (object)[
                'id' => 3,
                'nome' => 'Roberto Medeiros',
                'telefone' => '(11) 99345-1234',
                'veiculos' => [
                    (object)['id' => 3, 'placa' => 'GTS9F99', 'marca' => 'Porsche', 'modelo' => 'Macan GTS'],
                ]
            ],
            (object)[
                'id' => 4,
                'nome' => 'Juliana Ferreira',
                'telefone' => '(11) 98456-7890',
                'veiculos' => [
                    (object)['id' => 4, 'placa' => 'VOL6C30', 'marca' => 'Volvo', 'modelo' => 'XC60 Recharge'],
                ]
            ],
        ];

        $servicosAtivos = $servicos ?? [
            (object)['id' => 1, 'nome' => 'Polimento Técnico Comercial', 'preco_base' => 650.00, 'tempo_estimado_minutos' => 240, 'descricao' => 'Correção de verniz com remoção de swirls'],
            (object)['id' => 2, 'nome' => 'Vitrificação Cerâmica 9H (Pintura)', 'preco_base' => 1200.00, 'tempo_estimado_minutos' => 360, 'descricao' => 'Proteção 3 anos de durabilidade'],
            (object)['id' => 3, 'nome' => 'Higienização Interna Completa + Ozônio', 'preco_base' => 380.00, 'tempo_estimado_minutos' => 210, 'descricao' => 'Limpeza detalhada de estofados e teto'],
            (object)['id' => 4, 'nome' => 'Lavagem Detalhada Premium', 'preco_base' => 180.00, 'tempo_estimado_minutos' => 90, 'descricao' => 'Lavagem dois baldes com descontaminação'],
            (object)['id' => 5, 'nome' => 'Limpeza e Condicionamento de Couro', 'preco_base' => 220.00, 'tempo_estimado_minutos' => 75, 'descricao' => 'Hidratação profunda'],
        ];

        // Mapeamento cliente -> veículos para o script JS de dependência
        $clientesVeiculosJson = json_encode(
            collect($clientes)->mapWithKeys(function ($cli) {
                return [$cli->id => $cli->veiculos ?? []];
            })
        );
    @endphp

    <form method="POST" action="{{ route('agendamentos.store') }}" id="form-agendamento">
        @csrf

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Coluna Principal (2 cols): Cliente, Veículo, Horário, Serviços -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Seção 1: Cliente e Veículo (com dependência dinâmica estrita) -->
                <x-card title="Cliente & Veículo" description="Selecione o cliente para visualizar apenas os veículos pertencentes a ele">
                    <div class="space-y-4">
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

                        <div>
                            <label for="veiculo_select" class="block text-xs font-medium text-foreground mb-1.5">
                                Veículo do Cliente <span class="text-destructive">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    name="veiculo_id"
                                    id="veiculo_select"
                                    required
                                    class="block w-full rounded-lg bg-input/40 border border-border focus:border-ring focus:ring-ring/40 px-3 py-2 pr-8 text-sm text-foreground focus:outline-none focus:ring-2 transition-all appearance-none cursor-pointer"
                                >
                                    <option value="">Selecione primeiro o cliente acima</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-2.5 pointer-events-none text-muted-foreground">
                                    <x-icon name="chevron-down" class="w-4 h-4" />
                                </div>
                            </div>
                            <p id="veiculo_aviso" class="text-[11px] text-muted-foreground mt-1">
                                O veículo deve obrigatoriamente pertencer ao cliente selecionado.
                            </p>
                        </div>
                    </div>
                </x-card>

                <!-- Seção 2: Data, Horário e Origem -->
                <x-card title="Programação do Atendimento">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input
                                type="datetime-local"
                                name="data_inicio"
                                label="Data e Horário de Início"
                                :value="now()->addDay()->setHour(9)->setMinute(0)->format('Y-m-d\TH:i')"
                                required
                            />
                        </div>

                        <div>
                            <x-input
                                type="datetime-local"
                                name="data_fim"
                                label="Previsão de Conclusão (Opcional)"
                                :value="now()->addDay()->setHour(13)->setMinute(0)->format('Y-m-d\TH:i')"
                            />
                        </div>

                        <div>
                            <x-select name="origem" label="Canal de Origem do Agendamento">
                                <option value="WhatsApp" selected>WhatsApp</option>
                                <option value="Telefone">Telefone</option>
                                <option value="Presencial">Presencial / Balcão</option>
                                <option value="Site">Site / Formulário</option>
                                <option value="Outro">Outro</option>
                            </x-select>
                        </div>

                        <div>
                            <x-select name="status" label="Status Inicial">
                                <option value="agendado" selected>Agendado</option>
                                <option value="confirmado">Confirmado</option>
                                <option value="em_andamento">Em Andamento</option>
                            </x-select>
                        </div>

                        <div class="sm:col-span-2">
                            <x-textarea
                                name="observacoes"
                                label="Observações do Agendamento"
                                placeholder="Recomendações passadas pelo cliente, restrições de horário, etc..."
                                rows="2"
                            />
                        </div>
                    </div>
                </x-card>

                <!-- Seção 3: Seleção de Serviços Contratados (com cálculo dinâmico) -->
                <x-card title="Serviços Contratados" description="Selecione um ou mais procedimentos para o atendimento">
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
                                <span>Tempo Total Estimado:</span>
                                <span id="resumo-tempo-total" class="font-semibold text-foreground">0 min</span>
                            </div>

                            <div class="flex items-center justify-between text-sm">
                                <span class="font-bold text-foreground">Total Estimado:</span>
                                <span id="resumo-valor-total" class="text-xl font-extrabold text-primary">R$ 0,00</span>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-border/60">
                            <p class="text-[11px] text-muted-foreground mb-4 leading-relaxed">
                                Os valores e tempos estimados serão congelados em <strong class="text-foreground">AgendamentoServico</strong> preservando a fidelidade histórica do serviço.
                            </p>

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
            const clientesVeiculos = {!! $clientesVeiculosJson !!};
            const clienteSelect = document.getElementById('cliente_select');
            const veiculoSelect = document.getElementById('veiculo_select');
            const veiculoAviso = document.getElementById('veiculo_aviso');
            const targetVeiculoPre = "{{ old('veiculo_id', request('veiculo_id')) }}";

            function atualizarVeiculos() {
                const clienteId = clienteSelect.value;
                const veiculos = clientesVeiculos[clienteId] || [];

                veiculoSelect.innerHTML = '';

                if (veiculos.length === 0) {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = 'Nenhum veículo cadastrado para este cliente';
                    veiculoSelect.appendChild(opt);
                    veiculoAviso.innerHTML = '⚠ Este cliente não tem veículos cadastrados. <a href="{{ route('veiculos.create') }}?cliente_id=' + clienteId + '" class="text-primary underline">Cadastrar veículo agora</a>';
                } else {
                    const optDefault = document.createElement('option');
                    optDefault.value = '';
                    optDefault.textContent = 'Selecione o veículo (' + veiculos.length + ' disponível(is))';
                    veiculoSelect.appendChild(optDefault);

                    veiculos.forEach(v => {
                        const opt = document.createElement('option');
                        opt.value = v.id;
                        opt.textContent = v.placa + ' — ' + v.marca + ' ' + v.modelo;
                        if (targetVeiculoPre && targetVeiculoPre == v.id) {
                            opt.selected = true;
                        }
                        veiculoSelect.appendChild(opt);
                    });

                    veiculoAviso.textContent = 'Veículo validado para o cliente selecionado.';
                }
            }

            if (clienteSelect) {
                clienteSelect.addEventListener('change', atualizarVeiculos);
                atualizarVeiculos();
            }

            // Totalizador Dinâmico de Serviços
            const checkboxes = document.querySelectorAll('.service-checkbox');
            const listaResumo = document.getElementById('resumo-servicos-lista');
            const resumoValorTotal = document.getElementById('resumo-valor-total');
            const resumoTempoTotal = document.getElementById('resumo-tempo-total');

            function atualizarResumoServicos() {
                let totalValor = 0;
                let totalMinutos = 0;
                const selecionados = [];

                checkboxes.forEach(cb => {
                    const label = cb.closest('.service-checkbox-label');
                    if (cb.checked) {
                        label.classList.add('border-primary', 'bg-primary/10');
                        const preco = parseFloat(cb.dataset.preco) || 0;
                        const tempo = parseInt(cb.dataset.tempo) || 0;
                        totalValor += preco;
                        totalMinutos += tempo;
                        selecionados.push({
                            nome: cb.dataset.nome,
                            preco: preco,
                            tempoStr: cb.dataset.tempoStr
                        });
                    } else {
                        label.classList.remove('border-primary', 'bg-primary/10');
                    }
                });

                if (selecionados.length === 0) {
                    listaResumo.innerHTML = '<p class="text-muted-foreground italic text-center py-4">Nenhum serviço selecionado ainda.</p>';
                } else {
                    let html = '';
                    selecionados.forEach(s => {
                        html += `
                            <div class="flex items-center justify-between py-1.5 border-b border-border/50">
                                <div>
                                    <span class="font-medium text-foreground">${s.nome}</span>
                                    <span class="text-[10px] text-muted-foreground block">${s.tempoStr}</span>
                                </div>
                                <span class="font-bold text-foreground">R$ ${s.preco.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</span>
                            </div>
                        `;
                    });
                    listaResumo.innerHTML = html;
                }

                // Formatar tempo total
                const horas = Math.floor(totalMinutos / 60);
                const minutos = totalMinutos % 60;
                let tempoStr = '';
                if (horas > 0) tempoStr += `${horas}h `;
                if (minutos > 0 || horas === 0) tempoStr += `${minutos}min`;

                resumoTempoTotal.textContent = tempoStr;
                resumoValorTotal.textContent = `R$ ${totalValor.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
            }

            checkboxes.forEach(cb => cb.addEventListener('change', atualizarResumoServicos));
            atualizarResumoServicos();
        });
    </script>
</x-layouts.app>