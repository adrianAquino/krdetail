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
            <x-button href="{{ route('agendamentos.index', ($isAdmin ?? false) && !empty($empresaSelecionadaId) ? ['empresa_id' => $empresaSelecionadaId] : []) }}" variant="outline" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    @if($isAdmin ?? false)
        <x-admin-empresa-filter
            :action="route('agendamentos.create')"
            :empresas="$empresas ?? collect()"
            :empresaSelecionadaId="$empresaSelecionadaId ?? null"
            :permitirTodas="false"
            label="Empresa do Agendamento"
        />
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
                            <input type="hidden" name="cliente_id" id="cliente_select" value="{{ $clienteUnico?->id }}" data-nome="{{ $clienteUnico?->nome ?? auth()->user()->name }}" />
                        @else
                            <div>
                                <x-select
                                    name="cliente_id"
                                    id="cliente_select"
                                    label="Cliente Responsável"
                                    required
                                >
                                    @foreach($clientes as $cli)
                                        <option value="{{ $cli->id }}" data-nome="{{ $cli->nome }}" {{ old('cliente_id', request('cliente_id')) == $cli->id ? 'selected' : '' }}>
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

                <!-- Seção 2: Seleção de Serviços Contratados (com cálculo dinâmico) -->
                <x-card title="Serviços Desejados" description="Selecione um ou mais procedimentos para calcular a duração e filtrar as janelas disponíveis">
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

                <!-- Seção 3: Data, Janelas Disponíveis e Origem -->
                <x-card title="Programação do Atendimento" description="Escolha uma janela livre de 30 em 30 minutos ou ajuste manualmente abaixo">
                    @php
                        $padraoInicio = now()->addDay()->isSunday()
                            ? now()->addDays(2)->setHour(9)->setMinute(0)
                            : now()->addDay()->setHour(9)->setMinute(0);

                        $dataInicioQueryRaw = request('data_inicio');
                        if (is_scalar($dataInicioQueryRaw) && trim((string) $dataInicioQueryRaw) !== '') {
                            try {
                                $dtQuery = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', trim((string) $dataInicioQueryRaw));
                                if ($dtQuery && $dtQuery->gte(now()->startOfDay()) && ! $dtQuery->isSunday() && $dtQuery->hour >= 8 && $dtQuery->hour < 18) {
                                    $padraoInicio = $dtQuery;
                                }
                            } catch (\Throwable) {
                                // Mantém data padrão segura
                            }
                        }
                    @endphp

                    <!-- Painel Inline de Janelas Futuras Disponíveis -->
                    <div
                        id="janelas-disponiveis-root"
                        data-janelas-url="{{ route('agendamentos.janelas-disponiveis') }}"
                        data-empresa-id="{{ $empresaSelecionadaId ?? '' }}"
                        data-is-cliente="{{ $isCliente ? '1' : '0' }}"
                        class="mb-6 rounded-xl border border-border bg-secondary/15 p-4 space-y-4"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <x-icon name="calendar" class="w-4 h-4 text-primary shrink-0" />
                                    <h4 class="text-sm font-bold text-foreground">Janelas de Horários Disponíveis</h4>
                                </div>
                                <p id="janelas-info-duracao" class="text-[11px] text-muted-foreground mt-0.5">
                                    Consultando disponibilidade futura em blocos de 30 minutos...
                                </p>
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0">
                                <button
                                    type="button"
                                    id="janelas-btn-prev"
                                    class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-2.5 py-1.5 text-xs font-medium text-foreground hover:bg-secondary/70 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors"
                                >
                                    <x-icon name="chevron-left" class="w-3.5 h-3.5" />
                                    <span>Semana ant.</span>
                                </button>
                                <button
                                    type="button"
                                    id="janelas-btn-hoje"
                                    class="rounded-lg border border-border bg-card px-2.5 py-1.5 text-xs font-medium text-foreground hover:bg-secondary/70 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors"
                                >
                                    Hoje
                                </button>
                                <button
                                    type="button"
                                    id="janelas-btn-next"
                                    class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-2.5 py-1.5 text-xs font-medium text-foreground hover:bg-secondary/70 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors"
                                >
                                    <span>Próx. semana</span>
                                    <x-icon name="chevron-right" class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 text-[11px] text-muted-foreground">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Disponível
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-amber-500"></span> {{ $isCliente ? 'Última vaga' : 'Poucas vagas' }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-destructive"></span> Lotado
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-muted-foreground/50"></span> Indisponível / Fechado
                            </span>
                        </div>

                        <div id="janelas-erro" class="hidden rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-xs text-destructive">
                            Não foi possível consultar as janelas disponíveis agora. Você ainda pode informar a data manualmente abaixo.
                        </div>

                        <div id="janelas-dias-strip" class="flex gap-2 overflow-x-auto pb-1"></div>

                        <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-border/60">
                            <span id="janelas-resumo-dia" class="text-xs font-semibold text-foreground"></span>
                            <div class="flex items-center gap-2">
                                <span id="janelas-loading" class="hidden text-[11px] text-primary font-medium">Atualizando horários...</span>
                                <span id="janelas-selecionada-badge" class="hidden rounded-md border border-primary/30 bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary"></span>
                            </div>
                        </div>

                        <div id="janelas-slots-grid" class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 gap-2"></div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input
                                type="datetime-local"
                                name="data_inicio"
                                id="data_inicio"
                                label="Data e Horário Desejados"
                                step="1800"
                                min="{{ now()->format('Y-m-d\TH:i') }}"
                                :value="old('data_inicio', $padraoInicio->format('Y-m-d\TH:i'))"
                                hint="Atendimento de Segunda a Sábado, das 08:00 às 18:00 (janelas de 30 min, 4 boxes simultâneos)."
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
                                label="Previsão Estimada de Conclusão (+30min de margem)"
                                :value="old('data_hora_estimado_conclusao')"
                                hint="Calculado automaticamente com base nos serviços escolhidos e eventual pernoite."
                                readonly
                                class="cursor-not-allowed opacity-80"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <label for="deixar_veiculo" class="flex items-start gap-3 p-3.5 rounded-xl border border-border bg-secondary/25 hover:bg-secondary/40 cursor-pointer transition-colors">
                                <input
                                    type="checkbox"
                                    name="deixar_veiculo"
                                    id="deixar_veiculo"
                                    value="1"
                                    {{ old('deixar_veiculo') ? 'checked' : '' }}
                                    class="mt-0.5 h-4 w-4 rounded border-border bg-input text-primary focus:ring-primary cursor-pointer"
                                />
                                <div class="text-xs">
                                    <span class="font-semibold text-foreground block">Deixar o veículo na estética (Pernoite / Continuação no próximo dia útil)</span>
                                    <span class="text-muted-foreground">Marque esta opção quando a duração total dos serviços ultrapassar as 18:00 do dia selecionado; a execução retoma às 08:00 do próximo dia útil respeitando os boxes da estética.</span>
                                </div>
                            </label>
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

    @include('agendamentos.partials._modais_e_script', ['podeCadastrarVeiculo' => true])
</x-layouts.app>
