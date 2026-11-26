<?php

namespace App\Models;

use App\Support\AdminTenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class MovimentacaoEstoque extends Model
{
    use HasFactory;

    protected $table = 'movimentacoes_estoque';

    protected static function booted(): void
    {
        static::addGlobalScope('empresa_movimentacao', function (Builder $builder) {
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
        'produto_id',
        'tarefa_produto_id',
        'usuario_id',
        'tipo_movimentacao',
        'quantidade',
        'quantidade_anterior',
        'quantidade_posterior',
        'motivo',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'quantidade_anterior' => 'decimal:3',
            'quantidade_posterior' => 'decimal:3',
        ];
    }

    /**
     * Produto movimentado.
     */
    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }

    /**
     * Vínculo direto com o consumo de produto em tarefa (Seção 9.6).
     */
    public function tarefaProduto()
    {
        return $this->belongsTo(TarefaProduto::class, 'tarefa_produto_id');
    }

    /**
     * Usuário responsável pelo registro da movimentação.
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
