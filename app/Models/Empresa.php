<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use HasFactory, SoftDeletes;

    public const CAPACIDADE_MAXIMA_BOXES = 4;

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

    /**
     * Conta profissionais operacionais ativos vinculados à empresa (funcionários ativos + dono ativo),
     * sem duplicar o proprietário nem depender da empresa ativa selecionada na sessão.
     */
    public function contarEquipeOperacionalAtiva(): int
    {
        $empresaId = (int) $this->id;
        $donoId = $this->dono_id ? (int) $this->dono_id : null;

        return User::query()
            ->where('ativo', true)
            ->whereIn('tipo_usuario', ['dono', 'funcionario'])
            ->whereDoesntHave('funcionario', fn ($fq) => $fq->withoutGlobalScopes()->where('ativo', false))
            ->where(function ($q) use ($empresaId, $donoId) {
                $q->where('empresa_id', $empresaId)
                    ->orWhereHas('funcionario', fn ($fq) => $fq->withoutGlobalScopes()
                        ->where('empresa_id', $empresaId)
                        ->where('ativo', true));

                if ($donoId) {
                    $q->orWhere('id', $donoId);
                }
            })
            ->count();
    }

    /**
     * Capacidade simultânea efetiva: mínimo entre os boxes físicos (4) e a equipe operacional ativa.
     */
    public function calcularCapacidadeSimultanea(): int
    {
        return min(self::CAPACIDADE_MAXIMA_BOXES, $this->contarEquipeOperacionalAtiva());
    }

    /**
     * Resolve a capacidade simultânea para o ID de empresa informado (fallback 4 se nulo/inexistente).
     */
    public static function capacidadeSimultaneaPara(?int $empresaId): int
    {
        if ($empresaId === null) {
            return self::CAPACIDADE_MAXIMA_BOXES;
        }

        $empresa = self::whereKey($empresaId)->first(['id', 'dono_id']);

        return $empresa
            ? $empresa->calcularCapacidadeSimultanea()
            : self::CAPACIDADE_MAXIMA_BOXES;
    }
}
