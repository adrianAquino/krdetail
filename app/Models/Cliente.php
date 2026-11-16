<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = [
        'pessoa_id',
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

    protected static function booted(): void
    {
        // Centraliza os dados cadastrais na tabela `pessoas` (Seção 9.3c)
        // e mantém sincronia automática com `users` quando o cliente possui conta.
        static::saving(function (Cliente $cliente) {
            $dadosPessoa = [
                'nome' => $cliente->nome,
                'cpf_cnpj' => $cliente->cpf_cnpj,
                'telefone' => $cliente->telefone,
                'email' => $cliente->email,
                'cep' => $cliente->cep,
                'logradouro' => $cliente->logradouro,
                'numero' => $cliente->numero,
                'complemento' => $cliente->complemento,
                'bairro' => $cliente->bairro,
                'cidade' => $cliente->cidade,
                'estado' => $cliente->estado,
            ];

            if (!$cliente->pessoa_id && $cliente->user_id) {
                $user = User::find($cliente->user_id);
                if ($user && $user->pessoa_id) {
                    $cliente->pessoa_id = $user->pessoa_id;
                }
            }

            if ($cliente->pessoa_id) {
                Pessoa::withoutEvents(function () use ($cliente, $dadosPessoa) {
                    Pessoa::where('id', $cliente->pessoa_id)->update($dadosPessoa);
                });
            } else {
                $pessoa = Pessoa::withoutEvents(fn () => Pessoa::create($dadosPessoa));
                $cliente->pessoa_id = $pessoa->id;
            }
        });

        static::saved(function (Cliente $cliente) {
            if ($cliente->user_id) {
                User::withoutEvents(function () use ($cliente) {
                    $dadosUser = [
                        'pessoa_id' => $cliente->pessoa_id,
                        'name' => $cliente->nome,
                        'telefone' => $cliente->telefone,
                    ];
                    if (!empty($cliente->email)) {
                        $dadosUser['email'] = $cliente->email;
                    }
                    User::where('id', $cliente->user_id)->update($dadosUser);
                });
            }
        });
    }

    /**
     * Registro centralizado de dados pessoais/cadastrais (Tabela PESSOAS - 3FN).
     */
    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    /**
     * Conta de usuário associada ao cliente.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Veículos pertencentes ao cliente.
     */
    public function veiculos()
    {
        return $this->hasMany(Veiculo::class);
    }

    /**
     * Agendamentos realizados pelo cliente.
     */
    public function agendamentos()
    {
        return $this->hasMany(Agendamento::class);
    }

    /**
     * Notificações destinadas ao cliente.
     */
    public function notificacoes()
    {
        return $this->hasMany(Notificacao::class);
    }
}
