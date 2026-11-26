<?php

namespace App\Models;

use App\Support\AdminTenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
        static::addGlobalScope('empresa_tarefa', function (Builder $builder) {
            if (!Auth::hasUser()) {
                return;
            }
            $user = Auth::user();
            if (!($user instanceof User)) {
                return;
            }
            if ($user->isDono() || $user->isFuncionario() || $user->isAdministrador()) {
                $empresaId = $user->isAdministrador()
                    ? AdminTenantContext::getEmpresaId()
                    : $user->getEmpresaAtivaId();
                if ($user->isAdministrador() && $empresaId === null) {
                    return;
                }
                if ($empresaId === null) {
                    $builder->whereRaw('1 = 0');
                    return;
                }
                $builder->whereHas('ordemServico', function (Builder $oq) use ($empresaId) {
                    $oq->withoutGlobalScopes()->whereHas('agendamento', function (Builder $aq) use ($empresaId) {
                        $aq->withoutGlobalScopes()->where(function (Builder $q) use ($empresaId) {
                            $q->where('agendamentos.empresa_id', $empresaId)
                                ->orWhere(function (Builder $q2) use ($empresaId) {
                                    $q2->whereNull('agendamentos.empresa_id')
                                        ->whereHas('cliente', fn (Builder $cq) => $cq->withoutGlobalScopes()->where('empresa_id', $empresaId));
                                });
                        });
                    });
                });
            } elseif ($user->isCliente()) {
                $builder->whereHas('ordemServico', function (Builder $oq) use ($user) {
                    $oq->withoutGlobalScopes()->whereHas('agendamento', function (Builder $aq) use ($user) {
                        $aq->withoutGlobalScopes()->whereHas('cliente', function (Builder $cq) use ($user) {
                            $cq->withoutGlobalScopes()->where('user_id', $user->id);
                        });
                    });
                });
            }
        });

        static::saving(function (Tarefa $tarefa) {
            if ($tarefa->ordem_servico_id && $tarefa->agendamento_servico_id) {
                $osAgendamentoId = (!$tarefa->isDirty('ordem_servico_id') && $tarefa->relationLoaded('ordemServico') && $tarefa->ordemServico)
                    ? $tarefa->ordemServico->agendamento_id
                    : OrdemServico::withoutGlobalScopes()->where('id', $tarefa->ordem_servico_id)->value('agendamento_id');

                $itemAgendamentoId = (!$tarefa->isDirty('agendamento_servico_id') && $tarefa->relationLoaded('agendamentoServico') && $tarefa->agendamentoServico)
                    ? $tarefa->agendamentoServico->agendamento_id
                    : AgendamentoServico::withoutGlobalScopes()->where('id', $tarefa->agendamento_servico_id)->value('agendamento_id');

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
