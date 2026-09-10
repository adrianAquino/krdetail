<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Produto extends Model
{
    //
    use HasFactory;
    protected $table = 'produtos';

    protected $fillable = ['nome', 'descricao', 'unidade_medida', 'quantidade_estoque', 'estoque_minimo', 'custo_unitario', 'ativo',];
    protected function casts(): array
    {
        return ['quantidade_estoque' => 'decimal:3', 'estoque_minimo' => 'decimal:3', 'custo_unitario' => 'decimal:4', 'ativo' => 'boolean',];
    }
    /** * Registros de utilização do produto em tarefas. */ 
    public function tarefasProdutos()
    {
        return $this->hasMany(TarefaProduto::class);
    }
    /** * Tarefas nas quais o produto foi utilizado. */ 
    public function tarefas()
    {
        return $this->belongsToMany(Tarefa::class, 'tarefa_produto')->withPivot(['id', 'quantidade_utilizada', 'custo_unitario',])->withTimestamps();
    }
    /** * Histórico de movimentações do produto. */ public function movimentacoes()
    {
        return $this->hasMany(MovimentacaoEstoque::class);
    }
}
