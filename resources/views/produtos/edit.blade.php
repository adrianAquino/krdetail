<x-layouts.app :title="'Editar: ' . ($produto->nome ?? 'Produto')">
    <x-header
        :title="'Editar: ' . ($produto->nome ?? 'Produto')"
        subtitle="Atualize o volume por embalagem, parâmetros de estoque ou custo unitário."
    >
        <x-slot:actions>
            <x-button href="{{ route('produtos.show', $produto->id) }}" variant="outline" size="sm" icon="eye">
                Ver Ficha
            </x-button>
            <x-button href="{{ route('produtos.index') }}" variant="ghost" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('produtos.update', $produto->id) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-card title="Identificação e Embalagem do Produto">
                <div class="space-y-4">
                    @if(auth()->check() && auth()->user()->isAdministrador() && $produto?->empresa)
                        <div class="rounded-lg border border-border bg-secondary/30 px-3 py-2 text-xs text-muted-foreground flex items-center gap-2">
                            <x-icon name="briefcase" class="w-3.5 h-3.5 text-primary" />
                            <span>Empresa vinculada: <strong class="text-foreground">{{ $produto->empresa->nome_fantasia }}</strong></span>
                        </div>
                    @endif

                    <div>
                        <x-input
                            name="nome"
                            label="Nome do Produto"
                            :value="$produto->nome"
                            maxlength="100"
                            required
                        />
                    </div>

                    <div>
                        <x-textarea
                            name="descricao"
                            label="Descrição"
                            :value="$produto->descricao ?? ''"
                            maxlength="500"
                            rows="3"
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-select name="unidade_medida" id="unidade_medida" label="Unidade de Medida do Volume" required>
                                @php $unidadeAtual = old('unidade_medida', $produto->unidade_medida ?? 'ml'); @endphp
                                <option value="ml" {{ $unidadeAtual === 'ml' ? 'selected' : '' }}>Mililitros (ml)</option>
                                <option value="L" {{ $unidadeAtual === 'L' ? 'selected' : '' }}>Litros (L)</option>
                                <option value="g" {{ $unidadeAtual === 'g' ? 'selected' : '' }}>Gramas (g)</option>
                                <option value="kg" {{ $unidadeAtual === 'kg' ? 'selected' : '' }}>Quilos (kg)</option>
                                <option value="un" {{ $unidadeAtual === 'un' ? 'selected' : '' }}>Unidade (un)</option>
                            </x-select>
                        </div>

                        <div>
                            <x-input
                                type="number"
                                step="0.001"
                                min="0.001"
                                max="999999"
                                name="volume_unitario"
                                id="volume_unitario"
                                label="Volume / Conteúdo de 1 Unidade"
                                :value="old('volume_unitario', (float) ($produto->volume_unitario ?? 1))"
                                required
                            />
                        </div>
                    </div>
                </div>
            </x-card>

            @php
                $volUnitEdit = (float) ($produto->volume_unitario ?? 1);
                $minimoUnidadesExato = $volUnitEdit > 0
                    ? round((float) $produto->estoque_minimo / $volUnitEdit, 3)
                    : 0;
            @endphp

            <x-card title="Parâmetros de Estoque e Custo">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <x-input
                            type="number"
                            step="0.001"
                            min="0"
                            max="9999999"
                            name="quantidade_estoque"
                            label="Volume Total Atual ({{ $produto->unidade_medida }})"
                            :value="old('quantidade_estoque', (float) $produto->quantidade_estoque)"
                            hint="Equivale a {{ $produto->unidades_estoque }} un. em estoque"
                            required
                        />
                    </div>

                    <div>
                        <x-input
                            type="number"
                            step="0.001"
                            min="0"
                            max="99999"
                            name="estoque_minimo_unidades"
                            label="Estoque Mínimo (Unidades)"
                            :value="old('estoque_minimo_unidades', $minimoUnidadesExato)"
                            hint="Mínimo atual: {{ rtrim(rtrim(number_format((float) $produto->estoque_minimo, 3, ',', '.'), '0'), ',') }} {{ $produto->unidade_medida }}"
                            required
                        />
                    </div>

                    <div>
                        <x-input
                            type="number"
                            step="0.0001"
                            min="0"
                            max="999999.99"
                            name="custo_unitario"
                            label="Custo por Unidade (R$)"
                            :value="old('custo_unitario', (float) $produto->custo_unitario)"
                            required
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
