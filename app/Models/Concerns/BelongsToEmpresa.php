<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToEmpresa
{
    public static function bootBelongsToEmpresa(): void
    {
        static::addGlobalScope('empresa', function (Builder $builder) {
            if (!Auth::hasUser()) {
                return;
            }

            /** @var User|null $user */
            $user = Auth::user();
            if (!$user instanceof User) {
                return;
            }

            // Administrador Geral do Sistema tem visão global de todas as empresas
            if ($user->isAdministrador()) {
                return;
            }

            // Dono da empresa e Funcionário só podem ver e manipular dados relacionados à empresa deles
            if ($user->isDono() || $user->isFuncionario()) {
                $empresaId = $user->getEmpresaAtivaId();
                $table = $builder->getModel()->getTable();

                if ($empresaId) {
                    $builder->where("{$table}.empresa_id", $empresaId);
                } else {
                    $builder->whereRaw('1 = 0');
                }
                return;
            }

            // Cliente visualiza serviços da empresa à qual está vinculado
            if ($user->isCliente() && $builder->getModel() instanceof Servico) {
                $empresaId = $user->getEmpresaAtivaId();
                if ($empresaId) {
                    $table = $builder->getModel()->getTable();
                    $builder->where("{$table}.empresa_id", $empresaId);
                }
            }
        });

        static::creating(function ($model) {
            if (empty($model->empresa_id)) {
                if (Auth::hasUser() && Auth::user() instanceof User) {
                    $model->empresa_id = Auth::user()->getEmpresaAtivaId();
                }

                if (empty($model->empresa_id)) {
                    $model->empresa_id = Empresa::query()->value('id');
                }
            }
        });
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
