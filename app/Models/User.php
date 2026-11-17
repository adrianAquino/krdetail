<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
                Pessoa::withoutEvents(function () use ($user) {
                    Pessoa::where('id', $user->pessoa_id)->update([
                        'nome' => $user->name,
                        'email' => $user->email,
                        'telefone' => $user->telefone,
                    ]);
                });
            } else {
                $pessoa = Pessoa::withoutEvents(fn () => Pessoa::create([
                    'nome' => $user->name,
                    'email' => $user->email,
                    'telefone' => $user->telefone,
                ]));
                $user->pessoa_id = $pessoa->id;
            }

            if (empty($user->empresa_id) && $user->tipo_usuario !== 'administrador' && Schema::hasTable('empresas')) {
                $user->empresa_id = Empresa::query()->value('id');
            }
        });

        static::saved(function (User $user) {
            Cliente::withoutEvents(function () use ($user) {
                $dadosUpdate = [
                    'pessoa_id' => $user->pessoa_id,
                    'nome' => $user->name,
                    'email' => $user->email,
                    'telefone' => $user->telefone ?? '',
                ];
                if ($user->empresa_id) {
                    $dadosUpdate['empresa_id'] = $user->empresa_id;
                }

                Cliente::withoutGlobalScopes()
                    ->where('user_id', $user->id)
                    ->orWhere('pessoa_id', $user->pessoa_id)
                    ->update($dadosUpdate);
            });

            // Donos de empresa também podem prestar serviços (não limitado somente a funcionários)
            if (in_array($user->tipo_usuario, ['dono', 'funcionario'], true) && Schema::hasTable('funcionarios')) {
                $cargoPadrao = $user->tipo_usuario === 'dono'
                    ? 'Proprietário / Especialista'
                    : 'Operador de Detalhamento';

                $funcExistente = Funcionario::withoutGlobalScopes()->where('user_id', $user->id)->first();
                if ($funcExistente) {
                    $dadosFunc = ['ativo' => (bool) $user->ativo];
                    if ($user->empresa_id) {
                        $dadosFunc['empresa_id'] = $user->empresa_id;
                    }
                    if ($user->tipo_usuario === 'dono' && $funcExistente->cargo === 'Operador de Detalhamento') {
                        $dadosFunc['cargo'] = $cargoPadrao;
                    }
                    $funcExistente->update($dadosFunc);
                } else {
                    Funcionario::withoutGlobalScopes()->create([
                        'user_id' => $user->id,
                        'empresa_id' => $user->empresa_id,
                        'cargo' => $cargoPadrao,
                        'ativo' => (bool) $user->ativo,
                    ]);
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
        if ($this->isAdministrador()) {
            return true;
        }

        if ($this->isDono()) {
            if ($empresa === null) {
                return true;
            }

            return (int) $this->empresa_id === (int) $empresa->id
                || (int) $empresa->dono_id === (int) $this->id;
        }

        return false;
    }

    /**
     * Verifica se o usuário pode criar/editar/desativar outros usuários.
     * Funcionários e Clientes não podem alterar dados de outros usuários.
     */
    public function canManageUsers(): bool
    {
        return $this->isAdministrador() || $this->isDono();
    }

    /**
     * Retorna o ID da empresa ativa do usuário (para Dono, Funcionário ou Cliente).
     */
    public function getEmpresaAtivaId(): ?int
    {
        if ($this->empresa_id) {
            return (int) $this->empresa_id;
        }

        if ($this->isDono()) {
            $id = Empresa::where('dono_id', $this->id)->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return Empresa::query()->value('id');
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
     * Garante que um usuário do tipo cliente possua um perfil Cliente vinculado
     * compartilhando o mesmo registro em `pessoas`.
     */
    public function ensureClienteProfile(): Cliente
    {
        $cliente = Cliente::withoutGlobalScopes()->where('user_id', $this->id)->first();
        if ($cliente) {
            return $cliente;
        }

        $clienteExistente = Cliente::withoutGlobalScopes()
            ->whereNull('user_id')
            ->where(function ($q) {
                $q->whereRaw('LOWER(email) = ?', [strtolower($this->email)]);
                if ($this->pessoa_id) {
                    $q->orWhere('pessoa_id', $this->pessoa_id);
                }
            })
            ->first();

        $empresaId = $this->getEmpresaAtivaId();

        if ($clienteExistente) {
            $pessoaId = $clienteExistente->pessoa_id ?? $this->pessoa_id;

            $clienteExistente->update([
                'user_id' => $this->id,
                'pessoa_id' => $pessoaId,
                'empresa_id' => $clienteExistente->empresa_id ?: $empresaId,
                'telefone' => $clienteExistente->telefone ?: ($this->telefone ?? ''),
            ]);

            if ($pessoaId && $this->pessoa_id !== $pessoaId) {
                $oldPessoaId = $this->pessoa_id;
                self::withoutEvents(fn () => $this->update(['pessoa_id' => $pessoaId]));
                if ($oldPessoaId && $oldPessoaId !== $pessoaId) {
                    Pessoa::where('id', $oldPessoaId)->delete();
                }
            }

            return $clienteExistente;
        }

        return Cliente::withoutGlobalScopes()->create([
            'pessoa_id' => $this->pessoa_id,
            'empresa_id' => $empresaId,
            'user_id' => $this->id,
            'nome' => $this->name,
            'email' => $this->email,
            'telefone' => $this->telefone ?? '',
        ]);
    }

    /**
     * Empresa à qual o usuário pertence.
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Empresas das quais o usuário é proprietário (dono).
     */
    public function empresasProprias()
    {
        return $this->hasMany(Empresa::class, 'dono_id');
    }

    /**
     * Dados pessoais centralizados na tabela PESSOAS (3FN).
     */
    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    /**
     * Um usuário pode estar associado a um cliente.
     */
    public function cliente()
    {
        return $this->hasOne(Cliente::class);
    }

    /**
     * Um usuário pode estar associado a um funcionário (ou dono prestador de serviço).
     */
    public function funcionario()
    {
        return $this->hasOne(Funcionario::class);
    }

    /**
     * Movimentações de estoque registradas pelo usuário.
     */
    public function movimentacoesEstoque()
    {
        return $this->hasMany(MovimentacaoEstoque::class, 'usuario_id');
    }

    /**
     * Lançamentos financeiros registrados pelo usuário.
     */
    public function lancamentosFinanceiros()
    {
        return $this->hasMany(LancamentoFinanceiro::class, 'usuario_id');
    }
}
