<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdemServico extends Model
{
    use HasFactory;

    protected $table = 'ordem_servicos';

    protected $fillable = [
        'agendamento_id',
        'numero',
        'data_inicio',
        'data_fim',
        'status',
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
     * Quantidade total de tarefas vinculadas à Ordem de Serviço.
     */
    public function getTarefasTotalAttribute(): int
    {
        if ($this->relationLoaded('tarefas')) {
            return $this->tarefas->count();
        }

        return $this->tarefas()->count();
    }

    /**
     * Quantidade de tarefas concluídas na Ordem de Serviço.
     */
    public function getTarefasConcluidasAttribute(): int
    {
        if ($this->relationLoaded('tarefas')) {
            return $this->tarefas->where('status', 'concluida')->count();
        }

        return $this->tarefas()->where('status', 'concluida')->count();
    }

    /**
     * Nomes dos serviços vinculados às tarefas desta Ordem de Serviço.
     */
    public function getServicosNomesAttribute(): string
    {
        if ($this->relationLoaded('tarefas')) {
            $nomes = $this->tarefas
                ->map(fn ($t) => $t->agendamentoServico?->servico?->nome)
                ->filter()
                ->unique()
                ->join(' + ');

            if ($nomes !== '') {
                return $nomes;
            }
        }

        return $this->agendamento?->servicos_nomes ?? '';
    }

    /**
     * Agendamento que originou a ordem de serviço.
     */
    public function agendamento()
    {
        return $this->belongsTo(Agendamento::class);
    }

    /**
     * Tarefas pertencentes à ordem de serviço.
     */
    public function tarefas()
    {
        return $this->hasMany(Tarefa::class);
    }
}
