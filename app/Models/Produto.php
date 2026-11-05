<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    use HasFactory;

    protected $table = 'produtos';

    protected $fillable = [
        'nome',
        'descricao',
        'unidade_medida',
        'volume_unitario',
        'quantidade_estoque',
        'estoque_minimo',
        'custo_unitario',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'volume_unitario' => 'decimal:3',
            'quantidade_estoque' => 'decimal:3',
            'estoque_minimo' => 'decimal:3',
            'custo_unitario' => 'decimal:4',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Quantidade de unidades/embalagens em estoque (arredondado para cima).
     * Só diminui 1 unidade quando o volume completo daquela unidade se esgota.
     */
    public function getUnidadesEstoqueAttribute(): int
    {
        $volUnit = (float) ($this->volume_unitario > 0 ? $this->volume_unitario : 1);
        $volAtual = (float) $this->quantidade_estoque;

        if ($volAtual <= 0) {
            return 0;
        }

        return (int) ceil(round($volAtual / $volUnit, 6));
    }

    /**
     * Estoque mínimo equivalente em unidades/embalagens.
     */
    public function getUnidadesMinimasAttribute(): int
    {
        $volUnit = (float) ($this->volume_unitario > 0 ? $this->volume_unitario : 1);
        $volMin = (float) $this->estoque_minimo;

        if ($volMin <= 0) {
            return 0;
        }

        return (int) ceil(round($volMin / $volUnit, 6));
    }

    /**
     * Volume restante na embalagem atualmente aberta/em uso.
     */
    public function getVolumeUnidadeAbertaAttribute(): float
    {
        $volUnit = (float) ($this->volume_unitario > 0 ? $this->volume_unitario : 1);
        $volAtual = (float) $this->quantidade_estoque;

        if ($volAtual <= 0) {
            return 0.0;
        }

        $resto = fmod($volAtual, $volUnit);

        return $resto > 0.0001 ? round($resto, 3) : $volUnit;
    }

    /**
     * Custo proporcional por 1 medida de volume (ex: R$ por ml ou L).
     */
    public function getCustoPorVolumeAttribute(): float
    {
        $volUnit = (float) ($this->volume_unitario > 0 ? $this->volume_unitario : 1);

        return ((float) $this->custo_unitario) / $volUnit;
    }

    /**
     * Valor financeiro total do estoque disponível.
     */
    public function getValorTotalEstoqueAttribute(): float
    {
        $volUnit = (float) ($this->volume_unitario > 0 ? $this->volume_unitario : 1);

        return ((float) $this->quantidade_estoque / $volUnit) * (float) $this->custo_unitario;
    }

    /**
     * Registros de utilização do produto em tarefas.
     */
    public function tarefasProdutos()
    {
        return $this->hasMany(TarefaProduto::class);
    }

    /**
     * Tarefas nas quais o produto foi utilizado.
     */
    public function tarefas()
    {
        return $this->belongsToMany(Tarefa::class, 'tarefa_produto')
            ->withPivot(['id', 'quantidade_utilizada', 'custo_unitario'])
            ->withTimestamps();
    }

    /**
     * Histórico de movimentações do produto.
     */
    public function movimentacoes()
    {
        return $this->hasMany(MovimentacaoEstoque::class);
    }
}
