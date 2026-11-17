<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\AgendamentoServico;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\User;
use App\Models\Veiculo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgendamentoController extends Controller
{
    public const GARGALO_MINUTOS = 30;
    public const CAPACIDADE_BOXES = 4;
    public const HORA_ABERTURA = 8;
    public const HORA_FECHAMENTO = 18;
    public const ANTECEDENCIA_CANCELAMENTO_HORAS = 1;

    public function index(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        $query = Agendamento::with(['cliente', 'veiculo', 'servicos', 'agendamentosServicos.servico', 'ordemServico.tarefas']);

        if ($user instanceof User && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            $query->where('cliente_id', $cliente->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca, $user) {
                $q->whereHas('veiculo', fn ($vq) => $vq->where('placa', 'like', "%{$busca}%")
                    ->orWhere('modelo', 'like', "%{$busca}%")
                    ->orWhere('marca', 'like', "%{$busca}%"));

                if (! ($user instanceof User) || ! $user->isCliente()) {
                    $q->orWhereHas('cliente', fn ($cq) => $cq->where('nome', 'like', "%{$busca}%"));
                }
            });
        }

        $agendamentos = $query->orderByDesc('data_inicio')->paginate(15);

        return view('agendamentos.index', compact('agendamentos'));
    }

    public function create(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user instanceof User && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            $clientes = Cliente::with('veiculos')->where('id', $cliente->id)->get();
        } else {
            $clientes = Cliente::with('veiculos')->orderBy('nome')->get();
        }

        $servicos = Servico::where('ativo', true)->orderBy('nome')->get();

        return view('agendamentos.create', compact('clientes', 'servicos'));
    }

    public function store(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user instanceof User && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            $request->merge([
                'cliente_id' => $cliente->id,
                'status' => 'agendado',
                'origem' => 'Site',
            ]);
        }

        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'veiculo_id' => 'required|exists:veiculos,id',
            'data_inicio' => 'required|date|after_or_equal:' . now()->subMinutes(1)->format('Y-m-d H:i:s'),
            'status' => 'nullable|in:agendado,confirmado,em_andamento',
            'origem' => 'nullable|string|max:50',
            'observacoes' => 'nullable|string|max:500',
            'servicos' => 'required|array|min:1',
            'servicos.*.servico_id' => 'required|distinct|exists:servicos,id',
        ], [
            'data_inicio.required' => 'Informe a data e o horário de início do agendamento.',
            'data_inicio.date' => 'Informe uma data e horário válidos.',
            'data_inicio.after_or_equal' => 'A data e o horário de início não podem ser inferiores à data e horário atuais.',
            'servicos.required' => 'Selecione pelo menos um serviço para o agendamento.',
            'servicos.min' => 'Selecione pelo menos um serviço para o agendamento.',
            'servicos.*.servico_id.distinct' => 'Não é permitido adicionar o mesmo serviço mais de uma vez no mesmo agendamento.',
        ]);

        if ($user instanceof User && $user->isCliente()) {
            $validated['cliente_id'] = $user->ensureClienteProfile()->id;
            $validated['status'] = 'agendado';
            $validated['origem'] = 'Site';
        }

        $veiculo = Veiculo::findOrFail($validated['veiculo_id']);
        if ((int) $veiculo->cliente_id !== (int) $validated['cliente_id']) {
            throw ValidationException::withMessages([
                'veiculo_id' => 'O veículo selecionado não pertence ao cliente informado.',
            ]);
        }

        $servicosModels = $this->obterServicosValidos($validated['servicos']);

        $dataInicio = Carbon::parse($validated['data_inicio']);
        $somaMinutos = (int) $servicosModels->sum('tempo_estimado_minutos');
        $dataFimEstimada = $dataInicio->copy()->addMinutes($somaMinutos + self::GARGALO_MINUTOS);

        $this->validarExpediente($dataInicio);

        $clienteModel = Cliente::withoutGlobalScopes()->find($validated['cliente_id']);
        $empresaId = $clienteModel?->empresa_id ?? ($user?->getEmpresaAtivaId());

        $this->validarDisponibilidade(
            veiculoId: (int) $veiculo->id,
            empresaId: $empresaId ? (int) $empresaId : null,
            dataInicio: $dataInicio,
            dataFimEstimada: $dataFimEstimada
        );

        $agendamento = DB::transaction(function () use ($validated, $servicosModels, $dataInicio, $dataFimEstimada) {
            $ag = Agendamento::create([
                'cliente_id' => $validated['cliente_id'],
                'veiculo_id' => $validated['veiculo_id'],
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFimEstimada,
                'data_conclusao' => null,
                'status' => $validated['status'] ?? 'agendado',
                'origem' => $validated['origem'] ?? 'Balcão',
                'observacoes' => $validated['observacoes'] ?? null,
            ]);

            foreach ($servicosModels as $servico) {
                AgendamentoServico::create([
                    'agendamento_id' => $ag->id,
                    'servico_id' => $servico->id,
                    'valor_servico' => $servico->preco_base,
                    'tempo_estimado_minutos' => $servico->tempo_estimado_minutos,
                    'observacoes' => null,
                ]);
            }

            return $ag;
        });

        return redirect()->route('agendamentos.show', $agendamento)->with('success', 'Agendamento registrado com sucesso!');
    }

    public function show(Agendamento $agendamento)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user instanceof User && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            if ((int) $agendamento->cliente_id !== (int) $cliente->id) {
                abort(403, 'Você não tem permissão para visualizar este agendamento.');
            }
        }

        $agendamento->load([
            'cliente',
            'veiculo',
            'agendamentosServicos.servico',
            'ordemServico.tarefas.agendamentoServico.servico',
            'ordemServico.tarefas.funcionario.user',
        ]);

        return view('agendamentos.show', compact('agendamento'));
    }

    public function edit(Agendamento $agendamento)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user instanceof User && $user->isCliente()) {
            abort(403);
        }

        if (in_array($agendamento->status, ['concluido', 'cancelado'], true)) {
            return redirect()
                ->route('agendamentos.show', $agendamento)
                ->with('error', 'Não é possível editar um agendamento que já foi concluído ou cancelado.');
        }

        $agendamento->load(['agendamentosServicos', 'ordemServico']);
        $clientes = Cliente::with('veiculos')->orderBy('nome')->get();
        $servicos = Servico::where('ativo', true)->orderBy('nome')->get();

        return view('agendamentos.edit', compact('agendamento', 'clientes', 'servicos'));
    }

    public function update(Request $request, Agendamento $agendamento)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user instanceof User && $user->isCliente()) {
            abort(403);
        }

        if (in_array($agendamento->status, ['concluido', 'cancelado'], true)) {
            return redirect()
                ->route('agendamentos.show', $agendamento)
                ->with('error', 'Não é possível editar um agendamento que já foi concluído ou cancelado.');
        }

        $agendamento->load(['agendamentosServicos', 'ordemServico.tarefas']);
        $possuiOS = $agendamento->ordemServico !== null;

        $rules = [
            'cliente_id' => 'required|exists:clientes,id',
            'veiculo_id' => 'required|exists:veiculos,id',
            'data_inicio' => 'required|date',
            'status' => 'required|in:agendado,confirmado,em_andamento,concluido,cancelado',
            'origem' => 'nullable|string|max:50',
            'observacoes' => 'nullable|string|max:500',
        ];

        if (!$possuiOS) {
            $rules['servicos'] = 'required|array|min:1';
            $rules['servicos.*.servico_id'] = 'required|distinct|exists:servicos,id';
        }

        $validated = $request->validate($rules, [
            'data_inicio.required' => 'Informe a data e o horário de início do agendamento.',
            'data_inicio.date' => 'Informe uma data e horário válidos.',
            'servicos.required' => 'Selecione pelo menos um serviço para o agendamento.',
            'servicos.min' => 'Selecione pelo menos um serviço para o agendamento.',
            'servicos.*.servico_id.distinct' => 'Não é permitido adicionar o mesmo serviço mais de uma vez no mesmo agendamento.',
        ]);

        if ($possuiOS) {
            if (
                (int) $validated['cliente_id'] !== (int) $agendamento->cliente_id
                || (int) $validated['veiculo_id'] !== (int) $agendamento->veiculo_id
            ) {
                throw ValidationException::withMessages([
                    'veiculo_id' => 'Não é possível alterar o cliente ou o veículo porque este agendamento já possui uma Ordem de Serviço vinculada.',
                ]);
            }

            if ($validated['status'] === 'concluido') {
                $temTarefasPendentes = $agendamento->ordemServico->tarefas->contains(fn ($t) => $t->status !== 'concluida');
                if ($temTarefasPendentes) {
                    throw ValidationException::withMessages([
                        'status' => 'Não é possível concluir o agendamento enquanto existirem tarefas pendentes ou em andamento na Ordem de Serviço.',
                    ]);
                }
            }

            if ($validated['status'] === 'cancelado') {
                $temTarefasIniciadas = $agendamento->ordemServico->tarefas->contains(
                    fn ($t) => in_array($t->status, ['em_andamento', 'concluida'], true)
                );
                if ($temTarefasIniciadas) {
                    throw ValidationException::withMessages([
                        'status' => 'Não é possível cancelar o agendamento porque já existem tarefas iniciadas ou concluídas na Ordem de Serviço.',
                    ]);
                }
            }
        }

        $veiculo = Veiculo::findOrFail($validated['veiculo_id']);
        if ((int) $veiculo->cliente_id !== (int) $validated['cliente_id']) {
            throw ValidationException::withMessages([
                'veiculo_id' => 'O veículo selecionado não pertence ao cliente informado.',
            ]);
        }

        $dataInicio = Carbon::parse($validated['data_inicio']);

        // Se o horário foi alterado, exige que não seja inferior ao momento atual
        if (!$agendamento->data_inicio || !$dataInicio->equalTo($agendamento->data_inicio)) {
            if ($dataInicio->lt(now()->subMinutes(1))) {
                throw ValidationException::withMessages([
                    'data_inicio' => 'A data e o horário de início não podem ser inferiores à data e horário atuais.',
                ]);
            }
        }

        $servicosModels = !$possuiOS
            ? $this->obterServicosValidos($validated['servicos'])
            : collect();

        $somaMinutos = !$possuiOS
            ? (int) $servicosModels->sum('tempo_estimado_minutos')
            : (int) $agendamento->agendamentosServicos->sum('tempo_estimado_minutos');

        $dataFimEstimada = $dataInicio->copy()->addMinutes($somaMinutos + self::GARGALO_MINUTOS);

        // Valida expediente e conflitos sempre que estiver ativo (não cancelado)
        if ($validated['status'] !== 'cancelado') {
            $this->validarExpediente($dataInicio);

            $clienteModel = Cliente::withoutGlobalScopes()->find($validated['cliente_id']);
            $empresaId = $clienteModel?->empresa_id ?? ($user?->getEmpresaAtivaId());

            $this->validarDisponibilidade(
                veiculoId: (int) $veiculo->id,
                empresaId: $empresaId ? (int) $empresaId : null,
                dataInicio: $dataInicio,
                dataFimEstimada: $dataFimEstimada,
                ignorarAgendamentoId: (int) $agendamento->id
            );
        }

        DB::transaction(function () use ($agendamento, $validated, $dataInicio, $dataFimEstimada, $possuiOS, $servicosModels) {
            $dadosUpdate = [
                'cliente_id' => $validated['cliente_id'],
                'veiculo_id' => $validated['veiculo_id'],
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFimEstimada,
                'status' => $validated['status'],
                'origem' => $validated['origem'] ?? $agendamento->origem,
                'observacoes' => $validated['observacoes'] ?? null,
            ];

            if ($validated['status'] === 'concluido' && !$agendamento->data_conclusao) {
                $dadosUpdate['data_conclusao'] = now();
            }

            $agendamento->update($dadosUpdate);

            if ($validated['status'] === 'cancelado' && $agendamento->ordemServico && $agendamento->ordemServico->status === 'aberta') {
                $agendamento->ordemServico->update(['status' => 'cancelada']);
            }

            if (!$possuiOS) {
                $agendamento->agendamentosServicos()->delete();

                foreach ($servicosModels as $servico) {
                    AgendamentoServico::create([
                        'agendamento_id' => $agendamento->id,
                        'servico_id' => $servico->id,
                        'valor_servico' => $servico->preco_base,
                        'tempo_estimado_minutos' => $servico->tempo_estimado_minutos,
                        'observacoes' => null,
                    ]);
                }
            }
        });

        return redirect()->route('agendamentos.show', $agendamento)->with('success', 'Agendamento atualizado com sucesso!');
    }

    public function cancelar(Agendamento $agendamento)
    {
        /** @var User|null $user */
        $user = Auth::user();
        $agendamento->load(['ordemServico.tarefas']);

        if ($user instanceof User && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            if ((int) $agendamento->cliente_id !== (int) $cliente->id) {
                abort(403, 'Você não tem permissão para cancelar este agendamento.');
            }

            if (now()->diffInMinutes($agendamento->data_inicio, false) < (self::ANTECEDENCIA_CANCELAMENTO_HORAS * 60)) {
                $unidadeHora = self::ANTECEDENCIA_CANCELAMENTO_HORAS === 1 ? 'hora' : 'horas';
                return back()->with('error', 'O cancelamento pelo portal exige antecedência mínima de ' . self::ANTECEDENCIA_CANCELAMENTO_HORAS . " {$unidadeHora} do horário agendado.");
            }
        }

        if (!in_array($agendamento->status, ['agendado', 'confirmado'], true)) {
            return back()->with('error', 'Apenas agendamentos com status Agendado ou Confirmado podem ser cancelados.');
        }

        if ($agendamento->ordemServico) {
            $temTarefasEmAndamentoOuConcluidas = $agendamento->ordemServico->tarefas->contains(
                fn ($t) => in_array($t->status, ['em_andamento', 'concluida'], true)
            );

            if (in_array($agendamento->ordemServico->status, ['em_andamento', 'concluida'], true) || $temTarefasEmAndamentoOuConcluidas) {
                return back()->with('error', 'Não é possível cancelar este agendamento porque a Ordem de Serviço já iniciou a execução.');
            }
        }

        DB::transaction(function () use ($agendamento) {
            $agendamento->update(['status' => 'cancelado']);

            if ($agendamento->ordemServico && $agendamento->ordemServico->status === 'aberta') {
                $agendamento->ordemServico->update(['status' => 'cancelada']);
            }
        });

        return back()->with('success', 'Agendamento cancelado com sucesso.');
    }

    public function destroy(Agendamento $agendamento)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user instanceof User && $user->isCliente()) {
            abort(403);
        }

        $possuiOrdemServico = $agendamento->ordemServico()->exists();
        $possuiTarefas = $agendamento->agendamentosServicos()->whereHas('tarefa')->exists();

        if ($possuiOrdemServico || $possuiTarefas) {
            return redirect()
                ->back()
                ->with('error', 'Não é possível excluir este agendamento, pois ele já possui uma Ordem de Serviço ou tarefas atribuídas.');
        }

        try {
            DB::transaction(function () use ($agendamento) {
                $agendamento->agendamentosServicos()->delete();
                $agendamento->delete();
            });
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()
                ->back()
                ->with('error', 'Não é possível excluir este agendamento, pois ele já possui Ordem de Serviço ou tarefas vinculadas.');
        }

        return redirect()->route('agendamentos.index')->with('success', 'Agendamento removido com sucesso!');
    }

    /**
     * Valida e retorna os serviços ativos pertencentes à empresa do usuário.
     */
    private function obterServicosValidos(array $servicosInput): Collection
    {
        $servicosIds = collect($servicosInput)
            ->pluck('servico_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $servicosModels = Servico::whereIn('id', $servicosIds)
            ->where('ativo', true)
            ->get();

        if ($servicosModels->count() !== $servicosIds->count()) {
            throw ValidationException::withMessages([
                'servicos' => 'Um ou mais serviços selecionados estão inativos ou não pertencem a esta empresa.',
            ]);
        }

        return $servicosModels;
    }

    /**
     * Valida dias úteis (segunda a sábado) e horário de expediente (08:00 às 18:00).
     */
    private function validarExpediente(Carbon $dataInicio): void
    {
        if ($dataInicio->isSunday()) {
            throw ValidationException::withMessages([
                'data_inicio' => 'A oficina não realiza atendimentos aos domingos. Escolha uma data entre segunda e sábado.',
            ]);
        }

        $horaFormatada = $dataInicio->format('H:i');
        $abertura = str_pad((string) self::HORA_ABERTURA, 2, '0', STR_PAD_LEFT) . ':00';
        $fechamento = str_pad((string) self::HORA_FECHAMENTO, 2, '0', STR_PAD_LEFT) . ':00';

        if ($horaFormatada < $abertura || $horaFormatada > $fechamento) {
            throw ValidationException::withMessages([
                'data_inicio' => "O horário de início do agendamento deve estar dentro do expediente comercial ({$abertura} às {$fechamento}).",
            ]);
        }
    }

    /**
     * Garante ausência de sobreposição para o mesmo veículo e respeito à capacidade de boxes simultâneos.
     */
    private function validarDisponibilidade(
        int $veiculoId,
        ?int $empresaId,
        Carbon $dataInicio,
        Carbon $dataFimEstimada,
        ?int $ignorarAgendamentoId = null
    ): void {
        $aplicarSobreposicao = function ($query) use ($dataInicio, $dataFimEstimada) {
            $query->where('data_inicio', '<', $dataFimEstimada)
                ->where(function ($q) use ($dataInicio) {
                    $q->where('data_fim', '>', $dataInicio)
                        ->orWhere(function ($q2) use ($dataInicio) {
                            $q2->whereNull('data_fim')
                                ->where('data_inicio', '>', $dataInicio->copy()->subHours(2));
                        });
                });
        };

        $conflitoVeiculo = Agendamento::withoutGlobalScopes()
            ->where('veiculo_id', $veiculoId)
            ->whereIn('status', ['agendado', 'confirmado', 'em_andamento'])
            ->when($ignorarAgendamentoId, fn ($q) => $q->where('id', '!=', $ignorarAgendamentoId))
            ->tap($aplicarSobreposicao)
            ->orderBy('data_inicio')
            ->first();

        if ($conflitoVeiculo) {
            $inicioConf = $conflitoVeiculo->data_inicio->format('d/m/Y H:i');
            $fimConf = ($conflitoVeiculo->data_fim ?? $conflitoVeiculo->data_inicio)->format('H:i');
            throw ValidationException::withMessages([
                'data_inicio' => "Este veículo já possui um agendamento ativo neste intervalo ({$inicioConf} às {$fimConf}).",
            ]);
        }

        $boxesOcupados = Agendamento::withoutGlobalScopes()
            ->whereIn('status', ['agendado', 'confirmado', 'em_andamento'])
            ->when($ignorarAgendamentoId, fn ($q) => $q->where('id', '!=', $ignorarAgendamentoId))
            ->when($empresaId, function ($q) use ($empresaId) {
                $q->whereHas('cliente', fn ($cq) => $cq->withoutGlobalScopes()->where('empresa_id', $empresaId));
            })
            ->tap($aplicarSobreposicao)
            ->count();

        if ($boxesOcupados >= self::CAPACIDADE_BOXES) {
            throw ValidationException::withMessages([
                'data_inicio' => 'Todos os ' . self::CAPACIDADE_BOXES . ' boxes de atendimento simultâneo estão ocupados nesta faixa de horário. Escolha outro horário.',
            ]);
        }
    }
}
