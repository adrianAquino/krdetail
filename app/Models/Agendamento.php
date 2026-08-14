<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agendamento extends Model
{
    //
    use HasFactory;

    protected $table = "agendamentos";
    protected $fillable = [
        'cliente_id',
        'veiculo_id',
        'data_agendamento',
        'hora_agendamento',
        'status',
        'origem_agendamento',
        'observacoes'
    ];
}
