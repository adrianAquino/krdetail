<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AgendamentoServico extends Model
{
    use HasFactory;

    protected $table = 'agendamento_servico';

    protected $fillable = ['agendamento_id', 'servico_id', 'valor_servico', 'tempo_estimado_minutos'];

    protected static function booted(): void
    {
        static::addGlobalScope('empresa_agendamento_servico', function (Builder $builder) {
            if (!Auth::hasUser()) {
                return;
            }
            $user = Auth::user();
            if (!($user instanceof User)) {
                return;
            }
            if ($user->isDono() || $user->isFuncionario()) {
                $empresaId = $user->getEmpresaAtivaId();
                if ($empresaId === null) {
                    $builder->whereRaw('1 = 0');
                    return;
                }
                $builder->whereHas('agendamento', function (Builder $aq) use ($empresaId) {
                    $aq->withoutGlobalScopes()->whereHas('cliente', function (Builder $cq) use ($empresaId) {
                        $cq->withoutGlobalScopes()->where('empresa_id', $empresaId);
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

    protected function casts(): array
    {
        return ['valor_servico' => 'decimal:2'];
    }

    /**
     * Agendamento ao qual o serviço pertence.
     */
    public function agendamento()
    {
        return $this->belongsTo(Agendamento::class);
    }

    /**
     * Serviço contratado.
     */
    public function servico()
    {
        return $this->belongsTo(Servico::class);
    }

    /**
     * Tarefa criada para a execução deste serviço.
     */
    public function tarefa()
    {
        return $this->hasOne(Tarefa::class);
    }
}
