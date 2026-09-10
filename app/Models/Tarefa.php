<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tarefa extends Model
{
    use HasFactory;

    protected $table = 'tarefas';
    protected $fillable = ['ordem_servico_id', 'agendamento_servico_id', 'funcionario_id', 'status', 'data_inicio', 'data_conclusao', 'observacoes',];
    protected function casts(): array
    {
        return ['data_inicio' => 'datetime', 'data_conclusao' => 'datetime',];
    }
    /** * Ordem de serviço à qual a tarefa pertence. */ 
    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class);
    }
    /** * Serviço contratado que originou a tarefa. */ 
    public function agendamentoServico()
    {
        return $this->belongsTo(AgendamentoServico::class);
    }
    /** * Funcionário responsável pela tarefa. */ 
    public function funcionario()
    {
        return $this->belongsTo(Funcionario::class);
    }
    /** * Produtos utilizados durante a tarefa. */ 
    public function tarefasProdutos()
    {
        return $this->hasMany(TarefaProduto::class);
    }
    /** * Produtos utilizados durante a execução da tarefa. */ 
    public function produtos()
    {
        return $this->belongsToMany(Produto::class, 'tarefa_produto')->withPivot(['id', 'quantidade_utilizada', 'custo_unitario',])->withTimestamps();
    }
    /** * Lançamento financeiro gerado pela conclusão da tarefa. */ 
    public function lancamentoFinanceiro()
    {
        return $this->hasOne(LancamentoFinanceiro::class);
    }
}
