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

        $servicosAtivos = $servicos ?? [
            (object)['id' => 1, 'nome' => 'Polimento Técnico Comercial', 'preco_base' => 650.00, 'tempo_estimado_minutos' => 240, 'descricao' => 'Correção de verniz com remoção de swirls'],
            (object)['id' => 2, 'nome' => 'Vitrificação Cerâmica 9H (Pintura)', 'preco_base' => 1200.00, 'tempo_estimado_minutos' => 360, 'descricao' => 'Proteção 3 anos de durabilidade'],
            (object)['id' => 3, 'nome' => 'Higienização Interna Completa + Ozônio', 'preco_base' => 380.00, 'tempo_estimado_minutos' => 210, 'descricao' => 'Limpeza detalhada de estofados e teto'],
            (object)['id' => 4, 'nome' => 'Lavagem Detalhada Premium', 'preco_base' => 180.00, 'tempo_estimado_minutos' => 90, 'descricao' => 'Lavagem dois baldes com descontaminação'],
        ];

        $servicosContratadosIds = old('servicos_ids', $agendamentoObj->servicos_ids ?? [1]);

        $clientesVeiculosJson = json_encode(
            collect($clientesDemo)->mapWithKeys(function ($cli) {
                return [$cli->id => $cli->veiculos ?? []];
            })
        );
    @endphp

    <form method="POST" action="{{ route('agendamentos.update', $agendamentoObj->id) }}" id="form-agendamento">
        @csrf
        @method('PUT')

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <!-- Cliente & Veículo -->
                <x-card title="Cliente & Veículo">
                    <div class="space-y-4">
                        <div>
                            <x-select name="cliente_id" id="cliente_select" label="Cliente Responsável" required>
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
                                    name="veiculo_id"
                                    id="veiculo_select"
                                    required
                                    class="block w-full rounded-lg bg-input/40 border border-border focus:border-ring focus:ring-ring/40 px-3 py-2 pr-8 text-sm text-foreground focus:outline-none focus:ring-2 transition-all appearance-none cursor-pointer"
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
                                label="Data e Horário de Início"
                                :value="\Carbon\Carbon::parse($agendamentoObj->data_inicio)->format('Y-m-d\TH:i')"
                                required
                            />
                        </div>

                        <div>
                            <x-input
                                type="datetime-local"
                                name="data_fim"
                                label="Previsão de Conclusão"
                                :value="$agendamentoObj->data_fim ? \Carbon\Carbon::parse($agendamentoObj->data_fim)->format('Y-m-d\TH:i') : ''"
                            />
                        </div>

                        <div>
                            <x-select name="status" label="Status Atual">
                                <option value="agendado" {{ $agendamentoObj->status === 'agendado' ? 'selected' : '' }}>Agendado</option>
                                <option value="confirmado" {{ $agendamentoObj->status === 'confirmado' ? 'selected' : '' }}>Confirmado</option>
                                <option value="em_andamento" {{ $agendamentoObj->status === 'em_andamento' ? 'selected' : '' }}>Em Andamento</option>
                                <option value="concluido" {{ $agendamentoObj->status === 'concluido' ? 'selected' : '' }}>Concluído</option>
                                <option value="cancelado" {{ $agendamentoObj->status === 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                            </x-select>
                        </div>

                        <div>
                            <x-select name="origem" label="Canal de Origem">
                                <option value="WhatsApp" {{ $agendamentoObj->origem === 'WhatsApp' ? 'selected' : '' }}>WhatsApp</option>
                                <option value="Telefone" {{ $agendamentoObj->origem === 'Telefone' ? 'selected' : '' }}>Telefone</option>
                                <option value="Presencial" {{ $agendamentoObj->origem === 'Presencial' ? 'selected' : '' }}>Presencial</option>
                                <option value="Site" {{ $agendamentoObj->origem === 'Site' ? 'selected' : '' }}>Site</option>
                                <option value="Outro" {{ $agendamentoObj->origem === 'Outro' ? 'selected' : '' }}>Outro</option>
                            </x-select>
                        </div>

                        <div class="sm:col-span-2">
                            <x-textarea
                                name="observacoes"
                                label="Observações"
                                :value="$agendamentoObj->observacoes ?? ''"
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
                                <span>Tempo Total Estimado:</span>
                                <span id="resumo-tempo-total" class="font-semibold text-foreground">0 min</span>
                            </div>

                            <div class="flex items-center justify-between text-sm">
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
            const clientesVeiculos = {!! $clientesVeiculosJson !!};
            const clienteSelect = document.getElementById('cliente_select');
            const veiculoSelect = document.getElementById('veiculo_select');
            const targetVeiculoPre = "{{ old('veiculo_id', $agendamentoObj->veiculo_id) }}";

            function atualizarVeiculos() {
                const clienteId = clienteSelect.value;
                const veiculos = clientesVeiculos[clienteId] || [];

                veiculoSelect.innerHTML = '';

                veiculos.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.id;
                    opt.textContent = v.placa + ' — ' + v.marca + ' ' + v.modelo;
                    if (targetVeiculoPre && targetVeiculoPre == v.id) {
                        opt.selected = true;
                    }
                    veiculoSelect.appendChild(opt);
                });
            }

            if (clienteSelect) {
                clienteSelect.addEventListener('change', atualizarVeiculos);
                atualizarVeiculos();
            }

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
                    listaResumo.innerHTML = '<p class="text-muted-foreground italic text-center py-4">Nenhum serviço selecionado.</p>';
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