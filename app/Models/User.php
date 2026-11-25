<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'ativo' => true,
        'tipo_usuario' => 'cliente',
    ];

    protected $fillable = [
        'pessoa_id',
        'empresa_id',
        'name',
        'email',
        'password',
        'telefone',
        'tipo_usuario',
        'ativo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'pessoa_id' => 'integer',
            'empresa_id' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Centraliza e sincroniza os dados pessoais na tabela `pessoas` (Seção 9.3c)
        static::saving(function (User $user) {
            if ($user->pessoa_id) {
                if ($user->isDirty(['name', 'email', 'telefone'])) {
                    $dadosPessoa = [];
                    if ($user->isDirty('name')) {
                        $dadosPessoa['nome'] = $user->name;
                    }
                    if ($user->isDirty('email')) {
                        $dadosPessoa['email'] = $user->email;
                    }
                    if ($user->isDirty('telefone')) {
                        $dadosPessoa['telefone'] = $user->telefone;
                    }

                    if (! empty($dadosPessoa)) {
                        Pessoa::withoutEvents(function () use ($user, $dadosPessoa) {
                            Pessoa::where('id', $user->pessoa_id)->update($dadosPessoa);
                        });
                    }
                }
            } else {
                $pessoa = Pessoa::withoutEvents(fn () => Pessoa::create([
                    'nome' => $user->name,
                    'email' => $user->email,
                    'telefone' => $user->telefone,
                ]));
                $user->pessoa_id = $pessoa->id;
            }

            if (empty($user->empresa_id) && $user->tipo_usuario === 'cliente' && $user->pessoa_id) {
                $empresaDoCliente = Cliente::withoutGlobalScopes()
                    ->where('pessoa_id', $user->pessoa_id)
                    ->whereNotNull('empresa_id')
                    ->value('empresa_id');

                if ($empresaDoCliente) {
                    $user->empresa_id = (int) $empresaDoCliente;
                }
            }
        });

        static::saved(function (User $user) {
            // Propaga apenas alteração cadastral básica para Cliente vinculado (sem alterar empresa_id existente)
            if ($user->wasRecentlyCreated || $user->wasChanged(['name', 'email', 'telefone', 'pessoa_id'])) {
                Cliente::withoutEvents(function () use ($user) {
                    $queryCliente = Cliente::withoutGlobalScopes()->where(function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                        if ($user->pessoa_id) {
                            $q->orWhere('pessoa_id', $user->pessoa_id);
                        }
                    });

                    $dadosUpdate = [
                        'pessoa_id' => $user->pessoa_id,
                        'nome' => $user->name,
                        'email' => $user->email,
                        'telefone' => $user->telefone ?? '',
                    ];

                    (clone $queryCliente)->update($dadosUpdate);

                    if ($user->empresa_id) {
                        (clone $queryCliente)->whereNull('empresa_id')->update(['empresa_id' => $user->empresa_id]);
                    }
                });
            }

            // Donos de empresa e funcionários possuem registro em funcionarios apenas quando o perfil/status exige
            if ($user->wasRecentlyCreated || $user->wasChanged(['tipo_usuario', 'ativo', 'empresa_id'])) {
                if (in_array($user->tipo_usuario, ['dono', 'funcionario'], true)) {
                    $ativo = (bool) ($user->ativo ?? true);
                    $cargoPadrao = $user->tipo_usuario === 'dono'
                        ? 'Proprietário / Especialista'
                        : 'Operador de Detalhamento';

                    $funcExistente = Funcionario::withoutGlobalScopes()->where('user_id', $user->id)->first();
                    if ($funcExistente) {
                        $dadosFunc = ['ativo' => $ativo];
                        if ($user->empresa_id) {
                            $dadosFunc['empresa_id'] = $user->empresa_id;
                        }
                        if ($user->tipo_usuario === 'dono' && $funcExistente->cargo === 'Operador de Detalhamento') {
                            $dadosFunc['cargo'] = $cargoPadrao;
                        }
                        Funcionario::withoutEvents(fn () => $funcExistente->update($dadosFunc));
                    } else {
                        Funcionario::withoutGlobalScopes()->create([
                            'user_id' => $user->id,
                            'empresa_id' => $user->empresa_id,
                            'cargo' => $cargoPadrao,
                            'ativo' => $ativo,
                        ]);
                    }
                } elseif ($user->wasChanged('tipo_usuario')) {
                    Funcionario::withoutGlobalScopes()
                        ->where('user_id', $user->id)
                        ->update(['ativo' => false]);
                }
            }
        });
    }

    public function isAdministrador(): bool
    {
        return $this->tipo_usuario === 'administrador';
    }

    public function isDono(): bool
    {
        return $this->tipo_usuario === 'dono';
    }

    public function isFuncionario(): bool
    {
        return $this->tipo_usuario === 'funcionario';
    }

    public function isCliente(): bool
    {
        return $this->tipo_usuario === 'cliente';
    }

    public function isStaff(): bool
    {
        return in_array($this->tipo_usuario, ['administrador', 'dono', 'funcionario'], true);
    }

    /**
     * Verifica se o usuário tem permissão para gerenciar (criar/editar/excluir) dados de empresa.
     */
    public function canManageEmpresa(?Empresa $empresa = null): bool
    {
        if (! $this->ativo) {
            return false;
        }

        if ($this->isAdministrador()) {
            return true;
        }

        if ($this->isDono()) {
            if ($empresa === null) {
                return true;
            }

            if (! $empresa->exists) {
                return false;
            }

            $mesmaEmpresa = $this->empresa_id !== null && (int) $this->empresa_id === (int) $empresa->id;

            return $mesmaEmpresa || ((int) $empresa->dono_id === (int) $this->id);
        }

        return false;
    }

    /**
     * Verifica se o usuário pode criar/editar/desativar outros usuários.
     * Funcionários e Clientes não podem alterar dados de outros usuários.
     */
    public function canManageUsers(): bool
    {
        return $this->ativo && ($this->isAdministrador() || $this->isDono());
    }

    /**
     * Indica se o usuário possui atualmente uma estética automotiva vinculada e ativa.
     */
    public function possuiEsteticaVinculada(): bool
    {
        if ($this->isCliente()) {
            $empresaId = $this->empresa_id
                ?: Cliente::withoutGlobalScopes()->where('user_id', $this->id)->value('empresa_id');

            return $empresaId !== null && Empresa::whereKey($empresaId)->exists();
        }

        return $this->getEmpresaAtivaId() !== null;
    }

    /**
     * Retorna o ID da empresa ativa do usuário (fail-closed: sem fallback cruzado para Dono/Funcionário/Cliente).
     */
    public function getEmpresaAtivaId(): ?int
    {
        if ($this->empresa_id) {
            if (Empresa::whereKey($this->empresa_id)->exists()) {
                return (int) $this->empresa_id;
            }

            if ($this->isDono()) {
                $id = Empresa::where('dono_id', $this->id)->orderBy('id')->value('id');
                return $id ? (int) $id : null;
            }

            return null;
        }

        if ($this->isDono()) {
            $id = Empresa::where('dono_id', $this->id)->orderBy('id')->value('id');
            return $id ? (int) $id : null;
        }

        if ($this->isCliente()) {
            $empresaCliente = Cliente::withoutGlobalScopes()
                ->where('user_id', $this->id)
                ->value('empresa_id');

            if ($empresaCliente && Empresa::whereKey($empresaCliente)->exists()) {
                return (int) $empresaCliente;
            }

            return null;
        }

        if ($this->isAdministrador()) {
            $id = Empresa::query()->orderBy('id')->value('id');
            return $id ? (int) $id : null;
        }

        return null;
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->tipo_usuario) {
            'administrador' => 'Administrador Geral',
            'dono' => 'Dono da Empresa',
            'funcionario' => 'Funcionário',
            'cliente' => 'Cliente',
            default => ucfirst((string) $this->tipo_usuario),
        };
    }

    /**
     * Garante que um usuário do tipo cliente possua um perfil Cliente vinculado.
     * Sem pré-cadastro em uma estética, o perfil base permanece sem empresa_id (null)
     * até que um dono de estética cadastre/vincule este cliente.
     */
    public function ensureClienteProfile(): Cliente
    {
        $cliente = Cliente::withoutGlobalScopes()->where('user_id', $this->id)->first();
        if ($cliente) {
            if ($cliente->empresa_id === null && $this->empresa_id) {
                $cliente->update(['empresa_id' => $this->empresa_id]);
            } elseif ($cliente->empresa_id) {
                ClienteEmpresaHistorico::registrarVinculo($this->id, $cliente->id, (int) $cliente->empresa_id);
            }

            return $cliente;
        }

        $empresaId = $this->getEmpresaAtivaId();
        $pessoa = $this->pessoa_id ? Pessoa::find($this->pessoa_id) : null;

        $clienteExistente = Cliente::withoutGlobalScopes()
            ->whereNull('user_id')
            ->where(function ($q) use ($pessoa) {
                $q->whereRaw('LOWER(email) = ?', [strtolower((string) $this->email)]);
                if ($this->pessoa_id) {
                    $q->orWhere('pessoa_id', $this->pessoa_id);
                }
                if ($pessoa?->cpf_cnpj) {
                    $q->orWhere('cpf_cnpj', $pessoa->cpf_cnpj);
                }
            })
            ->first();

        if ($clienteExistente) {
            $pessoaId = $clienteExistente->pessoa_id ?: $this->pessoa_id;
            if ($pessoaId && self::where('pessoa_id', $pessoaId)->where('id', '!=', $this->id)->exists()) {
                $pessoaId = $this->pessoa_id;
            }

            $empresaFinalId = $clienteExistente->empresa_id ?: $empresaId;

            $clienteExistente->update([
                'user_id' => $this->id,
                'pessoa_id' => $pessoaId,
                'empresa_id' => $empresaFinalId,
                'telefone' => $clienteExistente->telefone ?: ($this->telefone ?? ''),
            ]);

            if ($empresaFinalId && (int) $this->empresa_id !== (int) $empresaFinalId) {
                self::withoutEvents(fn () => $this->update(['empresa_id' => $empresaFinalId]));
            }

            if ($pessoaId && (int) $this->pessoa_id !== (int) $pessoaId) {
                $oldPessoaId = $this->pessoa_id;
                self::withoutEvents(fn () => $this->update(['pessoa_id' => $pessoaId]));
                if ($oldPessoaId && (int) $oldPessoaId !== (int) $pessoaId) {
                    $emUso = self::where('pessoa_id', $oldPessoaId)->exists()
                        || Cliente::withoutGlobalScopes()->where('pessoa_id', $oldPessoaId)->exists();
                    if (! $emUso) {
                        Pessoa::where('id', $oldPessoaId)->delete();
                    }
                }
            }

            if ($empresaFinalId) {
                ClienteEmpresaHistorico::registrarVinculo($this->id, $clienteExistente->id, (int) $empresaFinalId);
            }

            return $clienteExistente;
        }

        $novoCliente = Cliente::withoutGlobalScopes()->create([
            'pessoa_id' => $this->pessoa_id,
            'empresa_id' => $empresaId,
            'user_id' => $this->id,
            'nome' => $this->name,
            'email' => $this->email,
            'telefone' => $this->telefone ?? '',
            'cpf_cnpj' => $pessoa?->cpf_cnpj,
            'cep' => $pessoa?->cep,
            'logradouro' => $pessoa?->logradouro,
            'numero' => $pessoa?->numero,
            'complemento' => $pessoa?->complemento,
            'bairro' => $pessoa?->bairro,
            'cidade' => $pessoa?->cidade,
            'estado' => $pessoa?->estado,
        ]);

        if ($empresaId) {
            ClienteEmpresaHistorico::registrarVinculo($this->id, $novoCliente->id, (int) $empresaId);
        }

        return $novoCliente;
    }

    public function historicoEmpresas()
    {
        return $this->hasMany(ClienteEmpresaHistorico::class, 'user_id')->orderByDesc('vinculado_em');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function empresasProprias()
    {
        return $this->hasMany(Empresa::class, 'dono_id');
    }

    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function cliente()
    {
        return $this->hasOne(Cliente::class);
    }

    public function funcionario()
    {
        return $this->hasOne(Funcionario::class);
    }

    public function movimentacoesEstoque()
    {
        return $this->hasMany(MovimentacaoEstoque::class, 'usuario_id');
    }

    public function lancamentosFinanceiros()
    {
        return $this->hasMany(LancamentoFinanceiro::class, 'usuario_id');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
