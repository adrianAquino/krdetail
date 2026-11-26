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
use Illuminate\View\View;

class RelatorioController extends Controller
{
    public function index(Request $request): View
    {
        $filtroAdmin = $this->resolverFiltroEmpresaAdmin($request);

        $periodoRaw = $this->inputString($request, 'periodo');
        $periodo = in_array($periodoRaw, ['hoje', 'semana', 'mes', 'ano'], true) ? $periodoRaw : 'mes';
        $agora = Carbon::now();

        [$inicioPeriodo, $fimPeriodo, $inicioAnterior, $fimAnterior] = match ($periodo) {
            'hoje' => [
                $agora->copy()->startOfDay(),
                $agora->copy()->endOfDay(),
                $agora->copy()->subDay()->startOfDay(),
                $agora->copy()->subDay()->endOfDay(),
            ],
            'semana' => [
                $agora->copy()->startOfWeek(),
                $agora->copy()->endOfWeek(),
                $agora->copy()->subWeek()->startOfWeek(),
                $agora->copy()->subWeek()->endOfWeek(),
            ],
            'ano' => [
                $agora->copy()->startOfYear(),
                $agora->copy()->endOfYear(),
                $agora->copy()->subYear()->startOfYear(),
                $agora->copy()->subYear()->endOfYear(),
            ],
            default => [
                $agora->copy()->startOfMonth(),
                $agora->copy()->endOfMonth(),
                $agora->copy()->subMonthNoOverflow()->startOfMonth(),
                $agora->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
        };

        // 1. Lançamentos Financeiros no Período Atual e Anterior
        $lancamentosPeriodo = LancamentoFinanceiro::with(['empresa', 'tarefa.agendamentoServico.servico', 'tarefa.ordemServico'])
            ->whereBetween('data_lancamento', [$inicioPeriodo->toDateString(), $fimPeriodo->toDateString()])
            ->orderByDesc('data_lancamento')
            ->orderByDesc('id')
            ->get();

        // 1.1 Custos de Produtos/Insumos Consumidos nas Tarefas (exclui OS canceladas)
        $consumosTarefasPeriodo = TarefaProduto::with(['produto.empresa', 'tarefa.ordemServico', 'tarefa.agendamentoServico.servico'])
            ->whereBetween('created_at', [$inicioPeriodo, $fimPeriodo])
            ->whereHas('tarefa.ordemServico', fn ($q) => $q->where('status', '!=', 'cancelada'))
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

        if ($receitaTotal > 0) {
            $margemPercent = round(($saldoLiquido / $receitaTotal) * 100, 1);
        } elseif ($despesaTotal > 0) {
            $margemPercent = -100.0;
        } else {
            $margemPercent = 0.0;
        }

        // 2. Ordens de Serviço (exclui canceladas) e Ticket Médio no Período
        $totalOS = OrdemServico::where('status', '!=', 'cancelada')
            ->where(function ($q) use ($inicioPeriodo, $fimPeriodo) {
                $q->whereBetween('data_fim', [$inicioPeriodo, $fimPeriodo])
                    ->orWhere(function ($sub) use ($inicioPeriodo, $fimPeriodo) {
                        $sub->whereNull('data_fim')
                            ->whereBetween('created_at', [$inicioPeriodo, $fimPeriodo]);
                    });
            })
            ->count();

        $qtdOsComReceita = $lancamentosPeriodo
            ->where('tipo', 'receita')
            ->map(fn ($l) => $l->tarefa?->ordem_servico_id)
            ->filter()
            ->unique()
            ->count();

        $divisorTicket = $qtdOsComReceita > 0 ? $qtdOsComReceita : $totalOS;
        $ticketMedio = $divisorTicket > 0 ? ($receitaTotal / $divisorTicket) : 0.0;

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

        // 5. Extrato de Lançamentos Financeiros
        $extratoLancamentos = $lancamentosPeriodo->map(function ($item) {
            return [
                'timestamp' => Carbon::parse($item->data_lancamento)->endOfDay()->timestamp,
                'data' => Carbon::parse($item->data_lancamento)->format('d/m/Y'),
                'descricao' => $item->descricao,
                'categoria' => $item->categoria,
                'tipo' => $item->tipo,
                'valor' => (float) $item->valor,
                'empresa_nome' => $item->empresa?->nome_fantasia,
            ];
        });

        $extratoConsumos = $consumosTarefasPeriodo->map(function ($tp) use ($agora) {
            $produtoNome = $tp->produto?->nome ?? 'Produto';
            $unidade = $tp->produto?->unidade_medida ?? 'un';
            $qtdFormatada = rtrim(rtrim(number_format((float) $tp->quantidade_utilizada, 3, ',', '.'), '0'), ',');
            $numeroOS = $tp->tarefa?->ordemServico?->numero ?? ('Tarefa #' . $tp->tarefa_id);
            $servicoNome = $tp->tarefa?->agendamentoServico?->servico?->nome;
            $complemento = $servicoNome ? "{$servicoNome} ({$numeroOS})" : $numeroOS;

            return [
                'timestamp' => $tp->created_at ? $tp->created_at->timestamp : $agora->timestamp,
                'data' => $tp->created_at ? $tp->created_at->format('d/m/Y') : $agora->format('d/m/Y'),
                'descricao' => "Consumo de Insumo: {$produtoNome} ({$qtdFormatada} {$unidade}) — {$complemento}",
                'categoria' => 'Insumos (Tarefas)',
                'tipo' => 'despesa',
                'valor' => $tp->custo_total,
                'empresa_nome' => $tp->produto?->empresa?->nome_fantasia,
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
            ->whereHas('ordemServico', fn ($q) => $q->where('status', '!=', 'cancelada'))
            ->get();

        $tarefasExecutadas = $tarefasConcluidasPeriodo->count();
        $mediaTarefasPorOs = $totalOS > 0 ? round($tarefasExecutadas / $totalOS, 1) : 0.0;

        // Tempo médio real de atendimento das tarefas concluídas (< 60s arredonda para min 1m)
        if ($tarefasExecutadas > 0) {
            $somaMinutos = $tarefasConcluidasPeriodo->sum(function ($tarefa) {
                if ($tarefa->data_inicio && $tarefa->data_conclusao) {
                    $diffSeconds = $tarefa->data_inicio->diffInSeconds($tarefa->data_conclusao, false);
                    if ($diffSeconds >= 0) {
                        return max(1, (int) round($diffSeconds / 60));
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

        // 7. Ranking de Serviços Mais Realizados (apenas agendamentos concluídos no período)
        $itensServicosPeriodo = AgendamentoServico::with(['servico.empresa', 'agendamento.empresa'])
            ->whereHas('agendamento', function ($q) use ($inicioPeriodo, $fimPeriodo) {
                $q->where('status', 'concluido')
                    ->where(function ($dq) use ($inicioPeriodo, $fimPeriodo) {
                        $dq->whereBetween('data_conclusao', [$inicioPeriodo, $fimPeriodo])
                            ->orWhere(function ($sub) use ($inicioPeriodo, $fimPeriodo) {
                                $sub->whereNull('data_conclusao')
                                    ->whereBetween('data_inicio', [$inicioPeriodo, $fimPeriodo]);
                            });
                    });
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
                    'empresa_nome' => $grupo->first()?->agendamento?->empresa?->nome_fantasia ?? $servico?->empresa?->nome_fantasia,
                    'execucoes' => $execucoes,
                    'tempo_total_formatado' => $tempoStr,
                    'valor_gerado' => (float) $grupo->sum('valor_servico'),
                ];
            })
            ->sortByDesc('execucoes')
            ->take(5)
            ->values();

        $maxExecucoesServico = max((int) $servicosRanking->max('execucoes'), 1);

        // 8. Produtividade da Equipe no Período (1 query, sem N+1, inclui inativos com atividade no período)
        $tarefasEquipe = Tarefa::whereNotNull('funcionario_id')
            ->whereHas('ordemServico', fn ($q) => $q->where('status', '!=', 'cancelada'))
            ->where(function ($q) use ($inicioPeriodo, $fimPeriodo) {
                $q->whereBetween('created_at', [$inicioPeriodo, $fimPeriodo])
                    ->orWhereBetween('data_conclusao', [$inicioPeriodo, $fimPeriodo]);
            })
            ->get();

        $idsComAtividade = $tarefasEquipe->pluck('funcionario_id')->unique()->filter()->all();
        $tarefasPorFuncionario = $tarefasEquipe->groupBy('funcionario_id');

        $funcionarios = Funcionario::with(['user', 'empresa'])
            ->where(function ($q) use ($idsComAtividade) {
                $q->where('ativo', true);
                if (!empty($idsComAtividade)) {
                    $q->orWhereIn('id', $idsComAtividade);
                }
            })
            ->get()
            ->map(function (Funcionario $func) use ($tarefasPorFuncionario, $inicioPeriodo, $fimPeriodo) {
                $tarefasFunc = $tarefasPorFuncionario->get($func->id, collect());

                $concluidasNoPeriodo = $tarefasFunc->filter(function (Tarefa $t) use ($inicioPeriodo, $fimPeriodo) {
                    return $t->status === 'concluida'
                        && $t->data_conclusao !== null
                        && $t->data_conclusao->between($inicioPeriodo, $fimPeriodo);
                });

                $criadasNoPeriodo = $tarefasFunc->filter(function (Tarefa $t) use ($inicioPeriodo, $fimPeriodo) {
                    return $t->created_at !== null && $t->created_at->between($inicioPeriodo, $fimPeriodo);
                });

                $totalAtribuidas = $criadasNoPeriodo
                    ->merge($concluidasNoPeriodo)
                    ->unique('id')
                    ->count();

                $concluidas = $concluidasNoPeriodo->count();
                $taxa = $totalAtribuidas > 0 ? min(100, (int) round(($concluidas / $totalAtribuidas) * 100)) : 0;

                $nome = trim((string) ($func->user?->name ?? 'Colaborador'));
                $partes = preg_split('/\s+/u', $nome, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $p1 = $partes[0] ?? 'C';
                $iniciais = isset($partes[1])
                    ? mb_strtoupper(mb_substr($p1, 0, 1, 'UTF-8') . mb_substr($partes[1], 0, 1, 'UTF-8'), 'UTF-8')
                    : mb_strtoupper(mb_substr($p1, 0, 2, 'UTF-8'), 'UTF-8');

                return [
                    'nome' => $nome,
                    'iniciais' => $iniciais,
                    'cargo' => $func->cargo ?: 'Técnico em Estética Automotiva',
                    'empresa_nome' => $func->empresa?->nome_fantasia,
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

        return view('relatorios.index', array_merge(compact(
            'kpis',
            'lancamentos',
            'receitasPorServico',
            'despesasPorCategoria',
            'servicosRanking',
            'maxExecucoesServico',
            'funcionarios',
            'periodo'
        ), $filtroAdmin));
    }
}
