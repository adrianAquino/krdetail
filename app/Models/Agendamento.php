<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agendamento extends Model
{
    use HasFactory;

    protected $table = 'agendamentos';

    protected $fillable = [
        'cliente_id',
        'veiculo_id',
        'data_inicio',
        'data_fim',
        'status',
        'origem',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'datetime',
            'data_fim' => 'datetime',
        ];
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
     * Tempo total estimado formatado (ex: "3h 30min").
     */
    public function getTempoTotalAttribute(): string
    {
        if ($this->relationLoaded('agendamentosServicos')) {
            $minutosTotais = (int) $this->agendamentosServicos->sum(
                fn ($item) => $item->tempo_estimado_minutos ?? $item->servico?->tempo_estimado_minutos ?? 0
            );
        } elseif ($this->relationLoaded('servicos')) {
            $minutosTotais = (int) $this->servicos->sum(
                fn ($servico) => $servico->pivot?->tempo_estimado_minutos ?? $servico->tempo_estimado_minutos ?? 0
            );
        } else {
            $minutosTotais = (int) $this->agendamentosServicos()->sum('tempo_estimado_minutos');
        }

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
