<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tarefa extends Model
{
    use HasFactory;

    protected $table = 'tarefas';

    protected $fillable = [
        'ordem_servico_id',
        'agendamento_servico_id',
        'funcionario_id',
        'status',
        'data_inicio',
        'data_conclusao',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'datetime',
            'data_conclusao' => 'datetime',
        ];
    }

    /**
     * Seção 9.2 (b) — Integridade referencial entre Tarefas, Ordem de Serviço e AgendamentoServico:
     * Impede que uma tarefa seja vinculada a uma Ordem de Serviço de um agendamento e a um
     * serviço contratado (`agendamento_servico`) de outro agendamento diferente.
     */
    protected static function booted(): void
    {
        static::saving(function (Tarefa $tarefa) {
            if ($tarefa->ordem_servico_id && $tarefa->agendamento_servico_id) {
                $osAgendamentoId = $tarefa->relationLoaded('ordemServico') && $tarefa->ordemServico
                    ? $tarefa->ordemServico->agendamento_id
                    : OrdemServico::where('id', $tarefa->ordem_servico_id)->value('agendamento_id');

                $itemAgendamentoId = $tarefa->relationLoaded('agendamentoServico') && $tarefa->agendamentoServico
                    ? $tarefa->agendamentoServico->agendamento_id
                    : AgendamentoServico::where('id', $tarefa->agendamento_servico_id)->value('agendamento_id');

                if ($osAgendamentoId !== null && $itemAgendamentoId !== null && (int) $osAgendamentoId !== (int) $itemAgendamentoId) {
                    throw new \DomainException(
                        'Inconsistência referencial: a Ordem de Serviço e o serviço contratado (agendamento_servico) devem pertencer ao mesmo agendamento.'
                    );
                }
            }
        });
    }

    /**
     * Ordem de serviço à qual a tarefa pertence.
     */
    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class);
    }

    /**
     * Serviço contratado que originou a tarefa.
     */
    public function agendamentoServico()
    {
        return $this->belongsTo(AgendamentoServico::class);
    }

    /**
     * Funcionário responsável pela tarefa.
     */
    public function funcionario()
    {
        return $this->belongsTo(Funcionario::class);
    }

    /**
     * Produtos utilizados durante a tarefa.
     */
    public function tarefasProdutos()
    {
        return $this->hasMany(TarefaProduto::class);
    }

    /**
     * Produtos utilizados durante a execução da tarefa.
     */
    public function produtos()
    {
        return $this->belongsToMany(Produto::class, 'tarefa_produto')
            ->withPivot(['id', 'quantidade_utilizada', 'custo_unitario'])
            ->withTimestamps();
    }

    /**
     * Lançamento financeiro gerado pela conclusão da tarefa.
     */
    public function lancamentoFinanceiro()
    {
        return $this->hasOne(LancamentoFinanceiro::class);
    }

    public function getServicoNomeAttribute(): string
    {
        return $this->agendamentoServico?->servico?->nome ?? 'Execução de Detalhamento';
    }

    public function getTituloAttribute(): string
    {
        return $this->servico_nome;
    }

    public function getServicoDescricaoAttribute(): string
    {
        return $this->agendamentoServico?->servico?->descricao ?? '';
    }

    public function getValorServicoAttribute(): float
    {
        return (float) ($this->agendamentoServico?->valor_servico ?? $this->agendamentoServico?->servico?->preco_base ?? 0);
    }
}
