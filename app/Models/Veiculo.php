<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Veiculo extends Model
{
    use HasFactory;
    protected $table = "veiculos";

    protected $fillable = ['cliente_id', 'placa', 'marca', 'modelo', 'ano', 'cor', 'observacoes',];
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
