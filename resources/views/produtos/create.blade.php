<x-layouts.app title="Novo Produto">
    <x-header
        title="Cadastrar Produto"
        subtitle="Adicione um novo insumo informando o volume de cada embalagem e a quantidade de unidades em estoque."
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

            <x-card title="Identificação e Embalagem do Produto">
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

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-select name="unidade_medida" id="unidade_medida" label="Unidade de Medida do Volume" onchange="atualizarResumoVolume()" required>
                                <option value="ml" selected>Mililitros (ml)</option>
                                <option value="L">Litros (L)</option>
                                <option value="g">Gramas (g)</option>
                                <option value="kg">Quilos (kg)</option>
                                <option value="un">Unidade (un)</option>
                            </x-select>
                        </div>

                        <div>
                            <x-input
                                type="number"
                                step="0.001"
                                min="0.001"
                                name="volume_unitario"
                                id="volume_unitario"
                                label="Volume / Conteúdo de 1 Unidade"
                                placeholder="Ex: 500 (para frasco de 500ml)"
                                value="500"
                                oninput="atualizarResumoVolume()"
                                required
                            />
                        </div>
                    </div>
                </div>
            </x-card>

            <x-card title="Parâmetros de Estoque (em Unidades) e Custo">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <x-input
                            type="number"
                            step="1"
                            min="0"
                            name="quantidade_unidades"
                            id="quantidade_unidades"
                            label="Qtd. de Unidades (Frascos)"
                            placeholder="Ex: 3"
                            value="1"
                            oninput="atualizarResumoVolume()"
                            required
                        />
                    </div>

                    <div>
                        <x-input
                            type="number"
                            step="1"
                            min="0"
                            name="estoque_minimo_unidades"
                            id="estoque_minimo_unidades"
                            label="Estoque Mínimo (Unidades)"
                            placeholder="Ex: 1"
                            value="1"
                            oninput="atualizarResumoVolume()"
                            required
                        />
                    </div>

                    <div>
                        <x-input
                            type="number"
                            step="0.01"
                            min="0"
                            name="custo_unitario"
                            id="custo_unitario"
                            label="Custo por Unidade (R$)"
                            placeholder="0,00"
                            required
                        />
                    </div>
                </div>

                <!-- Resumo automático do volume total calculado -->
                <div class="mt-4 p-3.5 rounded-lg border border-primary/30 bg-primary/5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs">
                    <div>
                        <span class="font-semibold text-foreground block">Volume Total Disponível Calculado:</span>
                        <span class="text-muted-foreground">O consumo nas tarefas será abatido por volume e reduzirá 1 unidade no estoque a cada embalagem esgotada.</span>
                    </div>
                    <div class="text-right shrink-0">
                        <span id="resumo_volume_total" class="text-base font-extrabold text-primary">500 ml</span>
                        <span id="resumo_volume_minimo" class="block text-[11px] text-muted-foreground">Mínimo: 500 ml (1 un.)</span>
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

    @push('scripts')
    <script>
        function atualizarResumoVolume() {
            const unidade = document.getElementById('unidade_medida')?.value || 'ml';
            const volUnit = parseFloat(document.getElementById('volume_unitario')?.value) || 0;
            const qtdUnid = parseFloat(document.getElementById('quantidade_unidades')?.value) || 0;
            const minUnid = parseFloat(document.getElementById('estoque_minimo_unidades')?.value) || 0;

            const volTotal = qtdUnid * volUnit;
            const volMin = minUnid * volUnit;

            const fmt = (n) => Number.isInteger(n) ? n.toString() : n.toFixed(3).replace(/\.?0+$/, '');

            const elTotal = document.getElementById('resumo_volume_total');
            const elMin = document.getElementById('resumo_volume_minimo');

            if (elTotal) {
                elTotal.textContent = `${fmt(volTotal)} ${unidade} (${qtdUnid} un.)`;
            }
            if (elMin) {
                elMin.textContent = `Mínimo: ${fmt(volMin)} ${unidade} (${minUnid} un.)`;
            }
        }

        document.addEventListener('DOMContentLoaded', atualizarResumoVolume);
    </script>
    @endpush
</x-layouts.app>
