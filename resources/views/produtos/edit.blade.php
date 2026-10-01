<x-layouts.app :title="'Editar: ' . ($produto->nome ?? 'Produto')">
    <x-header
        :title="'Editar: ' . ($produto->nome ?? 'Produto')"
        subtitle="Atualize os parâmetros de estoque, unidade de medida ou custo unitário."
    >
        <x-slot:actions>
            <x-button href="{{ route('produtos.show', $produto->id ?? 1) }}" variant="outline" size="sm" icon="eye">
                Ver Ficha
            </x-button>
            <x-button href="{{ route('produtos.index') }}" variant="ghost" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('produtos.update', $produto->id ?? 1) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-card title="Identificação do Produto">
                <div class="space-y-4">
                    <div>
                        <x-input
                            name="nome"
                            label="Nome do Produto"
                            :value="$produto->nome ?? ''"
                            required
                        />
                    </div>

                    <div>
                        <x-textarea
                            name="descricao"
                            label="Descrição"
                            :value="$produto->descricao ?? ''"
                            rows="3"
                        />
                    </div>

                    <div>
                        <x-select name="unidade_medida" label="Unidade de Medida" required>
                            @php $unidadeAtual = $produto->unidade_medida ?? 'L'; @endphp
                            <option value="L" {{ $unidadeAtual === 'L' ? 'selected' : '' }}>Litros (L)</option>
                            <option value="ml" {{ $unidadeAtual === 'ml' ? 'selected' : '' }}>Mililitros (ml)</option>
                            <option value="kg" {{ $unidadeAtual === 'kg' ? 'selected' : '' }}>Quilos (kg)</option>
                            <option value="g" {{ $unidadeAtual === 'g' ? 'selected' : '' }}>Gramas (g)</option>
                            <option value="un" {{ $unidadeAtual === 'un' ? 'selected' : '' }}>Unidade (un)</option>
                            <option value="cx" {{ $unidadeAtual === 'cx' ? 'selected' : '' }}>Caixa (cx)</option>
                        </x-select>
                    </div>
                </div>
            </x-card>

            <x-card title="Parâmetros de Estoque e Custo">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <x-input
                            type="number"
                            step="0.001"
                            min="0"
                            name="quantidade_estoque"
                            label="Estoque Atual"
                            :value="$produto->quantidade_estoque ?? ''"
                            required
                        />
                    </div>

                    <div>
                        <x-input
                            type="number"
                            step="0.001"
                            min="0"
                            name="estoque_minimo"
                            label="Estoque Mínimo"
                            :value="$produto->estoque_minimo ?? ''"
                            required
                        />
                    </div>

                    <div>
                        <x-input
                            type="number"
                            step="0.01"
                            min="0"
                            name="custo_unitario"
                            label="Custo Unitário (R$)"
                            :value="$produto->custo_unitario ?? ''"
                        />
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-border/60">
                    <div class="flex items-center justify-between p-3.5 rounded-lg border border-border bg-secondary/20">
                        <div>
                            <label for="ativo" class="text-sm font-semibold text-foreground cursor-pointer">Produto Ativo</label>
                            <p class="text-xs text-muted-foreground">Produtos inativos não podem ser selecionados em tarefas.</p>
                        </div>
                        <input
                            type="checkbox"
                            name="ativo"
                            id="ativo"
                            value="1"
                            {{ old('ativo', $produto->ativo ?? true) ? 'checked' : '' }}
                            class="h-5 w-5 rounded border-border bg-input text-primary focus:ring-primary cursor-pointer"
                        />
                    </div>
                </div>
            </x-card>

            <div class="flex items-center justify-end gap-3 pt-2">
                <x-button href="{{ route('produtos.index') }}" variant="outline" size="md">
                    Cancelar
                </x-button>
                <x-button type="submit" variant="primary" size="md" icon="save">
                    Salvar Alterações
                </x-button>
            </div>
        </form>
    </div>
</x-layouts.app>