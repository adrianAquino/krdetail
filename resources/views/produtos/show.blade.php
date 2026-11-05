<x-layouts.app :title="'Produto: ' . ($produto->nome ?? 'Insumo')">
    <x-header :title="$produto->nome ?? 'Produto'"
        subtitle="Ficha técnica do produto, volume disponível, unidades em estoque e histórico de movimentações.">
        <x-slot:actions>
            <x-button type="button" onclick="window.openModal('modal-movimentacao')" variant="primary" size="sm"
                icon="arrow-up-circle">
                Registrar Movimentação
            </x-button>
            <x-button href="{{ route('produtos.edit', $produto->id) }}" variant="secondary" size="sm"
                icon="pencil">
                Editar
            </x-button>
            <x-button href="{{ route('produtos.index') }}" variant="ghost" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    @php
        $isBaixo = $produto->quantidade_estoque <= $produto->estoque_minimo;
        $volUnit = (float) ($produto->volume_unitario ?? 1);
        $volEstoque = (float) $produto->quantidade_estoque;
        $volMinimo = (float) $produto->estoque_minimo;
        $progressoEstoque = $volMinimo > 0
            ? min(($volEstoque / $volMinimo) * 100, 100)
            : 100;
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
                    <span class="text-3xl font-extrabold block {{ $isBaixo ? 'text-destructive' : 'text-foreground' }}">
                        {{ $produto->unidades_estoque }} {{ $produto->unidades_estoque === 1 ? 'unidade' : 'unidades' }}
                    </span>
                    <span class="text-sm font-bold text-primary block mt-1">
                        {{ rtrim(rtrim(number_format($volEstoque, 3, ',', '.'), '0'), ',') }} {{ $produto->unidade_medida }} disponíveis no total
                    </span>
                    @if($produto->unidades_estoque > 0 && $produto->volume_unidade_aberta < $volUnit)
                        <p class="text-[11px] text-muted-foreground mt-1">
                            ({{ $produto->unidades_estoque - 1 }} fechada(s) + 1 em uso com {{ rtrim(rtrim(number_format($produto->volume_unidade_aberta, 3, ',', '.'), '0'), ',') }} {{ $produto->unidade_medida }})
                        </p>
                    @endif
                    <p class="text-xs text-muted-foreground mt-2 pt-2 border-t border-border/50">
                        Estoque Mínimo: {{ $produto->unidades_minimas }} un. ({{ rtrim(rtrim(number_format($volMinimo, 3, ',', '.'), '0'), ',') }} {{ $produto->unidade_medida }})
                    </p>
                </div>

                <div class="space-y-1.5 mb-5">
                    <div class="w-full bg-secondary h-2.5 rounded-full overflow-hidden border border-border">
                        <div class="h-full rounded-full transition-all duration-500 {{ $isBaixo ? 'bg-destructive' : 'bg-success' }}"
                            style="width: {{ min($progressoEstoque, 100) }}%"></div>
                    </div>
                </div>

                <div class="space-y-3 text-xs pt-3 border-t border-border/60">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Volume por Embalagem:</span>
                        <span class="font-bold text-foreground">
                            {{ rtrim(rtrim(number_format($volUnit, 3, ',', '.'), '0'), ',') }} {{ $produto->unidade_medida }} / un.
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Custo por Unidade (Embalagem):</span>
                        <span class="font-bold text-foreground">R$
                            {{ number_format($produto->custo_unitario, 2, ',', '.') }} / un.</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Valor Total em Estoque:</span>
                        <span class="font-extrabold text-primary text-sm">R$
                            {{ number_format($produto->valor_total_estoque, 2, ',', '.') }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Situação no Catálogo:</span>
                        <x-badge-status :status="$produto->ativo ? 'ativo' : 'inativo'" />
                    </div>
                </div>
            </x-card>

            <x-card title="Descrição do Produto">
                <p class="text-xs text-muted-foreground leading-relaxed">
                    {{ $produto->descricao ?? 'Sem descrição técnica cadastrada.' }}
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
                        <thead
                            class="bg-secondary/40 text-muted-foreground uppercase font-semibold border-b border-border">
                            <tr>
                                <th class="py-2.5 px-3">Data / Hora</th>
                                <th class="py-2.5 px-3 text-center">Tipo</th>
                                <th class="py-2.5 px-3 text-center">Volume Movimentado</th>
                                <th class="py-2.5 px-3 text-center">Saldo ({{ $produto->unidade_medida }})</th>
                                <th class="py-2.5 px-3">Motivo / Operação</th>
                                <th class="py-2.5 px-3">Responsável</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            @forelse($produto->movimentacoes as $mov)
                                <tr>
                                    <td class="py-2.5 px-3 text-muted-foreground font-medium">
                                        {{ \Carbon\Carbon::parse($mov->created_at)->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <x-badge-status :status="$mov->tipo_movimentacao" />
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-bold text-foreground">
                                        {{ $mov->tipo_movimentacao === 'saida' ? '-' : '+' }}{{ rtrim(rtrim(number_format((float) $mov->quantidade, 3, ',', '.'), '0'), ',') }}
                                        {{ $produto->unidade_medida }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center text-muted-foreground">
                                        {{ rtrim(rtrim(number_format((float) $mov->quantidade_anterior, 3, ',', '.'), '0'), ',') }} ➔ <strong
                                            class="text-foreground">{{ rtrim(rtrim(number_format((float) $mov->quantidade_posterior, 3, ',', '.'), '0'), ',') }} {{ $produto->unidade_medida }}</strong>
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
    <x-modal id="modal-movimentacao" :title="'Nova Movimentação: ' . $produto->nome">
        <form method="POST" action="{{ route('estoque.movimentar') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="produto_id" value="{{ $produto->id }}" />

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-select name="tipo_movimentacao" label="Tipo de Movimentação" required>
                        <option value="entrada">Entrada (Compra / Reposição)</option>
                        <option value="saida" selected>Saída (Consumo por Volume)</option>
                        <option value="ajuste">Ajuste (Inventário Físico)</option>
                    </x-select>
                </div>

                <div>
                    <x-select name="modo_quantidade" label="Informar Quantidade em:" required>
                        <option value="volume" selected>Volume ({{ $produto->unidade_medida }})</option>
                        <option value="unidades">Unidades / Embalagens ({{ rtrim(rtrim(number_format($volUnit, 3, ',', '.'), '0'), ',') }} {{ $produto->unidade_medida }} cada)</option>
                    </x-select>
                </div>
            </div>

            <div>
                <x-input type="number" step="0.001" min="0.001" name="quantidade"
                    label="Quantidade a Movimentar" placeholder="Ex: 150 (para 150 {{ $produto->unidade_medida }}) ou 2 (para 2 un.)" required />
            </div>

            <div>
                <x-input name="motivo" label="Motivo da Movimentação"
                    placeholder="Ex: Compra NF-e 1234, consumo avulso, inventário mensal..." required />
            </div>

            <div>
                <x-textarea name="observacoes" label="Observações Adicionais (Opcional)"
                    placeholder="Detalhes complementares..." rows="2" />
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
