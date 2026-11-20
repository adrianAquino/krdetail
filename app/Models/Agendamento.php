<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Agendamento extends Model
{
    use HasFactory;

    public const GARGALO_MINUTOS = 30;

    protected $table = 'agendamentos';

    protected $fillable = [
        'cliente_id',
        'veiculo_id',
        'data_inicio',
        'data_fim',
        'data_conclusao',
        'status',
        'origem',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'datetime',
            'data_fim' => 'datetime',
            'data_conclusao' => 'datetime',
        ];
    }

    /**
     * Seção 9.2 (a) — Integridade referencial entre Agendamentos, Clientes e Veículos (RN01):
     * O atributo `cliente_id` é mantido em `agendamentos` para preservar o histórico do contratante
     * na época do atendimento, mesmo que o veículo seja transferido para um novo proprietário no futuro
     * (`veiculos.cliente_id` atualizado). No momento da criação ou alteração de veículo/cliente do
     * agendamento, garante-se que o veículo selecionado pertence ao cliente informado.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('empresa_agendamento', function (Builder $builder) {
            if (!Auth::hasUser()) {
                return;
            }
            $user = Auth::user();
            if (!($user instanceof User)) {
                return;
            }
            if ($user->isDono() || $user->isFuncionario()) {
                $empresaId = $user->getEmpresaAtivaId();
                if ($empresaId === null) {
                    $builder->whereRaw('1 = 0');
                    return;
                }
                $builder->whereHas('cliente', function (Builder $cq) use ($empresaId) {
                    $cq->withoutGlobalScopes()->where('empresa_id', $empresaId);
                });
            } elseif ($user->isCliente()) {
                $builder->whereHas('cliente', function (Builder $cq) use ($user) {
                    $cq->withoutGlobalScopes()->where('user_id', $user->id);
                });
            }
        });

        static::saving(function (Agendamento $agendamento) {
            if (!$agendamento->exists || $agendamento->isDirty(['cliente_id', 'veiculo_id'])) {
                if ($agendamento->veiculo_id && $agendamento->cliente_id) {
                    $veiculoPertenceAoCliente = Veiculo::withoutGlobalScopes()
                        ->where('id', $agendamento->veiculo_id)
                        ->where('cliente_id', $agendamento->cliente_id)
                        ->exists();

                    if (!$veiculoPertenceAoCliente) {
                        throw new \DomainException('Inconsistência referencial (RN01): o veículo selecionado não pertence ao cliente informado no agendamento.');
                    }
                }
            }
        });
    }

    /**
     * Valor total estimado/contratado dos serviços vinculados ao agendamento.
     */
    public function getValorTotalAttribute(): float
    {
        if ($this->relationLoaded('agendamentosServicos')) {
            return (float) $this->agendamentosServicos->sum(
                fn ($item) => $item->valor_servico ?? $item->servico?->preco_base ?? 0
            );
        }

        if ($this->relationLoaded('servicos')) {
            return (float) $this->servicos->sum(
                fn ($servico) => $servico->pivot?->valor_servico ?? $servico->preco_base ?? 0
            );
        }

        return (float) $this->agendamentosServicos()->sum('valor_servico');
    }

    /**
     * Nomes dos serviços contratados concatenados.
     */
    public function getServicosNomesAttribute(): string
    {
        if ($this->relationLoaded('servicos')) {
            return $this->servicos->pluck('nome')->filter()->join(' + ');
        }

        if ($this->relationLoaded('agendamentosServicos')) {
            return $this->agendamentosServicos
                ->map(fn ($item) => $item->servico?->nome)
                ->filter()
                ->join(' + ');
        }

        return $this->servicos()->pluck('nome')->filter()->join(' + ');
    }

    /**
     * Alias semântico para a previsão estimada de conclusão (data_fim + gargalo operacional).
     */
    public function getDataHoraEstimadaConclusaoAttribute()
    {
        return $this->data_fim;
    }

    /**
     * Data e hora real de conclusão do agendamento (preenchida no fechamento da OS).
     */
    public function getDataHoraConclusaoAttribute()
    {
        return $this->data_conclusao;
    }

    /**
     * Soma dos minutos estimados dos serviços contratados (sem gargalo).
     */
    public function getMinutosServicosAttribute(): int
    {
        if ($this->relationLoaded('agendamentosServicos')) {
            return (int) $this->agendamentosServicos->sum(
                fn ($item) => $item->tempo_estimado_minutos ?? $item->servico?->tempo_estimado_minutos ?? 0
            );
        }

        if ($this->relationLoaded('servicos')) {
            return (int) $this->servicos->sum(
                fn ($servico) => $servico->pivot?->tempo_estimado_minutos ?? $servico->tempo_estimado_minutos ?? 0
            );
        }

        return (int) $this->agendamentosServicos()
            ->withoutGlobalScopes()
            ->with('servico')
            ->get()
            ->sum(fn ($item) => $item->tempo_estimado_minutos ?? $item->servico?->tempo_estimado_minutos ?? 0);
    }

    /**
     * Tempo total estimado em minutos considerando os serviços + 30 minutos de gargalo.
     */
    public function getMinutosTotaisComGargaloAttribute(): int
    {
        $minutosServicos = $this->minutos_servicos;

        return $minutosServicos > 0 ? $minutosServicos + self::GARGALO_MINUTOS : 0;
    }

    /**
     * Tempo estimado apenas dos serviços formatado (ex: "3h 30min").
     */
    public function getTempoServicosAttribute(): string
    {
        return self::formatarMinutos($this->minutos_servicos);
    }

    /**
     * Tempo total estimado formatado já incluindo o gargalo de 30min (ex: "4h 00min").
     */
    public function getTempoTotalAttribute(): string
    {
        return self::formatarMinutos($this->minutos_totais_com_gargalo);
    }

    public static function formatarMinutos(int $minutosTotais): string
    {
        if ($minutosTotais <= 0) {
            return '0min';
        }

        $horas = intdiv($minutosTotais, 60);
        $minutos = $minutosTotais % 60;

        if ($horas > 0) {
            return $minutos > 0 ? "{$horas}h {$minutos}min" : "{$horas}h 00min";
        }

        return "{$minutos}min";
    }

    /**
     * Cliente responsável pelo agendamento.
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Veículo relacionado ao agendamento.
     */
    public function veiculo()
    {
        return $this->belongsTo(Veiculo::class);
    }

    /**
     * Serviços contratados no agendamento.
     */
    public function agendamentosServicos()
    {
        return $this->hasMany(AgendamentoServico::class);
    }

    /**
     * Serviços relacionados ao agendamento.
     */
    public function servicos()
    {
        return $this->belongsToMany(Servico::class, 'agendamento_servico')
            ->withPivot(['id', 'valor_servico', 'tempo_estimado_minutos'])
            ->withTimestamps();
    }

    /**
     * Ordem de serviço gerada para o agendamento.
     */
    public function ordemServico()
    {
        return $this->hasOne(OrdemServico::class);
    }
}
