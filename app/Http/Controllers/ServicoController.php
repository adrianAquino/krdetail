<?php

namespace App\Http\Controllers;

use App\Models\Servico;
use Illuminate\Http\Request;

class ServicoController extends Controller
{
    public function index(Request $request)
    {
        $query = Servico::query();

        if ($request->filled('busca')) {
            $busca = $request->input('busca');
            $query->where('nome', 'like', "%{$busca}%")
                  ->orWhere('descricao', 'like', "%{$busca}%");
        }

        if ($request->filled('status')) {
            if ($request->input('status') === 'ativo') {
                $query->where('ativo', true);
            } elseif ($request->input('status') === 'inativo') {
                $query->where('ativo', false);
            }
        }

        $servicos = $query->orderBy('nome', 'asc')->paginate(12)->withQueryString();

        return view('servicos.index', compact('servicos'));
    }

    public function create()
    {
        return view('servicos.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'preco_base' => 'required|numeric|min:0',
            'tempo_estimado_minutos' => 'required|integer|min:1',
            'ativo' => 'sometimes|boolean',
        ]);

        $validated['ativo'] = $request->has('ativo');

        Servico::create($validated);

        return redirect()->route('servicos.index')->with('success', 'Serviço cadastrado com sucesso!');
    }

    public function edit(Servico $servico)
    {
        return view('servicos.edit', compact('servico'));
    }

    public function update(Request $request, Servico $servico)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'preco_base' => 'required|numeric|min:0',
            'tempo_estimado_minutos' => 'required|integer|min:1',
            'ativo' => 'sometimes|boolean',
        ]);

        $validated['ativo'] = $request->has('ativo');

        $servico->update($validated);

        return redirect()->route('servicos.index')->with('success', 'Serviço atualizado com sucesso!');
    }

    public function destroy(Servico $servico)
    {
        if ($servico->agendamentos()->count() > 0) {
            $servico->update(['ativo' => false]);
            return redirect()->route('servicos.index')->with('info', 'O serviço possui histórico e foi desativado em vez de excluído.');
        }

        $servico->delete();

        return redirect()->route('servicos.index')->with('success', 'Serviço excluído com sucesso!');
    }
}
