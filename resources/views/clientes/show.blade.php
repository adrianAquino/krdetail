<x-layouts.app :title="$cliente->nome ?? 'Detalhes do Cliente'">
    <x-header
        :title="$cliente->nome ?? 'Lucas Guimarães'"
        subtitle="Ficha cadastral completa, veículos associados e histórico operacional."
    >
        <x-slot:actions>
            <x-button href="{{ route('veiculos.create', ['cliente_id' => $cliente->id ?? 1]) }}" variant="outline" size="sm" icon="plus">
                Adicionar Veículo
            </x-button>
            <x-button href="{{ route('agendamentos.create', ['cliente_id' => $cliente->id ?? 1]) }}" variant="primary" size="sm" icon="calendar">
                Novo Agendamento
            </x-button>
            <x-button href="{{ route('clientes.edit', $cliente->id ?? 1) }}" variant="secondary" size="sm" icon="pencil">
                Editar
            </x-button>
            <x-button href="{{ route('clientes.index') }}" variant="ghost" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>



    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Coluna Esquerda: Dados do Cliente e Conta -->
        <div class="space-y-6">
            <!-- Card Perfil -->
            <x-card>
                <div class="flex items-center gap-3.5 mb-5 pb-5 border-b border-border">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary font-bold text-xl">
                        {{ strtoupper(substr($cliente->nome, 0, 2)) }}
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-foreground">{{ $cliente->nome }}</h2>
                        <div class="mt-1">
                            @if($cliente->user_id)
                                <x-badge-status status="acesso_ativo" label="Acesso Ativo" />
                            @else
                                <x-badge-status status="sem_acesso" label="Sem Acesso" />
                            @endif
                        </div>
                    </div>
                </div>

                <div class="space-y-3.5 text-sm">
                    <div>
                        <span class="text-xs text-muted-foreground uppercase font-semibold">CPF / CNPJ</span>
                        <p class="font-medium text-foreground mt-0.5">{{ $cliente->cpf_cnpj ?? 'Não informado' }}</p>
                    </div>

                    <div>
                        <span class="text-xs text-muted-foreground uppercase font-semibold">Telefone / WhatsApp</span>
                        <p class="font-medium text-foreground mt-0.5 flex items-center gap-2">
                            <x-icon name="phone" class="w-4 h-4 text-primary" />
                            {{ $cliente->telefone }}
                        </p>
                    </div>

                    <div>
                        <span class="text-xs text-muted-foreground uppercase font-semibold">E-mail</span>
                        <p class="font-medium text-foreground mt-0.5 flex items-center gap-2">
                            <x-icon name="mail" class="w-4 h-4 text-primary" />
                            {{ $cliente->email ?? 'Não informado' }}
                        </p>
                    </div>

                    <div class="pt-3 border-t border-border">
                        <span class="text-xs text-muted-foreground uppercase font-semibold">Endereço</span>
                        <p class="font-medium text-foreground mt-0.5">
                            {{ $cliente->logradouro }}, {{ $cliente->numero }}
                            @if($cliente->complemento) - {{ $cliente->complemento }} @endif
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ $cliente->bairro }} • {{ $cliente->cidade }}/{{ $cliente->estado }}
                            @if($cliente->cep) • CEP {{ $cliente->cep }} @endif
                        </p>
                    </div>

                    <div class="pt-3 border-t border-border text-xs text-muted-foreground">
                        Cliente desde {{ \Carbon\Carbon::parse($cliente->created_at)->format('d/m/Y') }}
                    </div>
                </div>
            </x-card>

            <!-- Card Conta de Acesso -->
            <x-card title="Acesso ao Sistema" description="Permissões e login do cliente">
                @if($cliente->user_id)
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center gap-2 text-success font-medium">
                            <x-icon name="check-circle" class="w-4 h-4" />
                            <span>Conta de Usuário Ativa (#{{ $cliente->user_id }})</span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            O cliente pode visualizar o histórico de seus veículos e o status dos agendamentos através do login de cliente.
                        </p>
                    </div>
                @else
                    <div class="space-y-3 text-sm">
                        <p class="text-muted-foreground text-xs">
                            Este cliente ainda não possui uma conta de usuário vinculada.
                        </p>
                        <x-button href="{{ route('clientes.edit', $cliente->id) }}" variant="outline" size="sm" class="w-full">
                            Criar Conta de Acesso
                        </x-button>
                    </div>
                @endif
            </x-card>
        </div>

        <!-- Coluna Direita: Veículos e Histórico Operacional -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Veículos do Cliente -->
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 rounded-lg bg-chart-2/10 text-chart-2">
                                <x-icon name="car" class="h-4 w-4" />
                            </div>
                            <h3 class="text-base font-semibold text-foreground">Veículos do Cliente</h3>
                        </div>
                        <x-button href="{{ route('veiculos.create', ['cliente_id' => $cliente->id]) }}" variant="ghost" size="sm" icon="plus">
                            Adicionar Veículo
                        </x-button>
                    </div>
                </x-slot:header>

                <div class="grid gap-3 sm:grid-cols-2">
                    @forelse($cliente->veiculos as $veiculo)
                        <div class="rounded-xl border border-border bg-secondary/20 p-4 hover:border-primary/50 transition-colors">
                            <div class="flex items-start justify-between mb-2">
                                <div>
                                    <h4 class="font-bold text-foreground text-base">{{ $veiculo->marca }} {{ $veiculo->modelo }}</h4>
                                    <p class="text-xs text-muted-foreground">{{ $veiculo->cor }} • Ano {{ $veiculo->ano ?? 'N/D' }}</p>
                                </div>
                                <span class="font-mono text-xs font-bold px-2 py-1 rounded bg-secondary text-primary border border-border">
                                    {{ $veiculo->placa }}
                                </span>
                            </div>

                            @if(!empty($veiculo->observacoes))
                                <p class="text-xs text-muted-foreground italic mb-3 line-clamp-2">
                                    "{{ $veiculo->observacoes }}"
                                </p>
                            @endif

                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-border/50">
                                <x-button href="{{ route('veiculos.show', $veiculo->id) }}" variant="ghost" size="sm" icon="eye">
                                    Ficha
                                </x-button>
                                <x-button href="{{ route('veiculos.edit', $veiculo->id) }}" variant="ghost" size="sm" icon="pencil">
                                    Editar
                                </x-button>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-2 text-center py-6 text-muted-foreground text-sm">
                            Nenhum veículo registrado para este cliente.
                        </div>
                    @endforelse
                </div>
            </x-card>

            <!-- Histórico de Serviços e Agendamentos -->
            <x-card>
                <x-slot:header>
                    <div class="flex items-center gap-2">
                        <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                            <x-icon name="calendar" class="h-4 w-4" />
                        </div>
                        <h3 class="text-base font-semibold text-foreground">Histórico Operacional</h3>
                    </div>
                </x-slot:header>

                <div class="space-y-3">
                    @forelse($cliente->agendamentos as $hist)
                        <div class="p-4 rounded-xl border border-border bg-secondary/20 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-bold text-foreground px-2 py-0.5 rounded bg-secondary border border-border">
                                        {{ $hist->ordem_servico->numero ?? 'Agendamento' }}
                                    </span>
                                    <x-badge-status :status="$hist->status" />
                                    <span class="text-xs text-muted-foreground">
                                        {{ \Carbon\Carbon::parse($hist->data_inicio)->format('d/m/Y H:i') }}
                                    </span>
                                </div>
                                <p class="text-sm font-semibold text-foreground mt-1.5">{{ $hist->servicos_nomes }}</p>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    Veículo: {{ $hist->veiculo->marca }} {{ $hist->veiculo->modelo }} ({{ $hist->veiculo->placa }})
                                </p>
                            </div>

                            <div class="text-right sm:shrink-0">
                                <p class="text-sm font-bold text-primary">R$ {{ number_format($hist->valor_total, 2, ',', '.') }}</p>
                                @if(isset($hist->ordem_servico))
                                    <x-button href="{{ route('ordens-servico.show', $hist->ordem_servico->id) }}" variant="ghost" size="sm" class="mt-1" icon="eye">
                                        Ver OS
                                    </x-button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-muted-foreground text-sm">
                            Nenhum serviço realizado até o momento.
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
</x-layouts.app>