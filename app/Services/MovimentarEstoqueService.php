<?php

namespace App\Services;

use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Models\Tarefa;
use App\Models\TarefaProduto;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MovimentarEstoqueService
{
    /**
     * Centraliza todas as alterações de estoque (entrada, saída, ajuste) de forma atômica
     * e gera o histórico auditável em movimentacoes_estoque.
     *
     * @param Produto|int $produto
     * @param string $tipo ('entrada', 'saida', 'ajuste')
     * @param float $quantidade
     * @param User|int|null $usuario
     * @param string|null $motivo
     * @param string|null $observacoes
     * @return MovimentacaoEstoque
     * @throws InvalidArgumentException|DomainException
     */
    public function movimentar(
        Produto|int $produto,
        string $tipo,
        float $quantidade,
        User|int|null $usuario = null,
        ?string $motivo = null,
        ?string $observacoes = null
    ): MovimentacaoEstoque {
        $tipoNormalizado = strtolower(trim($tipo));

        // 1. Validação de tipo de movimentação
        if (!in_array($tipoNormalizado, ['entrada', 'saida', 'ajuste'], true)) {
            throw new InvalidArgumentException("Tipo de movimentação inválido: '{$tipo}'. Tipos permitidos: entrada, saida, ajuste.");
        }

        // 2. Validação da quantidade
        if (in_array($tipoNormalizado, ['entrada', 'saida'], true) && $quantidade <= 0) {
            throw new InvalidArgumentException("A quantidade para movimentação do tipo '{$tipoNormalizado}' deve ser maior que zero.");
        }

        if ($tipoNormalizado === 'ajuste' && $quantidade < 0) {
            throw new InvalidArgumentException("A quantidade ajustada de estoque não pode ser negativa.");
        }

        $produtoModel = $produto instanceof Produto ? $produto : Produto::findOrFail($produto);

        // 3. Validação de produto ativo
        if (!$produtoModel->ativo) {
            throw new DomainException("Não é possível movimentar o estoque de um produto inativo no sistema.");
        }

        // 4. Execução atômica dentro de transação com bloqueio de concorrência
        return DB::transaction(function () use ($produtoModel, $tipoNormalizado, $quantidade, $usuario, $motivo, $observacoes) {
            // Recarrega o produto com lock para garantir consistência
            $produtoAtualizado = Produto::lockForUpdate()->findOrFail($produtoModel->id);
            $qtdAnterior = (float) $produtoAtualizado->quantidade_estoque;

            $usuarioId = null;
            if ($usuario instanceof User) {
                $usuarioId = $usuario->id;
            } elseif (is_numeric($usuario)) {
                $usuarioId = (int) $usuario;
            } else {
                $usuarioId = Auth::id();
            }

            // Cálculo do novo estoque e da quantidade registrada
            if ($tipoNormalizado === 'entrada') {
                $qtdPosterior = $qtdAnterior + $quantidade;
                $qtdRegistrada = $quantidade;
                $motivoPadrao = $motivo ?? 'Entrada manual de estoque';
            } elseif ($tipoNormalizado === 'saida') {
                // Impede estoque negativo
                if ($qtdAnterior < $quantidade) {
                    throw new DomainException("Estoque insuficiente para o produto '{$produtoAtualizado->nome}'. Estoque atual: {$qtdAnterior}, saída solicitada: {$quantidade}.");
                }
                $qtdPosterior = $qtdAnterior - $quantidade;
                $qtdRegistrada = $quantidade;
                $motivoPadrao = $motivo ?? 'Saída manual de estoque';
            } else { // ajuste
                // No ajuste, $quantidade representa a nova quantidade física real do produto
                $qtdPosterior = $quantidade;
                $qtdRegistrada = $quantidade;
                $motivoPadrao = $motivo ?? 'Ajuste de inventário físico';
            }

            // Atualiza a tabela de produtos
            $produtoAtualizado->quantidade_estoque = $qtdPosterior;
            $produtoAtualizado->save();

            // Registra o histórico auditável
            return MovimentacaoEstoque::create([
                'produto_id' => $produtoAtualizado->id,
                'usuario_id' => $usuarioId,
                'tipo_movimentacao' => $tipoNormalizado,
                'quantidade' => $qtdRegistrada,
                'quantidade_anterior' => $qtdAnterior,
                'quantidade_posterior' => $qtdPosterior,
                'motivo' => $motivoPadrao,
                'observacoes' => $observacoes,
            ]);
        });
    }

    /**
     * Registra o consumo de um produto em uma tarefa e efetua a saída correspondente do estoque.
     *
     * @param Tarefa|int $tarefa
     * @param Produto|int $produto
     * @param float $quantidadeUtilizada
     * @param User|int|null $usuario
     * @return TarefaProduto
     * @throws DomainException
     */
    public function registrarConsumoEmTarefa(
        Tarefa|int $tarefa,
        Produto|int $produto,
        float $quantidadeUtilizada,
        User|int|null $usuario = null
    ): TarefaProduto {
        $tarefaModel = $tarefa instanceof Tarefa ? $tarefa : Tarefa::with('ordemServico')->findOrFail($tarefa);
        $produtoModel = $produto instanceof Produto ? $produto : Produto::findOrFail($produto);

        if ($tarefaModel->status === 'concluida') {
            throw new DomainException("Não é possível registrar consumo de produtos em uma tarefa já concluída.");
        }

        return DB::transaction(function () use ($tarefaModel, $produtoModel, $quantidadeUtilizada, $usuario) {
            $numeroOS = $tarefaModel->ordemServico ? $tarefaModel->ordemServico->numero : "OS-{$tarefaModel->ordem_servico_id}";
            $motivo = "Consumo na tarefa #{$tarefaModel->id} ({$numeroOS})";

            // Executa a saída no estoque através do método centralizado
            $this->movimentar(
                produto: $produtoModel,
                tipo: 'saida',
                quantidade: $quantidadeUtilizada,
                usuario: $usuario,
                motivo: $motivo
            );

            // Registra a associação em tarefa_produto com o custo unitário congelado
            return TarefaProduto::create([
                'tarefa_id' => $tarefaModel->id,
                'produto_id' => $produtoModel->id,
                'quantidade_utilizada' => $quantidadeUtilizada,
                'custo_unitario' => $produtoModel->custo_unitario,
            ]);
        });
    }
}