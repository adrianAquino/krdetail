@extends('layouts.app')

@section('title', 'Relatórios & Desempenho')

@section('content')
<div class="space-y-6">
    <!-- Header com Filtro de Período -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <x-icon name="trending-up" class="w-7 h-7 text-primary" />
                Relatórios & Desempenho
            </h1>
            <p class="text-sm text-muted-foreground mt-1">
                Acompanhe as métricas financeiras de receitas e despesas e o desempenho operacional da oficina.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- Seletor de Período Rápido -->
            <div class="inline-flex rounded-lg bg-secondary/50 p-1 border border-border">
                <a href="?periodo=hoje" class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors {{ request('periodo') === 'hoje' ? 'bg-primary text-primary-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                    Hoje
                </a>
                <a href="?periodo=semana" class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors {{ request('periodo') === 'semana' ? 'bg-primary text-primary-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                    Esta Semana
                </a>
                <a href="?periodo=mes" class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors {{ request('periodo', 'mes') === 'mes' ? 'bg-primary text-primary-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                    Este Mês
                </a>
                <a href="?periodo=ano" class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors {{ request('periodo') === 'ano' ? 'bg-primary text-primary-foreground font-semibold shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                    Este Ano
                </a>
            </div>

            <!-- Botão Exportar -->
            <x-button variant="outline" size="sm" onclick="window.print()" class="gap-1.5">
                <x-icon name="printer" class="w-4 h-4" />
                <span>Imprimir / PDF</span>
            </x-button>
        </div>
    </div>

    <!-- Navegação de Abas (Tabs) -->
    <div class="border-b border-border">
        <nav class="flex space-x-6" aria-label="Abas de Relatório">
            <button type="button" 
                    id="tab-financeiro-btn" 
                    onclick="switchTab('financeiro')" 
                    class="tab-button active py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 border-primary text-primary transition-colors">
                <x-icon name="dollar-sign" class="w-4 h-4" />
                Visão Financeira (Receitas & Despesas)
            </button>
            <button type="button" 
                    id="tab-operacional-btn" 
                    onclick="switchTab('operacional')" 
                    class="tab-button py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 border-transparent text-muted-foreground hover:text-foreground hover:border-border transition-colors">
                <x-icon name="activity" class="w-4 h-4" />
                Desempenho Operacional & Equipe
            </button>
        </nav>
    </div>

    <!-- CONTEÚDO DA ABA FINANCEIRA -->
    <div id="tab-financeiro" class="tab-content space-y-6">
        <!-- Banner Informativo da Regra de Negócio -->
        <div class="p-4 rounded-lg bg-primary/5 border border-primary/20 flex items-start gap-3">
            <x-icon name="info" class="w-5 h-5 text-primary shrink-0 mt-0.5" />
            <div class="text-xs text-muted-foreground space-y-0.5">
                <strong class="text-foreground font-semibold">Regra de Lançamentos Financeiros:</strong>
                <p>As receitas são lançadas automaticamente no sistema assim que cada tarefa de uma Ordem de Serviço é concluída pelo colaborador. Custos de produtos utilizados e despesas operacionais avulsas compõem o saldo líquido.</p>
            </div>
        </div>

        <!-- KPIs Financeiros -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-card class="border-l-4 border-l-emerald-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Receita Total</p>
                        <p class="text-2xl font-bold text-foreground mt-1">R$ {{ number_format($kpis['receita_total'] ?? 18450.00, 2, ',', '.') }}</p>
                        <p class="text-xs text-emerald-500 flex items-center gap-1 mt-1 font-medium">
                            <x-icon name="trending-up" class="w-3.5 h-3.5" />
                            <span>+14.2% vs período ant.</span>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500">
                        <x-icon name="arrow-up-right" class="w-6 h-6" />
                    </div>
                </div>
            </x-card>

            <x-card class="border-l-4 border-l-rose-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Despesas Totais</p>
                        <p class="text-2xl font-bold text-foreground mt-1">R$ {{ number_format($kpis['despesa_total'] ?? 4320.50, 2, ',', '.') }}</p>
                        <p class="text-xs text-muted-foreground flex items-center gap-1 mt-1">
                            <span>Insumos & Custos fixos</span>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-500">
                        <x-icon name="arrow-down-right" class="w-6 h-6" />
                    </div>
                </div>
            </x-card>

            <x-card class="border-l-4 border-l-primary">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Saldo Líquido</p>
                        <p class="text-2xl font-bold text-primary mt-1">R$ {{ number_format($kpis['saldo_liquido'] ?? 14129.50, 2, ',', '.') }}</p>
                        <p class="text-xs text-emerald-500 flex items-center gap-1 mt-1 font-medium">
                            <span>Margem estimada: 76.5%</span>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary">
                        <x-icon name="dollar-sign" class="w-6 h-6" />
                    </div>
                </div>
            </x-card>

            <x-card class="border-l-4 border-l-sky-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Ticket Médio por OS</p>
                        <p class="text-2xl font-bold text-foreground mt-1">R$ {{ number_format($kpis['ticket_medio'] ?? 461.25, 2, ',', '.') }}</p>
                        <p class="text-xs text-muted-foreground flex items-center gap-1 mt-1">
                            <span>Com base em 40 ordens</span>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-500">
                        <x-icon name="file-text" class="w-6 h-6" />
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Distribuição por Categoria e Gráficos de Proporção -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Categorias de Receita -->
            <x-card>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-foreground text-sm flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Receitas por Serviço
                    </h3>
                    <span class="text-xs text-muted-foreground">Top 4</span>
                </div>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-foreground font-medium">Vitrificação Cerâmica</span>
                            <span class="text-muted-foreground">R$ 8.200 (44%)</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-secondary overflow-hidden">
                            <div class="h-full rounded-full bg-emerald-500" style="width: 44%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-foreground font-medium">Polimento Técnico</span>
                            <span class="text-muted-foreground">R$ 5.100 (28%)</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-secondary overflow-hidden">
                            <div class="h-full rounded-full bg-emerald-500/80" style="width: 28%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-foreground font-medium">Higienização Interna Completa</span>
                            <span class="text-muted-foreground">R$ 3.250 (18%)</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-secondary overflow-hidden">
                            <div class="h-full rounded-full bg-emerald-500/60" style="width: 18%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-foreground font-medium">Lavagem Detalhada</span>
                            <span class="text-muted-foreground">R$ 1.900 (10%)</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-secondary overflow-hidden">
                            <div class="h-full rounded-full bg-emerald-500/40" style="width: 10%"></div>
                        </div>
                    </div>
                </div>
            </x-card>

            <!-- Categorias de Despesas -->
            <x-card>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-foreground text-sm flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                        Despesas por Categoria
                    </h3>
                    <span class="text-xs text-muted-foreground">Total: R$ 4.320</span>
                </div>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-foreground font-medium">Reposição de Insumos & Produtos</span>
                            <span class="text-muted-foreground">R$ 2.450 (57%)</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-secondary overflow-hidden">
                            <div class="h-full rounded-full bg-rose-500" style="width: 57%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-foreground font-medium">Equipamentos & Manutenção</span>
                            <span class="text-muted-foreground">R$ 980 (23%)</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-secondary overflow-hidden">
                            <div class="h-full rounded-full bg-rose-500/80" style="width: 23%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-foreground font-medium">Utilidades (Água / Luz)</span>
                            <span class="text-muted-foreground">R$ 650 (15%)</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-secondary overflow-hidden">
                            <div class="h-full rounded-full bg-rose-500/60" style="width: 15%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-foreground font-medium">Outros Custos Operacionais</span>
                            <span class="text-muted-foreground">R$ 240.50 (5%)</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-secondary overflow-hidden">
                            <div class="h-full rounded-full bg-rose-500/40" style="width: 5%"></div>
                        </div>
                    </div>
                </div>
            </x-card>

            <!-- Resumo de Equilíbrio Operacional -->
            <x-card class="flex flex-col justify-between">
                <div>
                    <h3 class="font-semibold text-foreground text-sm mb-2 flex items-center gap-2">
                        <x-icon name="pie-chart" class="w-4 h-4 text-primary" />
                        Equilíbrio Financeiro
                    </h3>
                    <p class="text-xs text-muted-foreground mb-4">
                        Proporção entre faturamento bruto e custos diretos no período selecionado.
                    </p>

                    <div class="p-4 rounded-lg bg-secondary/40 border border-border space-y-3">
                        <div class="flex justify-between text-xs">
                            <span class="text-muted-foreground">Faturamento Bruto:</span>
                            <span class="font-semibold text-foreground">R$ 18.450,00</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-muted-foreground">(-) Custos de Insumos:</span>
                            <span class="font-medium text-rose-400">- R$ 2.450,00</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-muted-foreground">(-) Despesas Administrativas:</span>
                            <span class="font-medium text-rose-400">- R$ 1.870,50</span>
                        </div>
                        <div class="border-t border-border pt-2 flex justify-between text-xs">
                            <span class="font-bold text-foreground">Resultado Líquido:</span>
                            <span class="font-bold text-emerald-400">R$ 14.129,50</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-border flex items-center justify-between text-xs">
                    <span class="text-muted-foreground">Rentabilidade Operacional:</span>
                    <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 font-semibold border border-emerald-500/20">
                        Excelente (76.5%)
                    </span>
                </div>
            </x-card>
        </div>

        <!-- Tabela de Lançamentos Recentes -->
        <x-card>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-semibold text-foreground text-sm flex items-center gap-2">
                        <x-icon name="list" class="w-4 h-4 text-primary" />
                        Lançamentos Financeiros do Período
                    </h3>
                    <p class="text-xs text-muted-foreground">Extrato detalhado de receitas geradas por tarefas e despesas lançadas.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-secondary/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider border-b border-border">
                        <tr>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Descrição / Origem</th>
                            <th class="px-4 py-3">Categoria</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-xs">
                        @php
                            $lancamentosExemplo = $lancamentos ?? [
                                ['data' => '11/09/2026 10:40', 'descricao' => 'Tarefa Concluída: Vitrificação Cerâmica (#OS-00042)', 'categoria' => 'Serviços Prestados', 'tipo' => 'receita', 'valor' => 1200.00],
                                ['data' => '11/09/2026 09:15', 'descricao' => 'Compra de Microfibras & Aplicadores (10 un)', 'categoria' => 'Insumos', 'tipo' => 'despesa', 'valor' => 180.00],
                                ['data' => '10/09/2026 16:30', 'descricao' => 'Tarefa Concluída: Polimento Técnico (#OS-00041)', 'categoria' => 'Serviços Prestados', 'tipo' => 'receita', 'valor' => 650.00],
                                ['data' => '10/09/2026 14:00', 'descricao' => 'Tarefa Concluída: Higienização Interna (#OS-00040)', 'categoria' => 'Serviços Prestados', 'tipo' => 'receita', 'valor' => 380.00],
                                ['data' => '09/09/2026 11:20', 'descricao' => 'Reabastecimento de Composto Polidor Corte 1L', 'categoria' => 'Insumos', 'tipo' => 'despesa', 'valor' => 240.00],
                                ['data' => '08/09/2026 17:00', 'descricao' => 'Tarefa Concluída: Lavagem Detalhada (#OS-00039)', 'categoria' => 'Serviços Prestados', 'tipo' => 'receita', 'valor' => 180.00],
                            ];
                        @endphp

                        @foreach($lancamentosExemplo as $item)
                        <tr class="hover:bg-secondary/20 transition-colors">
                            <td class="px-4 py-3 font-mono text-muted-foreground whitespace-nowrap">{{ $item['data'] }}</td>
                            <td class="px-4 py-3 font-medium text-foreground">{{ $item['descricao'] }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ $item['categoria'] }}</td>
                            <td class="px-4 py-3">
                                @if($item['tipo'] === 'receita')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <x-icon name="arrow-up" class="w-3 h-3" />
                                        Receita
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        <x-icon name="arrow-down" class="w-3 h-3" />
                                        Despesa
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-semibold {{ $item['tipo'] === 'receita' ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $item['tipo'] === 'receita' ? '+' : '-' }} R$ {{ number_format($item['valor'], 2, ',', '.') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    <!-- CONTEÚDO DA ABA OPERACIONAL -->
    <div id="tab-operacional" class="tab-content space-y-6 hidden">
        <!-- KPIs Operacionais -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-card class="border-l-4 border-l-primary">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Agendamentos Totais</p>
                        <p class="text-2xl font-bold text-foreground mt-1">{{ $kpis['total_agendamentos'] ?? 42 }}</p>
                        <p class="text-xs text-muted-foreground mt-1">
                            <span>No período selecionado</span>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary">
                        <x-icon name="calendar" class="w-6 h-6" />
                    </div>
                </div>
            </x-card>

            <x-card class="border-l-4 border-l-emerald-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Taxa de Conclusão</p>
                        <p class="text-2xl font-bold text-emerald-400 mt-1">95.2%</p>
                        <p class="text-xs text-muted-foreground mt-1">
                            <span>40 concluídos / 2 cancelados</span>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500">
                        <x-icon name="check-circle" class="w-6 h-6" />
                    </div>
                </div>
            </x-card>

            <x-card class="border-l-4 border-l-amber-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Tarefas Executadas</p>
                        <p class="text-2xl font-bold text-foreground mt-1">78</p>
                        <p class="text-xs text-muted-foreground mt-1">
                            <span>Média de 1.9 tarefas / OS</span>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500">
                        <x-icon name="check-square" class="w-6 h-6" />
                    </div>
                </div>
            </x-card>

            <x-card class="border-l-4 border-l-indigo-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Tempo Médio de Atendimento</p>
                        <p class="text-2xl font-bold text-foreground mt-1">3h 45m</p>
                        <p class="text-xs text-emerald-400 mt-1">
                            <span>Dentro do prazo previsto</span>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-500">
                        <x-icon name="clock" class="w-6 h-6" />
                    </div>
                </div>
            </x-card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Ranking de Serviços Mais Solicitados -->
            <x-card>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-foreground text-sm flex items-center gap-2">
                            <x-icon name="award" class="w-4 h-4 text-primary" />
                            Serviços Mais Realizados
                        </h3>
                        <p class="text-xs text-muted-foreground">Volume de execuções e tempo total investido na oficina.</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-primary/20 text-primary font-bold flex items-center justify-center text-[10px]">1</span>
                                <span class="font-semibold text-foreground">Polimento Técnico Comercial</span>
                            </div>
                            <span class="font-bold text-foreground">18 execuções</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-secondary overflow-hidden">
                            <div class="h-full rounded-full bg-primary" style="width: 85%"></div>
                        </div>
                        <div class="flex justify-between text-[11px] text-muted-foreground mt-1">
                            <span>Tempo estimado total: 72h</span>
                            <span class="text-emerald-400">R$ 9.900 gerados</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-primary/20 text-primary font-bold flex items-center justify-center text-[10px]">2</span>
                                <span class="font-semibold text-foreground">Higienização e Oxi-Sanitização Interna</span>
                            </div>
                            <span class="font-bold text-foreground">14 execuções</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-secondary overflow-hidden">
                            <div class="h-full rounded-full bg-primary/80" style="width: 65%"></div>
                        </div>
                        <div class="flex justify-between text-[11px] text-muted-foreground mt-1">
                            <span>Tempo estimado total: 35h</span>
                            <span class="text-emerald-400">R$ 4.900 gerados</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-primary/20 text-primary font-bold flex items-center justify-center text-[10px]">3</span>
                                <span class="font-semibold text-foreground">Lavagem Detalhada Chassi & Motor</span>
                            </div>
                            <span class="font-bold text-foreground">11 execuções</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-secondary overflow-hidden">
                            <div class="h-full rounded-full bg-primary/60" style="width: 50%"></div>
                        </div>
                        <div class="flex justify-between text-[11px] text-muted-foreground mt-1">
                            <span>Tempo estimado total: 16h 30m</span>
                            <span class="text-emerald-400">R$ 2.420 gerados</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-primary/20 text-primary font-bold flex items-center justify-center text-[10px]">4</span>
                                <span class="font-semibold text-foreground">Vitrificação de Pintura Cerâmica 9H</span>
                            </div>
                            <span class="font-bold text-foreground">7 execuções</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-secondary overflow-hidden">
                            <div class="h-full rounded-full bg-primary/40" style="width: 32%"></div>
                        </div>
                        <div class="flex justify-between text-[11px] text-muted-foreground mt-1">
                            <span>Tempo estimado total: 35h</span>
                            <span class="text-emerald-400">R$ 8.400 gerados</span>
                        </div>
                    </div>
                </div>
            </x-card>

            <!-- Produtividade da Equipe de Detalhamento -->
            <x-card>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-foreground text-sm flex items-center gap-2">
                            <x-icon name="users" class="w-4 h-4 text-primary" />
                            Produtividade da Equipe
                        </h3>
                        <p class="text-xs text-muted-foreground">Tarefas finalizadas e volume de trabalho por colaborador.</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 rounded-lg bg-secondary/30 border border-border">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold text-sm border border-emerald-500/20">
                                CA
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-foreground">Carlos Andrade</p>
                                <p class="text-xs text-muted-foreground">Especialista em Polimento & Vitrificação</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-foreground">32 tarefas</p>
                            <span class="text-[11px] text-emerald-400 font-medium">98% pontualidade</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-lg bg-secondary/30 border border-border">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-sky-500/10 text-sky-400 flex items-center justify-center font-bold text-sm border border-sky-500/20">
                                LS
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-foreground">Lucas Silva</p>
                                <p class="text-xs text-muted-foreground">Técnico em Higienização Interna</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-foreground">27 tarefas</p>
                            <span class="text-[11px] text-emerald-400 font-medium">96% pontualidade</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-lg bg-secondary/30 border border-border">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-purple-500/10 text-purple-400 flex items-center justify-center font-bold text-sm border border-purple-500/20">
                                MR
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-foreground">Matheus Ramos</p>
                                <p class="text-xs text-muted-foreground">Auxiliar de Estética & Lavagem Detalhada</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-foreground">19 tarefas</p>
                            <span class="text-[11px] text-emerald-400 font-medium">100% pontualidade</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-border flex items-center justify-between text-xs text-muted-foreground">
                    <span>Média da equipe no período:</span>
                    <span class="font-semibold text-foreground">26 tarefas por colaborador</span>
                </div>
            </x-card>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    const finTab = document.getElementById('tab-financeiro');
    const opTab = document.getElementById('tab-operacional');
    const finBtn = document.getElementById('tab-financeiro-btn');
    const opBtn = document.getElementById('tab-operacional-btn');

    if (tab === 'financeiro') {
        finTab.classList.remove('hidden');
        opTab.classList.add('hidden');
        
        finBtn.classList.add('border-primary', 'text-primary');
        finBtn.classList.remove('border-transparent', 'text-muted-foreground');
        
        opBtn.classList.remove('border-primary', 'text-primary');
        opBtn.classList.add('border-transparent', 'text-muted-foreground');
    } else {
        opTab.classList.remove('hidden');
        finTab.classList.add('hidden');
        
        opBtn.classList.add('border-primary', 'text-primary');
        opBtn.classList.remove('border-transparent', 'text-muted-foreground');
        
        finBtn.classList.remove('border-primary', 'text-primary');
        finBtn.classList.add('border-transparent', 'text-muted-foreground');
    }
}
</script>
@endsection