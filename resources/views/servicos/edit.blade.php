<x-layouts.app :title="'Editar: ' . ($servico->nome ?? 'Serviço')">
    <x-header
        :title="'Editar: ' . ($servico->nome ?? 'Serviço')"
        subtitle="Atualize os parâmetros base de preço, duração estimada ou status de disponibilidade."
    >
        <x-slot:actions>
            <x-button href="{{ route('servicos.index') }}" variant="ghost" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('servicos.update', $servico->id ?? 1) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-card title="Informações do Serviço">
                <div class="space-y-4">
                    <div>
                        <x-input
                            name="nome"
                            label="Nome do Serviço"
                            :value="$servico->nome ?? ''"
                            required
                        />
                    </div>

                    <div>
                        <x-textarea
                            name="descricao"
                            label="Descrição do Procedimento"
                            :value="$servico->descricao ?? ''"
                            rows="4"
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input
                                type="number"
                                step="0.01"
                                min="0"
                                name="preco_base"
                                label="Preço Base (R$)"
                                :value="$servico->preco_base ?? ''"
                                required
                            />
                        </div>

                        <div>
                            <x-input
                                type="number"
                                min="10"
                                step="5"
                                name="tempo_estimado_minutos"
                                label="Tempo Estimado (minutos)"
                                :value="$servico->tempo_estimado_minutos ?? ''"
                                required
                            />
                        </div>
                    </div>

                    <div class="pt-2 border-t border-border/50">
                        <div class="flex items-center justify-between p-3.5 rounded-lg border border-border bg-secondary/20">
                            <div>
                                <label for="ativo" class="text-sm font-semibold text-foreground cursor-pointer">Serviço Ativo</label>
                                <p class="text-xs text-muted-foreground">Serviços inativos deixam de aparecer em novos agendamentos.</p>
                            </div>
                            <input
                                type="checkbox"
                                name="ativo"
                                id="ativo"
                                value="1"
                                {{ old('ativo', $servico->ativo ?? true) ? 'checked' : '' }}
                                class="h-5 w-5 rounded border-border bg-input text-primary focus:ring-primary cursor-pointer"
                            />
                        </div>
                    </div>
                </div>
            </x-card>

            <div class="flex items-center justify-end gap-3 pt-2">
                <x-button href="{{ route('servicos.index') }}" variant="outline" size="md">
                    Cancelar
                </x-button>
                <x-button type="submit" variant="primary" size="md" icon="save">
                    Salvar Alterações
                </x-button>
            </div>
        </form>
    </div>
</x-layouts.app>