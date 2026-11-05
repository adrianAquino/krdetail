<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VeiculoController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Veiculo::with('cliente')->withCount('agendamentos');

        if ($user && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            $query->where('cliente_id', $cliente->id);
        }

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca, $user) {
                $q->where('placa', 'like', "%{$busca}%")
                    ->orWhere('marca', 'like', "%{$busca}%")
                    ->orWhere('modelo', 'like', "%{$busca}%");

                if (! $user || ! $user->isCliente()) {
                    $q->orWhereHas('cliente', fn ($cq) => $cq->where('nome', 'like', "%{$busca}%"));
                }
            });
        }

        $veiculos = $query->orderByDesc('created_at')->paginate(15);

        return view('veiculos.index', compact('veiculos'));
    }

    public function create(Request $request)
    {
        $user = Auth::user();

        if ($user && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            $clientes = collect([$cliente]);
            $selectedClienteId = $cliente->id;
        } else {
            $clientes = Cliente::orderBy('nome')->get();
            $selectedClienteId = $request->query('cliente_id');
        }

        return view('veiculos.create', compact('clientes', 'selectedClienteId'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            $request->merge(['cliente_id' => $cliente->id]);
        }

        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'placa' => 'required|string|max:10|unique:veiculos,placa',
            'marca' => 'required|string|max:100',
            'modelo' => 'required|string|max:100',
            'cor' => 'nullable|string|max:50',
            'ano' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'observacoes' => 'nullable|string',
        ]);

        if ($user && $user->isCliente()) {
            $validated['cliente_id'] = $user->ensureClienteProfile()->id;
        }

        $validated['placa'] = strtoupper($validated['placa']);
        $veiculo = Veiculo::create($validated);

        return redirect()->route('veiculos.show', $veiculo)->with('success', 'Veículo cadastrado com sucesso!');
    }

    public function show(Veiculo $veiculo)
    {
        $user = Auth::user();

        if ($user && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            if ((int) $veiculo->cliente_id !== (int) $cliente->id) {
                abort(403, 'Você não tem permissão para visualizar este veículo.');
            }
        }

        $veiculo->load(['cliente', 'agendamentos.servicos', 'agendamentos.ordemServico.tarefas']);

        return view('veiculos.show', compact('veiculo'));
    }

    public function edit(Veiculo $veiculo)
    {
        $user = Auth::user();

        if ($user && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            if ((int) $veiculo->cliente_id !== (int) $cliente->id) {
                abort(403, 'Você não tem permissão para editar este veículo.');
            }
            $clientes = collect([$cliente]);
        } else {
            $clientes = Cliente::orderBy('nome')->get();
        }

        return view('veiculos.edit', compact('veiculo', 'clientes'));
    }

    public function update(Request $request, Veiculo $veiculo)
    {
        $user = Auth::user();

        if ($user && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            if ((int) $veiculo->cliente_id !== (int) $cliente->id) {
                abort(403, 'Você não tem permissão para atualizar este veículo.');
            }
            $request->merge(['cliente_id' => $cliente->id]);
        }

        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'placa' => 'required|string|max:10|unique:veiculos,placa,' . $veiculo->id,
            'marca' => 'required|string|max:100',
            'modelo' => 'required|string|max:100',
            'cor' => 'nullable|string|max:50',
            'ano' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'observacoes' => 'nullable|string',
        ]);

        if ($user && $user->isCliente()) {
            $validated['cliente_id'] = $user->ensureClienteProfile()->id;
        }

        $validated['placa'] = strtoupper($validated['placa']);
        $veiculo->update($validated);

        return redirect()->route('veiculos.show', $veiculo)->with('success', 'Veículo atualizado com sucesso!');
    }

    public function destroy(Veiculo $veiculo)
    {
        $user = Auth::user();
        if ($user && $user->isCliente()) {
            abort(403);
        }

        $veiculo->delete();

        return redirect()->route('veiculos.index')->with('success', 'Veículo removido com sucesso!');
    }
}
