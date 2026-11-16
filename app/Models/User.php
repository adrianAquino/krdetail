<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'pessoa_id',
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
        });

        static::saved(function (User $user) {
            Cliente::withoutEvents(function () use ($user) {
                Cliente::where('user_id', $user->id)
                    ->orWhere('pessoa_id', $user->pessoa_id)
                    ->update([
                        'pessoa_id' => $user->pessoa_id,
                        'nome' => $user->name,
                        'email' => $user->email,
                        'telefone' => $user->telefone ?? '',
                    ]);
            });
        });
    }

    public function isCliente(): bool
    {
        return $this->tipo_usuario === 'cliente';
    }

    /**
     * Garante que um usuário do tipo cliente possua um perfil Cliente vinculado
     * compartilhando o mesmo registro em `pessoas`.
     */
    public function ensureClienteProfile(): Cliente
    {
        if ($this->cliente) {
            return $this->cliente;
        }

        $clienteExistente = Cliente::whereNull('user_id')
            ->where(function ($q) {
                $q->whereRaw('LOWER(email) = ?', [strtolower($this->email)]);
                if ($this->pessoa_id) {
                    $q->orWhere('pessoa_id', $this->pessoa_id);
                }
            })
            ->first();

        if ($clienteExistente) {
            $pessoaId = $clienteExistente->pessoa_id ?? $this->pessoa_id;

            $clienteExistente->update([
                'user_id' => $this->id,
                'pessoa_id' => $pessoaId,
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

        return Cliente::create([
            'pessoa_id' => $this->pessoa_id,
            'user_id' => $this->id,
            'nome' => $this->name,
            'email' => $this->email,
            'telefone' => $this->telefone ?? '',
        ]);
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
     * Um usuário pode estar associado a um funcionário.
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
