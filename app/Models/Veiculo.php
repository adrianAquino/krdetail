<?php

namespace App\Models;

use App\Support\AdminTenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Veiculo extends Model
{
    use HasFactory;

    protected $table = 'veiculos';

    protected $fillable = ['cliente_id', 'placa', 'marca', 'modelo', 'ano', 'cor', 'observacoes'];

    protected static function booted(): void
    {
        static::addGlobalScope('empresa_veiculo', function (Builder $builder) {
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

        static::saving(function (Veiculo $veiculo) {
            if (!Auth::hasUser() || !$veiculo->cliente_id) {
                return;
            }
            $user = Auth::user();
            if ($user instanceof User && ($user->isDono() || $user->isFuncionario())) {
                $empresaId = $user->getEmpresaAtivaId();
                $clienteEmpresaId = Cliente::withoutGlobalScopes()
                    ->where('id', $veiculo->cliente_id)
                    ->value('empresa_id');

                if ($empresaId === null || ($clienteEmpresaId !== null && (int) $clienteEmpresaId !== $empresaId)) {
                    throw new \DomainException('O cliente selecionado para este veículo pertence a outra empresa.');
                }
            }
        });
    }

    /**
     * Cliente proprietário do veículo.
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Histórico de agendamentos do veículo.
     */
    public function agendamentos()
    {
        return $this->hasMany(Agendamento::class);
    }
}
