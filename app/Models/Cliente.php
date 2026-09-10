<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;
    protected $table = 'clientes';
    protected $fillable = [
        'user_id',
        'nome',
        'cpf_cnpj',
        'telefone',
        'email',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'estado',
    ];


    /** * Conta de usuário associada ao cliente. */ 
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    /** * Veículos pertencentes ao cliente. */ 
    public function veiculos()
    {
        return $this->hasMany(Veiculo::class);
    }
    /** * Agendamentos realizados pelo cliente. */ 
    public function agendamentos()
    {
        return $this->hasMany(Agendamento::class);
    }
    /** * Notificações destinadas ao cliente. */ 
    public function notificacoes()
    {
        return $this->hasMany(Notificacao::class);
    }
}
