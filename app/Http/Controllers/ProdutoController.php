<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use Illuminate\Http\Request;

class ProdutoController extends Controller
{
     public function index(Request $request)
    {
        $query = Produto::query();

        if ($request->filled('busca')) {
            $busca = $request->input('busca');
            $query->where('nome', 'like', "%{$busca}%")
                  ->orWhere('descricao', 'like', "%{$busca}%");
        }

        if ($request->filled('status')) {
            if ($request->input('status') === 'baixo') {
                $query->whereColumn('quantidade_estoque', '<=', 'estoque_minimo');
            } elseif ($request->input('status') === 'ativo') {
                $query->where('ativo', true);
            }
        }

        $produtos = $query->orderBy('nome', 'asc')->paginate(12)->withQueryString();

        return view('produtos.index', compact('produtos'));
    }

    public function create()
    {
        return view('produtos.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'unidade_medida' => 'required|string|max:10',
            'quantidade_estoque' => 'required|numeric|min:0',
            'estoque_minimo' => 'required|numeric|min:0',
            'custo_unitario' => 'required|numeric|min:0',
            'ativo' => 'sometimes|boolean',
        ]);

        $validated['ativo'] = $request->has('ativo');

        $produto = Produto::create($validated);

        return redirect()->route('produtos.show', $produto)->with('success', 'Produto cadastrado com sucesso!');
    }

    public function show(Produto $produto)
    {
        $produto->load(['movimentacoes.usuario']);

        return view('produtos.show', compact('produto'));
    }

    public function edit(Produto $produto)
    {
        return view('produtos.edit', compact('produto'));
    }

    public function update(Request $request, Produto $produto)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'unidade_medida' => 'required|string|max:10',
            'quantidade_estoque' => 'required|numeric|min:0',
            'estoque_minimo' => 'required|numeric|min:0',
            'custo_unitario' => 'required|numeric|min:0',
            'ativo' => 'sometimes|boolean',
        ]);

        $validated['ativo'] = $request->has('ativo');

        $produto->update($validated);

        return redirect()->route('produtos.show', $produto)->with('success', 'Produto atualizado com sucesso!');
    }

    public function destroy(Produto $produto)
    {
        if ($produto->tarefas()->count() > 0) {
            $produto->update(['ativo' => false]);
            return redirect()->route('produtos.index')->with('info', 'O produto já foi utilizado em tarefas e foi desativado em vez de excluído.');
        }

        $produto->delete();

        return redirect()->route('produtos.index')->with('success', 'Produto excluído com sucesso!');
    }
}
