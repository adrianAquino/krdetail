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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AgendamentoController extends Controller
{
    public const GARGALO_MINUTOS = 30;
    public const INTERVALO_SLOT_MINUTOS = 30;
    public const CAPACIDADE_BOXES = 4;
    public const HORA_ABERTURA = 8;
    public const HORA_FECHAMENTO = 18;
    public const ANTECEDENCIA_CANCELAMENTO_HORAS = 1;

    public function index(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isCliente = $user instanceof User && $user->isCliente();

        $filtroAdmin = $isCliente
            ? [
                'isAdmin' => false,
                'empresasFiltro' => collect(),
                'empresaFiltroId' => null,
                'empresaFiltroAtual' => null,
            ]
            : $this->resolverFiltroEmpresaAdmin($request);

        $query = Agendamento::with(['empresa', 'cliente', 'veiculo', 'servicos', 'agendamentosServicos.servico', 'ordemServico.tarefas']);

        if ($isCliente) {
            $cliente = $user->ensureClienteProfile();
            $query->where('cliente_id', $cliente->id);
        }

        $this->aplicarFiltrosListagem($query, $request, $isCliente);

        $agendamentos = $query->orderByDesc('data_inicio')->paginate(15)->withQueryString();

        return view('agendamentos.index', array_merge(compact('agendamentos'), $filtroAdmin));
    }

    /**
     * Feed JSON de eventos para o FullCalendar na listagem de agendamentos.
     */
    public function eventos(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isCliente = $user instanceof User && $user->isCliente();

        if (! $isCliente) {
            $this->resolverFiltroEmpresaAdmin($request);
        }

        [$inicioRange, $fimRange] = $this->parseRangeCalendario($request);

        $query = Agendamento::with([
            'empresa',
            'cliente' => fn ($q) => $q->withoutGlobalScopes(),
            'veiculo' => fn ($q) => $q->withoutGlobalScopes(),
            'servicos',
            'agendamentosServicos.servico',
        ]);

        if ($isCliente) {
            $cliente = $user->ensureClienteProfile();
            $query->where('cliente_id', $cliente->id);
        }

        $this->aplicarFiltrosListagem($query, $request, $isCliente);
        $this->aplicarFiltroSobreposicao($query, $inicioRange, $fimRange);

        $agendamentos = $query->orderBy('data_inicio')->get();

        $eventos = $agendamentos->map(function (Agendamento $ag) use ($isCliente) {
            $cfg = $this->configuracaoVisualStatus((string) $ag->status);
            $start = $ag->data_inicio;
            $end = $ag->data_fim ?? $start->copy()->addHour();
            $placa = $ag->veiculo?->placa ?? '';
            $veiculoTexto = trim(implode(' ', array_filter([
                $ag->veiculo?->marca,
                $ag->veiculo?->modelo,
                $placa !== '' ? "({$placa})" : null,
            ])));
            $servicosTexto = $ag->servicos_nomes;

            if ($isCliente) {
                $titulo = $placa !== ''
                    ? ($servicosTexto !== '' ? "{$placa} — {$servicosTexto}" : "Veículo {$placa}")
                    : ($servicosTexto !== '' ? $servicosTexto : 'Meu Agendamento');
            } else {
                $clienteNome = $ag->cliente?->nome ?? 'Cliente';
                $titulo = $placa !== '' ? "{$clienteNome} • {$placa}" : $clienteNome;
            }

            $extended = [
                'tipo' => $isCliente ? 'proprio' : 'equipe',
                'status' => $ag->status,
                'statusLabel' => $cfg['label'],
                'veiculo' => $veiculoTexto,
                'placa' => $placa,
                'servicos' => $servicosTexto,
                'empresa' => $ag->empresa?->nome_fantasia,
                'deixarVeiculo' => (bool) $ag->deixar_veiculo,
            ];

            if (! $isCliente) {
                $extended['cliente'] = $ag->cliente?->nome ?? 'Cliente';
                $extended['valorTotal'] = (float) $ag->valor_total;
            }

            return [
                'id' => (string) $ag->id,
                'title' => $titulo,
                'start' => $start->format('Y-m-d\TH:i:s'),
                'end' => $end->format('Y-m-d\TH:i:s'),
                'url' => route('agendamentos.show', $ag->id),
                'backgroundColor' => $cfg['bg'],
                'borderColor' => $cfg['border'],
                'textColor' => $cfg['text'],
                'extendedProps' => $extended,
            ];
        })->values()->all();

        if ($isCliente) {
            $cliente = $user->ensureClienteProfile();
            $empresaId = $cliente->empresa_id
                ? (int) $cliente->empresa_id
                : $user->getEmpresaAtivaId();

            if ($empresaId) {
                $eventos = array_merge(
                    $eventos,
                    $this->gerarEventosLotacaoAnonimos($empresaId, $inicioRange, $fimRange)
                );
            }
        }

        return response()->json($eventos);
    }

    /**
     * Feed JSON de janelas de horários futuras (de 30 em 30 min) para planejamento no formulário.
     */
    public function janelasDisponiveis(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        [$semEstetica, $empresaId, $veiculoValidoId, $isCliente] = $this->resolverContextoJanelas($request, $user);

        if ($semEstetica || ! $empresaId) {
            return response()->json([
                'sem_estetica' => true,
                'duracao_servicos_minutos' => 0,
                'gargalo_minutos' => self::GARGALO_MINUTOS,
                'duracao_total_minutos' => 0,
                'servicos_selecionados_count' => 0,
                'usando_previa_padrao' => true,
                'dias' => [],
            ]);
        }

        $hoje = Carbon::today();
        $dataBaseRaw = trim($this->inputString($request, 'data_base'));
        try {
            $dataBase = $dataBaseRaw !== ''
                ? Carbon::createFromFormat('Y-m-d', $dataBaseRaw)->startOfDay()
                : $hoje->copy();
        } catch (\Throwable) {
            $dataBase = $hoje->copy();
        }

        if ($dataBase->lt($hoje)) {
            $dataBase = $hoje->copy();
        } elseif ($dataBase->gt($hoje->copy()->addDays(365))) {
            $dataBase = $hoje->copy()->addDays(365);
        }

        $diasQtd = min(21, max(1, $request->integer('dias', 14)));
        $deixarVeiculo = $request->boolean('deixar_veiculo');
        $ignorarAgendamentoId = $request->integer('ignorar_agendamento_id') ?: null;

        $servicosRaw = $request->input('servicos', []);
        $servicosIds = collect(is_array($servicosRaw) ? $servicosRaw : [])
            ->map(fn ($item) => is_array($item) ? ($item['servico_id'] ?? null) : $item)
            ->filter(fn ($v) => is_scalar($v) && is_numeric($v))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        $servicosModels = $servicosIds->isNotEmpty()
            ? Servico::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->where('ativo', true)
                ->whereIn('id', $servicosIds)
                ->get(['id', 'tempo_estimado_minutos'])
            : collect();

        $servicosCount = $servicosModels->count();
        $usandoPrevia = $servicosCount === 0;
        $duracaoServicos = $usandoPrevia
            ? self::INTERVALO_SLOT_MINUTOS
            : (int) $servicosModels->sum('tempo_estimado_minutos');
        $duracaoTotal = $duracaoServicos + self::GARGALO_MINUTOS;

        $fimConsulta = $dataBase->copy()->addDays($diasQtd + 4)->endOfDay();

        $agendamentosEmpresa = Agendamento::withoutGlobalScopes()
            ->select(['id', 'veiculo_id', 'data_inicio', 'data_fim'])
            ->whereIn('status', ['agendado', 'confirmado', 'em_andamento'])
            ->when($ignorarAgendamentoId, fn ($q) => $q->where('id', '!=', $ignorarAgendamentoId))
            ->where(function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId)
                    ->orWhere(function ($q2) use ($empresaId) {
                        $q2->whereNull('empresa_id')
                            ->whereHas('cliente', fn ($cq) => $cq->withoutGlobalScopes()->where('empresa_id', $empresaId));
                    });
            })
            ->tap(fn ($q) => $this->aplicarFiltroSobreposicao($q, $dataBase, $fimConsulta))
            ->get();

        $agendamentosVeiculo = $veiculoValidoId
            ? Agendamento::withoutGlobalScopes()
                ->select(['id', 'veiculo_id', 'data_inicio', 'data_fim'])
                ->where('veiculo_id', $veiculoValidoId)
                ->whereIn('status', ['agendado', 'confirmado', 'em_andamento'])
                ->when($ignorarAgendamentoId, fn ($q) => $q->where('id', '!=', $ignorarAgendamentoId))
                ->tap(fn ($q) => $this->aplicarFiltroSobreposicao($q, $dataBase, $fimConsulta))
                ->get()
            : collect();

        $resultadoDias = [];
        for ($i = 0; $i < $diasQtd; $i++) {
            $dia = $dataBase->copy()->addDays($i)->startOfDay();
            $resultadoDias[] = $this->avaliarDiaJanelas(
                dia: $dia,
                duracaoTotal: $duracaoTotal,
                deixarVeiculo: $deixarVeiculo,
                isCliente: $isCliente,
                agendamentosEmpresa: $agendamentosEmpresa,
                agendamentosVeiculo: $agendamentosVeiculo
            );
        }

        $capacidadeDiariaMinutos = (self::HORA_FECHAMENTO - self::HORA_ABERTURA) * 60;

        return response()->json([
            'sem_estetica' => false,
            'data_base' => $dataBase->format('Y-m-d'),
            'dias_qtd' => $diasQtd,
            'duracao_servicos_minutos' => $duracaoServicos,
            'gargalo_minutos' => self::GARGALO_MINUTOS,
            'duracao_total_minutos' => $duracaoTotal,
            'servicos_selecionados_count' => $servicosCount,
            'usando_previa_padrao' => $usandoPrevia,
            'ultrapassa_um_dia_util' => $duracaoTotal > $capacidadeDiariaMinutos,
            'deixar_veiculo' => $deixarVeiculo,
            'dias' => $resultadoDias,
        ]);
    }

    public function create(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isAdmin = $user instanceof User && $user->isAdministrador();
        $empresas = collect();
        $empresaSelecionadaId = null;

        if ($user instanceof User && $user->isCliente()) {
            if (! $user->possuiEsteticaVinculada()) {
                return redirect()
                    ->route('agendamentos.index')
                    ->with('error', 'Você ainda não está vinculado a nenhuma estética automotiva. Solicite que uma estética cadastre seus dados para realizar novos agendamentos.');
            }

            $cliente = $user->ensureClienteProfile();
            $clientes = Cliente::with('veiculos')->where('id', $cliente->id)->get();
            $servicos = Servico::where('ativo', true)->orderBy('nome')->get();
        } elseif ($isAdmin) {
            $empresas = Empresa::orderBy('nome_fantasia')->get();
            $empReq = $request->integer('empresa_id') ?: null;
            $empresaSelecionadaId = ($empReq && $empresas->contains('id', $empReq))
                ? $empReq
                : ($empresas->first()?->id ? (int) $empresas->first()->id : null);

            if ($empresaSelecionadaId) {
                $clientes = Cliente::with('veiculos')
                    ->where('empresa_id', $empresaSelecionadaId)
                    ->orderBy('nome')
                    ->get();
                $servicos = Servico::where('ativo', true)
                    ->where('empresa_id', $empresaSelecionadaId)
                    ->orderBy('nome')
                    ->get();
            } else {
                $clientes = collect();
                $servicos = collect();
            }
        } else {
            $clientes = Cliente::with('veiculos')->orderBy('nome')->get();
            $servicos = Servico::where('ativo', true)->orderBy('nome')->get();
        }

        return view('agendamentos.create', compact('clientes', 'servicos', 'isAdmin', 'empresas', 'empresaSelecionadaId'));
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
        $isAdmin = $user instanceof User && $user->isAdministrador();
        $empresaId = $clienteModel->empresa_id
            ? (int) $clienteModel->empresa_id
            : ($isAdmin ? null : $user?->getEmpresaAtivaId());

        if ($isAdmin && ! $empresaId) {
            throw ValidationException::withMessages([
                'cliente_id' => 'Este cliente não está vinculado a nenhuma empresa ativa.',
            ]);
        }

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

        $agendamento->load(['empresa', 'agendamentosServicos', 'ordemServico', 'cliente', 'veiculo']);
        $empresaAgId = $agendamento->empresa_id ?? $agendamento->cliente?->empresa_id;
        $isAdmin = $user instanceof User && $user->isAdministrador();

        $clientesQuery = Cliente::with('veiculos')->orderBy('nome');
        if ($isAdmin && $empresaAgId) {
            $clientesQuery->where('empresa_id', $empresaAgId);
        }
        $clientes = $clientesQuery->get();

        // Inclui serviços já contratados no agendamento mesmo que tenham sido desativados posteriormente
        $servicosVinculadosIds = $agendamento->agendamentosServicos->pluck('servico_id')->filter()->unique();
        $servicosQuery = Servico::where(function ($q) use ($servicosVinculadosIds) {
            $q->where('ativo', true);
            if ($servicosVinculadosIds->isNotEmpty()) {
                $q->orWhereIn('id', $servicosVinculadosIds);
            }
        });
        if ($isAdmin && $empresaAgId) {
            $servicosQuery->where(function ($q) use ($empresaAgId, $servicosVinculadosIds) {
                $q->where('empresa_id', $empresaAgId);
                if ($servicosVinculadosIds->isNotEmpty()) {
                    $q->orWhereIn('id', $servicosVinculadosIds);
                }
            });
        }
        $servicos = $servicosQuery->orderBy('nome')->get();

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
        $isAdmin = $user instanceof User && $user->isAdministrador();
        $empresaId = $clienteModel->empresa_id
            ? (int) $clienteModel->empresa_id
            : ($isAdmin ? null : $user?->getEmpresaAtivaId());

        if ($isAdmin && ! $empresaId) {
            throw ValidationException::withMessages([
                'cliente_id' => 'Este cliente não está vinculado a nenhuma empresa ativa.',
            ]);
        }

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

    public function confirmar(Agendamento $agendamento)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user instanceof User && $user->isCliente()) {
            abort(403, 'Você não tem permissão para confirmar agendamentos.');
        }

        try {
            DB::transaction(function () use ($agendamento) {
                $agTravado = Agendamento::lockForUpdate()->findOrFail($agendamento->id);

                if ($agTravado->status !== 'agendado') {
                    throw new \DomainException('Apenas agendamentos com status Agendado podem ser confirmados.');
                }

                $agTravado->update(['status' => 'confirmado']);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Agendamento confirmado com sucesso!');
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
        $conflitoVeiculo = Agendamento::withoutGlobalScopes()
            ->with(['veiculo' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('veiculo_id', $veiculoId)
            ->whereIn('status', ['agendado', 'confirmado', 'em_andamento'])
            ->when($ignorarAgendamentoId, fn ($q) => $q->where('id', '!=', $ignorarAgendamentoId))
            ->tap(fn ($q) => $this->aplicarFiltroSobreposicao($q, $dataInicio, $dataFimEstimada))
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
            ->tap(fn ($q) => $this->aplicarFiltroSobreposicao($q, $dataInicio, $dataFimEstimada))
            ->get();

        $picoSimultaneo = $this->calcularPicoBoxes($sobrepostos, $dataInicio, $dataFimEstimada);

        if ($picoSimultaneo >= self::CAPACIDADE_BOXES) {
            throw ValidationException::withMessages([
                'data_inicio' => 'Todos os ' . self::CAPACIDADE_BOXES . ' boxes de atendimento simultâneo estão ocupados nesta faixa de horário. Escolha outra data ou horário.',
            ]);
        }
    }

    /**
     * Aplica filtros de status e busca compartilhados entre listagem e calendário.
     */
    private function aplicarFiltrosListagem(Builder $query, Request $request, bool $isCliente): void
    {
        $status = trim($this->inputString($request, 'status'));
        if (in_array($status, ['agendado', 'confirmado', 'em_andamento', 'concluido', 'cancelado'], true)) {
            $query->where('status', $status);
        }

        $busca = trim($this->inputString($request, 'busca'));
        if ($busca !== '') {
            $query->where(function ($q) use ($busca, $isCliente) {
                $q->whereHas('veiculo', fn ($vq) => $vq->where('placa', 'like', "%{$busca}%")
                    ->orWhere('modelo', 'like', "%{$busca}%")
                    ->orWhere('marca', 'like', "%{$busca}%"));

                if (! $isCliente) {
                    $q->orWhereHas('cliente', fn ($cq) => $cq->where('nome', 'like', "%{$busca}%"));
                }
            });
        }
    }

    /**
     * Restringe a query a agendamentos cujo intervalo sobrepõe [inicio, fim].
     */
    private function aplicarFiltroSobreposicao(Builder $query, Carbon $inicio, Carbon $fim): void
    {
        $query->where('data_inicio', '<', $fim)
            ->where(function ($q) use ($inicio) {
                $q->where('data_fim', '>', $inicio)
                    ->orWhere(function ($q2) use ($inicio) {
                        $q2->whereNull('data_fim')
                            ->where('data_inicio', '>', $inicio->copy()->subHours(2));
                    });
            });
    }

    /**
     * Faz parse seguro do intervalo start/end enviado pelo FullCalendar (limitado a 93 dias).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function parseRangeCalendario(Request $request): array
    {
        $tz = (string) config('app.timezone', 'America/Sao_Paulo');
        $agora = Carbon::now($tz);

        try {
            $inicio = $request->filled('start')
                ? Carbon::parse($this->inputString($request, 'start'))->setTimezone($tz)
                : $agora->copy()->startOfMonth()->startOfDay();
        } catch (\Throwable) {
            $inicio = $agora->copy()->startOfMonth()->startOfDay();
        }

        try {
            $fim = $request->filled('end')
                ? Carbon::parse($this->inputString($request, 'end'))->setTimezone($tz)
                : $agora->copy()->endOfMonth()->endOfDay();
        } catch (\Throwable) {
            $fim = $agora->copy()->endOfMonth()->endOfDay();
        }

        if ($fim->lte($inicio)) {
            $fim = $inicio->copy()->addDays(7)->endOfDay();
        }

        if ($inicio->diffInDays($fim) > 93) {
            $fim = $inicio->copy()->addDays(93)->endOfDay();
        }

        return [$inicio, $fim];
    }

    /**
     * @return array{label: string, bg: string, border: string, text: string}
     */
    private function configuracaoVisualStatus(string $status): array
    {
        return match ($status) {
            'confirmado' => [
                'label' => 'Confirmado',
                'bg' => '#10b981',
                'border' => '#059669',
                'text' => '#ffffff',
            ],
            'em_andamento' => [
                'label' => 'Em Andamento',
                'bg' => '#f59e0b',
                'border' => '#d97706',
                'text' => '#111827',
            ],
            'concluido' => [
                'label' => 'Concluído',
                'bg' => '#64748b',
                'border' => '#475569',
                'text' => '#ffffff',
            ],
            'cancelado' => [
                'label' => 'Cancelado',
                'bg' => '#ef4444',
                'border' => '#dc2626',
                'text' => '#ffffff',
            ],
            default => [
                'label' => 'Agendado',
                'bg' => '#3b82f6',
                'border' => '#2563eb',
                'text' => '#ffffff',
            ],
        };
    }

    /**
     * Resolve empresa alvo e veículo autorizado para consulta de janelas disponíveis sem vazar dados entre empresas ou clientes.
     *
     * @return array{0: bool, 1: int|null, 2: int|null, 3: bool}
     */
    private function resolverContextoJanelas(Request $request, ?User $user): array
    {
        if (! $user instanceof User) {
            return [true, null, null, false];
        }

        $isCliente = $user->isCliente();
        $veiculoReqId = $request->integer('veiculo_id') ?: null;

        if ($isCliente) {
            if (! $user->possuiEsteticaVinculada()) {
                return [true, null, null, true];
            }

            $cliente = $user->ensureClienteProfile();
            $empresaId = $cliente->empresa_id
                ? (int) $cliente->empresa_id
                : $user->getEmpresaAtivaId();

            $veiculoValidoId = null;
            if ($veiculoReqId) {
                $pertenceAoCliente = Veiculo::withoutGlobalScopes()
                    ->whereKey($veiculoReqId)
                    ->where('cliente_id', $cliente->id)
                    ->exists();
                if ($pertenceAoCliente) {
                    $veiculoValidoId = $veiculoReqId;
                }
            }

            return [false, $empresaId, $veiculoValidoId, true];
        }

        if ($user->isDono() || $user->isFuncionario()) {
            $empresaId = $user->getEmpresaAtivaId();
            if (! $empresaId) {
                return [true, null, null, false];
            }

            $veiculoValidoId = null;
            if ($veiculoReqId) {
                $pertenceAEmpresa = Veiculo::withoutGlobalScopes()
                    ->whereKey($veiculoReqId)
                    ->whereHas('cliente', fn ($q) => $q->withoutGlobalScopes()->where('empresa_id', $empresaId))
                    ->exists();
                if ($pertenceAEmpresa) {
                    $veiculoValidoId = $veiculoReqId;
                }
            }

            return [false, $empresaId, $veiculoValidoId, false];
        }

        // Administrador Geral
        $empReq = $request->integer('empresa_id') ?: null;
        $empresaId = ($empReq && Empresa::whereKey($empReq)->exists()) ? $empReq : null;

        if (! $empresaId) {
            $clienteReq = $request->integer('cliente_id') ?: null;
            if ($clienteReq) {
                $empCli = Cliente::withoutGlobalScopes()->whereKey($clienteReq)->value('empresa_id');
                $empresaId = $empCli ? (int) $empCli : null;
            }
        }

        if (! $empresaId) {
            $empPrimeira = Empresa::orderBy('id')->value('id');
            $empresaId = $empPrimeira ? (int) $empPrimeira : null;
        }

        $veiculoValidoId = null;
        if ($veiculoReqId && $empresaId) {
            $pertenceAEmpresa = Veiculo::withoutGlobalScopes()
                ->whereKey($veiculoReqId)
                ->whereHas('cliente', fn ($q) => $q->withoutGlobalScopes()->where('empresa_id', $empresaId))
                ->exists();
            if ($pertenceAEmpresa) {
                $veiculoValidoId = $veiculoReqId;
            }
        }

        return [false, $empresaId, $veiculoValidoId, false];
    }

    /**
     * Gera eventos de fundo anônimos apenas para faixas de 30 min com todos os 4 boxes ocupados (visão do cliente).
     *
     * @return array<int, array<string, mixed>>
     */
    private function gerarEventosLotacaoAnonimos(int $empresaId, Carbon $inicioRange, Carbon $fimRange): array
    {
        $ocupacaoEmpresa = Agendamento::withoutGlobalScopes()
            ->select(['data_inicio', 'data_fim'])
            ->whereIn('status', ['agendado', 'confirmado', 'em_andamento'])
            ->where(function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId)
                    ->orWhere(function ($q2) use ($empresaId) {
                        $q2->whereNull('empresa_id')
                            ->whereHas('cliente', fn ($cq) => $cq->withoutGlobalScopes()->where('empresa_id', $empresaId));
                    });
            })
            ->tap(fn ($q) => $this->aplicarFiltroSobreposicao($q, $inicioRange, $fimRange))
            ->get();

        if ($ocupacaoEmpresa->isEmpty()) {
            return [];
        }

        $eventos = [];
        $cursorDia = $inicioRange->copy()->startOfDay();
        $ultimoDia = $fimRange->copy()->subSecond()->startOfDay();
        if ($ultimoDia->lt($cursorDia)) {
            $ultimoDia = $cursorDia->copy();
        }

        while ($cursorDia->lte($ultimoDia)) {
            if (! $cursorDia->isSunday()) {
                $abertura = $cursorDia->copy()->setTime(self::HORA_ABERTURA, 0, 0);
                $fechamento = $cursorDia->copy()->setTime(self::HORA_FECHAMENTO, 0, 0);

                $agDoDia = $ocupacaoEmpresa->filter(function (Agendamento $ag) use ($abertura, $fechamento): bool {
                    $agFim = $ag->data_fim ?? $ag->data_inicio->copy()->addHours(2);

                    return $ag->data_inicio->lt($fechamento) && $agFim->gt($abertura);
                })->values();

                if ($agDoDia->isNotEmpty()) {
                    $blocoInicio = null;
                    $blocoFim = null;

                    for ($min = self::HORA_ABERTURA * 60; $min < self::HORA_FECHAMENTO * 60; $min += self::INTERVALO_SLOT_MINUTOS) {
                        $slotIni = $cursorDia->copy()->addMinutes($min);
                        $slotFim = $slotIni->copy()->addMinutes(self::INTERVALO_SLOT_MINUTOS);
                        $ocupados = $this->calcularPicoBoxes($agDoDia, $slotIni, $slotFim);

                        if ($ocupados >= self::CAPACIDADE_BOXES) {
                            $blocoInicio ??= $slotIni;
                            $blocoFim = $slotFim;
                        } elseif ($blocoInicio !== null && $blocoFim !== null) {
                            $eventos[] = [
                                'id' => 'lotado-' . $blocoInicio->format('YmdHi'),
                                'start' => $blocoInicio->format('Y-m-d\TH:i:s'),
                                'end' => $blocoFim->format('Y-m-d\TH:i:s'),
                                'display' => 'background',
                                'title' => 'Horário lotado',
                                'classNames' => ['fc-slot-lotado'],
                                'extendedProps' => ['tipo' => 'lotado'],
                            ];
                            $blocoInicio = null;
                            $blocoFim = null;
                        }
                    }

                    if ($blocoInicio !== null && $blocoFim !== null) {
                        $eventos[] = [
                            'id' => 'lotado-' . $blocoInicio->format('YmdHi'),
                            'start' => $blocoInicio->format('Y-m-d\TH:i:s'),
                            'end' => $blocoFim->format('Y-m-d\TH:i:s'),
                            'display' => 'background',
                            'title' => 'Horário lotado',
                            'classNames' => ['fc-slot-lotado'],
                            'extendedProps' => ['tipo' => 'lotado'],
                        ];
                    }
                }
            }

            $cursorDia->addDay();
        }

        return $eventos;
    }

    /**
     * Avalia todos os slots de 30 min de um dia e gera o resumo diário para o seletor de janelas.
     *
     * @param  Collection<int, Agendamento>  $agendamentosEmpresa
     * @param  Collection<int, Agendamento>  $agendamentosVeiculo
     * @return array<string, mixed>
     */
    private function avaliarDiaJanelas(
        Carbon $dia,
        int $duracaoTotal,
        bool $deixarVeiculo,
        bool $isCliente,
        Collection $agendamentosEmpresa,
        Collection $agendamentosVeiculo
    ): array {
        $diasSemanaCurto = [0 => 'Dom', 1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];
        $base = [
            'data' => $dia->format('Y-m-d'),
            'dia_semana' => $diasSemanaCurto[$dia->dayOfWeek] ?? '',
            'label_data' => $dia->format('d/m'),
        ];

        if ($dia->isSunday()) {
            return array_merge($base, [
                'fechado' => true,
                'status' => 'fechado',
                'livres' => 0,
                'slots' => [],
            ]);
        }

        $agoraLimite = Carbon::now()->subMinute();
        $fechamentoStr = str_pad((string) self::HORA_FECHAMENTO, 2, '0', STR_PAD_LEFT) . ':00:00';
        $slots = [];
        $livresNoDia = 0;
        $slotsFuturosNoDia = 0;

        for ($min = self::HORA_ABERTURA * 60; $min < self::HORA_FECHAMENTO * 60; $min += self::INTERVALO_SLOT_MINUTOS) {
            $slotInicio = $dia->copy()->addMinutes($min);
            $slotFim = $this->calcularDataFimEstimada($slotInicio, $duracaoTotal, $deixarVeiculo);
            $noPassado = $slotInicio->lt($agoraLimite);
            $ultrapassaExpediente = ! $deixarVeiculo
                && (! $slotFim->isSameDay($slotInicio) || $slotFim->format('H:i:s') > $fechamentoStr);

            $conflitoVeiculo = $agendamentosVeiculo->contains(function (Agendamento $ag) use ($slotInicio, $slotFim): bool {
                $agFim = $ag->data_fim ?? $ag->data_inicio->copy()->addHours(2);

                return $ag->data_inicio->lt($slotFim) && $agFim->gt($slotInicio);
            });

            $ocupados = $this->calcularPicoBoxes($agendamentosEmpresa, $slotInicio, $slotFim);
            $boxesLivres = max(0, self::CAPACIDADE_BOXES - $ocupados);
            $lotado = $ocupados >= self::CAPACIDADE_BOXES;

            if (! $noPassado) {
                $slotsFuturosNoDia++;
            }

            if ($noPassado) {
                $disponivel = false;
                $motivo = 'passado';
                $nivel = 'indisponivel';
                $vagasLabel = 'Encerrado';
            } elseif ($ultrapassaExpediente) {
                $disponivel = false;
                $motivo = 'ultrapassa_expediente';
                $nivel = 'indisponivel';
                $vagasLabel = 'Após 18:00';
            } elseif ($conflitoVeiculo) {
                $disponivel = false;
                $motivo = 'conflito_veiculo';
                $nivel = 'indisponivel';
                $vagasLabel = 'Veículo agendado';
            } elseif ($lotado) {
                $disponivel = false;
                $motivo = 'lotado';
                $nivel = 'lotado';
                $vagasLabel = 'Lotado';
            } else {
                $disponivel = true;
                $motivo = null;
                $nivel = $boxesLivres === 1 ? 'ultimas_vagas' : 'disponivel';
                $vagasLabel = $isCliente
                    ? ($boxesLivres === 1 ? 'Última vaga' : 'Disponível')
                    : "{$boxesLivres}/" . self::CAPACIDADE_BOXES . ' boxes';
                $livresNoDia++;
            }

            $slotData = [
                'hora' => $slotInicio->format('H:i'),
                'data_hora_iso' => $slotInicio->format('Y-m-d\TH:i'),
                'fim_estimado' => $slotFim->isSameDay($slotInicio)
                    ? $slotFim->format('H:i')
                    : $slotFim->format('d/m H:i'),
                'pernoite' => ! $slotFim->isSameDay($slotInicio),
                'disponivel' => $disponivel,
                'motivo' => $motivo,
                'nivel' => $nivel,
                'vagas_label' => $vagasLabel,
            ];

            if (! $isCliente) {
                $slotData['boxes_livres'] = $disponivel ? $boxesLivres : 0;
                $slotData['boxes_total'] = self::CAPACIDADE_BOXES;
            }

            $slots[] = $slotData;
        }

        if ($slotsFuturosNoDia === 0) {
            $statusDia = 'passado';
        } elseif ($livresNoDia === 0) {
            $statusDia = 'lotado';
        } elseif ($livresNoDia <= 4) {
            $statusDia = 'poucas_vagas';
        } else {
            $statusDia = 'disponivel';
        }

        return array_merge($base, [
            'fechado' => false,
            'status' => $statusDia,
            'livres' => $livresNoDia,
            'slots' => $slots,
        ]);
    }
}
