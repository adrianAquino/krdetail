<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LancamentoFinanceiro extends Model
{
    use BelongsToEmpresa, HasFactory;

    protected $table = 'lancamentos_financeiros';

    protected $fillable = [
        'empresa_id',
        'tarefa_id',
        'usuario_id',
        'tipo',
        'categoria',
        'descricao',
        'valor',
        'data_lancamento',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'data_lancamento' => 'date',
        ];
    }

    /**
     * Tarefa que originou o lançamento financeiro (nula para lançamentos manuais).
     */
    public function tarefa()
    {
        return $this->belongsTo(Tarefa::class);
    }

    /**
     * Usuário responsável pelo lançamento.
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
