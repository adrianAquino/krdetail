<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\AgendamentoServico;
use App\Models\Funcionario;
use App\Models\LancamentoFinanceiro;
use App\Models\OrdemServico;
use App\Models\Tarefa;
use App\Models\TarefaProduto;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RelatorioController extends Controller
{
    public function index(Request $request)
    {
        $periodo = $request->input('periodo', 'mes');

        [$inicioPeriodo, $fimPeriodo, $inicioAnterior, $fimAnterior] = match ($periodo) {
            'hoje' => [
                Carbon::today()->startOfDay(),
                Carbon::today()->endOfDay(),
                Carbon::yesterday()->startOfDay(),
                Carbon::yesterday()->endOfDay(),
            ],
            'semana' => [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
                Carbon::now()->subWeek()->startOfWeek(),
                Carbon::now()->subWeek()->endOfWeek(),
            ],
            'ano' => [
                Carbon::now()->startOfYear(),
                Carbon::now()->endOfYear(),
                Carbon::now()->subYear()->startOfYear(),
                Carbon::now()->subYear()->endOfYear(),
            ],
            default => [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
                Carbon::now()->subMonthNoOverflow()->startOfMonth(),
                Carbon::now()->subMonthNoOverflow()->endOfMonth(),
            ],
        };

        // 1. Lançamentos Financeiros no Período Atual e Anterior
        $queryLancamentos = LancamentoFinanceiro::with('tarefa.agendamentoServico.servico')
            ->whereBetween('data_lancamento', [$inicioPeriodo->toDateString(), $fimPeriodo->toDateString()]);

        $lancamentosPeriodo = (clone $queryLancamentos)
            ->orderByDesc('data_lancamento')
            ->orderByDesc('id')
            ->get();

        // 1.1 Custos de Produtos/Insumos Consumidos nas Tarefas no Período (tabela tarefa_produto)
        $consumosTarefasPeriodo = TarefaProduto::with(['produto', 'tarefa.ordemServico', 'tarefa.agendamentoServico.servico'])
            ->whereBetween('created_at', [$inicioPeriodo, $fimPeriodo])
            ->orderByDesc('created_at')
            ->get();

        $custoInsumosTarefas = (float) $consumosTarefasPeriodo->sum(fn ($tp) => $tp->custo_total);

        $receitaTotal = (float) $lancamentosPeriodo->where('tipo', 'receita')->sum('valor');
        $despesasLancadas = (float) $lancamentosPeriodo->where('tipo', 'despesa')->sum('valor');
        $despesaTotal = $despesasLancadas + $custoInsumosTarefas;
        $saldoLiquido = $receitaTotal - $despesaTotal;

        $receitaAnterior = (float) LancamentoFinanceiro::where('tipo', 'receita')
            ->whereBetween('data_lancamento', [$inicioAnterior->toDateString(), $fimAnterior->toDateString()])
            ->sum('valor');

        if ($receitaAnterior > 0) {
            $variacaoReceitaPercent = round((($receitaTotal - $receitaAnterior) / $receitaAnterior) * 100, 1);
        } else {
            $variacaoReceitaPercent = $receitaTotal > 0 ? 100.0 : 0.0;
        }

        $margemPercent = $receitaTotal > 0
            ? round(($saldoLiquido / $receitaTotal) * 100, 1)
            : 0.0;

        // 2. Ordens de Serviço e Ticket Médio no Período
        $totalOS = OrdemServico::whereBetween('created_at', [$inicioPeriodo, $fimPeriodo])->count();
        $ticketMedio = $totalOS > 0 ? ($receitaTotal / $totalOS) : 0.0;

        // 3. Receitas por Serviço (Top 4)
        $receitasPorServico = $lancamentosPeriodo
            ->where('tipo', 'receita')
            ->groupBy(function ($lancamento) {
                return $lancamento->tarefa?->agendamentoServico?->servico?->nome
                    ?? $lancamento->categoria
                    ?? 'Outros Serviços';
            })
            ->map(function ($grupo, $nomeServico) use ($receitaTotal) {
                $valor = (float) $grupo->sum('valor');
                $percent = $receitaTotal > 0 ? round(($valor / $receitaTotal) * 100, 1) : 0.0;

                return [
                    'nome' => $nomeServico,
                    'valor' => $valor,
                    'percent' => $percent,
                ];
            })
            ->sortByDesc('valor')
            ->take(4)
            ->values();

        // 4. Despesas por Categoria (Unificando Despesas Lançadas + Insumos Consumidos nas Tarefas)
        $despesasCollection = $lancamentosPeriodo->where('tipo', 'despesa');

        // Agrupa insumos consumidos por produto para detalhamento nas categorias de despesa
        $itensDespesaUnificados = collect();

        foreach ($despesasCollection as $item) {
            $itensDespesaUnificados->push([
                'categoria' => $item->categoria ?: 'Outras Despesas',
                'valor' => (float) $item->valor,
            ]);
        }

        foreach ($consumosTarefasPeriodo as $tp) {
            $nomeProduto = $tp->produto?->nome ?? 'Insumo';
            $itensDespesaUnificados->push([
                'categoria' => "Insumo: {$nomeProduto}",
                'valor' => $tp->custo_total,
            ]);
        }

        $despesasPorCategoria = $itensDespesaUnificados
            ->groupBy('categoria')
            ->map(function ($grupo, $categoria) use ($despesaTotal) {
                $valor = (float) $grupo->sum('valor');
                $percent = $despesaTotal > 0 ? round(($valor / $despesaTotal) * 100, 1) : 0.0;

                return [
                    'categoria' => $categoria,
                    'valor' => $valor,
                    'percent' => $percent,
                ];
            })
            ->sortByDesc('valor')
            ->take(4)
            ->values();

        $despesaInsumosLancadas = (float) $despesasCollection->filter(function ($item) {
            $cat = mb_strtolower((string) $item->categoria);
            return str_contains($cat, 'insumo') || str_contains($cat, 'estoque') || str_contains($cat, 'produto');
        })->sum('valor');

        $despesaInsumos = $custoInsumosTarefas + $despesaInsumosLancadas;
        $despesaAdministrativa = max(0.0, $despesaTotal - $despesaInsumos);

        // 5. Extrato de Lançamentos Financeiros (Receitas + Despesas Manuais + Consumo de Insumos em Tarefas)
        $extratoLancamentos = $lancamentosPeriodo->map(function ($item) {
            return [
                'timestamp' => Carbon::parse($item->data_lancamento)->endOfDay()->timestamp,
                'data' => Carbon::parse($item->data_lancamento)->format('d/m/Y'),
                'descricao' => $item->descricao,
                'categoria' => $item->categoria,
                'tipo' => $item->tipo,
                'valor' => (float) $item->valor,
            ];
        });

        $extratoConsumos = $consumosTarefasPeriodo->map(function ($tp) {
            $produtoNome = $tp->produto?->nome ?? 'Produto';
            $unidade = $tp->produto?->unidade_medida ?? 'un';
            $qtdFormatada = rtrim(rtrim(number_format((float) $tp->quantidade_utilizada, 3, ',', '.'), '0'), ',');
            $numeroOS = $tp->tarefa?->ordemServico?->numero ?? ('Tarefa #' . $tp->tarefa_id);
            $servicoNome = $tp->tarefa?->agendamentoServico?->servico?->nome;
            $complemento = $servicoNome ? "{$servicoNome} ({$numeroOS})" : $numeroOS;

            return [
                'timestamp' => $tp->created_at ? $tp->created_at->timestamp : now()->timestamp,
                'data' => $tp->created_at ? $tp->created_at->format('d/m/Y') : now()->format('d/m/Y'),
                'descricao' => "Consumo de Insumo: {$produtoNome} ({$qtdFormatada} {$unidade}) — {$complemento}",
                'categoria' => 'Insumos (Tarefas)',
                'tipo' => 'despesa',
                'valor' => $tp->custo_total,
            ];
        });

        $lancamentos = $extratoLancamentos
            ->concat($extratoConsumos)
            ->sortByDesc('timestamp')
            ->take(25)
            ->values();

        // 6. Métricas Operacionais no Período
        $agendamentosPeriodoQuery = Agendamento::whereBetween('data_inicio', [$inicioPeriodo, $fimPeriodo]);
        $totalAgendamentos = (clone $agendamentosPeriodoQuery)->count();
        $agendamentosConcluidos = (clone $agendamentosPeriodoQuery)->where('status', 'concluido')->count();
        $agendamentosCancelados = (clone $agendamentosPeriodoQuery)->where('status', 'cancelado')->count();

        $taxaConclusao = $totalAgendamentos > 0
            ? round(($agendamentosConcluidos / $totalAgendamentos) * 100, 1)
            : 0.0;

        $tarefasConcluidasPeriodo = Tarefa::with('agendamentoServico.servico')
            ->where('status', 'concluida')
            ->whereBetween('data_conclusao', [$inicioPeriodo, $fimPeriodo])
            ->get();

        $tarefasExecutadas = $tarefasConcluidasPeriodo->count();
        $mediaTarefasPorOs = $totalOS > 0 ? round($tarefasExecutadas / $totalOS, 1) : 0.0;

        // Tempo médio real de atendimento das tarefas concluídas
        if ($tarefasExecutadas > 0) {
            $somaMinutos = $tarefasConcluidasPeriodo->sum(function ($tarefa) {
                if ($tarefa->data_inicio && $tarefa->data_conclusao) {
                    $diff = (int) $tarefa->data_inicio->diffInMinutes($tarefa->data_conclusao);
                    if ($diff > 0) {
                        return $diff;
                    }
                }

                return (int) ($tarefa->agendamentoServico?->tempo_estimado_minutos
                    ?? $tarefa->agendamentoServico?->servico?->tempo_estimado_minutos
                    ?? 0);
            });

            $mediaMinutos = (int) round($somaMinutos / $tarefasExecutadas);
            $horasMed = intdiv($mediaMinutos, 60);
            $minMed = $mediaMinutos % 60;
            $tempoMedioFormatado = $horasMed > 0 ? "{$horasMed}h {$minMed}m" : "{$minMed}m";
        } else {
            $tempoMedioFormatado = '0m';
        }

        // 7. Ranking de Serviços Mais Realizados no Período
        $itensServicosPeriodo = AgendamentoServico::with(['servico', 'agendamento'])
            ->whereHas('agendamento', function ($q) use ($inicioPeriodo, $fimPeriodo) {
                $q->whereBetween('data_inicio', [$inicioPeriodo, $fimPeriodo])
                    ->where('status', '!=', 'cancelado');
            })
            ->get();

        $servicosRanking = $itensServicosPeriodo
            ->groupBy('servico_id')
            ->map(function ($grupo) {
                $servico = $grupo->first()?->servico;
                $execucoes = $grupo->count();
                $minutosTotais = (int) $grupo->sum(fn ($i) => $i->tempo_estimado_minutos ?? $servico?->tempo_estimado_minutos ?? 0);
                $horas = intdiv($minutosTotais, 60);
                $minutos = $minutosTotais % 60;
                $tempoStr = $horas > 0
                    ? ($minutos > 0 ? "{$horas}h {$minutos}m" : "{$horas}h")
                    : "{$minutos}m";

                return [
                    'nome' => $servico?->nome ?? 'Serviço',
                    'execucoes' => $execucoes,
                    'tempo_total_formatado' => $tempoStr,
                    'valor_gerado' => (float) $grupo->sum('valor_servico'),
                ];
            })
            ->sortByDesc('execucoes')
            ->take(5)
            ->values();

        $maxExecucoesServico = max((int) $servicosRanking->max('execucoes'), 1);

        // 8. Produtividade da Equipe no Período
        $funcionarios = Funcionario::with('user')
            ->where('ativo', true)
            ->get()
            ->map(function ($func) use ($inicioPeriodo, $fimPeriodo) {
                $tarefasAtribuidas = Tarefa::where('funcionario_id', $func->id)
                    ->where(function ($q) use ($inicioPeriodo, $fimPeriodo) {
                        $q->whereBetween('created_at', [$inicioPeriodo, $fimPeriodo])
                            ->orWhereBetween('data_conclusao', [$inicioPeriodo, $fimPeriodo]);
                    })
                    ->get();

                $totalAtribuidas = $tarefasAtribuidas->count();
                $concluidas = $tarefasAtribuidas->where('status', 'concluida')->count();
                $taxa = $totalAtribuidas > 0 ? (int) round(($concluidas / $totalAtribuidas) * 100) : 0;

                $nome = $func->user?->name ?? 'Colaborador';
                $partes = preg_split('/\s+/', trim($nome)) ?: [];
                $iniciais = strtoupper(
                    substr($partes[0] ?? 'C', 0, 1) .
                    substr($partes[1] ?? ($partes[0] ?? 'B'), isset($partes[1]) ? 0 : 1, 1)
                );

                return [
                    'nome' => $nome,
                    'iniciais' => $iniciais,
                    'cargo' => $func->cargo ?: 'Técnico em Estética Automotiva',
                    'tarefas_concluidas' => $concluidas,
                    'tarefas_atribuidas' => $totalAtribuidas,
                    'taxa_conclusao' => $taxa,
                ];
            })
            ->sortByDesc('tarefas_concluidas')
            ->values();

        $mediaTarefasEquipe = $funcionarios->count() > 0
            ? round($funcionarios->avg('tarefas_concluidas'), 1)
            : 0.0;

        $kpis = [
            'receita_total' => $receitaTotal,
            'variacao_receita_percent' => $variacaoReceitaPercent,
            'despesa_total' => $despesaTotal,
            'despesa_insumos' => $despesaInsumos,
            'despesa_administrativa' => $despesaAdministrativa,
            'saldo_liquido' => $saldoLiquido,
            'margem_percent' => $margemPercent,
            'ticket_medio' => $ticketMedio,
            'total_os' => $totalOS,
            'total_agendamentos' => $totalAgendamentos,
            'agendamentos_concluidos' => $agendamentosConcluidos,
            'agendamentos_cancelados' => $agendamentosCancelados,
            'taxa_conclusao' => $taxaConclusao,
            'tarefas_executadas' => $tarefasExecutadas,
            'media_tarefas_por_os' => $mediaTarefasPorOs,
            'tempo_medio_formatado' => $tempoMedioFormatado,
            'media_tarefas_equipe' => $mediaTarefasEquipe,
        ];

        return view('relatorios.index', compact(
            'kpis',
            'lancamentos',
            'receitasPorServico',
            'despesasPorCategoria',
            'servicosRanking',
            'maxExecucoesServico',
            'funcionarios'
        ));
    }
}
