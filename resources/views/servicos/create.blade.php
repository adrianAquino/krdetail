<x-layouts.app title="Novo Serviço">
    <x-header
        title="Cadastrar Serviço"
        subtitle="Adicione um novo procedimento ao catálogo de estética automotiva."
    >
        <x-slot:actions>
            <x-button href="{{ route('servicos.index') }}" variant="outline" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('servicos.store') }}" class="space-y-6">
            @csrf

            <x-card title="Informações Gerais" description="Dados que serão apresentados aos clientes e utilizados nos agendamentos">
                <div class="space-y-4">
                    @if($isAdmin ?? false)
                        <div>
                            <x-select name="empresa_id" label="Empresa Proprietária" required>
                                <option value="">Selecione a empresa</option>
                                @foreach($empresas ?? [] as $emp)
                                    <option value="{{ $emp->id }}" {{ (string) old('empresa_id', $empresaSelecionadaId ?? '') === (string) $emp->id ? 'selected' : '' }}>
                                        {{ $emp->nome_fantasia }}
                                    </option>
                                @endforeach
                            </x-select>
                        </div>
                    @endif

                    <div>
                        <x-input
                            name="nome"
                            label="Nome do Serviço"
                            placeholder="Ex: Polimento Técnico Comercial"
                            maxlength="100"
                            required
                        />
                    </div>

                    <div>
                        <x-textarea
                            name="descricao"
                            label="Descrição Completa do Procedimento"
                            placeholder="Descreva as etapas técnicas, benefícios e produtos aplicados..."
                            maxlength="500"
                            rows="4"
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input
                                type="number"
                                step="0.01"
                                min="0"
                                max="99999.99"
                                name="preco_base"
                                label="Preço Base Sugerido (R$)"
                                placeholder="0,00"
                                :value="old('preco_base')"
                                required
                            />
                            <p class="text-[11px] text-muted-foreground mt-1">O valor pode ser ajustado individualmente em cada agendamento.</p>
                        </div>

                        <div>
                            <x-input
                                type="number"
                                min="10"
                                max="1440"
                                step="1"
                                name="tempo_estimado_minutos"
                                label="Tempo Estimado (em minutos)"
                                placeholder="Ex: 180 (3 horas)"
                                :value="old('tempo_estimado_minutos')"
                                required
                            />
                            <p class="text-[11px] text-muted-foreground mt-1">Ex: 60 = 1 hora, 120 = 2 horas, 240 = 4 horas.</p>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-border/50">
                        <div class="flex items-center justify-between p-3.5 rounded-lg border border-border bg-secondary/20">
                            <div>
                                <label for="ativo" class="text-sm font-semibold text-foreground cursor-pointer">Serviço Ativo</label>
                                <p class="text-xs text-muted-foreground">Serviços inativos deixam de aparecer para novos agendamentos, mas permanecem no histórico.</p>
                            </div>
                            <input
                                type="checkbox"
                                name="ativo"
                                id="ativo"
                                value="1"
                                {{ old('ativo', true) ? 'checked' : '' }}
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
                    Cadastrar Serviço
                </x-button>
            </div>
        </form>
    </div>
</x-layouts.app>