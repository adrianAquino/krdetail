<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendamentoServico extends Model
{
    use HasFactory;
    protected $table = 'agendamento_servico';
    protected $fillable = ['agendamento_id', 'servico_id', 'valor_servico', 'tempo_estimado_minutos',];
    protected function casts(): array
    {
        return ['valor_servico' => 'decimal:2',];
    }
    /** * Agendamento ao qual o serviço pertence. */ 
    public function agendamento()
    {
        return $this->belongsTo(Agendamento::class);
    }
    /** * Serviço contratado. */ 
    public function servico()
    {
        return $this->belongsTo(Servico::class);
    }
    /** * Tarefa criada para a execução deste serviço. */ 
    public function tarefa()
    {
        return $this->hasOne(Tarefa::class);
    }
}
