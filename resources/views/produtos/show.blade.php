<x-layouts.app :title="'Produto: ' . ($produto->nome ?? 'Insumo')">
    <x-header
        :title="$produto->nome ?? 'Composto Polidor Médio V40'"
        subtitle="Ficha técnica do produto, consumo em ordens de serviço e histórico de movimentações."
    >
        <x-slot:actions>
            <x-button
                type="button"
                onclick="window.openModal('modal-movimentacao')"
                variant="primary"
                size="sm"
                icon="arrow-up-circle"
            >
                Registrar Movimentação
            </x-button>
            <x-button href="{{ route('produtos.edit', $produto->id ?? 1) }}" variant="secondary" size="sm" icon="pencil">
                Editar
            </x-button>
            <x-button href="{{ route('produtos.index') }}" variant="ghost" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    @php
        $prodObj = $produto ?? (object)[
            'id' => 1,
            'nome' => 'Composto Polidor Médio V40',
            'descricao' => 'Composto polidor à base de água para refino e corte leve em vernizes médios e macios.',
            'unidade_medida' => 'L',
            'quantidade_estoque' => 0.800,
            'estoque_minimo' => 2.000,
            'custo_unitario' => 120.00,
            'ativo' => true,
            'created_at' => now()->subMonths(3),
        ];

        $movimentacoesDemo = $movimentacoes ?? [
            (object)[
                'id' => 1,
                'data' => now()->subDays(1),
                'tipo_movimentacao' => 'saida',
                'quantidade' => 0.250,
                'quantidade_anterior' => 1.050,
                'quantidade_posterior' => 0.800,
                'motivo' => 'Consumo na OS-00015 (Polimento BMW 320i)',
                'usuario' => (object)['name' => 'Carlos Alberto']
            ],
            (object)[
                'id' => 2,
                'data' => now()->subDays(12),
                'tipo_movimentacao' => 'entrada',
                'quantidade' => 2.000,
                'quantidade_anterior' => 0.500,
                'quantidade_posterior' => 2.500,
                'motivo' => 'Compra NF-e 4432 (Fornecedor Vonixx)',
                'usuario' => (object)['name' => 'Administrador']
            ],
        ];

        $isBaixo = $prodObj->quantidade_estoque <= $prodObj->estoque_minimo;
        $progressoEstoque = ($prodObj->quantidade_estoque / max($prodObj->estoque_minimo, 1)) * 100;
    @endphp

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Coluna Esquerda: Status de Estoque e Especificações -->
        <div class="space-y-6">
            <x-card>
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs text-muted-foreground uppercase font-semibold">Nível de Estoque</span>
                    @if($isBaixo)
                        <x-badge-status status="estoque_baixo" label="Estoque Baixo" />
                    @else
                        <x-badge-status status="estoque_normal" label="Normal" />
                    @endif
                </div>

                <div class="text-center p-4 rounded-xl bg-secondary/30 border border-border mb-4">
                    <span class="text-3xl font-extrabold {{ $isBaixo ? 'text-destructive' : 'text-foreground' }}">
                        {{ $prodObj->quantidade_estoque }} {{ $prodObj->unidade_medida }}
                    </span>
                    <p class="text-xs text-muted-foreground mt-1">Estoque Mínimo: {{ $prodObj->estoque_minimo }} {{ $prodObj->unidade_medida }}</p>
                </div>

                <div class="space-y-1.5 mb-5">
                    <div class="w-full bg-secondary h-2.5 rounded-full overflow-hidden border border-border">
                        <div
                            class="h-full rounded-full transition-all duration-500 {{ $isBaixo ? 'bg-destructive' : 'bg-success' }}"
                            style="width: {{ min($progressoEstoque, 100) }}%"
                        ></div>
                    </div>
                </div>

                <div class="space-y-3 text-xs pt-3 border-t border-border/60">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Custo Unitário:</span>
                        <span class="font-bold text-foreground">R$ {{ number_format($prodObj->custo_unitario, 2, ',', '.') }} / {{ $prodObj->unidade_medida }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Valor Total em Estoque:</span>
                        <span class="font-extrabold text-primary text-sm">R$ {{ number_format($prodObj->quantidade_estoque * $prodObj->custo_unitario, 2, ',', '.') }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Situação no Catálogo:</span>
                        <x-badge-status :status="$prodObj->ativo ? 'ativo' : 'inativo'" />
                    </div>
                </div>
            </x-card>

            <x-card title="Descrição do Produto">
                <p class="text-xs text-muted-foreground leading-relaxed">
                    {{ $prodObj->descricao ?? 'Sem descrição técnica cadastrada.' }}
                </p>
            </x-card>
        </div>

        <!-- Coluna Direita: Histórico de Movimentações -->
        <div class="lg:col-span-2">
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                                <x-icon name="arrow-up-circle" class="h-4 w-4" />
                            </div>
                            <h3 class="text-base font-semibold text-foreground">Histórico de Movimentações</h3>
                        </div>
                    </div>
                </x-slot:header>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-secondary/40 text-muted-foreground uppercase font-semibold border-b border-border">
                            <tr>
                                <th class="py-2.5 px-3">Data / Hora</th>
                                <th class="py-2.5 px-3 text-center">Tipo</th>
                                <th class="py-2.5 px-3 text-center">Qtd Movimentada</th>
                                <th class="py-2.5 px-3 text-center">Saldo</th>
                                <th class="py-2.5 px-3">Motivo / Operação</th>
                                <th class="py-2.5 px-3">Responsável</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            @forelse($movimentacoesDemo as $mov)
                                <tr>
                                    <td class="py-2.5 px-3 text-muted-foreground font-medium">
                                        {{ \Carbon\Carbon::parse($mov->data)->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <x-badge-status :status="$mov->tipo_movimentacao" />
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-bold text-foreground">
                                        {{ $mov->tipo_movimentacao === 'saida' ? '-' : '+' }}{{ $mov->quantidade }} {{ $prodObj->unidade_medida }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center text-muted-foreground">
                                        {{ $mov->quantidade_anterior }} ➔ <strong class="text-foreground">{{ $mov->quantidade_posterior }}</strong>
                                    </td>
                                    <td class="py-2.5 px-3 text-foreground font-medium">
                                        {{ $mov->motivo }}
                                    </td>
                                    <td class="py-2.5 px-3 text-muted-foreground">
                                        {{ $mov->usuario->name ?? 'Sistema' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-muted-foreground text-xs">
                                        Nenhuma movimentação registrada para este produto.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    </div>

    <!-- Modal Registrar Movimentação -->
    <x-modal id="modal-movimentacao" :title="'Nova Movimentação: ' . $prodObj->nome">
        <form method="POST" action="{{ route('estoque.movimentar') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="produto_id" value="{{ $prodObj->id }}" />

            <div>
                <x-select name="tipo_movimentacao" label="Tipo de Movimentação" required>
                    <option value="entrada">Entrada (Compra / Reposição de Estoque)</option>
                    <option value="saida">Saída (Consumo Interno / Perda)</option>
                    <option value="ajuste">Ajuste (Inventário Físico)</option>
                </x-select>
            </div>

            <div>
                <x-input
                    type="number"
                    step="0.001"
                    min="0.001"
                    name="quantidade"
                    label="Quantidade (em {{ $prodObj->unidade_medida }})"
                    placeholder="Ex: 1.000"
                    required
                />
            </div>

            <div>
                <x-input
                    name="motivo"
                    label="Motivo da Movimentação"
                    placeholder="Ex: Compra NF-e 1234, inventário mensal, etc..."
                    required
                />
            </div>

            <div>
                <x-textarea
                    name="observacoes"
                    label="Observações Adicionais (Opcional)"
                    placeholder="Detalhes complementares..."
                    rows="2"
                />
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                <x-button type="button" onclick="window.closeModal('modal-movimentacao')" variant="outline" size="sm">
                    Cancelar
                </x-button>
                <x-button type="submit" variant="primary" size="sm" icon="check">
                    Confirmar Movimentação
                </x-button>
            </div>
        </form>
    </x-modal>
</x-layouts.app>