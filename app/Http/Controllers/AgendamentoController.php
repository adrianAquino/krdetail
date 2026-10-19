<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\AgendamentoServico;
use App\Models\Cliente;
use App\Models\OrdemServico;
use App\Models\Servico;
use App\Models\Tarefa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class AgendamentoController extends Controller
{
    public function index(Request $request)
    {
        $query = Agendamento::with(['cliente', 'veiculo', 'servicos', 'ordemServico']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('data')) {
            $query->whereDate('data_inicio', $request->input('data'));
        }

        if ($request->filled('busca')) {
            $busca = $request->input('busca');
            $query->where(function ($q) use ($busca) {
                $q->whereHas('cliente', function ($qc) use ($busca) {
                    $qc->where('nome', 'like', "%{$busca}%");
                })->orWhereHas('veiculo', function ($qv) use ($busca) {
                    $qv->where('placa', 'like', "%{$busca}%")
                       ->orWhere('modelo', 'like', "%{$busca}%");
                });
            });
        }

        $agendamentos = $query->orderBy('data_inicio', 'desc')->paginate(10)->withQueryString();

        return view('agendamentos.index', compact('agendamentos'));
    }

    public function create(Request $request)
    {
        $clientes = Cliente::with('veiculos')->orderBy('nome', 'asc')->get();
        $servicos = Servico::where('ativo', true)->orderBy('nome', 'asc')->get();

        $selectedClienteId = $request->query('cliente_id');
        $selectedVeiculoId = $request->query('veiculo_id');

        return view('agendamentos.create', compact('clientes', 'servicos', 'selectedClienteId', 'selectedVeiculoId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'veiculo_id' => 'required|exists:veiculos,id',
            'data_inicio' => 'required|date',
            'data_fim' => 'nullable|date|after_or_equal:data_inicio',
            'status' => 'required|in:agendado,confirmado,em_andamento,concluido,cancelado',
            'origem' => 'nullable|string|max:50',
            'observacoes' => 'nullable|string',
            'servicos' => 'required|array|min:1',
            'servicos.*' => 'exists:servicos,id',
        ]);

        $agendamento = DB::transaction(function () use ($validated, $request) {
            $agendamento = Agendamento::create([
                'cliente_id' => $validated['cliente_id'],
                'veiculo_id' => $validated['veiculo_id'],
                'data_inicio' => $validated['data_inicio'],
                'data_fim' => $validated['data_fim'] ?? null,
                'status' => $validated['status'],
                'origem' => $validated['origem'] ?? 'presencial',
                'observacoes' => $validated['observacoes'] ?? null,
            ]);

            $servicos = Servico::whereIn('id', $validated['servicos'])->get();

            foreach ($servicos as $servico) {
                AgendamentoServico::create([
                    'agendamento_id' => $agendamento->id,
                    'servico_id' => $servico->id,
                    'valor_servico' => $servico->preco_base,
                    'tempo_estimado_minutos' => $servico->tempo_estimado_minutos,
                ]);
            }

            // Se for confirmado ou em andamento, cria a Ordem de Serviço e Tarefas automaticamente
            if (in_array($agendamento->status, ['confirmado', 'em_andamento'])) {
                $numeroOS = 'OS-' . str_pad($agendamento->id, 5, '0', STR_PAD_LEFT);
                $os = OrdemServico::create([
                    'agendamento_id' => $agendamento->id,
                    'numero' => $numeroOS,
                    'data_inicio' => Carbon::now(),
                    'status' => $agendamento->status === 'em_andamento' ? 'em_andamento' : 'aberta',
                    'observacoes' => $agendamento->observacoes,
                ]);

                foreach ($agendamento->agendamentosServicos as $as) {
                    Tarefa::create([
                        'ordem_servico_id' => $os->id,
                        'agendamento_servico_id' => $as->id,
                        'funcionario_id' => null,
                        'status' => 'pendente',
                    ]);
                }
            }

            return $agendamento;
        });

        return redirect()->route('agendamentos.show', $agendamento)->with('success', 'Agendamento registrado com sucesso!');
    }

    public function show(Agendamento $agendamento)
    {
        $agendamento->load(['cliente', 'veiculo', 'servicos', 'agendamentosServicos.servico', 'ordemServico.tarefas']);

        return view('agendamentos.show', compact('agendamento'));
    }

    public function edit(Agendamento $agendamento)
    {
        $clientes = Cliente::with('veiculos')->orderBy('nome', 'asc')->get();
        $servicos = Servico::where('ativo', true)->orderBy('nome', 'asc')->get();

        $agendamento->load(['servicos']);

        return view('agendamentos.edit', compact('agendamento', 'clientes', 'servicos'));
    }

    public function update(Request $request, Agendamento $agendamento)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'veiculo_id' => 'required|exists:veiculos,id',
            'data_inicio' => 'required|date',
            'data_fim' => 'nullable|date|after_or_equal:data_inicio',
            'status' => 'required|in:agendado,confirmado,em_andamento,concluido,cancelado',
            'origem' => 'nullable|string|max:50',
            'observacoes' => 'nullable|string',
        ]);

        $agendamento->update($validated);

        return redirect()->route('agendamentos.show', $agendamento)->with('success', 'Agendamento atualizado com sucesso!');
    }

    public function destroy(Agendamento $agendamento)
    {
        if ($agendamento->ordemServico) {
            return redirect()->route('agendamentos.index')->with('error', 'Não é possível excluir um agendamento com Ordem de Serviço vinculada.');
        }

        $agendamento->servicos()->detach();
        $agendamento->delete();

        return redirect()->route('agendamentos.index')->with('success', 'Agendamento removido com sucesso!');
    }
}
