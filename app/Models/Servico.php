<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servico extends Model
{
    use HasFactory;
    protected $table = 'servicos';
    protected $fillable = ['nome', 'descricao', 'preco_base', 'tempo_estimado_minutos', 'ativo',];
    protected function casts(): array
    {
        return ['preco_base' => 'decimal:2', 'ativo' => 'boolean',];
    }
    /** * Registros de agendamentos que contrataram este serviço. */ 
    public function agendamentosServicos()
    {
        return $this->hasMany(AgendamentoServico::class);
    }
    /** * Agendamentos que possuem este serviço. */ 
    public function agendamentos()
    {
        return $this->belongsToMany(Agendamento::class, 'agendamento_servico')->withPivot(['id', 'valor_servico', 'tempo_estimado_minutos',])->withTimestamps();
    }
}
