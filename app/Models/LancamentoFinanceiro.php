<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class LancamentoFinanceiro extends Model
{
    use HasFactory;
    protected $table = 'lancamentos_financeiros';
    protected $fillable = ['tarefa_id', 'usuario_id', 'tipo', 'categoria', 'descricao', 'valor', 'data_lancamento',];

    protected static function booted(): void
    {
        static::addGlobalScope('empresa_lancamento', function (Builder $builder) {
            if (!Auth::hasUser()) {
                return;
            }
            $user = Auth::user();
            if ($user instanceof User && ($user->isDono() || $user->isFuncionario())) {
                $empresaId = $user->getEmpresaAtivaId();
                $builder->where(function ($q) use ($empresaId) {
                    $q->whereHas('tarefa.ordemServico.agendamento.cliente')
                        ->orWhereHas('usuario', fn ($uq) => $uq->where('empresa_id', $empresaId));
                });
            }
        });
    }
    protected function casts(): array
    {
        return ['valor' => 'decimal:2', 'data_lancamento' => 'date',];
    }
    /** * Tarefa que originou o lançamento financeiro. * * Pode ser nula para despesas ou lançamentos manuais. */ 
    public function tarefa()
    {
        return $this->belongsTo(Tarefa::class);
    }
    /** * Usuário responsável pelo lançamento. */ 
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
