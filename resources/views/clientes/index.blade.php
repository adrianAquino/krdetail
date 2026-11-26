<x-layouts.app title="Clientes">
    <x-header
        title="Clientes"
        subtitle="Gerencie sua carteira de clientes, veículos associados e situação de acesso ao sistema."
    >
        <x-slot:actions>
            <x-button href="{{ route('clientes.create', ($isAdmin ?? false) && !empty($empresaFiltroId) ? ['empresa_id' => $empresaFiltroId] : []) }}" icon="plus" size="md">
                Novo Cliente
            </x-button>
        </x-slot:actions>
    </x-header>

    <x-admin-empresa-filter
        :action="route('clientes.index')"
        :empresas="$empresasFiltro ?? collect()"
        :empresaSelecionadaId="$empresaFiltroId ?? null"
        :preserve="['busca' => request('busca'), 'acesso' => request('acesso')]"
        label="Empresa nos Clientes"
    />

    <!-- Filters & Search Bar -->
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" action="{{ route('clientes.index') }}" class="flex-1 max-w-md flex items-center gap-2">
            @if(($isAdmin ?? false) && !empty($empresaFiltroId))
                <input type="hidden" name="empresa_id" value="{{ $empresaFiltroId }}" />
            @endif
            @if(request('acesso'))
                <input type="hidden" name="acesso" value="{{ request('acesso') }}" />
            @endif
            <x-input
                name="busca"
                value="{{ request('busca') }}"
                maxlength="100"
                placeholder="Buscar por nome, telefone, CPF ou e-mail..."
                icon="search"
            />
            @if(request('busca') || request('acesso'))
                <x-button href="{{ route('clientes.index', ($isAdmin ?? false) && !empty($empresaFiltroId) ? ['empresa_id' => $empresaFiltroId] : []) }}" variant="ghost" size="sm">
                    Limpar
                </x-button>
            @endif
        </form>

        <div class="flex items-center gap-2">
            @php $empParamCli = ($isAdmin ?? false) && !empty($empresaFiltroId) ? $empresaFiltroId : null; @endphp
            <a
                href="{{ route('clientes.index', array_filter(['busca' => request('busca'), 'empresa_id' => $empParamCli])) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-medium border {{ !request('acesso') ? 'bg-primary/10 border-primary text-primary font-semibold' : 'bg-secondary/40 border-border text-muted-foreground hover:text-foreground' }}"
            >
                Todos
            </a>
            <a
                href="{{ route('clientes.index', array_filter(['busca' => request('busca'), 'acesso' => 'ativo', 'empresa_id' => $empParamCli])) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-medium border {{ request('acesso') === 'ativo' ? 'bg-primary/10 border-primary text-primary font-semibold' : 'bg-secondary/40 border-border text-muted-foreground hover:text-foreground' }}"
            >
                Acesso Ativo
            </a>
            <a
                href="{{ route('clientes.index', array_filter(['busca' => request('busca'), 'acesso' => 'sem_acesso', 'empresa_id' => $empParamCli])) }}"
                class="px-3 py-1.5 rounded-lg text-xs font-medium border {{ in_array(request('acesso'), ['sem_acesso', 'inativo'], true) ? 'bg-primary/10 border-primary text-primary font-semibold' : 'bg-secondary/40 border-border text-muted-foreground hover:text-foreground' }}"
            >
                Sem Acesso / Inativo
            </a>
        </div>
    </div>


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

            @forelse($clientes as $cliente)
                <tr class="hover:bg-secondary/25 transition-colors">
                    <td class="py-3.5 px-4">
                        <a href="{{ route('clientes.show', $cliente->id) }}" class="flex items-center gap-3 group">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-bold text-sm group-hover:bg-primary group-hover:text-primary-foreground transition-colors">
                                {{ mb_strtoupper(mb_substr($cliente->nome, 0, 2, 'UTF-8'), 'UTF-8') }}
                            </div>
                            <div>
                                <p class="font-semibold text-foreground group-hover:text-primary transition-colors">
                                    {{ $cliente->nome }}
                                    @if(($isAdmin ?? false) && empty($empresaFiltroId) && ($cliente->empresa ?? null))
                                        <span class="ml-1 text-[10px] font-medium px-1.5 py-0.5 rounded bg-secondary text-muted-foreground border border-border">
                                            {{ $cliente->empresa->nome_fantasia }}
                                        </span>
                                    @endif
                                </p>
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
                        @if($cliente->user_id && ($cliente->user?->ativo ?? true))
                            <x-badge-status status="acesso_ativo" label="Acesso Ativo" />
                        @elseif($cliente->user_id)
                            <x-badge-status status="inativo" label="Acesso Inativo" />
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
        @if(method_exists($clientes, 'hasPages') && $clientes->hasPages())
            <div class="p-4 border-t border-border">
                {{ $clientes->links() }}
            </div>
        @endif
    </x-card>
</x-layouts.app>