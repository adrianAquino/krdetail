<x-layouts.app title="Estoque">
    <x-header
        title="Gestão de Estoque"
        subtitle="Monitore níveis de estoque, controle alertas de reposição mínima e registre movimentações de entrada e saída."
    >
        <x-slot:actions>
            <x-button
                type="button"
                onclick="window.openModal('modal-nova-movimentacao')"
                variant="primary"
                size="md"
                icon="arrow-up-circle"
            >
                Nova Movimentação
            </x-button>
            <x-button href="{{ route('produtos.create') }}" variant="secondary" size="md" icon="plus">
                Novo Produto
            </x-button>
        </x-slot:actions>
    </x-header>

    

    <!-- KPIs de Estoque -->
    <div class="grid gap-4 sm:grid-cols-3 mb-8">
        <x-card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-muted-foreground uppercase">Total de Produtos</p>
                    <p class="text-3xl font-extrabold text-foreground mt-1">{{ $kpis['total_itens'] }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">Cadastrados no catálogo</p>
                </div>
                <div class="p-3 rounded-xl bg-chart-1/10 text-chart-1">
                    <x-icon name="package" class="w-6 h-6" />
                </div>
            </div>
        </x-card>

        <x-card class="{{ $kpis['itens_criticos'] > 0 ? 'border-destructive/40 bg-destructive/5' : '' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold {{ $kpis['itens_criticos'] > 0 ? 'text-destructive' : 'text-muted-foreground' }} uppercase">Itens em Alerta</p>
                    <p class="text-3xl font-extrabold {{ $kpis['itens_criticos'] > 0 ? 'text-destructive' : 'text-foreground' }} mt-1">{{ $kpis['itens_criticos'] }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">Abaixo do estoque mínimo</p>
                </div>
                <div class="p-3 rounded-xl {{ $kpis['itens_criticos'] > 0 ? 'bg-destructive/20 text-destructive' : 'bg-secondary text-muted-foreground' }}">
                    <x-icon name="alert-triangle" class="w-6 h-6" />
                </div>
            </div>
        </x-card>

        <x-card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-muted-foreground uppercase">Valor em Estoque</p>
                    <p class="text-2xl font-extrabold text-primary mt-1">R$ {{ number_format($kpis['valor_total_estoque'], 2, ',', '.') }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">Baseado no custo unitário</p>
                </div>
                <div class="p-3 rounded-xl bg-primary/10 text-primary">
                    <x-icon name="dollar-sign" class="w-6 h-6" />
                </div>
            </div>
        </x-card>
    </div>

    <!-- Tabs Navigation -->
    <div class="border-b border-border mb-6">
        <nav class="flex space-x-6 text-sm font-medium">
            <a
                href="{{ route('estoque.index', ['tab' => 'produtos']) }}"
                class="pb-3 border-b-2 flex items-center gap-2 {{ $tabAtiva === 'produtos' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground' }}"
            >
                <x-icon name="package" class="w-4 h-4" />
                Produtos em Estoque ({{ $kpis['total_itens'] }})
            </a>
            <a
                href="{{ route('estoque.index', ['tab' => 'movimentacoes']) }}"
                class="pb-3 border-b-2 flex items-center gap-2 {{ $tabAtiva === 'movimentacoes' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground' }}"
            >
                <x-icon name="arrow-up-circle" class="w-4 h-4" />
                Histórico de Movimentações
            </a>
        </nav>
    </div>

    <!-- Tab 1: Produtos e Níveis de Estoque -->
    @if($tabAtiva === 'produtos')
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($produtos as $prod)
                @php
                    $isCritico = $prod->quantidade_estoque <= ($prod->estoque_minimo * 0.5);
                    $isBaixo = $prod->quantidade_estoque <= $prod->estoque_minimo;
                    $percentual = ($prod->quantidade_estoque / max($prod->estoque_minimo, 1)) * 100;
                @endphp
                <x-card class="{{ $isBaixo ? 'border-destructive/30' : '' }}">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div>
                            <a href="{{ route('produtos.show', $prod->id) }}" class="font-bold text-base text-foreground hover:text-primary transition-colors block">
                                {{ $prod->nome }}
                            </a>
                            <p class="text-xs text-muted-foreground mt-0.5 line-clamp-1">{{ $prod->descricao }}</p>
                        </div>
                        <x-badge-status :status="$isCritico ? 'estoque_critico' : ($isBaixo ? 'estoque_baixo' : 'estoque_normal')" />
                    </div>

                    <!-- Visual de Estoque Mínimo e Atual (Item 11 e 12) -->
                    <div class="mt-4 p-3 rounded-lg bg-secondary/30 border border-border space-y-2">
                        <div class="flex items-baseline justify-between">
                            <span class="text-xs text-muted-foreground">Estoque Atual:</span>
                            <span class="text-base font-black {{ $isBaixo ? 'text-destructive' : 'text-foreground' }}">
                                {{ $prod->quantidade_estoque }} {{ $prod->unidade_medida }}
                            </span>
                        </div>

                        <div class="w-full bg-secondary h-2 rounded-full overflow-hidden border border-border/60">
                            <div
                                class="h-full rounded-full transition-all duration-500 {{ $isCritico ? 'bg-destructive' : ($isBaixo ? 'bg-warning' : 'bg-success') }}"
                                style="width: {{ min($percentual, 100) }}%"
                            ></div>
                        </div>

                        <div class="flex items-center justify-between text-[11px] text-muted-foreground pt-1">
                            <span>Mínimo: {{ $prod->estoque_minimo }} {{ $prod->unidade_medida }}</span>
                            <span>Custo: R$ {{ number_format($prod->custo_unitario, 2, ',', '.') }}/{{ $prod->unidade_medida }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mt-4 pt-3 border-t border-border/60">
                        <span class="text-xs font-semibold text-primary">
                            Total: R$ {{ number_format($prod->quantidade_estoque * $prod->custo_unitario, 2, ',', '.') }}
                        </span>

                        <div class="flex items-center gap-1">
                            <x-button href="{{ route('produtos.show', $prod->id) }}" variant="ghost" size="sm" icon="eye">
                                Ficha
                            </x-button>
                            <x-button href="{{ route('produtos.edit', $prod->id) }}" variant="ghost" size="sm" icon="pencil">
                                Editar
                            </x-button>
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    <!-- Tab 2: Histórico de Movimentações (Item 12 do escopo) -->
    @if($tabAtiva === 'movimentacoes')
        <x-card class="p-0">
            <x-table>
                <x-slot:header>
                    <tr>
                        <th class="py-3.5 px-4">Data / Hora</th>
                        <th class="py-3.5 px-4">Produto</th>
                        <th class="py-3.5 px-4 text-center">Tipo</th>
                        <th class="py-3.5 px-4 text-center">Movimentação</th>
                        <th class="py-3.5 px-4 text-center">Evolução do Saldo</th>
                        <th class="py-3.5 px-4">Motivo / Origem</th>
                        <th class="py-3.5 px-4">Usuário</th>
                    </tr>
                </x-slot:header>

                @forelse($movimentacoes as $mov)
                    <tr class="hover:bg-secondary/20 transition-colors">
                        <td class="py-3.5 px-4 text-xs text-muted-foreground font-medium whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($mov->created_at)->format('d/m/Y H:i') }}
                        </td>

                        <td class="py-3.5 px-4 font-semibold text-foreground text-sm">
                            {{ $mov->produto->nome }}
                        </td>

                        <td class="py-3.5 px-4 text-center">
                            <x-badge-status :status="$mov->tipo_movimentacao" />
                        </td>

                        <td class="py-3.5 px-4 text-center font-extrabold text-sm {{ $mov->tipo_movimentacao === 'saida' ? 'text-destructive' : 'text-success' }}">
                            {{ $mov->tipo_movimentacao === 'saida' ? '-' : '+' }}{{ $mov->quantidade }} {{ $mov->produto->unidade_medida }}
                        </td>

                        <td class="py-3.5 px-4 text-center text-xs">
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-secondary/60 border border-border">
                                <span class="text-muted-foreground">{{ $mov->quantidade_anterior }}</span>
                                <x-icon name="chevron-right" class="w-3 h-3 text-muted-foreground" />
                                <span class="font-bold text-foreground">{{ $mov->quantidade_posterior }} {{ $mov->produto->unidade_medida }}</span>
                            </div>
                        </td>

                        <td class="py-3.5 px-4 text-xs text-foreground max-w-xs">
                            {{ $mov->motivo }}
                        </td>

                        <td class="py-3.5 px-4 text-xs text-muted-foreground">
                            {{ $mov->usuario->name ?? 'Sistema' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-muted-foreground text-sm">
                            Nenhuma movimentação registrada até o momento.
                        </td>
                    </tr>
                @endforelse
            </x-table>
        </x-card>
    @endif

    <!-- Modal para Nova Movimentação Rápida (Item 12 do escopo) -->
    <x-modal id="modal-nova-movimentacao" title="Registrar Movimentação de Estoque">
        <form method="POST" action="{{ route('estoque.movimentar') }}" class="space-y-4">
            @csrf

            <div>
                <x-select name="produto_id" label="Produto" required>
                    @foreach($produtos as $p)
                        <option value="{{ $p->id }}">
                            {{ $p->nome }} (Estoque atual: {{ $p->quantidade_estoque }} {{ $p->unidade_medida }})
                        </option>
                    @endforeach
                </x-select>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-select name="tipo_movimentacao" label="Tipo de Movimentação" required>
                        <option value="entrada" selected>Entrada (Compra / Reposição)</option>
                        <option value="saida">Saída (Consumo / Baixa)</option>
                        <option value="ajuste">Ajuste de Inventário</option>
                    </x-select>
                </div>

                <div>
                    <x-input
                        type="number"
                        step="0.001"
                        min="0.001"
                        name="quantidade"
                        label="Quantidade"
                        placeholder="Ex: 2.000"
                        required
                    />
                </div>
            </div>

            <div>
                <x-input
                    name="motivo"
                    label="Motivo da Movimentação"
                    placeholder="Ex: Compra de lote fornecedor, quebra de frasco, inventário..."
                    required
                />
            </div>

            <div>
                <x-textarea
                    name="observacoes"
                    label="Observações Adicionais (Opcional)"
                    placeholder="Informações adicionais para auditoria..."
                    rows="2"
                />
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                <x-button type="button" onclick="window.closeModal('modal-nova-movimentacao')" variant="outline" size="sm">
                    Cancelar
                </x-button>
                <x-button type="submit" variant="primary" size="sm" icon="check">
                    Confirmar Movimentação
                </x-button>
            </div>
        </form>
    </x-modal>
</x-layouts.app>