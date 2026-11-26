<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Funcionario;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\User;
use App\Services\AbrirOrdemServicoService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrdemServicoController extends Controller
{
    public function index(Request $request)
    {
        $filtroAdmin = $this->resolverFiltroEmpresaAdmin($request);

        $query = OrdemServico::with([
            'agendamento.empresa',
            'agendamento.cliente' => fn ($q) => $q->withoutGlobalScopes(),
            'agendamento.veiculo' => fn ($q) => $q->withoutGlobalScopes(),
            'tarefas.agendamentoServico.servico',
            'tarefas.funcionario.user',
        ]);

        $status = trim($this->inputString($request, 'status'));
        if (in_array($status, ['aberta', 'em_andamento', 'concluida', 'cancelada'], true)) {
            $query->where('status', $status);
        }

        $busca = trim($this->inputString($request, 'busca'));
        if ($busca !== '') {
            $query->where(function ($q) use ($busca) {
                $q->where('numero', 'like', "%{$busca}%")
                    ->orWhereHas('agendamento.cliente', function ($qc) use ($busca) {
                        $qc->withoutGlobalScopes()->where('nome', 'like', "%{$busca}%");
                    })
                    ->orWhereHas('agendamento.veiculo', function ($qv) use ($busca) {
                        $qv->withoutGlobalScopes()->where(function ($vq) use ($busca) {
                            $vq->where('placa', 'like', "%{$busca}%")
                                ->orWhere('modelo', 'like', "%{$busca}%");
                        });
                    });
            });
        }

        $ordensServico = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        return view('ordens-servico.index', array_merge(compact('ordensServico'), $filtroAdmin));
    }

    public function store(Request $request, AbrirOrdemServicoService $service)
    {
        $validated = $request->validate([
            'agendamento_id' => 'required|integer|exists:agendamentos,id',
            'observacoes' => 'nullable|string|max:2000',
        ]);

        // Valida escopo de tenant antes de tentar abrir a OS
        $agendamento = Agendamento::with('ordemServico')->findOrFail($validated['agendamento_id']);

        try {
            $os = $service->abrir($agendamento, $validated['observacoes'] ?? null);

            return redirect()->route('ordens-servico.show', $os)
                ->with('success', "Ordem de Serviço {$os->numero} aberta com sucesso!");
        } catch (\DomainException $e) {
            $agAtualizado = Agendamento::with('ordemServico')->find($agendamento->id);
            if (
                $agAtualizado
                && $agAtualizado->status !== 'cancelado'
                && $agAtualizado->ordemServico
                && $agAtualizado->ordemServico->status !== 'cancelada'
            ) {
                return redirect()->route('ordens-servico.show', $agAtualizado->ordemServico)
                    ->with('info', 'Esta Ordem de Serviço já foi aberta anteriormente.');
            }

            return back()->with('error', $e->getMessage());
        } catch (QueryException $e) {
            return back()->with('error', 'Não foi possível abrir a Ordem de Serviço devido a uma operação concorrente. Atualize a página.');
        }
    }

    public function show(OrdemServico $ordemServico)
    {
        $ordemServico->load([
            'agendamento.empresa',
            'agendamento.cliente' => fn ($q) => $q->withoutGlobalScopes(),
            'agendamento.veiculo' => fn ($q) => $q->withoutGlobalScopes(),
            'agendamento.agendamentosServicos',
            'tarefas.agendamentoServico.servico',
            'tarefas.funcionario.user',
            'tarefas.tarefasProdutos.produto',
            'tarefas.lancamentoFinanceiro',
        ]);

        $empresaOsId = $ordemServico->agendamento?->empresa_id
            ?? $ordemServico->agendamento?->cliente?->empresa_id;

        if ($empresaOsId === null) {
            $funcionarios = collect();
            $produtos = collect();
        } else {
            $funcionarios = Funcionario::withoutGlobalScopes()
                ->with('user')
                ->where('ativo', true)
                ->where('empresa_id', $empresaOsId)
                ->get();

            $produtos = Produto::withoutGlobalScopes()
                ->where('ativo', true)
                ->where('quantidade_estoque', '>', 0)
                ->where('empresa_id', $empresaOsId)
                ->get();
        }

        return view('ordens-servico.show', compact('ordemServico', 'funcionarios', 'produtos'));
    }

    public function concluir(OrdemServico $ordemServico)
    {
        try {
            DB::transaction(function () use ($ordemServico) {
                $os = OrdemServico::lockForUpdate()->with('agendamento')->findOrFail($ordemServico->id);

                if ($os->status === 'concluida') {
                    throw new \DomainException('Esta Ordem de Serviço já foi concluída anteriormente.');
                }

                if ($os->status === 'cancelada') {
                    throw new \DomainException('Não é possível concluir uma Ordem de Serviço cancelada.');
                }

                $totalTarefas = $os->tarefas()->count();
                if ($totalTarefas === 0) {
                    throw new \DomainException('Não é possível concluir uma Ordem de Serviço sem tarefas cadastradas.');
                }

                $tarefasPendentes = $os->tarefas()->where('status', '!=', 'concluida')->count();
                if ($tarefasPendentes > 0) {
                    throw new \DomainException("Não é possível concluir a OS: ainda restam {$tarefasPendentes} tarefas em andamento ou pendentes.");
                }

                $agora = Carbon::now();

                $os->update([
                    'status' => 'concluida',
                    'data_fim' => $agora,
                ]);

                if ($os->agendamento && $os->agendamento->status !== 'cancelado') {
                    $os->agendamento->update([
                        'status' => 'concluido',
                        'data_conclusao' => $agora,
                    ]);
                }
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ordem de Serviço finalizada com sucesso! Veículo pronto para entrega.');
    }
}
