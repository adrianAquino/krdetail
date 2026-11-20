<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\LancamentoFinanceiro;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\Tarefa;
use App\Models\Veiculo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $agora = Carbon::now();

        if ($user && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();

            $meusVeiculos = Veiculo::withCount([
                'agendamentos' => fn ($q) => $q->where('cliente_id', $cliente->id)->where('status', '!=', 'cancelado'),
            ])
                ->where('cliente_id', $cliente->id)
                ->orderBy('marca')
                ->orderBy('modelo')
                ->get();

            $agendamentosAtivos = Agendamento::with([
                'veiculo',
                'servicos',
                'ordemServico.tarefas.agendamentoServico.servico',
            ])
                ->where('cliente_id', $cliente->id)
                ->whereNotIn('status', ['concluido', 'cancelado'])
                ->orderBy('data_inicio')
                ->get();

            $totalConcluidos = Agendamento::where('cliente_id', $cliente->id)
                ->where('status', 'concluido')
                ->count();

            $historicoServicos = Agendamento::with(['veiculo', 'servicos'])
                ->where('cliente_id', $cliente->id)
                ->where('status', 'concluido')
                ->orderByDesc('data_conclusao')
                ->orderByDesc('data_inicio')
                ->limit(5)
                ->get();

            return view('dashboard.cliente', compact(
                'cliente',
                'meusVeiculos',
                'agendamentosAtivos',
                'totalConcluidos',
                'historicoServicos'
            ));
        }

        $inicioHoje = $agora->copy()->startOfDay();
        $fimHoje = $agora->copy()->endOfDay();
        $inicioMesAtual = $agora->copy()->startOfMonth();
        $fimMesAtual = $agora->copy()->endOfMonth();
        $inicioMesAnterior = $agora->copy()->subMonthNoOverflow()->startOfMonth();
        $fimMesAnterior = $agora->copy()->subMonthNoOverflow()->endOfMonth();

        // Clientes
        $totalClientes = Cliente::count();
        $clientesMesAtual = Cliente::whereBetween('created_at', [$inicioMesAtual, $fimMesAtual])->count();
        $clientesMesAnterior = Cliente::whereBetween('created_at', [$inicioMesAnterior, $fimMesAnterior])->count();
        $variacaoClientesPercent = $clientesMesAnterior > 0
            ? round((($clientesMesAtual - $clientesMesAnterior) / $clientesMesAnterior) * 100, 1)
            : ($clientesMesAtual > 0 ? 100.0 : 0.0);

        // Veículos
        $totalVeiculos = Veiculo::count();
        $veiculosComHistorico = Veiculo::whereHas('agendamentos', function ($q) {
            $q->where('status', '!=', 'cancelado');
        })->count();
        $percentVeiculosAtivos = $totalVeiculos > 0
            ? round(($veiculosComHistorico / $totalVeiculos) * 100, 1)
            : 0.0;

        // Agendamentos de hoje (exclui cancelados)
        $agendamentosHojeCount = Agendamento::whereBetween('data_inicio', [$inicioHoje, $fimHoje])
            ->where('status', '!=', 'cancelado')
            ->count();

        $agendamentosPendentesHoje = Agendamento::whereBetween('data_inicio', [$inicioHoje, $fimHoje])
            ->whereIn('status', ['agendado', 'confirmado'])
            ->count();

        // Receita do mês atual vs mês anterior
        $receitaMes = (float) LancamentoFinanceiro::where('tipo', 'receita')
            ->whereBetween('data_lancamento', [$inicioMesAtual->toDateString(), $fimMesAtual->toDateString()])
            ->sum('valor');

        $receitaMesAnterior = (float) LancamentoFinanceiro::where('tipo', 'receita')
            ->whereBetween('data_lancamento', [$inicioMesAnterior->toDateString(), $fimMesAnterior->toDateString()])
            ->sum('valor');

        $variacaoReceitaPercent = $receitaMesAnterior > 0
            ? round((($receitaMes - $receitaMesAnterior) / $receitaMesAnterior) * 100, 1)
            : ($receitaMes > 0 ? 100.0 : 0.0);

        // Próximos agendamentos (inclui em_andamento mesmo iniciados em dias anteriores)
        $proximosAgendamentos = Agendamento::with(['cliente.pessoa', 'veiculo', 'servicos'])
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->where(function ($q) use ($inicioHoje) {
                $q->where('data_inicio', '>=', $inicioHoje)
                    ->orWhere('status', 'em_andamento');
            })
            ->orderBy('data_inicio')
            ->limit(6)
            ->get();

        // Alertas de estoque baixo
        $produtosEstoqueBaixo = Produto::where('ativo', true)
            ->where('estoque_minimo', '>', 0)
            ->whereColumn('quantidade_estoque', '<=', 'estoque_minimo')
            ->orderByRaw('(quantidade_estoque / CASE WHEN estoque_minimo > 0 THEN estoque_minimo ELSE 1 END) ASC')
            ->limit(6)
            ->get();

        // Serviços mais realizados (exclui agendamentos cancelados)
        $servicosPopulares = Servico::where('ativo', true)
            ->withCount([
                'agendamentosServicos' => fn ($q) => $q->whereHas('agendamento', fn ($aq) => $aq->where('status', '!=', 'cancelado')),
            ])
            ->orderByDesc('agendamentos_servicos_count')
            ->limit(5)
            ->get();

        // Resumo operacional de Ordens de Serviço e Tarefas
        $osEmAndamento = OrdemServico::whereIn('status', ['aberta', 'em_andamento'])->count();

        $tarefasConcluidasHoje = Tarefa::where('status', 'concluida')
            ->whereBetween('data_conclusao', [$inicioHoje, $fimHoje])
            ->whereHas('ordemServico', fn ($q) => $q->where('status', '!=', 'cancelada'))
            ->count();

        $tarefasPendentes = Tarefa::whereIn('status', ['pendente', 'em_andamento'])
            ->whereHas('ordemServico', fn ($q) => $q->whereNotIn('status', ['cancelada', 'concluida']))
            ->count();

        return view('dashboard.index', compact(
            'totalClientes',
            'variacaoClientesPercent',
            'totalVeiculos',
            'percentVeiculosAtivos',
            'agendamentosHojeCount',
            'agendamentosPendentesHoje',
            'receitaMes',
            'variacaoReceitaPercent',
            'proximosAgendamentos',
            'produtosEstoqueBaixo',
            'servicosPopulares',
            'osEmAndamento',
            'tarefasConcluidasHoje',
            'tarefasPendentes'
        ));
    }
}
