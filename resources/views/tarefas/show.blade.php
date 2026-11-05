@php
    $osObj = $tarefa->ordemServico;
    $agendamentoObj = $osObj?->agendamento;
    $veiculoObj = $agendamentoObj?->veiculo;
    $clienteObj = $agendamentoObj?->cliente;
    $funcionarioObj = $tarefa->funcionario;
    $funcionarioNome = $funcionarioObj?->user?->name ?? 'Não atribuído';
    $servicoObj = $tarefa->agendamentoServico?->servico;
    $servicoNome = $servicoObj?->nome ?? 'Execução de Detalhamento';
    $valorServico = (float) ($tarefa->agendamentoServico?->valor_servico ?? $servicoObj?->preco_base ?? 0);
    $produtosUtilizados = $tarefa->tarefasProdutos ?? collect();
    $funcionariosLista = $funcionarios ?? collect();
    $produtosEstoque = $produtos ?? collect();

    $custoTotalTarefa = collect($produtosUtilizados)->sum(function ($pu) {
        return (float) $pu->quantidade_utilizada * (float) ($pu->custo_unitario ?? $pu->produto?->custo_unitario ?? 0);
    });
@endphp

<x-layouts.app :title="'Tarefa: ' . $servicoNome">
    <x-header
        :title="'Tarefa: ' . $servicoNome"
        :subtitle="'Vinculada à ' . ($osObj?->numero ?? 'OS #' . $tarefa->ordem_servico_id)"
    >
        <x-slot:actions>
            @if($osObj)
                <x-button href="{{ route('ordens-servico.show', $osObj->id) }}" variant="outline" size="sm" icon="clipboard-list">
                    Ver Ordem de Serviço
                </x-button>
            @endif
        </x-slot:actions>
    </x-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Status da Execução">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border/60">
                    <div class="flex items-center gap-3">
                        <div>
                            <span class="text-xs text-muted-foreground uppercase font-semibold">Estado Atual</span>
                            <div class="mt-1 flex items-center gap-2">
                                <x-badge-status :status="$tarefa->status" />
                                <span class="text-xs font-semibold px-2 py-0.5 rounded bg-secondary text-primary border border-border">
                                    R$ {{ number_format($valorServico, 2, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        @if($tarefa->status === 'pendente')
                            <form method="POST" action="{{ route('tarefas.iniciar', $tarefa->id) }}">
                                @csrf
                                @method('PATCH')
                                <x-button type="submit" variant="primary" size="md" icon="wrench">
                                    Iniciar Tarefa
                                </x-button>
                            </form>
                        @elseif($tarefa->status === 'em_andamento')
                            <form method="POST" action="{{ route('tarefas.concluir', $tarefa->id) }}">
                                @csrf
                                @method('PATCH')
                                <x-button type="submit" variant="primary" size="md" icon="check" class="bg-success text-success-foreground hover:bg-success/90">
                                    Concluir Tarefa
                                </x-button>
                            </form>
                        @else
                            <div class="flex items-center gap-1.5 text-xs text-success font-semibold bg-success/10 px-3 py-1.5 rounded-lg border border-success/30">
                                <x-icon name="check-circle" class="w-4 h-4" />
                                <span>Tarefa Concluída • Lançamento Financeiro Criado</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="pt-4 grid gap-4 sm:grid-cols-3 text-xs">
                    <div>
                        <span class="text-muted-foreground uppercase font-semibold block mb-1">Funcionário Responsável</span>
                        @if($tarefa->status !== 'concluida' && $osObj?->status !== 'concluida')
                            <form method="POST" action="{{ route('tarefas.atribuir', $tarefa->id) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <select
                                    name="funcionario_id"
                                    class="w-full text-xs rounded-lg bg-input/40 border border-border px-2.5 py-1.5 text-foreground focus:ring-1 focus:ring-ring"
                                    onchange="this.form.submit()"
                                >
                                    <option value="">Selecione o responsável</option>
                                    @foreach($funcionariosLista as $func)
                                        @php
                                            $funcNome = $func->user?->name ?? ('Colaborador #' . $func->id);
                                            $funcCargo = !empty($func->cargo) ? " ({$func->cargo})" : '';
                                            $isSelecionado = $tarefa->funcionario_id == $func->id;
                                        @endphp
                                        <option value="{{ $func->id }}" {{ $isSelecionado ? 'selected' : '' }}>
                                            {{ $funcNome }}{{ $funcCargo }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        @else
                            <p class="font-medium text-foreground text-sm">
                                {{ $funcionarioNome }}
                            </p>
                        @endif
                    </div>

                    <div>
                        <span class="text-muted-foreground uppercase font-semibold block mb-1">Iniciada em</span>
                        <p class="font-medium text-foreground text-sm">
                            {{ $tarefa->data_inicio ? $tarefa->data_inicio->format('d/m/Y H:i') : 'Não iniciada' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-muted-foreground uppercase font-semibold block mb-1">Concluída em</span>
                        <p class="font-medium text-foreground text-sm">
                            {{ $tarefa->data_conclusao ? $tarefa->data_conclusao->format('d/m/Y H:i') : ($tarefa->status === 'em_andamento' ? 'Em andamento' : 'Pendente') }}
                        </p>
                    </div>
                </div>
            </x-card>

            <!-- Produtos Consumidos -->
            <x-card title="Produtos Utilizados na Tarefa">
                @if($tarefa->status !== 'concluida' && $osObj?->status !== 'concluida')
                    <x-slot:action>
                        <x-button
                            type="button"
                            onclick="window.openModal('modal-produto-tarefa-{{ $tarefa->id }}')"
                            variant="outline"
                            size="sm"
                            icon="plus"
                        >
                            Adicionar Produto Utilizado
                        </x-button>
                    </x-slot:action>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-secondary/40 text-muted-foreground uppercase font-semibold border-b border-border">
                            <tr>
                                <th class="py-2.5 px-3">Produto</th>
                                <th class="py-2.5 px-3 text-center">Qtd Utilizada</th>
                                <th class="py-2.5 px-3 text-right">Custo Unitário</th>
                                <th class="py-2.5 px-3 text-right">Total</th>
                                @if($tarefa->status !== 'concluida')
                                    <th class="py-2.5 px-3 text-right">Remover</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            @forelse($produtosUtilizados as $pu)
                                @php
                                    $nomeProd = $pu->produto?->nome ?? ('Produto #' . $pu->produto_id);
                                    $qtdUtil = (float) $pu->quantidade_utilizada;
                                    $unidade = $pu->produto?->unidade_medida ?? 'un';
                                    $custo = (float) ($pu->custo_unitario ?? $pu->produto?->custo_unitario ?? 0);
                                    $custoTotalItem = $qtdUtil * $custo;
                                @endphp
                                <tr class="hover:bg-secondary/20">
                                    <td class="py-2.5 px-3 font-semibold text-foreground">{{ $nomeProd }}</td>
                                    <td class="py-2.5 px-3 text-center font-bold text-foreground">{{ number_format($qtdUtil, 3, ',', '.') }} {{ $unidade }}</td>
                                    <td class="py-2.5 px-3 text-right text-muted-foreground">R$ {{ number_format($custo, 2, ',', '.') }}</td>
                                    <td class="py-2.5 px-3 text-right font-bold text-foreground">R$ {{ number_format($custoTotalItem, 2, ',', '.') }}</td>
                                    @if($tarefa->status !== 'concluida')
                                        <td class="py-2.5 px-3 text-right">
                                            <form method="POST" action="{{ route('tarefas.remover-produto', [$tarefa->id, $pu->id]) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="text-muted-foreground hover:text-destructive p-1 rounded transition-colors cursor-pointer"
                                                    title="Remover e estornar ao estoque"
                                                    onclick="return confirm('Deseja realmente remover este item da tarefa e estornar o estoque?')"
                                                >
                                                    <x-icon name="trash-2" class="w-3.5 h-3.5" />
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $tarefa->status !== 'concluida' ? 5 : 4 }}" class="py-4 text-center text-muted-foreground">
                                        Nenhum insumo registrado para esta tarefa ainda.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if(count($produtosUtilizados) > 0)
                            <tfoot class="bg-secondary/30 font-semibold border-t border-border">
                                <tr>
                                    <td colspan="3" class="py-2.5 px-3 text-right text-muted-foreground">Custo Total de Produtos:</td>
                                    <td class="py-2.5 px-3 text-right text-primary font-bold">R$ {{ number_format($custoTotalTarefa, 2, ',', '.') }}</td>
                                    @if($tarefa->status !== 'concluida')
                                        <td></td>
                                    @endif
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Dados do Veículo e Cliente">
                @if($veiculoObj)
                    <div class="p-3 bg-secondary/40 rounded-xl border border-border text-center mb-3">
                        <span class="font-mono text-xl font-bold text-primary">{{ $veiculoObj->placa }}</span>
                        <p class="text-xs text-foreground mt-0.5">{{ $veiculoObj->marca }} {{ $veiculoObj->modelo }}</p>
                    </div>
                @endif
                <div class="text-xs space-y-1.5 text-muted-foreground">
                    <p><strong class="text-foreground">Cliente:</strong> {{ $clienteObj?->nome ?? '-' }}</p>
                    <p><strong class="text-foreground">Telefone:</strong> {{ $clienteObj?->telefone ?? '-' }}</p>
                    <p><strong class="text-foreground">Responsável:</strong> {{ $funcionarioNome }}</p>
                </div>
            </x-card>
        </div>
    </div>

    <!-- Modal para Adicionar Produto Utilizado nesta Tarefa -->
    @if($tarefa->status !== 'concluida' && $osObj?->status !== 'concluida')
        <x-modal id="modal-produto-tarefa-{{ $tarefa->id }}" :title="'Registrar Produto - ' . $servicoNome">
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
    @endif
</x-layouts.app>
