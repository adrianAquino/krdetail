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
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                  ->orWhere('descricao', 'like', "%{$busca}%");
            });
        }

        if ($request->input('status') === 'baixo' || $request->input('alerta') === 'baixo') {
            $query->whereColumn('quantidade_estoque', '<=', 'estoque_minimo');
        } elseif ($request->input('status') === 'ativo') {
            $query->where('ativo', true);
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
            'volume_unitario' => 'required|numeric|min:0.001',
            'quantidade_unidades' => 'nullable|numeric|min:0',
            'quantidade_estoque' => 'nullable|numeric|min:0',
            'estoque_minimo_unidades' => 'nullable|numeric|min:0',
            'estoque_minimo' => 'nullable|numeric|min:0',
            'custo_unitario' => 'required|numeric|min:0',
            'ativo' => 'sometimes|boolean',
        ]);

        $volumeUnitario = (float) $validated['volume_unitario'];

        $quantidadeEstoque = isset($validated['quantidade_unidades']) && $validated['quantidade_unidades'] !== null
            ? (float) $validated['quantidade_unidades'] * $volumeUnitario
            : (float) ($validated['quantidade_estoque'] ?? 0);

        $estoqueMinimo = isset($validated['estoque_minimo_unidades']) && $validated['estoque_minimo_unidades'] !== null
            ? (float) $validated['estoque_minimo_unidades'] * $volumeUnitario
            : (float) ($validated['estoque_minimo'] ?? 0);

        $produto = Produto::create([
            'nome' => $validated['nome'],
            'descricao' => $validated['descricao'] ?? null,
            'unidade_medida' => $validated['unidade_medida'],
            'volume_unitario' => $volumeUnitario,
            'quantidade_estoque' => $quantidadeEstoque,
            'estoque_minimo' => $estoqueMinimo,
            'custo_unitario' => $validated['custo_unitario'],
            'ativo' => $request->has('ativo'),
        ]);

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
            'volume_unitario' => 'required|numeric|min:0.001',
            'quantidade_unidades' => 'nullable|numeric|min:0',
            'quantidade_estoque' => 'nullable|numeric|min:0',
            'estoque_minimo_unidades' => 'nullable|numeric|min:0',
            'estoque_minimo' => 'nullable|numeric|min:0',
            'custo_unitario' => 'required|numeric|min:0',
            'ativo' => 'sometimes|boolean',
        ]);

        $volumeUnitario = (float) $validated['volume_unitario'];

        $quantidadeEstoque = isset($validated['quantidade_estoque']) && $validated['quantidade_estoque'] !== null
            ? (float) $validated['quantidade_estoque']
            : ((float) ($validated['quantidade_unidades'] ?? 0) * $volumeUnitario);

        $estoqueMinimo = isset($validated['estoque_minimo_unidades']) && $validated['estoque_minimo_unidades'] !== null
            ? (float) $validated['estoque_minimo_unidades'] * $volumeUnitario
            : (float) ($validated['estoque_minimo'] ?? 0);

        $produto->update([
            'nome' => $validated['nome'],
            'descricao' => $validated['descricao'] ?? null,
            'unidade_medida' => $validated['unidade_medida'],
            'volume_unitario' => $volumeUnitario,
            'quantidade_estoque' => $quantidadeEstoque,
            'estoque_minimo' => $estoqueMinimo,
            'custo_unitario' => $validated['custo_unitario'],
            'ativo' => $request->has('ativo'),
        ]);

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
