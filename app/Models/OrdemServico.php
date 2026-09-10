<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdemServico extends Model
{
    use HasFactory;
    protected $table = 'ordem_servicos';
    protected $fillable = ['agendamento_id', 'numero', 'data_inicio', 'data_fim', 'status', 'observacoes',];
    protected function casts(): array
    {
        return ['data_inicio' => 'datetime', 'data_fim' => 'datetime',];
    }
    /** * Agendamento que originou a ordem de serviço. */ 
    public function agendamento()
    {
        return $this->belongsTo(Agendamento::class);
    }
    /** * Tarefas pertencentes à ordem de serviço. */ 
    public function tarefas()
    {
        return $this->hasMany(Tarefa::class);
    }
}
