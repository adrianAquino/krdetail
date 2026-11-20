<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory, BelongsToEmpresa;

    protected $table = 'clientes';

    protected $fillable = [
        'pessoa_id',
        'empresa_id',
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

    protected function casts(): array
    {
        return [
            'pessoa_id' => 'integer',
            'empresa_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Centraliza os dados cadastrais na tabela `pessoas` (Seção 9.3c)
        // e mantém sincronia automática com `users` quando o cliente possui conta.
        static::saving(function (Cliente $cliente) {
            if (! $cliente->pessoa_id && $cliente->user_id) {
                $user = User::find($cliente->user_id);
                if ($user && $user->pessoa_id) {
                    $cliente->pessoa_id = $user->pessoa_id;
                }
            }

            $camposPessoa = [
                'nome', 'cpf_cnpj', 'telefone', 'email',
                'cep', 'logradouro', 'numero', 'complemento',
                'bairro', 'cidade', 'estado',
            ];

            $dadosPessoa = [];
            foreach ($camposPessoa as $campo) {
                $valor = $cliente->{$campo};
                if ($cliente->exists ? $cliente->isDirty($campo) : $valor !== null) {
                    $dadosPessoa[$campo] = $valor;
                }
            }

            if ($cliente->pessoa_id) {
                if (! empty($dadosPessoa)) {
                    Pessoa::withoutEvents(function () use ($cliente, $dadosPessoa) {
                        Pessoa::withTrashed()->where('id', $cliente->pessoa_id)->update($dadosPessoa);
                    });
                }
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
                    if ($cliente->empresa_id) {
                        $dadosUser['empresa_id'] = $cliente->empresa_id;
                    }
                    if (! empty($cliente->email)) {
                        $dadosUser['email'] = $cliente->email;
                    }
                    User::where('id', $cliente->user_id)->update($dadosUser);
                });
            }
        });
    }

    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function veiculos()
    {
        return $this->hasMany(Veiculo::class);
    }

    public function agendamentos()
    {
        return $this->hasMany(Agendamento::class);
    }

    public function notificacoes()
    {
        return $this->hasMany(Notificacao::class);
    }
}
