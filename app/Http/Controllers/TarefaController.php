<?php

namespace App\Http\Controllers;

use App\Models\Funcionario;
use App\Models\Produto;
use App\Models\Tarefa;
use App\Models\TarefaProduto;
use App\Services\ConcluirTarefaService;
use App\Services\IniciarTarefaService;
use App\Services\MovimentarEstoqueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TarefaController extends Controller
{
    public function show(Tarefa $tarefa)
    {
        $tarefa->load([
            'ordemServico.agendamento.cliente',
            'ordemServico.agendamento.veiculo',
            'agendamentoServico.servico',
            'funcionario.user',
            'tarefasProdutos.produto',
            'lancamentoFinanceiro',
        ]);

        $funcionarios = Funcionario::with('user')->where('ativo', true)->get();
        $produtos = Produto::where('ativo', true)->where('quantidade_estoque', '>', 0)->get();

        return view('tarefas.show', compact('tarefa', 'funcionarios', 'produtos'));
    }

    public function iniciar(Request $request, Tarefa $tarefa, IniciarTarefaService $service)
    {
        try {
            $funcionarioId = $request->input('funcionario_id')
                ?? $tarefa->funcionario_id
                ?? Funcionario::where('user_id', Auth::id())->where('ativo', true)->value('id')
                ?? Funcionario::where('ativo', true)->value('id');

            $service->iniciar($tarefa, $funcionarioId);

            return back()->with('success', 'Execução da tarefa iniciada com sucesso!');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function concluir(Request $request, Tarefa $tarefa, ConcluirTarefaService $service)
    {
        try {
            $funcionarioId = $request->input('funcionario_id')
                ?? $tarefa->funcionario_id
                ?? Funcionario::where('user_id', Auth::id())->where('ativo', true)->value('id')
                ?? Funcionario::where('ativo', true)->value('id');

            $service->concluir($tarefa, $funcionarioId, Auth::user());

            return back()->with('success', 'Tarefa concluída com sucesso! Receita correspondente lançada automaticamente no financeiro.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function atribuir(Request $request, Tarefa $tarefa)
    {
        $validated = $request->validate([
            'funcionario_id' => 'required|exists:funcionarios,id',
        ]);

        $funcionario = Funcionario::findOrFail($validated['funcionario_id']);
        if (!$funcionario->ativo) {
            return back()->with('error', 'Não é possível atribuir a tarefa a um colaborador inativo.');
        }

        $tarefa->update(['funcionario_id' => $funcionario->id]);

        return back()->with('success', 'Colaborador responsável atribuído à tarefa com sucesso!');
    }

    public function adicionarProduto(Request $request, Tarefa $tarefa, MovimentarEstoqueService $service)
    {
        $validated = $request->validate([
            'produto_id' => 'required|exists:produtos,id',
            'quantidade_utilizada' => 'required|numeric|min:0.001',
        ]);

        try {
            $produto = Produto::findOrFail($validated['produto_id']);
            $service->registrarConsumoEmTarefa($tarefa, $produto, (float) $validated['quantidade_utilizada'], Auth::user());

            return back()->with('success', 'Produto registrado e abatido do estoque com sucesso!');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function removerProduto(Tarefa $tarefa, TarefaProduto $tarefaProduto, MovimentarEstoqueService $service)
    {
        if ($tarefa->status === 'concluida') {
            return back()->with('error', 'Não é possível remover insumos de uma tarefa já concluída.');
        }

        if ($tarefaProduto->tarefa_id !== $tarefa->id) {
            return back()->with('error', 'O produto informado não pertence a esta tarefa.');
        }

        try {
            DB::transaction(function () use ($tarefa, $tarefaProduto, $service) {
                $produto = $tarefaProduto->produto;
                $qtd = (float) $tarefaProduto->quantidade_utilizada;

                $tarefaProduto->delete();

                if ($produto) {
                    $service->movimentar(
                        produto: $produto,
                        tipo: 'entrada',
                        quantidade: $qtd,
                        usuario: Auth::user(),
                        motivo: "Estorno por remoção da tarefa #{$tarefa->id} (OS #{$tarefa->ordemServico?->numero})"
                    );
                }
            });

            return back()->with('success', 'Produto removido da tarefa e estoque estornado com sucesso!');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
