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

    public function isCliente(): bool
    {
        return $this->tipo_usuario === 'cliente';
    }

    /**
     * Garante que um usuário do tipo cliente possua um perfil Cliente vinculado.
     * Se já houver um Cliente com o mesmo e-mail sem user_id, vincula-o.
     */
    public function ensureClienteProfile(): ?Cliente
    {
        if (! $this->isCliente()) {
            return $this->cliente;
        }

        if ($this->cliente) {
            return $this->cliente;
        }

        $clienteExistente = Cliente::whereNull('user_id')
            ->whereRaw('LOWER(email) = ?', [strtolower($this->email)])
            ->first();

        if ($clienteExistente) {
            $clienteExistente->update([
                'user_id' => $this->id,
                'telefone' => $clienteExistente->telefone ?: ($this->telefone ?? ''),
            ]);

            return $clienteExistente;
        }

        return Cliente::create([
            'user_id' => $this->id,
            'nome' => $this->name,
            'email' => $this->email,
            'telefone' => $this->telefone ?? '',
        ]);
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
     * Lançamentos financeiros registradas pelo usuário.
     */
    public function lancamentosFinanceiros()
    {
        return $this->hasMany(LancamentoFinanceiro::class, 'usuario_id');
    }
}
