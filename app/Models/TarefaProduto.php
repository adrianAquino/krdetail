<?php

namespace App\Models;

use App\Support\AdminTenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TarefaProduto extends Model
{
    use HasFactory;

    protected $table = 'tarefa_produto';

    protected static function booted(): void
    {
        static::addGlobalScope('empresa_tarefa_produto', function (Builder $builder) {
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
                $builder->whereHas('produto', function (Builder $pq) use ($empresaId) {
                    $pq->withoutGlobalScopes()->where('empresa_id', $empresaId);
                });
            } elseif ($user->isCliente()) {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    protected $fillable = [
        'tarefa_id',
        'produto_id',
        'quantidade_utilizada',
        'custo_unitario',
    ];

    protected function casts(): array
    {
        return [
            'quantidade_utilizada' => 'decimal:3',
            'custo_unitario' => 'decimal:4',
        ];
    }

    /**
     * Custo total proporcional do volume consumido nesta tarefa.
     */
    public function getCustoTotalAttribute(): float
    {
        return round((float) $this->quantidade_utilizada * (float) $this->custo_unitario, 2);
    }

    /**
     * Tarefa na qual o produto foi utilizado.
     */
    public function tarefa()
    {
        return $this->belongsTo(Tarefa::class);
    }

    /**
     * Produto utilizado.
     */
    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }
}
