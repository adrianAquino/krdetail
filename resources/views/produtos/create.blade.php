<x-layouts.app title="Novo Produto">
    <x-header
        title="Cadastrar Produto"
        subtitle="Adicione um novo insumo ou produto para controle de estoque e consumo nas ordens de serviço."
    >
        <x-slot:actions>
            <x-button href="{{ route('produtos.index') }}" variant="outline" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('produtos.store') }}" class="space-y-6">
            @csrf

            <x-card title="Identificação do Produto">
                <div class="space-y-4">
                    <div>
                        <x-input
                            name="nome"
                            label="Nome do Produto / Insumo"
                            placeholder="Ex: Composto Polidor Médio V40"
                            required
                        />
                    </div>

                    <div>
                        <x-textarea
                            name="descricao"
                            label="Descrição / Especificações"
                            placeholder="Finalidade, marca, diluição recomendada..."
                            rows="3"
                        />
                    </div>

                    <div>
                        <x-select name="unidade_medida" label="Unidade de Medida" required>
                            <option value="L" selected>Litros (L)</option>
                            <option value="ml">Mililitros (ml)</option>
                            <option value="kg">Quilos (kg)</option>
                            <option value="g">Gramas (g)</option>
                            <option value="un">Unidade (un)</option>
                            <option value="cx">Caixa (cx)</option>
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
                            label="Estoque Inicial"
                            placeholder="0.000"
                            required
                        />
                    </div>

                    <div>
                        <x-input
                            type="number"
                            step="0.001"
                            min="0"
                            name="estoque_minimo"
                            label="Estoque Mínimo (Alerta)"
                            placeholder="1.000"
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
                            placeholder="0,00"
                        />
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-border/60">
                    <div class="flex items-center justify-between p-3.5 rounded-lg border border-border bg-secondary/20">
                        <div>
                            <label for="ativo" class="text-sm font-semibold text-foreground cursor-pointer">Produto Ativo</label>
                            <p class="text-xs text-muted-foreground">Produtos ativos ficam disponíveis para registro de consumo nas tarefas.</p>
                        </div>
                        <input
                            type="checkbox"
                            name="ativo"
                            id="ativo"
                            value="1"
                            checked
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
                    Cadastrar Produto
                </x-button>
            </div>
        </form>
    </div>
</x-layouts.app>