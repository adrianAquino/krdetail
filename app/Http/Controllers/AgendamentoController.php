<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\AgendamentoServico;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgendamentoController extends Controller
{
    public function index(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        $query = Agendamento::with(['cliente', 'veiculo', 'servicos', 'ordemServico.tarefas']);

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
            'data_inicio' => 'required|date',
            'data_fim' => 'nullable|date|after_or_equal:data_inicio',
            'status' => 'nullable|in:agendado,confirmado,em_andamento,concluido,cancelado',
            'origem' => 'nullable|string|max:50',
            'observacoes' => 'nullable|string',
            'servicos' => 'required|array|min:1',
            'servicos.*.servico_id' => 'required|exists:servicos,id',
        ], [
            'servicos.required' => 'Selecione pelo menos um serviço para o agendamento.',
            'servicos.min' => 'Selecione pelo menos um serviço para o agendamento.',
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

        $agendamento = DB::transaction(function () use ($validated) {
            $ag = Agendamento::create([
                'cliente_id' => $validated['cliente_id'],
                'veiculo_id' => $validated['veiculo_id'],
                'data_inicio' => $validated['data_inicio'],
                'data_fim' => $validated['data_fim'] ?? null,
                'status' => $validated['status'] ?? 'agendado',
                'origem' => $validated['origem'] ?? 'Balcão',
                'observacoes' => $validated['observacoes'] ?? null,
            ]);

            foreach ($validated['servicos'] as $item) {
                $servico = Servico::findOrFail($item['servico_id']);
                AgendamentoServico::create([
                    'agendamento_id' => $ag->id,
                    'servico_id' => $servico->id,
                    'valor_servico' => $servico->preco_base,
                    'tempo_estimado_minutos' => $servico->tempo_estimado_minutos,
                    'observacoes' => $item['observacoes'] ?? null,
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
            'ordemServico.tarefas.servico',
            'ordemServico.tarefas.funcionario',
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

        $agendamento->load(['agendamentosServicos']);
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

        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'veiculo_id' => 'required|exists:veiculos,id',
            'data_inicio' => 'required|date',
            'data_fim' => 'nullable|date|after_or_equal:data_inicio',
            'status' => 'required|in:agendado,confirmado,em_andamento,concluido,cancelado',
            'origem' => 'nullable|string|max:50',
            'observacoes' => 'nullable|string',
        ]);

        $veiculo = Veiculo::findOrFail($validated['veiculo_id']);
        if ((int) $veiculo->cliente_id !== (int) $validated['cliente_id']) {
            throw ValidationException::withMessages([
                'veiculo_id' => 'O veículo selecionado não pertence ao cliente informado.',
            ]);
        }

        $agendamento->update($validated);

        return redirect()->route('agendamentos.show', $agendamento)->with('success', 'Agendamento atualizado com sucesso!');
    }

    public function destroy(Agendamento $agendamento)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user instanceof User && $user->isCliente()) {
            abort(403);
        }

        $agendamento->delete();

        return redirect()->route('agendamentos.index')->with('success', 'Agendamento removido com sucesso!');
    }
}
