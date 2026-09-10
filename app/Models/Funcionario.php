<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Funcionario extends Model
{
    
    protected $table = 'funcionarios';
    protected $fillable = ['user_id', 'cargo', 'ativo', 'observacoes',];
    protected function casts(): array
    {
        return ['ativo' => 'boolean',];
    }
    /** * Conta de usuário do funcionário. */ 
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    /** * Tarefas atribuídas ao funcionário. */ 
    public function tarefas()
    {
        return $this->hasMany(Tarefa::class);
    }
}
