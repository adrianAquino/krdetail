<?php

namespace App\Http\Controllers;

use App\Models\Funcionario;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\Tarefa;
use App\Models\TarefaProduto;
use App\Services\ConcluirTarefaService;
use App\Services\IniciarTarefaService;
use App\Services\MovimentarEstoqueService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TarefaController extends Controller
{
    /**
     * Exibe os detalhes da tarefa, responsável, produtos consumidos e ações.
     */
    public function show(Tarefa $tarefa): View
    {
        $tarefa->load([
            'ordemServico.agendamento.empresa',
            'ordemServico.agendamento.cliente' => fn ($q) => $q->withoutGlobalScopes()->with('pessoa'),
            'ordemServico.agendamento.veiculo' => fn ($q) => $q->withoutGlobalScopes(),
            'agendamentoServico.servico',
            'funcionario.user',
            'tarefasProdutos.produto',
            'lancamentoFinanceiro',
        ]);

        $empresaTarefaId = $tarefa->ordemServico?->agendamento?->empresa_id
            ?? $tarefa->ordemServico?->agendamento?->cliente?->empresa_id;

        if ($empresaTarefaId === null) {
            $funcionarios = collect();
            $produtos = collect();
        } else {
            $funcionarios = Funcionario::withoutGlobalScopes()
                ->with('user')
                ->where('ativo', true)
                ->where('empresa_id', $empresaTarefaId)
                ->get()
                ->sortBy(fn ($f) => mb_strtolower($f->nome))
                ->values();

            $produtos = Produto::withoutGlobalScopes()
                ->where('ativo', true)
                ->where('empresa_id', $empresaTarefaId)
                ->orderBy('nome')
                ->get();
        }

        return view('tarefas.show', compact('tarefa', 'funcionarios', 'produtos'));
    }

    /**
     * Inicia a execução de uma tarefa pendente.
     */
    public function iniciar(Request $request, Tarefa $tarefa, IniciarTarefaService $service): RedirectResponse
    {
        $empresaTarefaId = $this->resolverEmpresaTarefaId($tarefa);

        $validated = $request->validate([
            'funcionario_id' => [
                'nullable',
                'integer',
                Rule::exists('funcionarios', 'id')->where(function ($query) use ($empresaTarefaId) {
                    $query->where('ativo', true)
                        ->where('empresa_id', $empresaTarefaId ?? -1);
                }),
            ],
        ]);

        try {
            $service->iniciar($tarefa, $validated['funcionario_id'] ?? null);

            return back()->with('success', 'Tarefa iniciada com sucesso!');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (QueryException) {
            return back()->with('error', 'Não foi possível iniciar a tarefa no momento. Tente novamente.');
        }
    }

    /**
     * Conclui uma tarefa em andamento (ou pendente) e registra receita financeira.
     */
    public function concluir(Request $request, Tarefa $tarefa, ConcluirTarefaService $service): RedirectResponse
    {
        $empresaTarefaId = $this->resolverEmpresaTarefaId($tarefa);

        $validated = $request->validate([
            'funcionario_id' => [
                'nullable',
                'integer',
                Rule::exists('funcionarios', 'id')->where(function ($query) use ($empresaTarefaId) {
                    $query->where('ativo', true)
                        ->where('empresa_id', $empresaTarefaId ?? -1);
                }),
            ],
        ]);

        try {
            $service->concluir($tarefa, $validated['funcionario_id'] ?? null, Auth::user());

            return back()->with('success', 'Tarefa concluída e lançamento financeiro registrado!');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (QueryException) {
            return back()->with('error', 'Não foi possível concluir a tarefa no momento. Tente novamente.');
        }
    }

    /**
     * Atribui ou altera o funcionário responsável pela tarefa.
     */
    public function atribuir(Request $request, Tarefa $tarefa): RedirectResponse
    {
        $empresaTarefaId = $this->resolverEmpresaTarefaId($tarefa);

        $validated = $request->validate([
            'funcionario_id' => [
                'required',
                'integer',
                Rule::exists('funcionarios', 'id')->where(function ($query) use ($empresaTarefaId) {
                    $query->where('ativo', true)
                        ->where('empresa_id', $empresaTarefaId ?? -1);
                }),
            ],
        ]);

        try {
            DB::transaction(function () use ($tarefa, $validated) {
                $osTravada = OrdemServico::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail($tarefa->ordem_servico_id);

                $tarefaTravada = Tarefa::withoutGlobalScopes()
                    ->where('ordem_servico_id', $osTravada->id)
                    ->lockForUpdate()
                    ->findOrFail($tarefa->id);

                if ($tarefaTravada->status === 'concluida' || in_array($osTravada->status, ['concluida', 'cancelada'], true)) {
                    throw new \DomainException('Não é possível atribuir ou alterar o responsável de uma tarefa ou OS finalizada/cancelada.');
                }

                $tarefaTravada->funcionario_id = (int) $validated['funcionario_id'];
                $tarefaTravada->save();
            });

            return back()->with('success', 'Responsável pela tarefa atualizado com sucesso!');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (QueryException) {
            return back()->with('error', 'Erro ao atribuir funcionário à tarefa.');
        }
    }

    /**
     * Registra o consumo de um produto do estoque na execução da tarefa.
     */
    public function adicionarProduto(
        Request $request,
        Tarefa $tarefa,
        MovimentarEstoqueService $service
    ): RedirectResponse {
        $empresaTarefaId = $this->resolverEmpresaTarefaId($tarefa);

        $validated = $request->validate([
            'produto_id' => [
                'required',
                'integer',
                Rule::exists('produtos', 'id')->where(function ($rule) use ($empresaTarefaId) {
                    $rule->where('empresa_id', $empresaTarefaId ?? -1);
                }),
            ],
            'quantidade_utilizada' => ['required', 'numeric', 'min:0.001', 'max:999999999.999'],
            'observacoes' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $service->registrarConsumoEmTarefa(
                tarefa: $tarefa,
                produto: (int) $validated['produto_id'],
                quantidadeUtilizada: (float) $validated['quantidade_utilizada'],
                usuario: Auth::user(),
                observacoes: $validated['observacoes'] ?? null
            );

            return back()->with('success', 'Consumo de produto registrado e baixado do estoque!');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (QueryException) {
            return back()->with('error', 'Não foi possível registrar o consumo do produto. Tente novamente.');
        }
    }

    /**
     * Remove um produto consumido na tarefa com estorno automático e lock anti-corrida.
     */
    public function removerProduto(
        Tarefa $tarefa,
        TarefaProduto $tarefaProduto,
        MovimentarEstoqueService $service
    ): RedirectResponse {
        try {
            DB::transaction(function () use ($tarefa, $tarefaProduto, $service) {
                $osTravada = OrdemServico::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail($tarefa->ordem_servico_id);

                $tarefaTravada = Tarefa::withoutGlobalScopes()
                    ->where('ordem_servico_id', $osTravada->id)
                    ->lockForUpdate()
                    ->findOrFail($tarefa->id);

                if ($tarefaTravada->status === 'concluida' || in_array($osTravada->status, ['concluida', 'cancelada'], true)) {
                    throw new \DomainException('Não é possível remover produtos de uma tarefa ou OS já finalizada ou cancelada.');
                }

                $tpTravado = TarefaProduto::withoutGlobalScopes()
                    ->where('id', $tarefaProduto->id)
                    ->where('tarefa_id', $tarefaTravada->id)
                    ->lockForUpdate()
                    ->first();

                if (!$tpTravado) {
                    throw new \DomainException('Este registro de produto já foi removido da tarefa.');
                }

                $produtoId = (int) $tpTravado->produto_id;
                $quantidadeEstorno = round((float) $tpTravado->quantidade_utilizada, 3);

                $tpTravado->delete();

                if ((int) round($quantidadeEstorno * 1000) >= 1) {
                    $service->movimentar(
                        produto: $produtoId,
                        tipo: 'entrada',
                        quantidade: $quantidadeEstorno,
                        usuario: Auth::user(),
                        motivo: "Estorno de remoção de produto na OS #{$osTravada->numero}",
                        permitirProdutoInativo: true
                    );
                }
            });

            return back()->with('success', 'Produto removido da tarefa e saldo estornado para o estoque.');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (QueryException) {
            return back()->with('error', 'Não foi possível estornar o produto no momento.');
        }
    }

    private function resolverEmpresaTarefaId(Tarefa $tarefa): ?int
    {
        $tarefa->loadMissing([
            'ordemServico' => fn ($oq) => $oq->withoutGlobalScopes(),
            'ordemServico.agendamento' => fn ($aq) => $aq->withoutGlobalScopes(),
            'ordemServico.agendamento.cliente' => fn ($cq) => $cq->withoutGlobalScopes(),
        ]);

        $empresaId = $tarefa->ordemServico?->agendamento?->empresa_id
            ?? $tarefa->ordemServico?->agendamento?->cliente?->empresa_id;

        return $empresaId ? (int) $empresaId : null;
    }
}
