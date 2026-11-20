<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'empresas';

    protected $fillable = [
        'dono_id',
        'nome_fantasia',
        'razao_social',
        'cnpj',
        'inscricao_estadual',
        'telefone',
        'whatsapp',
        'email',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'horario_atendimento',
        'ativo',
    ];

    protected $attributes = [
        'ativo' => true,
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Libera o CNPJ ao excluir logicamente a empresa para permitir recadastro futuro
        static::deleting(function (Empresa $empresa) {
            if (! $empresa->isForceDeleting() && $empresa->cnpj !== null) {
                self::withoutEvents(function () use ($empresa) {
                    self::where('id', $empresa->id)->update(['cnpj' => null]);
                });
            }
        });
    }

    /**
     * Usuário proprietário (dono) da empresa.
     */
    public function dono()
    {
        return $this->belongsTo(User::class, 'dono_id');
    }

    /**
     * Usuários vinculados à empresa (dono, funcionários e clientes).
     */
    public function usuarios()
    {
        return $this->hasMany(User::class, 'empresa_id');
    }

    /**
     * Clientes vinculados à empresa.
     */
    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'empresa_id');
    }

    /**
     * Funcionários (e donos prestadores de serviço) vinculados à empresa.
     */
    public function funcionarios()
    {
        return $this->hasMany(Funcionario::class, 'empresa_id');
    }

    /**
     * Catálogo de serviços da empresa.
     */
    public function servicos()
    {
        return $this->hasMany(Servico::class, 'empresa_id');
    }

    /**
     * Produtos / insumos de estoque da empresa.
     */
    public function produtos()
    {
        return $this->hasMany(Produto::class, 'empresa_id');
    }
}
