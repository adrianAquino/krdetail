<x-layouts.app title="Veículos">
    <x-header
        title="Veículos"
        subtitle="Controle de veículos cadastrados na oficina, histórico de cuidados e clientes proprietários."
    >
        <x-slot:actions>
            <x-button href="{{ route('veiculos.create') }}" icon="plus" size="md">
                Novo Veículo
            </x-button>
        </x-slot:actions>
    </x-header>

    <!-- Filters & Search -->
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" action="{{ route('veiculos.index') }}" class="flex-1 max-w-md flex items-center gap-2">
            <x-input
                name="busca"
                value="{{ request('busca') }}"
                placeholder="Buscar por placa, modelo, marca ou cliente..."
                icon="search"
            />
            @if(request('busca'))
                <x-button href="{{ route('veiculos.index') }}" variant="ghost" size="sm">
                    Limpar
                </x-button>
            @endif
        </form>
    </div>



    <x-card class="p-0">
        <x-table>
            <x-slot:header>
                <tr>
                    <th class="py-3.5 px-4">Veículo</th>
                    <th class="py-3.5 px-4">Placa</th>
                    <th class="py-3.5 px-4">Proprietário</th>
                    <th class="py-3.5 px-4">Cor / Ano</th>
                    <th class="py-3.5 px-4 text-center">Histórico</th>
                    <th class="py-3.5 px-4 text-right">Ações</th>
                </tr>
            </x-slot:header>

            @forelse($veiculos as $veiculo)
                <tr class="hover:bg-secondary/25 transition-colors">
                    <td class="py-3.5 px-4">
                        <a href="{{ route('veiculos.show', $veiculo->id) }}" class="flex items-center gap-3 group">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary font-bold group-hover:bg-primary group-hover:text-primary-foreground transition-colors">
                                <x-icon name="car" class="w-5 h-5" />
                            </div>
                            <div>
                                <p class="font-semibold text-foreground group-hover:text-primary transition-colors">
                                    {{ $veiculo->marca }} {{ $veiculo->modelo }}
                                </p>
                                <p class="text-xs text-muted-foreground">{{ $veiculo->marca }}</p>
                            </div>
                        </a>
                    </td>

                    <td class="py-3.5 px-4">
                        <span class="font-mono text-xs font-bold px-2.5 py-1 rounded-md bg-secondary/80 text-foreground border border-border tracking-wider">
                            {{ $veiculo->placa }}
                        </span>
                    </td>

                    <td class="py-3.5 px-4">
                        @if(isset($veiculo->cliente))
                            <a href="{{ route('clientes.show', $veiculo->cliente->id) }}" class="text-sm font-medium text-foreground hover:text-primary transition-colors flex items-center gap-1.5">
                                <x-icon name="user" class="w-3.5 h-3.5 text-muted-foreground" />
                                {{ $veiculo->cliente->nome }}
                            </a>
                            <p class="text-xs text-muted-foreground ml-5">{{ $veiculo->cliente->telefone }}</p>
                        @else
                            <span class="text-xs text-muted-foreground">Não vinculado</span>
                        @endif
                    </td>

                    <td class="py-3.5 px-4">
                        <p class="text-sm font-medium text-foreground">{{ $veiculo->cor ?? 'Não informada' }}</p>
                        <p class="text-xs text-muted-foreground">{{ $veiculo->ano ? 'Ano ' . $veiculo->ano : 'Ano N/D' }}</p>
                    </td>

                    <td class="py-3.5 px-4 text-center">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-secondary text-muted-foreground">
                            {{ $veiculo->agendamentos_count ?? 0 }} serviço(s)
                        </span>
                    </td>

                    <td class="py-3.5 px-4 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <x-button href="{{ route('veiculos.show', $veiculo->id) }}" variant="ghost" size="icon-sm" title="Ver Histórico">
                                <x-icon name="eye" class="w-4 h-4" />
                            </x-button>
                            <x-button href="{{ route('veiculos.edit', $veiculo->id) }}" variant="ghost" size="icon-sm" title="Editar Veículo">
                                <x-icon name="pencil" class="w-4 h-4" />
                            </x-button>
                            <form method="POST" action="{{ route('veiculos.destroy', $veiculo->id) }}" onsubmit="return confirm('Tem certeza que deseja remover este veículo?');" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 rounded-lg text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors cursor-pointer" title="Excluir">
                                    <x-icon name="trash-2" class="w-4 h-4" />
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="py-8 text-center text-muted-foreground text-sm">
                        Nenhum veículo cadastrado.
                    </td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
</x-layouts.app>