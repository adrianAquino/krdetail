<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\AgendamentoServico;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Servico;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\MovimentarEstoqueService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        $query = Agendamento::with(['empresa', 'cliente', 'veiculo', 'servicos', 'agendamentosServicos.servico', 'ordemServico.tarefas']);

        if ($user instanceof User && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            $query->where('cliente_id', $cliente->id);
        }

        $status = trim($this->inputString($request, 'status'));
        if (in_array($status, ['agendado', 'confirmado', 'em_andamento', 'concluido', 'cancelado'], true)) {
            $query->where('status', $status);
        }

        $busca = trim($this->inputString($request, 'busca'));
        if ($busca !== '') {
            $query->where(function ($q) use ($busca, $user) {
                $q->whereHas('veiculo', fn ($vq) => $vq->where('placa', 'like', "%{$busca}%")
                    ->orWhere('modelo', 'like', "%{$busca}%")
                    ->orWhere('marca', 'like', "%{$busca}%"));

                if (! ($user instanceof User) || ! $user->isCliente()) {
                    $q->orWhereHas('cliente', fn ($cq) => $cq->where('nome', 'like', "%{$busca}%"));
                }
            });
        }

        $agendamentos = $query->orderByDesc('data_inicio')->paginate(15)->withQueryString();

        return view('agendamentos.index', compact('agendamentos'));
    }

    public function create(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user instanceof User && $user->isCliente()) {
            if (! $user->possuiEsteticaVinculada()) {
                return redirect()
                    ->route('agendamentos.index')
                    ->with('error', 'Você ainda não está vinculado a nenhuma estética automotiva. Solicite que uma estética cadastre seus dados para realizar novos agendamentos.');
            }

            $cliente = $user->ensureClienteProfile();
            $clientes = Cliente::with('veiculos')->where('id', $cliente->id)->get();
        } else {
            $clientes = Cliente::with('veiculos')->orderBy('nome')->get();
        }

        $servicos = Servico::where('ativo', true)->orderBy('nome')->get();

        return view('agendamentos.create', compact('clientes', 'servicos'));
    }

    /**
     * Retorna a disponibilidade mensal (dias e slots de 30min) para o modal de calendário,
     * considerando duração dos serviços + gargalo, limite de 4 boxes, conflitos do veículo e pernoite.
     */
    public function disponibilidade(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user instanceof User && $user->isCliente() && ! $user->possuiEsteticaVinculada()) {
            return response()->json([
                'message' => 'Você ainda não está vinculado a nenhuma estética automotiva.',
            ], 403);
        }

        $empresaId = $user?->getEmpresaAtivaId();
        if ($user instanceof User && ! $user->isCliente() && $request->filled('cliente_id')) {
            $cli = Cliente::find($request->integer('cliente_id'));
            if ($cli && $cli->empresa_id) {
                $empresaId = (int) $cli->empresa_id;
            }
        }

        $mesParam = trim($this->inputString($request, 'mes'));
        if (! preg_match('/^\d{4}-\d{2}$/', $mesParam)) {
            $mesParam = now()->format('Y-m');
        }

        try {
            $inicioMes = Carbon::createFromFormat('Y-m', $mesParam)->startOfMonth();
        } catch (\Throwable) {
            $inicioMes = now()->startOfMonth();
            $mesParam = $inicioMes->format('Y-m');
        }
        $fimMes = $inicioMes->copy()->endOfMonth();

        $servicosIds = collect($request->input('servicos_ids', []))
            ->filter(fn ($id) => is_scalar($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($servicosIds->isNotEmpty()) {
            $somaServicos = (int) Servico::whereIn('id', $servicosIds)
                ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
                ->sum('tempo_estimado_minutos');
            $duracaoMinutos = $somaServicos > 0 ? ($somaServicos + self::GARGALO_MINUTOS) : 90;
        } else {
            $duracaoMinutos = max(30, min(3000, $request->integer('duracao_minutos', 90)));
        }

        $deixarVeiculo = $request->boolean('deixar_veiculo');
        $ignorarId = $request->filled('ignorar_agendamento_id') ? $request->integer('ignorar_agendamento_id') : null;

        $veiculoAutorizado = null;
        if ($request->filled('veiculo_id')) {
            $veiculoAutorizado = Veiculo::find($request->integer('veiculo_id'));
        }

        $janelaInicio = $inicioMes->copy()->subDays(5)->startOfDay();
        $janelaFim = $fimMes->copy()->addDays(10)->endOfDay();

        $agendamentosEmpresa = Agendamento::withoutGlobalScopes()
            ->select(['id', 'data_inicio', 'data_fim'])
            ->whereIn('status', ['agendado', 'confirmado', 'em_andamento'])
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->when($empresaId, function ($q) use ($empresaId) {
                $q->where(function ($sub) use ($empresaId) {
                    $sub->where('empresa_id', $empresaId)
                        ->orWhere(function ($sub2) use ($empresaId) {
                            $sub2->whereNull('empresa_id')
                                ->whereHas('cliente', fn ($cq) => $cq->withoutGlobalScopes()->where('empresa_id', $empresaId));
                        });
                });
            })
            ->where('data_inicio', '<', $janelaFim)
            ->where(function ($q) use ($janelaInicio) {
                $q->where('data_fim', '>', $janelaInicio)
                    ->orWhereNull('data_fim');
            })
            ->get();

        $agendamentosVeiculo = $veiculoAutorizado
            ? Agendamento::withoutGlobalScopes()
                ->select(['id', 'data_inicio', 'data_fim'])
                ->where('veiculo_id', $veiculoAutorizado->id)
                ->whereIn('status', ['agendado', 'confirmado', 'em_andamento'])
                ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
                ->where('data_inicio', '<', $janelaFim)
                ->where(function ($q) use ($janelaInicio) {
                    $q->where('data_fim', '>', $janelaInicio)
                        ->orWhereNull('data_fim');
                })
                ->get()
            : collect();

        $hojeInicio = now()->startOfDay();
        $agoraLimite = now()->subMinute();
        $dias = [];

        $cursor = $inicioMes->copy();
        while ($cursor->lte($fimMes)) {
            $dataStr = $cursor->toDateString();

            if ($cursor->isSunday()) {
                $dias[$dataStr] = [
                    'data' => $dataStr,
                    'dia' => (int) $cursor->day,
                    'dia_semana' => (int) $cursor->dayOfWeek,
                    'status' => 'fechado',
                    'motivo' => 'Domingo (oficina fechada)',
                    'slots_livres_count' => 0,
                    'slots_pernoite_count' => 0,
                    'slots' => [],
                ];
                $cursor->addDay();
                continue;
            }

            if ($cursor->lt($hojeInicio)) {
                $dias[$dataStr] = [
                    'data' => $dataStr,
                    'dia' => (int) $cursor->day,
                    'dia_semana' => (int) $cursor->dayOfWeek,
                    'status' => 'passado',
                    'motivo' => 'Data anterior',
                    'slots_livres_count' => 0,
                    'slots_pernoite_count' => 0,
                    'slots' => [],
                ];
                $cursor->addDay();
                continue;
            }

            $fimExpedienteDia = $cursor->copy()->setTime(self::HORA_FECHAMENTO, 0, 0);
            $slots = [];
            $slotsLivresCount = 0;
            $slotsPernoiteCount = 0;
            $teveSlotLotadoOuConflito = false;

            for ($hora = self::HORA_ABERTURA; $hora < self::HORA_FECHAMENTO; $hora++) {
                foreach ([0, 30] as $minuto) {
                    $slotInicio = $cursor->copy()->setTime($hora, $minuto, 0);
                    if ($slotInicio->lt($agoraLimite)) {
                        continue;
                    }

                    $fimMesmoDia = $slotInicio->copy()->addMinutes($duracaoMinutos);
                    $requerPernoite = $fimMesmoDia->gt($fimExpedienteDia);
                    $slotFimEfetivo = $requerPernoite
                        ? $this->calcularDataFimEstimada($slotInicio, $duracaoMinutos, true)
                        : $fimMesmoDia;

                    $picoSimultaneo = $this->calcularPicoBoxes($agendamentosEmpresa, $slotInicio, $slotFimEfetivo);
                    $boxesLivres = max(0, self::CAPACIDADE_BOXES - $picoSimultaneo);

                    $conflitoDetalhe = null;
                    $conflitoVeic = false;
                    foreach ($agendamentosVeiculo as $agV) {
                        $fimV = $agV->data_fim ?? $agV->data_inicio->copy()->addHours(2);
                        if ($agV->data_inicio->lt($slotFimEfetivo) && $fimV->gt($slotInicio)) {
                            $conflitoVeic = true;
                            $conflitoDetalhe = $agV->data_inicio->format('d/m H:i') . ' às ' . $fimV->format('d/m H:i');
                            break;
                        }
                    }

                    $disponivelAgora = $boxesLivres > 0 && ! $conflitoVeic && (! $requerPernoite || $deixarVeiculo);
                    $disponivelComPernoite = $boxesLivres > 0 && ! $conflitoVeic && $requerPernoite;

                    if ($disponivelAgora) {
                        $slotsLivresCount++;
                    } elseif ($disponivelComPernoite) {
                        $slotsPernoiteCount++;
                    } elseif ($boxesLivres === 0 || $conflitoVeic) {
                        $teveSlotLotadoOuConflito = true;
                    }

                    $motivoIndisponivel = null;
                    if ($conflitoVeic) {
                        $motivoIndisponivel = "Veículo já agendado ({$conflitoDetalhe})";
                    } elseif ($boxesLivres === 0) {
                        $motivoIndisponivel = 'Todos os ' . self::CAPACIDADE_BOXES . ' boxes ocupados';
                    } elseif ($requerPernoite && ! $deixarVeiculo) {
                        $motivoIndisponivel = 'Ultrapassa as 18:00 (ative "Deixar veículo na estética")';
                    }

                    $slots[] = [
                        'hora' => $slotInicio->format('H:i'),
                        'inicio_iso' => $slotInicio->format('Y-m-d\TH:i'),
                        'fim_iso' => $slotFimEfetivo->format('Y-m-d\TH:i'),
                        'fim_formatado' => $slotFimEfetivo->format('d/m/Y \à\s H:i'),
                        'boxes_ocupados' => $picoSimultaneo,
                        'boxes_livres' => $boxesLivres,
                        'requer_pernoite' => $requerPernoite,
                        'conflito_veiculo' => $conflitoVeic,
                        'conflito_detalhe' => $conflitoDetalhe,
                        'disponivel' => $disponivelAgora,
                        'disponivel_com_pernoite' => $disponivelComPernoite,
                        'motivo_indisponivel' => $motivoIndisponivel,
                    ];
                }
            }

            $statusDia = 'indisponivel';
            if ($slotsLivresCount > 0) {
                $statusDia = 'disponivel';
            } elseif ($slotsPernoiteCount > 0) {
                $statusDia = 'apenas_pernoite';
            } elseif ($teveSlotLotadoOuConflito) {
                $statusDia = 'lotado';
            }

            $dias[$dataStr] = [
                'data' => $dataStr,
                'dia' => (int) $cursor->day,
                'dia_semana' => (int) $cursor->dayOfWeek,
                'status' => $statusDia,
                'slots_livres_count' => $slotsLivresCount,
                'slots_pernoite_count' => $slotsPernoiteCount,
                'slots' => $slots,
            ];

            $cursor->addDay();
        }

        return response()->json([
            'mes' => $mesParam,
            'capacidade_boxes' => self::CAPACIDADE_BOXES,
            'duracao_minutos' => $duracaoMinutos,
            'deixar_veiculo' => $deixarVeiculo,
            'veiculo_placa' => $veiculoAutorizado?->placa,
            'dias' => $dias,
        ]);
    }

    public function store(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user instanceof User && $user->isCliente()) {
            if (! $user->possuiEsteticaVinculada()) {
                return redirect()
                    ->route('agendamentos.index')
                    ->with('error', 'Você ainda não está vinculado a nenhuma estética automotiva. Solicite que uma estética cadastre seus dados para realizar novos agendamentos.');
            }

            $cliente = $user->ensureClienteProfile();
            $request->merge([
                'cliente_id' => $cliente->id,
                'status' => 'agendado',
                'origem' => 'Site',
            ]);
        }

        $empresaAtivaStaffId = ($user instanceof User && ($user->isDono() || $user->isFuncionario()))
            ? $user->getEmpresaAtivaId()
            : null;

        $clienteRule = Rule::exists('clientes', 'id');
        if ($user instanceof User && ($user->isDono() || $user->isFuncionario())) {
            $clienteRule = $clienteRule->where('empresa_id', $empresaAtivaStaffId ?? -1);
        }

        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', $clienteRule],
            'veiculo_id' => 'required|integer|exists:veiculos,id',
            'data_inicio' => [
                'required',
                'date',
                'after_or_equal:' . now()->subMinutes(1)->format('Y-m-d H:i:s'),
                'before:2100-01-01',
            ],
            'deixar_veiculo' => 'nullable|boolean',
            'status' => 'nullable|in:agendado,confirmado,em_andamento',
            'origem' => 'nullable|string|max:50',
            'observacoes' => 'nullable|string|max:500',
            'servicos' => 'required|array|min:1',
            'servicos.*.servico_id' => 'required|integer|distinct|exists:servicos,id',
        ], [
            'cliente_id.exists' => 'O cliente selecionado não existe ou não pertence à sua empresa.',
            'data_inicio.required' => 'Informe a data e o horário de início do agendamento.',
            'data_inicio.date' => 'Informe uma data e horário válidos.',
            'data_inicio.after_or_equal' => 'A data e o horário de início não podem ser inferiores à data e horário atuais.',
            'data_inicio.before' => 'Informe uma data válida dentro do intervalo operacional do sistema.',
            'servicos.required' => 'Selecione pelo menos um serviço para o agendamento.',
            'servicos.min' => 'Selecione pelo menos um serviço para o agendamento.',
            'servicos.*.servico_id.distinct' => 'Não é permitido adicionar o mesmo serviço mais de uma vez no mesmo agendamento.',
        ]);

        if ($user instanceof User && $user->isCliente()) {
            $validated['cliente_id'] = $user->ensureClienteProfile()->id;
            $validated['status'] = 'agendado';
            $validated['origem'] = 'Site';
        }

        $clienteModel = Cliente::findOrFail($validated['cliente_id']);
        $empresaId = $clienteModel->empresa_id ? (int) $clienteModel->empresa_id : $user?->getEmpresaAtivaId();

        $veiculo = Veiculo::findOrFail($validated['veiculo_id']);
        if ((int) $veiculo->cliente_id !== (int) $validated['cliente_id']) {
            throw ValidationException::withMessages([
                'veiculo_id' => 'O veículo selecionado não pertence ao cliente informado.',
            ]);
        }

        $servicosModels = $this->obterServicosValidos($validated['servicos'], $empresaId ? (int) $empresaId : null);

        $dataInicio = Carbon::parse($validated['data_inicio']);
        $somaMinutos = (int) $servicosModels->sum('tempo_estimado_minutos');
        $deixarVeiculo = $request->boolean('deixar_veiculo');
        $duracaoTotal = $somaMinutos + self::GARGALO_MINUTOS;
        $dataFimEstimada = $this->calcularDataFimEstimada($dataInicio, $duracaoTotal, $deixarVeiculo);

        $this->validarExpediente($dataInicio, $dataFimEstimada, $deixarVeiculo);

        $agendamento = DB::transaction(function () use ($validated, $servicosModels, $dataInicio, $dataFimEstimada, $deixarVeiculo, $veiculo, $empresaId) {
            if ($empresaId) {
                Empresa::whereKey($empresaId)->lockForUpdate()->first();
            }
            Veiculo::withoutGlobalScopes()->whereKey($veiculo->id)->lockForUpdate()->first();

            $this->validarDisponibilidade(
                veiculoId: (int) $veiculo->id,
                empresaId: $empresaId ? (int) $empresaId : null,
                dataInicio: $dataInicio,
                dataFimEstimada: $dataFimEstimada
            );

            $ag = Agendamento::create([
                'empresa_id' => $empresaId ? (int) $empresaId : null,
                'cliente_id' => $validated['cliente_id'],
                'veiculo_id' => $validated['veiculo_id'],
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFimEstimada,
                'data_conclusao' => null,
                'deixar_veiculo' => $deixarVeiculo,
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
            'empresa',
            'cliente' => fn ($q) => $q->withoutGlobalScopes(),
            'veiculo' => fn ($q) => $q->withoutGlobalScopes(),
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

        $agendamento->load(['agendamentosServicos', 'ordemServico', 'cliente', 'veiculo']);
        $clientes = Cliente::with('veiculos')->orderBy('nome')->get();

        // Inclui serviços já contratados no agendamento mesmo que tenham sido desativados posteriormente
        $servicosVinculadosIds = $agendamento->agendamentosServicos->pluck('servico_id')->filter()->unique();
        $servicos = Servico::where(function ($q) use ($servicosVinculadosIds) {
            $q->where('ativo', true);
            if ($servicosVinculadosIds->isNotEmpty()) {
                $q->orWhereIn('id', $servicosVinculadosIds);
            }
        })->orderBy('nome')->get();

        return view('agendamentos.edit', compact('agendamento', 'clientes', 'servicos'));
    }

    public function update(Request $request, Agendamento $agendamento, MovimentarEstoqueService $estoqueService)
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

        $empresaAtivaStaffId = ($user instanceof User && ($user->isDono() || $user->isFuncionario()))
            ? $user->getEmpresaAtivaId()
            : null;

        $clienteRule = Rule::exists('clientes', 'id');
        if ($user instanceof User && ($user->isDono() || $user->isFuncionario())) {
            $clienteRule = $clienteRule->where('empresa_id', $empresaAtivaStaffId ?? -1);
        }

        $rules = [
            'cliente_id' => ['required', 'integer', $clienteRule],
            'veiculo_id' => 'required|integer|exists:veiculos,id',
            'data_inicio' => 'required|date|before:2100-01-01',
            'deixar_veiculo' => 'nullable|boolean',
            'status' => 'required|in:agendado,confirmado,em_andamento,concluido,cancelado',
            'origem' => 'nullable|string|max:50',
            'observacoes' => 'nullable|string|max:500',
        ];

        if (! $possuiOS) {
            $rules['servicos'] = 'required|array|min:1';
            $rules['servicos.*.servico_id'] = 'required|integer|distinct|exists:servicos,id';
        }

        $validated = $request->validate($rules, [
            'cliente_id.exists' => 'O cliente selecionado não existe ou não pertence à sua empresa.',
            'data_inicio.required' => 'Informe a data e o horário de início do agendamento.',
            'data_inicio.date' => 'Informe uma data e horário válidos.',
            'data_inicio.before' => 'Informe uma data válida dentro do intervalo operacional do sistema.',
            'servicos.required' => 'Selecione pelo menos um serviço para o agendamento.',
            'servicos.min' => 'Selecione pelo menos um serviço para o agendamento.',
            'servicos.*.servico_id.distinct' => 'Não é permitido adicionar o mesmo serviço mais de uma vez no mesmo agendamento.',
        ]);

        $clienteModel = Cliente::findOrFail($validated['cliente_id']);
        $empresaId = $clienteModel->empresa_id ? (int) $clienteModel->empresa_id : $user?->getEmpresaAtivaId();

        $veiculo = Veiculo::findOrFail($validated['veiculo_id']);
        if ((int) $veiculo->cliente_id !== (int) $validated['cliente_id']) {
            throw ValidationException::withMessages([
                'veiculo_id' => 'O veículo selecionado não pertence ao cliente informado.',
            ]);
        }

        $dataInicio = Carbon::parse($validated['data_inicio']);
        $deixarVeiculo = $request->boolean('deixar_veiculo');

        DB::transaction(function () use ($agendamento, $validated, $dataInicio, $deixarVeiculo, $veiculo, $empresaId, $estoqueService, $user) {
            if ($empresaId) {
                Empresa::whereKey($empresaId)->lockForUpdate()->first();
            }
            Veiculo::withoutGlobalScopes()->whereKey($veiculo->id)->lockForUpdate()->first();

            $agTravado = Agendamento::lockForUpdate()
                ->with(['agendamentosServicos', 'ordemServico.tarefas.tarefasProdutos'])
                ->findOrFail($agendamento->id);

            if (in_array($agTravado->status, ['concluido', 'cancelado'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Este agendamento já foi concluído ou cancelado em outra operação.',
                ]);
            }

            $osVinculada = $agTravado->ordemServico;
            $possuiOSAtual = $osVinculada !== null;

            if ($possuiOSAtual) {
                if (
                    (int) $validated['cliente_id'] !== (int) $agTravado->cliente_id
                    || (int) $validated['veiculo_id'] !== (int) $agTravado->veiculo_id
                ) {
                    throw ValidationException::withMessages([
                        'veiculo_id' => 'Não é possível alterar o cliente ou o veículo porque este agendamento já possui uma Ordem de Serviço vinculada.',
                    ]);
                }

                if ($osVinculada->status === 'em_andamento' && in_array($validated['status'], ['agendado', 'confirmado'], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Não é possível regredir o status para Agendado ou Confirmado enquanto a Ordem de Serviço estiver em andamento.',
                    ]);
                }

                if ($validated['status'] === 'concluido') {
                    if ($osVinculada->tarefas->isEmpty()) {
                        throw ValidationException::withMessages([
                            'status' => 'A Ordem de Serviço vinculada não possui tarefas cadastradas.',
                        ]);
                    }
                    $temTarefasPendentes = $osVinculada->tarefas->contains(fn ($t) => $t->status !== 'concluida');
                    if ($temTarefasPendentes) {
                        throw ValidationException::withMessages([
                            'status' => 'Não é possível concluir o agendamento enquanto existirem tarefas pendentes ou em andamento na Ordem de Serviço.',
                        ]);
                    }
                }

                if ($validated['status'] === 'cancelado') {
                    $temTarefasIniciadas = $osVinculada->tarefas->contains(
                        fn ($t) => in_array($t->status, ['em_andamento', 'concluida'], true)
                    );
                    if ($temTarefasIniciadas || in_array($osVinculada->status, ['em_andamento', 'concluida'], true)) {
                        throw ValidationException::withMessages([
                            'status' => 'Não é possível cancelar o agendamento porque já existem tarefas iniciadas ou concluídas na Ordem de Serviço.',
                        ]);
                    }
                }
            } elseif ($validated['status'] === 'concluido') {
                throw ValidationException::withMessages([
                    'status' => 'Para concluir o atendimento e gerar o lançamento financeiro, abra e finalize a Ordem de Serviço correspondente.',
                ]);
            }

            // Se o horário foi alterado em um agendamento não cancelado, exige horário futuro
            $dataMudou = ! $agTravado->data_inicio || ! $dataInicio->equalTo($agTravado->data_inicio);
            if ($dataMudou && $validated['status'] !== 'cancelado' && $dataInicio->lt(now()->subMinutes(1))) {
                throw ValidationException::withMessages([
                    'data_inicio' => 'A data e o horário de início não podem ser inferiores à data e horário atuais.',
                ]);
            }

            $servicosPermitidosIds = $agTravado->agendamentosServicos->pluck('servico_id')->map(fn ($id) => (int) $id)->all();
            $servicosModels = ! $possuiOSAtual
                ? $this->obterServicosValidos($validated['servicos'] ?? [], $empresaId, $servicosPermitidosIds)
                : collect();

            $existentesPorServico = $agTravado->agendamentosServicos->keyBy('servico_id');

            if (! $possuiOSAtual) {
                $somaMinutos = (int) $servicosModels->sum(function (Servico $servico) use ($existentesPorServico) {
                    $existente = $existentesPorServico->get($servico->id);
                    return $existente && $existente->tempo_estimado_minutos !== null
                        ? (int) $existente->tempo_estimado_minutos
                        : (int) $servico->tempo_estimado_minutos;
                });
            } else {
                $somaMinutos = (int) $agTravado->agendamentosServicos->sum('tempo_estimado_minutos');
            }

            $duracaoTotal = $somaMinutos + self::GARGALO_MINUTOS;
            $dataFimEstimada = $this->calcularDataFimEstimada($dataInicio, $duracaoTotal, $deixarVeiculo);

            $dataMudou = ! $agTravado->data_inicio || ! $dataInicio->equalTo($agTravado->data_inicio);
            $veiculoMudou = (int) $validated['veiculo_id'] !== (int) $agTravado->veiculo_id;
            $servicosMudaram = ! $possuiOSAtual
                && $servicosModels->pluck('id')->sort()->values()->all() !== $agTravado->agendamentosServicos->pluck('servico_id')->sort()->values()->all();
            $pernoiteMudou = (bool) $agTravado->deixar_veiculo !== $deixarVeiculo;

            if ($validated['status'] !== 'cancelado') {
                if ($dataMudou || $veiculoMudou || $servicosMudaram || $pernoiteMudou) {
                    $this->validarExpediente($dataInicio, $dataFimEstimada, $deixarVeiculo);
                }

                // Sempre valida ausência de colisão de veículo e limite de boxes ao salvar um agendamento ativo
                $this->validarDisponibilidade(
                    veiculoId: (int) $veiculo->id,
                    empresaId: $empresaId,
                    dataInicio: $dataInicio,
                    dataFimEstimada: $dataFimEstimada,
                    ignorarAgendamentoId: (int) $agTravado->id
                );
            }

            $agora = Carbon::now();
            $dadosUpdate = [
                'empresa_id' => $agTravado->empresa_id ?: ($empresaId ? (int) $empresaId : null),
                'cliente_id' => $validated['cliente_id'],
                'veiculo_id' => $validated['veiculo_id'],
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFimEstimada,
                'deixar_veiculo' => $deixarVeiculo,
                'status' => $validated['status'],
                'origem' => $validated['origem'] ?? $agTravado->origem,
                'observacoes' => $validated['observacoes'] ?? null,
            ];

            if ($validated['status'] === 'concluido' && ! $agTravado->data_conclusao) {
                $dadosUpdate['data_conclusao'] = $agora;
            }

            $agTravado->update($dadosUpdate);

            if ($possuiOSAtual) {
                if ($validated['status'] === 'cancelado' && $osVinculada->status === 'aberta') {
                    $this->estornarInsumosEExcluirVinculos($osVinculada, $estoqueService, $user, "Estorno por cancelamento do Agendamento #{$agTravado->id}");
                    $osVinculada->update(['status' => 'cancelada']);
                } elseif ($validated['status'] === 'concluido' && $osVinculada->status !== 'concluida') {
                    $osVinculada->update([
                        'status' => 'concluida',
                        'data_fim' => $agora,
                    ]);
                }
            }

            // Faz diff preservando valor_servico e tempo_estimado_minutos históricos dos serviços mantidos
            if (! $possuiOSAtual) {
                $novosIds = $servicosModels->pluck('id')->map(fn ($id) => (int) $id)->all();
                $agTravado->agendamentosServicos()->whereNotIn('servico_id', $novosIds)->delete();

                foreach ($servicosModels as $servico) {
                    if (! $existentesPorServico->has($servico->id)) {
                        AgendamentoServico::create([
                            'agendamento_id' => $agTravado->id,
                            'servico_id' => $servico->id,
                            'valor_servico' => $servico->preco_base,
                            'tempo_estimado_minutos' => $servico->tempo_estimado_minutos,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('agendamentos.show', $agendamento)->with('success', 'Agendamento atualizado com sucesso!');
    }

    public function cancelar(Agendamento $agendamento, MovimentarEstoqueService $estoqueService)
    {
        /** @var User|null $user */
        $user = Auth::user();

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

        try {
            DB::transaction(function () use ($agendamento, $estoqueService, $user) {
                $agTravado = Agendamento::lockForUpdate()
                    ->with(['ordemServico.tarefas.tarefasProdutos'])
                    ->findOrFail($agendamento->id);

                if (! in_array($agTravado->status, ['agendado', 'confirmado'], true)) {
                    throw new \DomainException('Apenas agendamentos com status Agendado ou Confirmado podem ser cancelados.');
                }

                $os = $agTravado->ordemServico;
                if ($os) {
                    $temTarefasEmAndamentoOuConcluidas = $os->tarefas->contains(
                        fn ($t) => in_array($t->status, ['em_andamento', 'concluida'], true)
                    );

                    if (in_array($os->status, ['em_andamento', 'concluida'], true) || $temTarefasEmAndamentoOuConcluidas) {
                        throw new \DomainException('Não é possível cancelar este agendamento porque a Ordem de Serviço já iniciou a execução.');
                    }

                    if ($os->status === 'aberta') {
                        $this->estornarInsumosEExcluirVinculos($os, $estoqueService, $user, "Estorno automático por cancelamento do Agendamento #{$agTravado->id}");
                        $os->update(['status' => 'cancelada']);
                    }
                }

                $agTravado->update(['status' => 'cancelado']);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Agendamento cancelado com sucesso.');
    }

    public function destroy(Agendamento $agendamento)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user instanceof User && $user->isCliente()) {
            abort(403);
        }

        if (in_array($agendamento->status, ['em_andamento', 'concluido'], true)) {
            return redirect()
                ->back()
                ->with('error', 'Não é possível excluir um agendamento em andamento ou já concluído.');
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
        } catch (QueryException $e) {
            return redirect()
                ->back()
                ->with('error', 'Não é possível excluir este agendamento, pois ele já possui Ordem de Serviço ou tarefas vinculadas.');
        }

        return redirect()->route('agendamentos.index')->with('success', 'Agendamento removido com sucesso!');
    }

    /**
     * Estorna automaticamente eventuais produtos já alocados em tarefas pendentes antes de cancelar uma OS aberta.
     */
    private function estornarInsumosEExcluirVinculos(
        \App\Models\OrdemServico $os,
        MovimentarEstoqueService $estoqueService,
        ?User $usuario,
        string $motivoBase
    ): void {
        foreach ($os->tarefas as $tarefa) {
            foreach ($tarefa->tarefasProdutos as $tp) {
                $qtd = (float) $tp->quantidade_utilizada;
                $produtoId = (int) $tp->produto_id;
                $tp->delete();

                if ($qtd > 0) {
                    $estoqueService->movimentar(
                        produto: $produtoId,
                        tipo: 'entrada',
                        quantidade: $qtd,
                        usuario: $usuario,
                        motivo: "{$motivoBase} ({$os->numero})",
                        permitirProdutoInativo: true
                    );
                }
            }
        }
    }

    /**
     * Valida e retorna os serviços ativos pertencentes à empresa (aceitando inativos apenas se já vinculados na edição).
     *
     * @param  array<int, int>  $servicosJaVinculadosIds
     */
    private function obterServicosValidos(array $servicosInput, ?int $empresaId = null, array $servicosJaVinculadosIds = []): Collection
    {
        $servicosIds = collect($servicosInput)
            ->pluck('servico_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $query = Servico::whereIn('id', $servicosIds)
            ->where(function ($q) use ($servicosJaVinculadosIds) {
                $q->where('ativo', true);
                if (! empty($servicosJaVinculadosIds)) {
                    $q->orWhereIn('id', $servicosJaVinculadosIds);
                }
            });

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        $servicosModels = $query->get();

        if ($servicosModels->count() !== $servicosIds->count()) {
            throw ValidationException::withMessages([
                'servicos' => 'Um ou mais serviços selecionados estão inativos ou não pertencem a esta empresa.',
            ]);
        }

        return $servicosModels;
    }

    /**
     * Calcula a data/hora estimada de término, suportando continuação nos próximos dias úteis
     * quando a opção de deixar o veículo na estética está ativa (08:00–18:00, Seg–Sáb).
     */
    private function calcularDataFimEstimada(Carbon $dataInicio, int $duracaoMinutos, bool $deixarVeiculo = false): Carbon
    {
        if ($duracaoMinutos <= 0) {
            return $dataInicio->copy();
        }

        if (! $deixarVeiculo) {
            return $dataInicio->copy()->addMinutes($duracaoMinutos);
        }

        $restante = $duracaoMinutos;
        $cursor = $dataInicio->copy();
        $salvaguardaDias = 0;

        while ($restante > 0 && $salvaguardaDias < 60) {
            $salvaguardaDias++;

            if ($cursor->isSunday()) {
                $cursor->addDay()->setTime(self::HORA_ABERTURA, 0, 0);
                continue;
            }

            $aberturaDia = $cursor->copy()->setTime(self::HORA_ABERTURA, 0, 0);
            $fechamentoDia = $cursor->copy()->setTime(self::HORA_FECHAMENTO, 0, 0);

            if ($cursor->lt($aberturaDia)) {
                $cursor = $aberturaDia;
            }

            if ($cursor->gte($fechamentoDia)) {
                $cursor->addDay()->setTime(self::HORA_ABERTURA, 0, 0);
                continue;
            }

            $minutosDisponiveisDia = (int) $cursor->diffInMinutes($fechamentoDia, false);
            if ($minutosDisponiveisDia <= 0) {
                $cursor->addDay()->setTime(self::HORA_ABERTURA, 0, 0);
                continue;
            }

            if ($restante <= $minutosDisponiveisDia) {
                return $cursor->copy()->addMinutes($restante);
            }

            $restante -= $minutosDisponiveisDia;
            $cursor->addDay()->setTime(self::HORA_ABERTURA, 0, 0);
        }

        return $cursor;
    }

    /**
     * Valida dias úteis (segunda a sábado), horário de início [08:00, 18:00) e regra de término.
     */
    private function validarExpediente(Carbon $dataInicio, ?Carbon $dataFimEstimada = null, bool $deixarVeiculo = false): void
    {
        if ($dataInicio->isSunday()) {
            throw ValidationException::withMessages([
                'data_inicio' => 'A oficina não realiza atendimentos aos domingos. Escolha uma data entre segunda e sábado.',
            ]);
        }

        $horaInicio = $dataInicio->format('H:i:s');
        $abertura = str_pad((string) self::HORA_ABERTURA, 2, '0', STR_PAD_LEFT) . ':00';
        $fechamento = str_pad((string) self::HORA_FECHAMENTO, 2, '0', STR_PAD_LEFT) . ':00';

        if ($horaInicio < ($abertura . ':00') || $horaInicio >= ($fechamento . ':00')) {
            throw ValidationException::withMessages([
                'data_inicio' => "O horário de início do agendamento deve estar dentro do expediente comercial ({$abertura} às {$fechamento}).",
            ]);
        }

        if ($dataFimEstimada !== null && ! $deixarVeiculo) {
            if (! $dataFimEstimada->isSameDay($dataInicio) || $dataFimEstimada->format('H:i:s') > ($fechamento . ':00')) {
                throw ValidationException::withMessages([
                    'data_inicio' => "A duração estimada dos serviços encerraria após o expediente ({$fechamento}). Escolha um horário mais cedo ou ative a opção de deixar o veículo na estética para conclusão no próximo dia útil.",
                ]);
            }
        }
    }

    /**
     * Calcula o pico máximo de boxes ocupados simultaneamente no intervalo informado via sweep-line.
     *
     * @param  \Illuminate\Support\Collection<int, Agendamento>  $agendamentos
     */
    private function calcularPicoBoxes(Collection $agendamentos, Carbon $dataInicio, Carbon $dataFimEstimada): int
    {
        $eventos = [];
        $inicioTs = $dataInicio->getTimestamp();
        $fimTs = $dataFimEstimada->getTimestamp();

        foreach ($agendamentos as $ag) {
            $agInicioTs = $ag->data_inicio->getTimestamp();
            $fimRef = $ag->data_fim ?? $ag->data_inicio->copy()->addHours(2);
            $agFimTs = $fimRef->getTimestamp();

            if ($agInicioTs < $fimTs && $agFimTs > $inicioTs) {
                $evInicio = max($inicioTs, $agInicioTs);
                $evFim = min($fimTs, $agFimTs);

                if ($evInicio < $evFim) {
                    $eventos[] = [$evInicio, 1];
                    $eventos[] = [$evFim, -1];
                }
            }
        }

        usort($eventos, function (array $a, array $b): int {
            if ($a[0] === $b[0]) {
                return $a[1] <=> $b[1]; // Saída (-1) processada antes de entrada (+1) em fronteiras idênticas
            }

            return $a[0] <=> $b[0];
        });

        $simultaneos = 0;
        $picoSimultaneo = 0;
        foreach ($eventos as [, $delta]) {
            $simultaneos += $delta;
            if ($simultaneos > $picoSimultaneo) {
                $picoSimultaneo = $simultaneos;
            }
        }

        return $picoSimultaneo;
    }

    /**
     * Garante ausência de sobreposição para o mesmo veículo e respeito ao pico simultâneo máximo de 4 boxes.
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
            ->with(['veiculo' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('veiculo_id', $veiculoId)
            ->whereIn('status', ['agendado', 'confirmado', 'em_andamento'])
            ->when($ignorarAgendamentoId, fn ($q) => $q->where('id', '!=', $ignorarAgendamentoId))
            ->tap($aplicarSobreposicao)
            ->orderBy('data_inicio')
            ->first();

        if ($conflitoVeiculo) {
            $inicioConf = $conflitoVeiculo->data_inicio->format('d/m/Y H:i');
            $fimConfDate = $conflitoVeiculo->data_fim ?? $conflitoVeiculo->data_inicio;
            $fimConf = $fimConfDate->isSameDay($conflitoVeiculo->data_inicio)
                ? $fimConfDate->format('H:i')
                : $fimConfDate->format('d/m/Y H:i');
            $placaConf = $conflitoVeiculo->veiculo?->placa ? " ({$conflitoVeiculo->veiculo->placa})" : '';

            throw ValidationException::withMessages([
                'data_inicio' => "Aviso: Este veículo{$placaConf} já possui um agendamento ativo neste mesmo horário ({$inicioConf} às {$fimConf}). Escolha outro horário ou verifique a agenda.",
            ]);
        }

        $sobrepostos = Agendamento::withoutGlobalScopes()
            ->select(['id', 'data_inicio', 'data_fim'])
            ->whereIn('status', ['agendado', 'confirmado', 'em_andamento'])
            ->when($ignorarAgendamentoId, fn ($q) => $q->where('id', '!=', $ignorarAgendamentoId))
            ->when($empresaId, function ($q) use ($empresaId) {
                $q->where(function ($sub) use ($empresaId) {
                    $sub->where('empresa_id', $empresaId)
                        ->orWhere(function ($sub2) use ($empresaId) {
                            $sub2->whereNull('empresa_id')
                                ->whereHas('cliente', fn ($cq) => $cq->withoutGlobalScopes()->where('empresa_id', $empresaId));
                        });
                });
            })
            ->tap($aplicarSobreposicao)
            ->get();

        $picoSimultaneo = $this->calcularPicoBoxes($sobrepostos, $dataInicio, $dataFimEstimada);

        if ($picoSimultaneo >= self::CAPACIDADE_BOXES) {
            throw ValidationException::withMessages([
                'data_inicio' => 'Todos os ' . self::CAPACIDADE_BOXES . ' boxes de atendimento simultâneo estão ocupados nesta faixa de horário. Escolha outro horário no calendário.',
            ]);
        }
    }
}
