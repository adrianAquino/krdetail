<?php

namespace App\Http\Controllers;

use App\Models\Funcionario;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Services\AbrirOrdemServicoService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrdemServicoController extends Controller
{
    public function index(Request $request)
    {
        $query = OrdemServico::with([
            'agendamento.cliente',
            'agendamento.veiculo',
            'tarefas.agendamentoServico.servico',
            'tarefas.funcionario.user',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('busca')) {
            $busca = $request->input('busca');
            $query->where(function ($q) use ($busca) {
                $q->where('numero', 'like', "%{$busca}%")
                  ->orWhereHas('agendamento.cliente', function ($qc) use ($busca) {
                      $qc->where('nome', 'like', "%{$busca}%");
                  })
                  ->orWhereHas('agendamento.veiculo', function ($qv) use ($busca) {
                      $qv->where('placa', 'like', "%{$busca}%")
                         ->orWhere('modelo', 'like', "%{$busca}%");
                  });
            });
        }

        $ordensServico = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        return view('ordens-servico.index', compact('ordensServico'));
    }

    public function store(Request $request,  AbrirOrdemServicoService $service)
    {
        $validated = $request->validate([
            'agendamento_id' => 'required|exists:agendamentos,id',
            'observacoes' => 'nullable|string',
        ]);

        try {
            $os = $service->abrir($validated['agendamento_id'], $validated['observacoes'] ?? null);

            return redirect()->route('ordens-servico.show', $os)
                ->with('success', "Ordem de Serviço {$os->numero} aberta com sucesso!");
        } catch (\DomainException $e) {
            $agendamento = \App\Models\Agendamento::find($validated['agendamento_id']);
            if ($agendamento && $agendamento->ordemServico) {
                return redirect()->route('ordens-servico.show', $agendamento->ordemServico)
                    ->with('info', 'Esta Ordem de Serviço já foi aberta anteriormente.');
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function show(OrdemServico $ordemServico)
    {
        $ordemServico->load([
            'agendamento.cliente',
            'agendamento.veiculo',
            'agendamento.agendamentosServicos',
            'tarefas.agendamentoServico.servico',
            'tarefas.funcionario.user',
            'tarefas.tarefasProdutos.produto',
            'tarefas.lancamentoFinanceiro',
        ]);

        $funcionarios = Funcionario::with('user')->where('ativo', true)->get();
        $produtos = Produto::where('ativo', true)->where('quantidade_estoque', '>', 0)->get();

        return view('ordens-servico.show', compact('ordemServico', 'funcionarios', 'produtos'));
    }

    public function concluir(OrdemServico $ordemServico)
    {
        // Verifica se todas as tarefas estão concluídas
        $tarefasPendentes = $ordemServico->tarefas()->where('status', '!=', 'concluida')->count();

        if ($tarefasPendentes > 0) {
            return back()->with('error', "Não é possível concluir a OS: ainda restam {$tarefasPendentes} tarefas em andamento ou pendentes.");
        }

        $ordemServico->update([
            'status' => 'concluida',
            'data_fim' => Carbon::now(),
        ]);

        if ($ordemServico->agendamento) {
            $ordemServico->agendamento->update(['status' => 'concluido']);
        }

        return back()->with('success', 'Ordem de Serviço finalizada com sucesso! Veículo pronto para entrega.');
    }
}
