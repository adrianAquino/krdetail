<x-layouts.app title="Clientes">
    <x-header
        title="Clientes"
        subtitle="Gerencie sua carteira de clientes, veículos associados e situação de acesso ao sistema."
    >
        <x-slot:actions>
            <x-button href="{{ route('clientes.create') }}" icon="plus" size="md">
                Novo Cliente
            </x-button>
        </x-slot:actions>
    </x-header>

    <!-- Filters & Search Bar -->
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" action="{{ route('clientes.index') }}" class="flex-1 max-w-md flex items-center gap-2">
            <x-input
                name="busca"
                value="{{ request('busca') }}"
                placeholder="Buscar por nome, telefone, CPF ou e-mail..."
                icon="search"
            />
            @if(request('busca'))
                <x-button href="{{ route('clientes.index') }}" variant="ghost" size="sm">
                    Limpar
                </x-button>
            @endif
        </form>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('clientes.index', ['acesso' => 'ativo']) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-medium border {{ request('acesso') === 'ativo' ? 'bg-primary/10 border-primary text-primary' : 'bg-secondary/40 border-border text-muted-foreground hover:text-foreground' }}"
            >
                Acesso Ativo
            </a>
            <a
                href="{{ route('clientes.index', ['acesso' => 'sem_acesso']) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-medium border {{ request('acesso') === 'sem_acesso' ? 'bg-primary/10 border-primary text-primary' : 'bg-secondary/40 border-border text-muted-foreground hover:text-foreground' }}"
            >
                Sem Acesso
            </a>
        </div>
    </div>

    @php
        $clientesDemo = $clientes ?? [
            (object)[
                'id' => 1,
                'nome' => 'Lucas Guimarães',
                'telefone' => '(11) 98765-4321',
                'email' => 'lucas.guimaraes@email.com',
                'cpf_cnpj' => '345.890.123-45',
                'cidade' => 'São Paulo',
                'estado' => 'SP',
                'user_id' => 10, // possui conta de usuário
                'veiculos_count' => 2,
                'agendamentos_count' => 5,
            ],
            (object)[
                'id' => 2,
                'nome' => 'Marina Silveira',
                'telefone' => '(11) 97123-8890',
                'email' => 'marina.silveira@email.com',
                'cpf_cnpj' => '456.789.012-34',
                'cidade' => 'Campinas',
                'estado' => 'SP',
                'user_id' => null, // sem conta de usuário
                'veiculos_count' => 1,
                'agendamentos_count' => 2,
            ],
            (object)[
                'id' => 3,
                'nome' => 'Roberto Medeiros',
                'telefone' => '(11) 99345-1234',
                'email' => 'roberto.medeiros@empresa.com.br',
                'cpf_cnpj' => '12.345.678/0001-90',
                'cidade' => 'São Paulo',
                'estado' => 'SP',
                'user_id' => 12,
                'veiculos_count' => 3,
                'agendamentos_count' => 8,
            ],
            (object)[
                'id' => 4,
                'nome' => 'Juliana Ferreira',
                'telefone' => '(11) 98456-7890',
                'email' => 'juliana.ferreira@email.com',
                'cpf_cnpj' => '234.567.890-12',
                'cidade' => 'São Caetano do Sul',
                'estado' => 'SP',
                'user_id' => null,
                'veiculos_count' => 1,
                'agendamentos_count' => 3,
            ],
            (object)[
                'id' => 5,
                'nome' => 'Eduardo Castilho',
                'telefone' => '(11) 99876-5432',
                'email' => 'eduardo.castilho@email.com',
                'cpf_cnpj' => '567.890.123-88',
                'cidade' => 'Santo André',
                'estado' => 'SP',
                'user_id' => 14,
                'veiculos_count' => 2,
                'agendamentos_count' => 4,
            ],
        ];
    @endphp

    <!-- Clientes Table Card -->
    <x-card class="p-0">
        <x-table>
            <x-slot:header>
                <tr>
                    <th class="py-3.5 px-4">Cliente</th>
                    <th class="py-3.5 px-4">Contato</th>
                    <th class="py-3.5 px-4 text-center">Veículos</th>
                    <th class="py-3.5 px-4 text-center">Situação do Acesso</th>
                    <th class="py-3.5 px-4 text-right">Ações</th>
                </tr>
            </x-slot:header>

            @forelse($clientesDemo as $cliente)
                <tr class="hover:bg-secondary/25 transition-colors">
                    <td class="py-3.5 px-4">
                        <a href="{{ route('clientes.show', $cliente->id) }}" class="flex items-center gap-3 group">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-bold text-sm group-hover:bg-primary group-hover:text-primary-foreground transition-colors">
                                {{ strtoupper(substr($cliente->nome, 0, 2)) }}
                            </div>
                            <div>
                                <p class="font-semibold text-foreground group-hover:text-primary transition-colors">{{ $cliente->nome }}</p>
                                <p class="text-xs text-muted-foreground">{{ $cliente->cpf_cnpj ?? 'CPF/CNPJ não informado' }}</p>
                            </div>
                        </a>
                    </td>

                    <td class="py-3.5 px-4">
                        <div class="space-y-0.5 text-xs">
                            <p class="font-medium text-foreground flex items-center gap-1.5">
                                <x-icon name="phone" class="w-3.5 h-3.5 text-muted-foreground" />
                                {{ $cliente->telefone }}
                            </p>
                            @if($cliente->email)
                                <p class="text-muted-foreground flex items-center gap-1.5">
                                    <x-icon name="mail" class="w-3.5 h-3.5 text-muted-foreground" />
                                    {{ $cliente->email }}
                                </p>
                            @endif
                        </div>
                    </td>

                    <td class="py-3.5 px-4 text-center">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold bg-secondary/50 text-foreground border border-border">
                            <x-icon name="car" class="w-3.5 h-3.5 text-primary" />
                            {{ $cliente->veiculos_count ?? 0 }} veículo(s)
                        </span>
                    </td>

                    <td class="py-3.5 px-4 text-center">
                        @if($cliente->user_id)
                            <x-badge-status status="acesso_ativo" label="Acesso Ativo" />
                        @else
                            <x-badge-status status="sem_acesso" label="Sem Acesso" />
                        @endif
                    </td>

                    <td class="py-3.5 px-4 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <x-button href="{{ route('clientes.show', $cliente->id) }}" variant="ghost" size="icon-sm" title="Ver Detalhes">
                                <x-icon name="eye" class="w-4 h-4" />
                            </x-button>
                            <x-button href="{{ route('clientes.edit', $cliente->id) }}" variant="ghost" size="icon-sm" title="Editar Cliente">
                                <x-icon name="pencil" class="w-4 h-4" />
                            </x-button>
                            <form method="POST" action="{{ route('clientes.destroy', $cliente->id) }}" onsubmit="return confirm('Tem certeza que deseja remover este cliente?');" class="inline">
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
                    <td colspan="5" class="py-8 text-center text-muted-foreground text-sm">
                        Nenhum cliente cadastrado.
                    </td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
</x-layouts.app>