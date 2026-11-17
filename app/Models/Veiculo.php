<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Veiculo extends Model
{
    use HasFactory;
    protected $table = "veiculos";

    protected $fillable = ['cliente_id', 'placa', 'marca', 'modelo', 'ano', 'cor', 'observacoes',];

    protected static function booted(): void
    {
        static::addGlobalScope('empresa_veiculo', function (Builder $builder) {
            if (!Auth::hasUser()) {
                return;
            }
            $user = Auth::user();
            if ($user instanceof User && ($user->isDono() || $user->isFuncionario())) {
                $builder->whereHas('cliente');
            }
        });
    }
    /** * Cliente proprietário do veículo. */ 
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
    /** * Histórico de agendamentos do veículo. */ 
    public function agendamentos()
    {
        return $this->hasMany(Agendamento::class);
    }
}
