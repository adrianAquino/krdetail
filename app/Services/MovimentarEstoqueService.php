<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\MovimentacaoEstoque;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\Tarefa;
use App\Models\TarefaProduto;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MovimentarEstoqueService
{
    public const QUANTIDADE_MAXIMA = 999999999.999;
    public const CUSTO_MAXIMO = 99999999.9999;

    /**
     * Executa uma movimentação de estoque (entrada, saída ou ajuste) com lock
     * pessimista e aritmética em milésimos inteiros para evitar erro de ponto flutuante.
     */
    public function movimentar(
        Produto|int $produto,
        string $tipo,
        float $quantidade,
        User|int|null $usuario = null,
        string $motivo = 'Movimentação manual de estoque',
        ?string $observacoes = null,
        ?int $tarefaProdutoId = null,
        bool $permitirProdutoInativo = false
    ): MovimentacaoEstoque {
        if (!in_array($tipo, ['entrada', 'saida', 'ajuste'], true)) {
            throw new \InvalidArgumentException("Tipo de movimentação inválido: {$tipo}.");
        }

        if (!is_finite($quantidade)) {
            throw new \InvalidArgumentException('A quantidade informada é inválida.');
        }

        $quantidadeNorm = round($quantidade, 3);

        if ($tipo === 'ajuste') {
            if ((int) round($quantidadeNorm * 1000) < 0) {
                throw new \InvalidArgumentException('O saldo ajustado não pode ser negativo.');
            }
        } elseif ((int) round($quantidadeNorm * 1000) < 1) {
            throw new \InvalidArgumentException('A quantidade movimentada deve ser maior que zero (mín. 0,001).');
        }

        if ($quantidadeNorm > self::QUANTIDADE_MAXIMA) {
            throw new \DomainException('A quantidade informada excede o limite suportado pelo estoque (999.999.999,999).');
        }

        return DB::transaction(function () use (
            $produto,
            $tipo,
            $quantidadeNorm,
            $usuario,
            $motivo,
            $observacoes,
            $tarefaProdutoId,
            $permitirProdutoInativo
        ) {
            $produtoId = $produto instanceof Produto ? $produto->id : (int) $produto;

            $produtoTravado = Produto::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($produtoId);

            if (!$permitirProdutoInativo && !$produtoTravado->ativo) {
                throw new \DomainException("O produto \"{$produtoTravado->nome}\" está inativo e não pode ser movimentado.");
            }

            $qtdAnterior = round((float) $produtoTravado->quantidade_estoque, 3);
            $anteriorInt = (int) round($qtdAnterior * 1000);
            $qtdInt = (int) round($quantidadeNorm * 1000);

            if ($tipo === 'entrada') {
                $posteriorInt = $anteriorInt + $qtdInt;
                $qtdPosterior = round($posteriorInt / 1000, 3);
                $qtdRegistrada = $quantidadeNorm;
            } elseif ($tipo === 'saida') {
                if ($anteriorInt < $qtdInt) {
                    throw new \DomainException(
                        "Estoque insuficiente para \"{$produtoTravado->nome}\". Disponível: " .
                        number_format($qtdAnterior, 3, ',', '.') .
                        " {$produtoTravado->unidade_medida}, solicitado: " .
                        number_format($quantidadeNorm, 3, ',', '.') .
                        " {$produtoTravado->unidade_medida}."
                    );
                }
                $posteriorInt = $anteriorInt - $qtdInt;
                $qtdPosterior = round($posteriorInt / 1000, 3);
                $qtdRegistrada = $quantidadeNorm;
            } else {
                // Em ajuste, $quantidadeNorm representa o novo saldo absoluto
                $qtdPosterior = $quantidadeNorm;
                $qtdRegistrada = round(abs($qtdPosterior - $qtdAnterior), 3);
            }

            if ($qtdPosterior > self::QUANTIDADE_MAXIMA) {
                throw new \DomainException('O saldo resultante excede o limite máximo suportado (999.999.999,999).');
            }

            $produtoTravado->quantidade_estoque = $qtdPosterior;
            $produtoTravado->save();

            $usuarioId = $usuario instanceof User
                ? $usuario->id
                : ($usuario ? (int) $usuario : Auth::id());

            $motivoLimpo = trim($motivo) !== ''
                ? mb_substr(trim($motivo), 0, 255)
                : 'Movimentação de estoque';

            return MovimentacaoEstoque::create([
                'produto_id' => $produtoTravado->id,
                'tarefa_produto_id' => $tarefaProdutoId,
                'usuario_id' => $usuarioId,
                'tipo_movimentacao' => $tipo,
                'quantidade' => $qtdRegistrada,
                'quantidade_anterior' => $qtdAnterior,
                'quantidade_posterior' => $qtdPosterior,
                'motivo' => $motivoLimpo,
                'observacoes' => $observacoes !== null ? mb_substr(trim($observacoes), 0, 1000) : null,
            ]);
        });
    }

    /**
     * Registra ou acumula o consumo de um produto em uma tarefa de Ordem de Serviço,
     * validando tenant, status da OS/tarefa, limites e média ponderada de custo.
     */
    public function registrarConsumoEmTarefa(
        Tarefa|int $tarefa,
        Produto|int $produto,
        float $quantidadeUtilizada,
        User|int|null $usuario = null,
        ?string $observacoes = null
    ): TarefaProduto {
        if (!is_finite($quantidadeUtilizada)) {
            throw new \InvalidArgumentException('A quantidade informada é inválida.');
        }

        $quantidadeNorm = round($quantidadeUtilizada, 3);
        if ((int) round($quantidadeNorm * 1000) < 1) {
            throw new \InvalidArgumentException('A quantidade utilizada deve ser maior que zero.');
        }

        if ($quantidadeNorm > self::QUANTIDADE_MAXIMA) {
            throw new \DomainException('A quantidade informada excede o limite máximo permitido.');
        }

        return DB::transaction(function () use ($tarefa, $produto, $quantidadeNorm, $usuario, $observacoes) {
            $tarefaId = $tarefa instanceof Tarefa ? $tarefa->id : (int) $tarefa;
            $produtoId = $produto instanceof Produto ? $produto->id : (int) $produto;

            $ordemServicoId = $tarefa instanceof Tarefa && $tarefa->ordem_servico_id
                ? (int) $tarefa->ordem_servico_id
                : (int) Tarefa::withoutGlobalScopes()->where('id', $tarefaId)->value('ordem_servico_id');

            if ($ordemServicoId <= 0) {
                throw new \DomainException('Tarefa não encontrada.');
            }

            // Ordem de locks: OrdemServico -> Tarefa -> Produto -> TarefaProduto
            $ordemServico = OrdemServico::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($ordemServicoId);

            $tarefaTravada = Tarefa::withoutGlobalScopes()
                ->where('ordem_servico_id', $ordemServico->id)
                ->lockForUpdate()
                ->findOrFail($tarefaId);

            $agendamento = Agendamento::withoutGlobalScopes()
                ->with('cliente')
                ->find($ordemServico->agendamento_id);

            if (in_array($ordemServico->status, ['cancelada', 'concluida'], true)) {
                throw new \DomainException('Não é possível registrar consumo em uma Ordem de Serviço concluída ou cancelada.');
            }

            if ($tarefaTravada->status === 'concluida') {
                throw new \DomainException('Não é possível adicionar produtos a uma tarefa já concluída.');
            }

            $produtoTravado = Produto::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($produtoId);

            $empresaOsId = $agendamento?->cliente?->empresa_id;
            if ($empresaOsId !== null && $produtoTravado->empresa_id !== null && (int) $produtoTravado->empresa_id !== (int) $empresaOsId) {
                throw new \DomainException('O produto selecionado não pertence à empresa desta Ordem de Serviço.');
            }

            if (!$produtoTravado->ativo) {
                throw new \DomainException("O produto \"{$produtoTravado->nome}\" está inativo e não pode ser consumido.");
            }

            $custoPorVolume = round((float) ($produtoTravado->custo_por_volume ?? 0), 4);
            if ($custoPorVolume > self::CUSTO_MAXIMO) {
                throw new \DomainException('O custo unitário por volume do produto excede o limite suportado.');
            }

            $existente = TarefaProduto::withoutGlobalScopes()
                ->where('tarefa_id', $tarefaTravada->id)
                ->where('produto_id', $produtoTravado->id)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                $qtdAtual = round((float) $existente->quantidade_utilizada, 3);
                $novaQtd = round(((int) round($qtdAtual * 1000) + (int) round($quantidadeNorm * 1000)) / 1000, 3);

                if ($novaQtd > self::QUANTIDADE_MAXIMA) {
                    throw new \DomainException('O total consumido deste produto na tarefa excede o limite suportado.');
                }

                $custoAtual = (float) ($existente->custo_unitario ?? 0);
                $custoMedio = $novaQtd > 0
                    ? round((($qtdAtual * $custoAtual) + ($quantidadeNorm * $custoPorVolume)) / $novaQtd, 4)
                    : $custoPorVolume;

                $existente->quantidade_utilizada = $novaQtd;
                $existente->custo_unitario = $custoMedio;
                $existente->save();

                $tarefaProduto = $existente;
            } else {
                $tarefaProduto = TarefaProduto::create([
                    'tarefa_id' => $tarefaTravada->id,
                    'produto_id' => $produtoTravado->id,
                    'quantidade_utilizada' => $quantidadeNorm,
                    'custo_unitario' => $custoPorVolume,
                ]);
            }

            $this->movimentar(
                produto: $produtoTravado,
                tipo: 'saida',
                quantidade: $quantidadeNorm,
                usuario: $usuario,
                motivo: "Consumo na OS #{$ordemServico->numero}",
                observacoes: $observacoes,
                tarefaProdutoId: $tarefaProduto->id
            );

            return $tarefaProduto->fresh(['produto', 'tarefa']);
        });
    }
}
