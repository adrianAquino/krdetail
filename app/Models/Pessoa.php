<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pessoa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pessoas';

    protected $fillable = [
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
        // Sempre que os dados cadastrais da Pessoa forem atualizados,
        // propaga de forma consistente para os registros vinculados em users e clientes.
        static::saved(function (Pessoa $pessoa) {
            User::withoutEvents(function () use ($pessoa) {
                $dadosUser = [
                    'name' => $pessoa->nome,
                    'telefone' => $pessoa->telefone,
                ];
                if (! empty($pessoa->email)) {
                    $dadosUser['email'] = $pessoa->email;
                }
                User::where('pessoa_id', $pessoa->id)->update($dadosUser);
            });

            Cliente::withoutEvents(function () use ($pessoa) {
                Cliente::withoutGlobalScopes()->where('pessoa_id', $pessoa->id)->update([
                    'nome' => $pessoa->nome,
                    'cpf_cnpj' => $pessoa->cpf_cnpj,
                    'telefone' => $pessoa->telefone ?? '',
                    'email' => $pessoa->email,
                    'cep' => $pessoa->cep,
                    'logradouro' => $pessoa->logradouro,
                    'numero' => $pessoa->numero,
                    'complemento' => $pessoa->complemento,
                    'bairro' => $pessoa->bairro,
                    'cidade' => $pessoa->cidade,
                    'estado' => $pessoa->estado,
                ]);
            });
        });

        // Ao excluir logicamente, libera cpf_cnpj para evitar bloqueio permanente em UNIQUE
        static::deleting(function (Pessoa $pessoa) {
            if (! $pessoa->isForceDeleting() && $pessoa->cpf_cnpj !== null) {
                self::withoutEvents(function () use ($pessoa) {
                    self::where('id', $pessoa->id)->update(['cpf_cnpj' => null]);
                });
            }
        });
    }

    public function user()
    {
        return $this->hasOne(User::class, 'pessoa_id');
    }

    public function cliente()
    {
        return $this->hasOne(Cliente::class, 'pessoa_id');
    }
}
