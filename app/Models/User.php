<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */

    protected $fillable = ['name', 'email', 'password', 'telefone', 'tipo_usuario', 'ativo',];
    protected $hidden = ['password', 'remember_token',];




    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
        ];
    }

    /** * Um usuário pode estar associado a um cliente. */ public function cliente()
    {
        return $this->hasOne(Cliente::class);
    }
    /** * Um usuário pode estar associado a um funcionário. */ public function funcionario()
    {
        return $this->hasOne(Funcionario::class);
    }
    /** * Movimentações de estoque registradas pelo usuário. */ public function movimentacoesEstoque()
    {
        return $this->hasMany(MovimentacaoEstoque::class, 'usuario_id');
    }
    /** * Lançamentos financeiros registrados pelo usuário. */ public function lancamentosFinanceiros()
    {
        return $this->hasMany(LancamentoFinanceiro::class, 'usuario_id');
    }
}
