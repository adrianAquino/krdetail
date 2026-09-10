<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agendamento extends Model
{
    //
    use HasFactory;

    protected $table = "agendamentos";
    protected $fillable = ['cliente_id', 'veiculo_id', 'data_inicio', 'data_fim', 'status', 'origem', 'observacoes',];
    protected function casts(): array
    {
        return ['data_inicio' => 'datetime', 'data_fim' => 'datetime',];
    }
    /** * Cliente responsável pelo agendamento. */ 
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
    /** * Veículo relacionado ao agendamento. */ 
    public function veiculo()
    {
        return $this->belongsTo(Veiculo::class);
    }
    /** * Serviços contratados no agendamento. */ 
    public function agendamentosServicos()
    {
        return $this->hasMany(AgendamentoServico::class);
    }
    /** * Serviços relacionados ao agendamento. */ 
    public function servicos()
    {
        return $this->belongsToMany(Servico::class, 'agendamento_servico')->withPivot(['id', 'valor_servico', 'tempo_estimado_minutos',])->withTimestamps();
    }
    /** * Ordem de serviço gerada para o agendamento. */ 
    public function ordemServico()
    {
        return $this->hasOne(OrdemServico::class);
    }
}
