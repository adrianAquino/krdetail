<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Funcionario extends Model
{
    use BelongsToEmpresa;

    protected $table = 'funcionarios';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'cargo',
        'ativo',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function getNomeAttribute(): string
    {
        return $this->user?->name ?? 'Colaborador';
    }

    /**
     * Conta de usuário do funcionário.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tarefas atribuídas ao funcionário.
     */
    public function tarefas()
    {
        return $this->hasMany(Tarefa::class);
    }
}
