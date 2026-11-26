<?php

namespace App\Models;

use App\Support\AdminTenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OrdemServico extends Model
{
    use HasFactory;

    protected $table = 'ordem_servicos';

    protected static function booted(): void
    {
        static::addGlobalScope('empresa_os', function (Builder $builder) {
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
                $builder->whereHas('agendamento', function (Builder $aq) use ($empresaId) {
                    $aq->withoutGlobalScopes()->where(function (Builder $q) use ($empresaId) {
                        $q->where('agendamentos.empresa_id', $empresaId)
                            ->orWhere(function (Builder $q2) use ($empresaId) {
                                $q2->whereNull('agendamentos.empresa_id')
                                    ->whereHas('cliente', fn (Builder $cq) => $cq->withoutGlobalScopes()->where('empresa_id', $empresaId));
                            });
                    });
                });
            } elseif ($user->isCliente()) {
                $builder->whereHas('agendamento', function (Builder $aq) use ($user) {
                    $aq->withoutGlobalScopes()->whereHas('cliente', function (Builder $cq) use ($user) {
                        $cq->withoutGlobalScopes()->where('user_id', $user->id);
                    });
                });
            }
        });
    }

    protected $fillable = [
        'agendamento_id',
        'numero',
        'data_inicio',
        'data_fim',
        'status',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'datetime',
            'data_fim' => 'datetime',
        ];
    }

    /**
     * Quantidade total de tarefas vinculadas à Ordem de Serviço.
     */
    public function getTarefasTotalAttribute(): int
    {
        if ($this->relationLoaded('tarefas')) {
            return $this->tarefas->count();
        }

        return $this->tarefas()->count();
    }

    /**
     * Quantidade de tarefas concluídas na Ordem de Serviço.
     */
    public function getTarefasConcluidasAttribute(): int
    {
        if ($this->relationLoaded('tarefas')) {
            return $this->tarefas->where('status', 'concluida')->count();
        }

        return $this->tarefas()->where('status', 'concluida')->count();
    }

    /**
     * Nomes dos serviços vinculados às tarefas desta Ordem de Serviço.
     */
    public function getServicosNomesAttribute(): string
    {
        if ($this->relationLoaded('tarefas')) {
            $nomes = $this->tarefas
                ->map(fn ($t) => $t->agendamentoServico?->servico?->nome)
                ->filter()
                ->unique()
                ->join(' + ');

            if ($nomes !== '') {
                return $nomes;
            }
        }

        return $this->agendamento?->servicos_nomes ?? '';
    }

    /**
     * Agendamento que originou a ordem de serviço.
     */
    public function agendamento()
    {
        return $this->belongsTo(Agendamento::class);
    }

    /**
     * Tarefas pertencentes à ordem de serviço.
     */
    public function tarefas()
    {
        return $this->hasMany(Tarefa::class);
    }
}
