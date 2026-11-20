<?php

namespace App\Models\Concerns;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToEmpresa
{
    public static function bootBelongsToEmpresa(): void
    {
        static::addGlobalScope('empresa', function (Builder $builder) {
            if (! Auth::hasUser()) {
                return;
            }

            /** @var User|null $user */
            $user = Auth::user();
            if (! $user instanceof User) {
                return;
            }

            // Administrador Geral do Sistema tem visão global de todas as empresas
            if ($user->isAdministrador()) {
                return;
            }

            $model = $builder->getModel();
            $table = $model->getTable();

            // Dono da empresa e Funcionário só podem ver e manipular dados relacionados à empresa ativa deles
            if ($user->isDono() || $user->isFuncionario()) {
                $empresaId = $user->getEmpresaAtivaId();

                if ($empresaId) {
                    $builder->where("{$table}.empresa_id", $empresaId);
                } else {
                    $builder->whereRaw('1 = 0');
                }
                return;
            }

            // Cliente: enxerga apenas serviços da própria empresa, seu próprio perfil de cliente,
            // e nunca visualiza listagens internas de funcionários ou insumos.
            if ($user->isCliente()) {
                if ($model instanceof Servico) {
                    $empresaId = $user->getEmpresaAtivaId();
                    if ($empresaId) {
                        $builder->where("{$table}.empresa_id", $empresaId);
                    } else {
                        $builder->whereRaw('1 = 0');
                    }
                } elseif ($model instanceof Cliente) {
                    $builder->where("{$table}.user_id", $user->id);
                } elseif ($model instanceof Produto || $model instanceof Funcionario) {
                    $builder->whereRaw('1 = 0');
                }
            }
        });

        static::creating(function ($model) {
            if (Auth::hasUser() && Auth::user() instanceof User) {
                /** @var User $user */
                $user = Auth::user();

                if ($user->isDono() || $user->isFuncionario()) {
                    $empresaId = $user->getEmpresaAtivaId();
                    if (! $empresaId) {
                        throw new DomainException('Seu usuário não está vinculado a uma empresa ativa.');
                    }

                    // Impede spoofing de empresa_id via mass assignment por Dono/Funcionário
                    $model->empresa_id = $empresaId;
                    return;
                }

                if ($user->isCliente() && empty($model->empresa_id)) {
                    $model->empresa_id = $user->getEmpresaAtivaId();
                }

                if ($user->isAdministrador() && empty($model->empresa_id)) {
                    $model->empresa_id = Empresa::query()->orderBy('id')->value('id');
                }
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('empresa_id') && Auth::hasUser() && Auth::user() instanceof User) {
                /** @var User $user */
                $user = Auth::user();
                if ($user->isDono() || $user->isFuncionario()) {
                    $empresaOriginal = $model->getOriginal('empresa_id');
                    if ($empresaOriginal !== null && (int) $model->empresa_id !== (int) $empresaOriginal) {
                        throw new DomainException('Não é permitido transferir este registro para outra empresa.');
                    }
                }
            }
        });
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
