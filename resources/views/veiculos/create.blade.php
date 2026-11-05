@php
    $isCliente = auth()->check() && auth()->user()->isCliente();
@endphp

<x-layouts.app title="Novo Veículo">
    <x-header
        title="Cadastrar Veículo"
        :subtitle="$isCliente ? 'Informe os dados do seu veículo para agendar serviços e acompanhar o histórico.' : 'Associe um novo veículo ao cliente correspondente e registre suas especificações.'"
    >
        <x-slot:actions>
            <x-button href="{{ route('veiculos.index') }}" variant="outline" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('veiculos.store') }}" class="space-y-6">
            @csrf

            <x-card title="Identificação do Veículo" :description="$isCliente ? 'Dados principais de emplacamento e fabricação' : 'Todo veículo registrado deve pertencer a um cliente cadastrado'">
                <div class="space-y-4">
                    @if($isCliente)
                        <input type="hidden" name="cliente_id" value="{{ $selectedClienteId }}" />
                    @else
                        <div>
                            <x-select
                                name="cliente_id"
                                label="Cliente Proprietário"
                                required
                            >
                                @foreach($clientes as $cliente)
                                    <option value="{{ $cliente->id }}" {{ old('cliente_id', $selectedClienteId) == $cliente->id ? 'selected' : '' }}>
                                        {{ $cliente->nome }} ({{ $cliente->cpf_cnpj ?? 'Sem documento' }})
                                    </option>
                                @endforeach
                            </x-select>
                            <p class="text-[11px] text-muted-foreground mt-1">
                                Não encontrou o cliente? <a href="{{ route('clientes.create') }}" class="text-primary hover:underline">Cadastre um novo cliente primeiro</a>.
                            </p>
                        </div>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input
                                name="placa"
                                label="Placa do Veículo"
                                placeholder="Ex: ABC1D23 ou ABC-1234"
                                value="{{ old('placa') }}"
                                class="uppercase font-mono tracking-wider font-bold"
                                maxlength="10"
                                required
                            />
                        </div>

                        <div>
                            <x-input
                                name="ano"
                                type="number"
                                label="Ano de Fabricação"
                                placeholder="2023"
                                value="{{ old('ano') }}"
                                min="1950"
                                max="{{ date('Y') + 1 }}"
                            />
                        </div>
                    </div>
                </div>
            </x-card>

            <x-card title="Especificações do Veículo" description="Dados visuais para identificação e histórico">
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input
                                name="marca"
                                label="Marca / Fabricante"
                                placeholder="Ex: BMW, Audi, Porsche, Toyota"
                                value="{{ old('marca') }}"
                                required
                            />
                        </div>

                        <div>
                            <x-input
                                name="modelo"
                                label="Modelo / Versão"
                                placeholder="Ex: 320i M Sport, Corolla Cross"
                                value="{{ old('modelo') }}"
                                required
                            />
                        </div>
                    </div>

                    <div>
                        <x-input
                            name="cor"
                            label="Cor / Acabamento da Pintura"
                            placeholder="Ex: Preto Metálico, Azul Portimão, Branco Pérola"
                            value="{{ old('cor') }}"
                        />
                    </div>

                    <div>
                        <x-textarea
                            name="observacoes"
                            label="Observações / Cuidados Especiais"
                            placeholder="Ex: Pintura vitrificada anteriormente, repintura no para-choque traseiro..."
                            value="{{ old('observacoes') }}"
                            rows="3"
                        />
                    </div>
                </div>
            </x-card>

            <!-- Form Actions -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <x-button href="{{ route('veiculos.index') }}" variant="outline" size="md">
                    Cancelar
                </x-button>
                <x-button type="submit" variant="primary" size="md" icon="save">
                    Cadastrar Veículo
                </x-button>
            </div>
        </form>
    </div>
</x-layouts.app>
