<x-layouts.app :title="'Veículo: ' . ($veiculo->placa ?? 'Detalhes')">
    <x-header
        :title="($veiculo->marca ?? 'BMW') . ' ' . ($veiculo->modelo ?? '320i M Sport')"
        :subtitle="'Placa: ' . ($veiculo->placa ?? 'BRA2E19')"
    >
        <x-slot:actions>
            <x-button href="{{ route('agendamentos.create', ['veiculo_id' => $veiculo->id ?? 1, 'cliente_id' => $veiculo->cliente_id ?? 1]) }}" variant="primary" size="sm" icon="calendar">
                Agendar Serviço
            </x-button>
            <x-button href="{{ route('veiculos.edit', $veiculo->id ?? 1) }}" variant="secondary" size="sm" icon="pencil">
                Editar
            </x-button>
            <x-button href="{{ route('veiculos.index') }}" variant="ghost" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

   
    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Coluna Esquerda: Especificações e Proprietário -->
        <div class="space-y-6">
            <!-- Placa e Dados Principais -->
            <x-card>
                <div class="p-4 rounded-xl bg-secondary/60 border border-border text-center mb-5">
                    <p class="text-xs text-muted-foreground uppercase tracking-widest font-semibold mb-1">Brasil - Mercosul</p>
                    <p class="font-mono text-3xl font-extrabold text-foreground tracking-widest">{{ $veiculo->placa }}</p>
                </div>

                <div class="space-y-3.5 text-sm">
                    <div class="flex items-center justify-between pb-2 border-b border-border/50">
                        <span class="text-xs text-muted-foreground">Marca</span>
                        <span class="font-semibold text-foreground">{{ $veiculo->marca }}</span>
                    </div>

                    <div class="flex items-center justify-between pb-2 border-b border-border/50">
                        <span class="text-xs text-muted-foreground">Modelo</span>
                        <span class="font-semibold text-foreground">{{ $veiculo->modelo }}</span>
                    </div>

                    <div class="flex items-center justify-between pb-2 border-b border-border/50">
                        <span class="text-xs text-muted-foreground">Ano</span>
                        <span class="font-semibold text-foreground">{{ $veiculo->ano ?? 'Não informado' }}</span>
                    </div>

                    <div class="flex items-center justify-between pb-2 border-b border-border/50">
                        <span class="text-xs text-muted-foreground">Cor / Acabamento</span>
                        <span class="font-semibold text-foreground">{{ $veiculo->cor ?? 'Não informada' }}</span>
                    </div>

                    @if(!empty($veiculo->observacoes))
                        <div class="pt-2">
                            <span class="text-xs text-muted-foreground font-semibold block mb-1">Observações Técnicas:</span>
                            <p class="text-xs text-muted-foreground italic bg-secondary/30 p-2.5 rounded-lg border border-border">
                                "{{ $veiculo->observacoes }}"
                            </p>
                        </div>
                    @endif
                </div>
            </x-card>

            <!-- Proprietário -->
            <x-card title="Proprietário">
                @if(isset($veiculo->cliente))
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-bold text-sm">
                            {{ strtoupper(substr($veiculo->cliente->nome, 0, 2)) }}
                        </div>
                        <div>
                            <a href="{{ route('clientes.show', $veiculo->cliente->id) }}" class="font-semibold text-sm text-foreground hover:text-primary transition-colors">
                                {{ $veiculo->cliente->nome }}
                            </a>
                            <p class="text-xs text-muted-foreground">{{ $veiculo->cliente->telefone }}</p>
                        </div>
                    </div>

                    <x-button href="{{ route('clientes.show', $veiculo->cliente->id) }}" variant="outline" size="sm" class="w-full" icon="user">
                        Ver Ficha do Cliente
                    </x-button>
                @else
                    <p class="text-xs text-muted-foreground">Nenhum cliente proprietário vinculado.</p>
                @endif
            </x-card>
        </div>

        <!-- Coluna Direita: Histórico de Manutenções e Intervenções -->
        <div class="lg:col-span-2">
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                                <x-icon name="clipboard-list" class="h-4 w-4" />
                            </div>
                            <h3 class="text-base font-semibold text-foreground">Histórico de Manutenções e Estética</h3>
                        </div>
                        <span class="text-xs text-muted-foreground">{{ count($historicoServicos) }} intervenção(ões)</span>
                    </div>
                </x-slot:header>

                <div class="space-y-4">
                    @forelse($historicoServicos as $hist)
                        <div class="rounded-xl border border-border bg-secondary/20 p-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-bold text-foreground bg-secondary px-2 py-0.5 rounded border border-border">
                                        {{ $hist->os_numero }}
                                    </span>
                                    <x-badge-status :status="$hist->status" />
                                    <span class="text-xs text-muted-foreground">
                                        {{ \Carbon\Carbon::parse($hist->data)->format('d/m/Y') }}
                                    </span>
                                </div>
                                <span class="text-sm font-bold text-primary">
                                    R$ {{ number_format($hist->valor_total, 2, ',', '.') }}
                                </span>
                            </div>

                            <p class="text-sm font-semibold text-foreground">{{ $hist->servicos_nomes }}</p>

                            @if(!empty($hist->observacoes))
                                <p class="text-xs text-muted-foreground mt-1 italic">
                                    {{ $hist->observacoes }}
                                </p>
                            @endif

                            <div class="flex justify-end pt-3 mt-3 border-t border-border/50">
                                <x-button href="{{ route('ordens-servico.show', $hist->os_id) }}" variant="ghost" size="sm" icon="eye">
                                    Ver Ordem de Serviço
                                </x-button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-muted-foreground text-sm">
                            Nenhum serviço registrado para este veículo até o momento.
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
</x-layouts.app>