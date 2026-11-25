<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClienteEmpresaHistorico extends Model
{
    use HasFactory;

    protected $table = 'cliente_empresa_historicos';

    protected $fillable = [
        'user_id',
        'cliente_id',
        'empresa_id',
        'vinculado_em',
        'desvinculado_em',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'cliente_id' => 'integer',
            'empresa_id' => 'integer',
            'vinculado_em' => 'datetime',
            'desvinculado_em' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id')->withTrashed();
    }

    /**
     * Registra um novo vínculo ativo com uma estética, encerrando eventuais vínculos ativos de outra estética.
     */
    public static function registrarVinculo(?int $userId, ?int $clienteId, int $empresaId): self
    {
        if (! $userId && ! $clienteId) {
            return self::create([
                'user_id' => null,
                'cliente_id' => null,
                'empresa_id' => $empresaId,
                'vinculado_em' => now(),
                'desvinculado_em' => null,
            ]);
        }

        $escopoIdentidade = function ($q) use ($userId, $clienteId) {
            if ($clienteId && $userId) {
                $q->where('cliente_id', $clienteId)->orWhere('user_id', $userId);
            } elseif ($clienteId) {
                $q->where('cliente_id', $clienteId);
            } elseif ($userId) {
                $q->where('user_id', $userId);
            }
        };

        // Encerra vínculos ativos anteriores com outras estéticas
        self::whereNull('desvinculado_em')
            ->where('empresa_id', '!=', $empresaId)
            ->where($escopoIdentidade)
            ->update(['desvinculado_em' => now()]);

        // Reutiliza o registro caso já haja vínculo ativo nesta mesma estética
        $existente = self::whereNull('desvinculado_em')
            ->where('empresa_id', $empresaId)
            ->where($escopoIdentidade)
            ->orderByDesc('vinculado_em')
            ->first();

        if ($existente) {
            $atualizar = [];
            if ($userId && ! $existente->user_id) {
                $atualizar['user_id'] = $userId;
            }
            if ($clienteId && ! $existente->cliente_id) {
                $atualizar['cliente_id'] = $clienteId;
            }
            if (! empty($atualizar)) {
                $existente->update($atualizar);
            }

            return $existente;
        }

        return self::create([
            'user_id' => $userId,
            'cliente_id' => $clienteId,
            'empresa_id' => $empresaId,
            'vinculado_em' => now(),
            'desvinculado_em' => null,
        ]);
    }

    /**
     * Encerra vínculos ativos do usuário/cliente (opcionalmente filtrando por estética).
     */
    public static function encerrarVinculosAtivos(?int $userId, ?int $clienteId, ?int $empresaId = null): void
    {
        if (! $userId && ! $clienteId) {
            return;
        }

        $query = self::whereNull('desvinculado_em')
            ->where(function ($q) use ($userId, $clienteId) {
                if ($clienteId && $userId) {
                    $q->where('cliente_id', $clienteId)->orWhere('user_id', $userId);
                } elseif ($clienteId) {
                    $q->where('cliente_id', $clienteId);
                } elseif ($userId) {
                    $q->where('user_id', $userId);
                }
            });

        if ($empresaId !== null) {
            $query->where('empresa_id', $empresaId);
        }

        $query->update(['desvinculado_em' => now()]);
    }
}
