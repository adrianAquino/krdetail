<x-layouts.app title="Produtos">
    <x-header
        title="Produtos & Insumos"
        subtitle="Controle de produtos utilizados nas tarefas e insumos para os serviços de estética."
    >
        <x-slot:actions>
            <x-button href="{{ route('estoque.index') }}" variant="outline" size="md" icon="package">
                Visão de Estoque & Movimentações
            </x-button>
            <x-button href="{{ route('produtos.create') }}" icon="plus" size="md">
                Novo Produto
            </x-button>
        </x-slot:actions>
    </x-header>

    <!-- Search & Filters -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" action="{{ route('produtos.index') }}" class="flex-1 max-w-md flex items-center gap-2">
            <x-input
                name="busca"
                value="{{ request('busca') }}"
                placeholder="Buscar produto por nome..."
                icon="search"
            />
            @if(request('busca'))
                <x-button href="{{ route('produtos.index') }}" variant="ghost" size="sm">
                    Limpar
                </x-button>
            @endif
        </form>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('produtos.index') }}"
                class="px-3 py-1.5 rounded-lg text-xs font-medium border {{ !request('alerta') ? 'bg-primary/15 border-primary text-primary font-semibold' : 'bg-secondary/40 border-border text-muted-foreground hover:text-foreground' }}"
            >
                Todos
            </a>
            <a
                href="{{ route('produtos.index', ['alerta' => 'baixo']) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-medium border {{ request('alerta') === 'baixo' ? 'bg-warning/15 border-warning text-warning font-semibold' : 'bg-secondary/40 border-border text-muted-foreground hover:text-foreground' }}"
            >
                Estoque Baixo
            </a>
        </div>
    </div>

    @php
        $produtosDemo = $produtos ?? [
            (object)[
                'id' => 1,
                'nome' => 'Composto Polidor Médio V40',
                'descricao' => 'Composto polidor à base de água para refino e corte leve em vernizes médios e macios.',
                'unidade_medida' => 'L',
                'quantidade_estoque' => 0.800,
                'estoque_minimo' => 2.000,
                'custo_unitario' => 120.00,
                'ativo' => true,
            ],
            (object)[
                'id' => 2,
                'nome' => 'Vitrificador de Pintura 9H 50ml',
                'descricao' => 'Coating cerâmico nanotecnológico com dureza 9H e proteção de 3 anos.',
                'unidade_medida' => 'un',
                'quantidade_estoque' => 1.000,
                'estoque_minimo' => 4.000,
                'custo_unitario' => 240.00,
                'ativo' => true,
            ],
            (object)[
                'id' => 3,
                'nome' => 'Detergente Extratora Sintético',
                'descricao' => 'Limpador concentrado de baixa espumação para extratora de estofados.',
                'unidade_medida' => 'L',
                'quantidade_estoque' => 6.500,
                'estoque_minimo' => 3.000,
                'custo_unitario' => 35.00,
                'ativo' => true,
            ],
            (object)[
                'id' => 4,
                'nome' => 'Boina de Lã Híbrida 5 Pol',
                'descricao' => 'Boina de corte rápido para politriz roto-orbital.',
                'unidade_medida' => 'un',
                'quantidade_estoque' => 2.000,
                'estoque_minimo' => 6.000,
                'custo_unitario' => 58.00,
                'ativo' => true,
            ],
            (object)[
                'id' => 5,
                'nome' => 'Desengordurante IPA Prep',
                'descricao' => 'Solução de álcool isopropílico para inspeção e descontaminação pré-coating.',
                'unidade_medida' => 'L',
                'quantidade_estoque' => 1.200,
                'estoque_minimo' => 3.000,
                'custo_unitario' => 45.00,
                'ativo' => true,
            ],
            (object)[
                'id' => 6,
                'nome' => 'Shampoo Automotivo pH Neutro',
                'descricao' => 'Shampoo super concentrado de alto rendimento para lavagem de manutenção.',
                'unidade_medida' => 'L',
                'quantidade_estoque' => 15.000,
                'estoque_minimo' => 5.000,
                'custo_unitario' => 28.00,
                'ativo' => true,
            ],
        ];
    @endphp

    <x-card class="p-0">
        <x-table>
            <x-slot:header>
                <tr>
                    <th class="py-3.5 px-4">Produto</th>
                    <th class="py-3.5 px-4 text-center">Unidade</th>
                    <th class="py-3.5 px-4 text-center">Estoque Atual</th>
                    <th class="py-3.5 px-4 text-center">Estoque Mínimo</th>
                    <th class="py-3.5 px-4 text-right">Custo Unitário</th>
                    <th class="py-3.5 px-4 text-center">Situação</th>
                    <th class="py-3.5 px-4 text-right">Ações</th>
                </tr>
            </x-slot:header>

            @forelse($produtosDemo as $prod)
                @php
                    $isEstoqueBaixo = $prod->quantidade_estoque <= $prod->estoque_minimo;
                @endphp
                <tr class="hover:bg-secondary/25 transition-colors">
                    <td class="py-3.5 px-4">
                        <a href="{{ route('produtos.show', $prod->id) }}" class="flex items-center gap-3 group">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary font-bold group-hover:bg-primary group-hover:text-primary-foreground transition-colors">
                                <x-icon name="package" class="w-5 h-5" />
                            </div>
                            <div>
                                <p class="font-semibold text-foreground group-hover:text-primary transition-colors">{{ $prod->nome }}</p>
                                <p class="text-xs text-muted-foreground truncate max-w-xs">{{ $prod->descricao }}</p>
                            </div>
                        </a>
                    </td>

                    <td class="py-3.5 px-4 text-center">
                        <span class="font-mono text-xs px-2 py-0.5 rounded bg-secondary text-foreground border border-border">
                            {{ $prod->unidade_medida }}
                        </span>
                    </td>

                    <td class="py-3.5 px-4 text-center">
                        <span class="font-extrabold text-sm {{ $isEstoqueBaixo ? 'text-destructive font-black' : 'text-foreground' }}">
                            {{ $prod->quantidade_estoque }} {{ $prod->unidade_medida }}
                        </span>
                    </td>

                    <td class="py-3.5 px-4 text-center text-xs text-muted-foreground">
                        {{ $prod->estoque_minimo }} {{ $prod->unidade_medida }}
                    </td>

                    <td class="py-3.5 px-4 text-right font-medium text-foreground">
                        R$ {{ number_format($prod->custo_unitario, 2, ',', '.') }}
                    </td>

                    <td class="py-3.5 px-4 text-center">
                        @if($isEstoqueBaixo)
                            <x-badge-status status="estoque_baixo" label="Estoque Baixo" />
                        @else
                            <x-badge-status status="estoque_normal" label="Normal" />
                        @endif
                    </td>

                    <td class="py-3.5 px-4 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <x-button href="{{ route('produtos.show', $prod->id) }}" variant="ghost" size="icon-sm" title="Ver Detalhes">
                                <x-icon name="eye" class="w-4 h-4" />
                            </x-button>
                            <x-button href="{{ route('produtos.edit', $prod->id) }}" variant="ghost" size="icon-sm" title="Editar Produto">
                                <x-icon name="pencil" class="w-4 h-4" />
                            </x-button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="py-8 text-center text-muted-foreground text-sm">
                        Nenhum produto cadastrado.
                    </td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
</x-layouts.app>