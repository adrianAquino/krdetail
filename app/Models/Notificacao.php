<?php

namespace App\Models;

use App\Support\AdminTenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Notificacao extends Model
{
    use HasFactory;

    protected $table = 'notificacoes';

    protected $fillable = ['cliente_id', 'tipo', 'titulo', 'mensagem', 'status', 'data_envio'];

    protected static function booted(): void
    {
        static::addGlobalScope('empresa_notificacao', function (Builder $builder) {
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
                $builder->whereHas('cliente', function (Builder $cq) use ($empresaId) {
                    $cq->withoutGlobalScopes()->where('empresa_id', $empresaId);
                });
            } elseif ($user->isCliente()) {
                $builder->whereHas('cliente', function (Builder $cq) use ($user) {
                    $cq->withoutGlobalScopes()->where('user_id', $user->id);
                });
            }
        });
    }

    protected function casts(): array
    {
        return ['data_envio' => 'datetime'];
    }

    /**
     * Cliente destinatário da notificação.
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}
