<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\LancamentoFinanceiro;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\Tarefa;
use App\Models\User;
use App\Models\Veiculo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user instanceof User && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();

            $meusVeiculos = Veiculo::withCount('agendamentos')
                ->where('cliente_id', $cliente->id)
                ->orderByDesc('created_at')
                ->get();

            $agendamentosAtivos = Agendamento::with([
                'veiculo',
                'servicos',
                'agendamentosServicos.servico',
                'ordemServico.tarefas.agendamentoServico.servico',
            ])
                ->where('cliente_id', $cliente->id)
                ->whereIn('status', ['agendado', 'confirmado', 'em_andamento'])
                ->orderBy('data_inicio', 'asc')
                ->get();

            $historicoServicos = Agendamento::with([
                'veiculo',
                'servicos',
                'agendamentosServicos.servico',
                'ordemServico.tarefas.agendamentoServico.servico',
            ])
                ->where('cliente_id', $cliente->id)
                ->whereIn('status', ['concluido', 'cancelado'])
                ->orderByDesc('data_inicio')
                ->take(10)
                ->get();

            $totalConcluidos = Agendamento::where('cliente_id', $cliente->id)
                ->where('status', 'concluido')
                ->count();

            return view('dashboard.cliente', compact(
                'cliente',
                'meusVeiculos',
                'agendamentosAtivos',
                'historicoServicos',
                'totalConcluidos'
            ));
        }

        $hoje = Carbon::today();
        $inicioMes = Carbon::now()->startOfMonth();
        $fimMes = Carbon::now()->endOfMonth();
        $inicioMesAnterior = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $fimMesAnterior = Carbon::now()->subMonthNoOverflow()->endOfMonth();

        // 1. Clientes e Variação Mensal
        $totalClientes = Cliente::count();
        $clientesMesAtual = Cliente::whereBetween('created_at', [$inicioMes, $fimMes])->count();
        $clientesMesAnterior = Cliente::whereBetween('created_at', [$inicioMesAnterior, $fimMesAnterior])->count();

        if ($clientesMesAnterior > 0) {
            $variacaoClientesPercent = round((($clientesMesAtual - $clientesMesAnterior) / $clientesMesAnterior) * 100, 1);
        } else {
            $variacaoClientesPercent = $clientesMesAtual > 0 ? 100.0 : 0.0;
        }

        // 2. Veículos e Percentual com Histórico de Agendamentos
        $totalVeiculos = Veiculo::count();
        $veiculosComHistorico = Veiculo::has('agendamentos')->count();
        $percentVeiculosAtivos = $totalVeiculos > 0
            ? round(($veiculosComHistorico / $totalVeiculos) * 100)
            : 0;

        // 3. Agendamentos de Hoje
        $agendamentosHojeCount = Agendamento::whereDate('data_inicio', $hoje)->count();
        $agendamentosPendentesHoje = Agendamento::whereDate('data_inicio', $hoje)
            ->whereIn('status', ['agendado', 'confirmado'])
            ->count();

        // 4. Receitas do Mês e Variação em relação ao Mês Anterior
        $receitaMes = (float) LancamentoFinanceiro::where('tipo', 'receita')
            ->whereBetween('data_lancamento', [$inicioMes->toDateString(), $fimMes->toDateString()])
            ->sum('valor');

        $receitaMesAnterior = (float) LancamentoFinanceiro::where('tipo', 'receita')
            ->whereBetween('data_lancamento', [$inicioMesAnterior->toDateString(), $fimMesAnterior->toDateString()])
            ->sum('valor');

        if ($receitaMesAnterior > 0) {
            $variacaoReceitaPercent = round((($receitaMes - $receitaMesAnterior) / $receitaMesAnterior) * 100, 1);
        } else {
            $variacaoReceitaPercent = $receitaMes > 0 ? 100.0 : 0.0;
        }

        // 5. Próximos Agendamentos
        $proximosAgendamentos = Agendamento::with(['cliente', 'veiculo', 'servicos'])
            ->where('data_inicio', '>=', $hoje)
            ->whereIn('status', ['agendado', 'confirmado', 'em_andamento'])
            ->orderBy('data_inicio', 'asc')
            ->take(5)
            ->get();

        // 6. Alertas de Estoque Baixo
        $produtosEstoqueBaixo = Produto::where('ativo', true)
            ->whereColumn('quantidade_estoque', '<=', 'estoque_minimo')
            ->orderBy('quantidade_estoque', 'asc')
            ->take(5)
            ->get();

        // 7. Serviços Mais Realizados (baseado na contagem real de agendamentos_servicos)
        $servicosPopulares = Servico::withCount('agendamentosServicos')
            ->where('ativo', true)
            ->orderByDesc('agendamentos_servicos_count')
            ->take(5)
            ->get();

        // 8. Resumo Operacional de Ordens de Serviço e Tarefas
        $osEmAndamento = OrdemServico::where('status', 'em_andamento')->count();
        $tarefasConcluidasHoje = Tarefa::where('status', 'concluida')
            ->whereDate('data_conclusao', $hoje)
            ->count();
        $tarefasPendentes = Tarefa::whereIn('status', ['pendente', 'em_andamento'])->count();

        return view('dashboard.index', compact(
            'totalClientes',
            'clientesMesAtual',
            'variacaoClientesPercent',
            'totalVeiculos',
            'veiculosComHistorico',
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
