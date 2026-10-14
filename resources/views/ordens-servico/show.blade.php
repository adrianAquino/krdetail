<x-layouts.app :title="'Ordem de Serviço ' . ($ordemServico->numero ?? 'OS-00015')">
    <x-header
        :title="'Ordem de Serviço ' . ($ordemServico->numero ?? 'OS-00015')"
        subtitle="Acompanhamento operacional, execução das tarefas por serviço e controle de insumos utilizados."
    >
        <x-slot:actions>
            @php
                $todasConcluidas = collect($ordemServico->tarefas ?? [])->every(fn($t) => $t->status === 'concluida');
                $osStatus = $ordemServico->status ?? 'em_andamento';
            @endphp

            @if($osStatus !== 'concluida' && $osStatus !== 'cancelada')
                <form method="POST" action="{{ route('ordens-servico.concluir', $ordemServico->id ?? 15) }}" class="inline">
                    @csrf
                    @method('PATCH')
                    <x-button
                        type="submit"
                        variant="primary"
                        size="sm"
                        icon="check-circle"
                        :disabled="!$todasConcluidas"
                        title="{{ !$todasConcluidas ? 'Conclua todas as tarefas individuais antes de finalizar a OS' : 'Finalizar OS' }}"
                    >
                        Concluir Ordem de Serviço
                    </x-button>
                </form>
            @endif

            <x-button href="{{ route('ordens-servico.index') }}" variant="ghost" size="sm" icon="chevron-left">
                Voltar à Lista
            </x-button>
        </x-slot:actions>
    </x-header>

    @php
        $osObj = $ordemServico ;
        $funcionariosLista = $funcionarios;

        $produtosEstoque = $produtos;

        $tarefasCount = count($osObj->tarefas);
        $tarefasConcluidasCount = collect($osObj->tarefas)->where('status', 'concluida')->count();
        $progressoPorcentagem = $tarefasCount > 0 ? round(($tarefasConcluidasCount / $tarefasCount) * 100) : 0;
    @endphp

    <!-- Top Summary Banner -->
    <div class="grid gap-6 lg:grid-cols-3 mb-6">
        <!-- Card 1: Cliente e Veículo -->
        <x-card>
            <div class="flex items-start justify-between mb-3">
                <div>
                    <span class="text-xs text-muted-foreground uppercase font-semibold">Cliente</span>
                    <a href="{{ route('clientes.show', $osObj->agendamento->cliente->id) }}" class="font-bold text-base text-foreground hover:text-primary transition-colors block mt-0.5">
                        {{ $osObj->agendamento->cliente->nome }}
                    </a>
                    <p class="text-xs text-muted-foreground">{{ $osObj->agendamento->cliente->telefone }}</p>
                </div>
                <div class="p-2 rounded-lg bg-primary/10 text-primary">
                    <x-icon name="user" class="w-5 h-5" />
                </div>
            </div>

            <div class="pt-3 border-t border-border/60 flex items-center justify-between">
                <div>
                    <span class="text-xs text-muted-foreground block">Veículo</span>
                    <span class="text-sm font-semibold text-foreground">{{ $osObj->agendamento->veiculo->marca }} {{ $osObj->agendamento->veiculo->modelo }}</span>
                </div>
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-secondary text-primary border border-border">
                    {{ $osObj->agendamento->veiculo->placa }}
                </span>
            </div>
        </x-card>

        <!-- Card 2: Progresso das Tarefas da OS -->
        <x-card>
            <div class="flex items-start justify-between mb-2">
                <div>
                    <span class="text-xs text-muted-foreground uppercase font-semibold">Progresso Operacional</span>
                    <h3 class="text-xl font-bold text-foreground mt-0.5">
                        {{ $tarefasConcluidasCount }} de {{ $tarefasCount }} tarefas
                    </h3>
                </div>
                <x-badge-status :status="$osObj->status" />
            </div>

            <div class="space-y-1.5 mt-3">
                <div class="w-full bg-secondary h-2.5 rounded-full overflow-hidden border border-border">
                    <div
                        class="h-full rounded-full transition-all duration-500 {{ $progressoPorcentagem == 100 ? 'bg-success' : 'bg-primary' }}"
                        style="width: {{ $progressoPorcentagem }}%"
                    ></div>
                </div>
                <p class="text-xs text-muted-foreground text-right font-medium">{{ $progressoPorcentagem }}% concluído</p>
            </div>
        </x-card>

        <!-- Card 3: Resumo Financeiro da OS -->
        <x-card>
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs text-muted-foreground uppercase font-semibold">Valor Total Contratado</span>
                    <p class="text-2xl font-extrabold text-primary mt-0.5">
                        R$ {{ number_format($osObj->agendamento->valor_total, 2, ',', '.') }}
                    </p>
                    <p class="text-xs text-muted-foreground mt-1">
                        Cada tarefa concluída gera automaticamente seu lançamento financeiro de receita correspondente.
                    </p>
                </div>
                <div class="p-2 rounded-lg bg-primary/10 text-primary">
                    <x-icon name="dollar-sign" class="w-5 h-5" />
                </div>
            </div>
        </x-card>
    </div>

    @if(!$todasConcluidas && $osObj->status === 'em_andamento')
        <div class="mb-6">
            <x-alert type="info" title="Fluxo Operacional Automatizado:">
                As tarefas abaixo foram geradas <strong>automaticamente</strong> a partir dos serviços do agendamento. 
                Os funcionários iniciam suas respectivas tarefas, registram os insumos/produtos consumidos do estoque e, ao concluir cada uma, a receita é lançada automaticamente no financeiro.
            </x-alert>
        </div>
    @endif

    <!-- Tarefas da Ordem de Serviço (Item 17, 18, 19, 20 do escopo) -->
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-bold text-foreground mb-1">Tarefas de Execução da OS</h2>
            <p class="text-xs text-muted-foreground">Cada serviço contratado possui uma tarefa própria atribuída a um funcionário responsável.</p>
        </div>

        <div class="space-y-4">
            @foreach($osObj->tarefas as $tarefa)
                <x-card class="border-border {{ $tarefa->status === 'concluida' ? 'bg-card/70 border-success/30' : ($tarefa->status === 'em_andamento' ? 'border-primary/60 bg-primary/5' : 'bg-card') }}">
                    <!-- Cabeçalho da Tarefa -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-border/60">
                        <div class="flex items-start gap-3.5">
                            <div class="p-2.5 rounded-xl shrink-0 {{ $tarefa->status === 'concluida' ? 'bg-success/15 text-success' : ($tarefa->status === 'em_andamento' ? 'bg-warning/15 text-warning animate-pulse' : 'bg-secondary text-muted-foreground') }}">
                                <x-icon :name="$tarefa->status === 'concluida' ? 'check-circle' : ($tarefa->status === 'em_andamento' ? 'wrench' : 'clock')" class="w-5 h-5" />
                            </div>

                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-bold text-base text-foreground">{{ $tarefa->servico_nome }}</h3>
                                    <x-badge-status :status="$tarefa->status" />
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded bg-secondary text-primary border border-border">
                                        R$ {{ number_format($tarefa->valor_servico, 2, ',', '.') }}
                                    </span>
                                </div>
                                <p class="text-xs text-muted-foreground mt-0.5">{{ $tarefa->servico_descricao }}</p>
                            </div>
                        </div>

                        <!-- Ações de Iniciar / Concluir Tarefa (Item 18 e 19) -->
                        <div class="flex flex-wrap items-center gap-2 md:justify-end">
                            @if($tarefa->status === 'pendente')
                                <form method="POST" action="{{ route('tarefas.iniciar', $tarefa->id) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <x-button type="submit" variant="primary" size="sm" icon="wrench">
                                        Iniciar Tarefa
                                    </x-button>
                                </form>
                            @elseif($tarefa->status === 'em_andamento')
                                <form method="POST" action="{{ route('tarefas.concluir', $tarefa->id) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <x-button type="submit" variant="primary" size="sm" icon="check" class="bg-success text-success-foreground hover:bg-success/90">
                                        Concluir Tarefa
                                    </x-button>
                                </form>
                            @elseif($tarefa->status === 'concluida')
                                <div class="flex items-center gap-1.5 text-xs font-semibold text-success bg-success/10 px-3 py-1.5 rounded-lg border border-success/30">
                                    <x-icon name="check-circle" class="w-4 h-4" />
                                    <span>Lançamento financeiro gerado</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Dados da Execução e Funcionário -->
                    <div class="py-3.5 grid gap-4 sm:grid-cols-3 text-xs border-b border-border/40">
                        <div>
                            <span class="text-muted-foreground uppercase font-semibold block mb-1">Funcionário Responsável</span>
                            @if($osObj->status !== 'concluida' && $tarefa->status !== 'concluida')
                                <form method="POST" action="{{ route('tarefas.atribuir', $tarefa->id) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select
                                        name="funcionario_id"
                                        class="text-xs rounded-lg bg-input/40 border border-border px-2.5 py-1 text-foreground focus:ring-1 focus:ring-ring"
                                        onchange="this.form.submit()"
                                    >
                                        <option value="">Selecione o responsável</option>
                                        @foreach($funcionariosLista as $func)
                                            @php
                                                $funcNome = $func->user?->name ?? $func->nome ?? ('Colaborador #' . $func->id);
                                                $funcCargo = !empty($func->cargo) ? " ({$func->cargo})" : '';
                                                $isSelecionado = ($tarefa->funcionario_id == $func->id) || (isset($tarefa->funcionario) && $tarefa->funcionario->id == $func->id);
                                            @endphp
                                            <option value="{{ $func->id }}" {{ $isSelecionado ? 'selected' : '' }}>
                                                {{ $funcNome }}{{ $funcCargo }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            @else
                                <p class="font-semibold text-foreground flex items-center gap-1.5">
                                    <x-icon name="user" class="w-3.5 h-3.5 text-primary" />
                                    {{ $tarefa->funcionario?->user?->name ?? $tarefa->funcionario?->nome ?? 'Não atribuído' }}
                                </p>
                            @endif
                        </div>

                        <div>
                            <span class="text-muted-foreground uppercase font-semibold block mb-1">Início da Execução</span>
                            <p class="font-medium text-foreground">
                                {{ $tarefa->data_inicio ? \Carbon\Carbon::parse($tarefa->data_inicio)->format('d/m/Y H:i') : 'Ainda não iniciada' }}
                            </p>
                        </div>

                        <div>
                            <span class="text-muted-foreground uppercase font-semibold block mb-1">Conclusão da Tarefa</span>
                            <p class="font-medium text-foreground">
                                {{ $tarefa->data_conclusao ? \Carbon\Carbon::parse($tarefa->data_conclusao)->format('d/m/Y H:i') : 'Em execução / pendente' }}
                            </p>
                        </div>
                    </div>

                    <!-- Seção de Produtos Utilizados na Tarefa (Item 20 do escopo: Tarefa -> TarefaProduto -> Produto) -->
                    <div class="pt-3.5">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-xs font-bold text-foreground uppercase tracking-wider flex items-center gap-1.5">
                                <x-icon name="package" class="w-3.5 h-3.5 text-primary" />
                                Produtos Utilizados nesta Tarefa
                            </h4>

                            @if($tarefa->status !== 'concluida' && $osObj->status !== 'concluida')
                                <x-button
                                    type="button"
                                    onclick="window.openModal('modal-produto-tarefa-{{ $tarefa->id }}')"
                                    variant="outline"
                                    size="sm"
                                    icon="plus"
                                >
                                    Adicionar Produto Utilizado
                                </x-button>
                            @endif
                        </div>

                        @php
                            $prodsUtilizados = $tarefa->tarefasProdutos ?? $tarefa->produtos_utilizados ?? [];
                            $custoTotalTarefa = collect($prodsUtilizados)->sum(function($pu) {
                                return (float) ($pu->custo_total ?? ($pu->quantidade_utilizada * ($pu->custo_unitario ?? $pu->produto?->custo_unitario ?? 0)));
                            });
                        @endphp

                        @if(count($prodsUtilizados) > 0)
                            <div class="overflow-x-auto rounded-lg border border-border bg-secondary/15">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-secondary/40 text-muted-foreground uppercase font-semibold border-b border-border">
                                        <tr>
                                            <th class="py-2 px-3">Produto / Insumo</th>
                                            <th class="py-2 px-3 text-center">Quantidade Utilizada</th>
                                            <th class="py-2 px-3 text-right">Custo Unitário</th>
                                            <th class="py-2 px-3 text-right">Custo Total</th>
                                            @if($tarefa->status !== 'concluida')
                                                <th class="py-2 px-3 text-right">Remover</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/50">
                                        @foreach($prodsUtilizados as $pu)
                                            @php
                                                $prodNome = $pu->produto?->nome ?? 'Produto #' . $pu->produto_id;
                                                $prodUnidade = $pu->produto?->unidade_medida ?? 'un';
                                                $qtdUtilizada = (float) $pu->quantidade_utilizada;
                                                $custoUnitario = (float) ($pu->custo_unitario ?? $pu->produto?->custo_unitario ?? 0);
                                                $custoTotalItem = (float) ($pu->custo_total ?? ($qtdUtilizada * $custoUnitario));
                                            @endphp
                                            <tr class="hover:bg-secondary/20">
                                                <td class="py-2 px-3 font-medium text-foreground">
                                                    {{ $prodNome }}
                                                </td>
                                                <td class="py-2 px-3 text-center font-bold text-foreground">
                                                    {{ number_format($qtdUtilizada, 3, ',', '.') }} {{ $prodUnidade }}
                                                </td>
                                                <td class="py-2 px-3 text-right text-muted-foreground">
                                                    R$ {{ number_format($custoUnitario, 2, ',', '.') }}
                                                </td>
                                                <td class="py-2 px-3 text-right font-bold text-foreground">
                                                    R$ {{ number_format($custoTotalItem, 2, ',', '.') }}
                                                </td>
                                                @if($tarefa->status !== 'concluida')
                                                    <td class="py-2 px-3 text-right">
                                                        <form method="POST" action="{{ route('tarefas.remover-produto', [$tarefa->id, $pu->id]) }}" class="inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-muted-foreground hover:text-destructive p-1 rounded transition-colors cursor-pointer" title="Remover e estornar ao estoque" onclick="return confirm('Deseja realmente remover este item da tarefa e estornar o estoque?')">
                                                                <x-icon name="trash-2" class="w-3.5 h-3.5" />
                                                            </button>
                                                        </form>
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="bg-secondary/30 font-semibold border-t border-border">
                                        <tr>
                                            <td colspan="3" class="py-2 px-3 text-right text-muted-foreground">Custo de Produtos Nesta Tarefa:</td>
                                            <td class="py-2 px-3 text-right text-primary font-bold">R$ {{ number_format($custoTotalTarefa, 2, ',', '.') }}</td>
                                            @if($tarefa->status !== 'concluida')
                                                <td></td>
                                            @endif
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <p class="text-xs text-muted-foreground italic py-1">
                                Nenhum produto ou insumo registrado para esta tarefa até o momento.
                            </p>
                        @endif
                    </div>

                    <!-- Modal para Adicionar Produto Utilizado nesta Tarefa -->
                    <x-modal id="modal-produto-tarefa-{{ $tarefa->id }}" :title="'Registrar Produto - ' . $tarefa->servico_nome">
                        <form method="POST" action="{{ route('tarefas.adicionar-produto', $tarefa->id) }}" class="space-y-4">
                            @csrf
                            <div>
                                <x-select name="produto_id" label="Produto do Estoque" required>
                                    @foreach($produtosEstoque as $prod)
                                        <option value="{{ $prod->id }}">
                                            {{ $prod->nome }} (Disponível: {{ $prod->quantidade_estoque }} {{ $prod->unidade_medida }} • Custo: R$ {{ number_format($prod->custo_unitario, 2, ',', '.') }})
                                        </option>
                                    @endforeach
                                </x-select>
                            </div>

                            <div>
                                <x-input
                                    type="number"
                                    step="0.001"
                                    min="0.001"
                                    name="quantidade_utilizada"
                                    label="Quantidade Utilizada"
                                    placeholder="Ex: 0.250"
                                    required
                                />
                                <p class="text-[11px] text-muted-foreground mt-1">Essa quantidade será deduzida automaticamente do estoque através de uma Movimentação de Saída.</p>
                            </div>

                            <div>
                                <x-textarea
                                    name="observacoes"
                                    label="Observações do Consumo (Opcional)"
                                    placeholder="Aplicação em duas demãos, etc..."
                                    rows="2"
                                />
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                                <x-button type="button" onclick="window.closeModal('modal-produto-tarefa-{{ $tarefa->id }}')" variant="outline" size="sm">
                                    Cancelar
                                </x-button>
                                <x-button type="submit" variant="primary" size="sm" icon="plus">
                                    Adicionar à Tarefa
                                </x-button>
                            </div>
                        </form>
                    </x-modal>
                </x-card>
            @endforeach
        </div>
    </div>
</x-layouts.app>