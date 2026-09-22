<x-layouts.app :title="'Editar: ' . ($veiculo->placa ?? 'Veículo')">
    <x-header
        :title="'Editar Veículo: ' . ($veiculo->marca ?? '') . ' ' . ($veiculo->modelo ?? '')"
        :subtitle="'Placa: ' . ($veiculo->placa ?? '')"
    >
        <x-slot:actions>
            <x-button href="{{ route('veiculos.show', $veiculo->id ?? 1) }}" variant="outline" size="sm" icon="eye">
                Ver Histórico
            </x-button>
            <x-button href="{{ route('veiculos.index') }}" variant="ghost" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>


    <div class="max-w-2xl">
        <form method="POST" action="{{ route('veiculos.update', $veiculo->id ?? 1) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-card title="Identificação e Proprietário">
                <div class="space-y-4">
                    <div>
                        <x-select
                            name="cliente_id"
                            label="Cliente Proprietário"
                            required
                        >
                            @foreach($clientes as $cliente)
                                <option value="{{ $cliente->id }}" {{ old('cliente_id', $veiculo->cliente_id ?? 1) == $cliente->id ? 'selected' : '' }}>
                                    {{ $cliente->nome }} ({{ $cliente->cpf_cnpj ?? 'Sem documento' }})
                                </option>
                            @endforeach
                        </x-select>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input
                                name="placa"
                                label="Placa do Veículo"
                                :value="$veiculo->placa ?? ''"
                                class="uppercase font-mono tracking-wider font-bold"
                                maxlength="10"
                                required
                            />
                        </div>

                        <div>
                            <x-input
                                name="ano"
                                type="number"
                                label="Ano"
                                :value="$veiculo->ano ?? ''"
                            />
                        </div>
                    </div>
                </div>
            </x-card>

            <x-card title="Especificações do Veículo">
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input
                                name="marca"
                                label="Marca"
                                :value="$veiculo->marca ?? ''"
                                required
                            />
                        </div>

                        <div>
                            <x-input
                                name="modelo"
                                label="Modelo"
                                :value="$veiculo->modelo ?? ''"
                                required
                            />
                        </div>
                    </div>

                    <div>
                        <x-input
                            name="cor"
                            label="Cor / Acabamento"
                            :value="$veiculo->cor ?? ''"
                        />
                    </div>

                    <div>
                        <x-textarea
                            name="observacoes"
                            label="Observações / Cuidados Especiais"
                            :value="$veiculo->observacoes ?? ''"
                            rows="3"
                        />
                    </div>
                </div>
            </x-card>

            <div class="flex items-center justify-end gap-3 pt-2">
                <x-button href="{{ route('veiculos.index') }}" variant="outline" size="md">
                    Cancelar
                </x-button>
                <x-button type="submit" variant="primary" size="md" icon="save">
                    Salvar Alterações
                </x-button>
            </div>
        </form>
    </div>
</x-layouts.app>