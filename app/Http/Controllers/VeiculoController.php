<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Veiculo;
use Illuminate\Http\Request;

class VeiculoController extends Controller
{
    public function index(Request $request)
    {
        $query = Veiculo::with('cliente');

        if ($request->filled('busca')) {
            $busca = $request->input('busca');
            $query->where(function ($q) use ($busca) {
                $q->where('placa', 'like', "%{$busca}%")
                  ->orWhere('modelo', 'like', "%{$busca}%")
                  ->orWhere('marca', 'like', "%{$busca}%")
                  ->orWhereHas('cliente', function ($qc) use ($busca) {
                      $qc->where('nome', 'like', "%{$busca}%");
                  });
            });
        }

        $veiculos = $query->orderBy('modelo', 'asc')->paginate(10)->withQueryString();

        return view('veiculos.index', compact('veiculos'));
    }

    public function create(Request $request)
    {
        $clientes = Cliente::orderBy('nome', 'asc')->get();
        $selectedClienteId = $request->query('cliente_id');

        return view('veiculos.create', compact('clientes', 'selectedClienteId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'placa' => 'required|string|max:10|unique:veiculos,placa',
            'marca' => 'required|string|max:100',
            'modelo' => 'required|string|max:100',
            'ano' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'cor' => 'nullable|string|max:50',
            'observacoes' => 'nullable|string',
        ]);

        $validated['placa'] = strtoupper(trim($validated['placa']));

        $veiculo = Veiculo::create($validated);

        return redirect()->route('veiculos.show', $veiculo)->with('success', 'Veículo cadastrado com sucesso!');
    }

    public function show(Veiculo $veiculo)
    {
        $veiculo->load([
            'cliente',
            'agendamentos' => function ($q) {
                $q->with(['servicos', 'ordemServico'])->orderBy('data_inicio', 'desc');
            }
        ]);

        return view('veiculos.show', compact('veiculo'));
    }

    public function edit(Veiculo $veiculo)
    {
        $clientes = Cliente::orderBy('nome', 'asc')->get();

        return view('veiculos.edit', compact('veiculo', 'clientes'));
    }

    public function update(Request $request, Veiculo $veiculo)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'placa' => 'required|string|max:10|unique:veiculos,placa,' . $veiculo->id,
            'marca' => 'required|string|max:100',
            'modelo' => 'required|string|max:100',
            'ano' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'cor' => 'nullable|string|max:50',
            'observacoes' => 'nullable|string',
        ]);

        $validated['placa'] = strtoupper(trim($validated['placa']));

        $veiculo->update($validated);

        return redirect()->route('veiculos.show', $veiculo)->with('success', 'Veículo atualizado com sucesso!');
    }

    public function destroy(Veiculo $veiculo)
    {
        if ($veiculo->agendamentos()->count() > 0) {
            return redirect()->route('veiculos.index')->with('error', 'Não é possível excluir um veículo que já possui histórico de agendamentos.');
        }

        $veiculo->delete();

        return redirect()->route('veiculos.index')->with('success', 'Veículo excluído com sucesso!');
    }
}
