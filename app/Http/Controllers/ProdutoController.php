<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Services\MovimentarEstoqueService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProdutoController extends Controller
{
    public function index(Request $request)
    {
        $query = Produto::query();

        $busca = $request->string('busca')->trim()->toString();
        if ($busca !== '') {
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('descricao', 'like', "%{$busca}%");
            });
        }

        $status = $request->string('status')->toString();
        $alerta = $request->string('alerta')->toString();
        if ($status === 'baixo' || $alerta === 'baixo') {
            $query->whereColumn('quantidade_estoque', '<=', 'estoque_minimo');
        } elseif ($status === 'ativo') {
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
            'nome' => 'required|string|max:100',
            'descricao' => 'nullable|string|max:500',
            'unidade_medida' => 'required|string|max:10',
            'volume_unitario' => 'required|numeric|min:0.001|max:999999',
            'quantidade_unidades' => 'nullable|numeric|min:0|max:99999',
            'quantidade_estoque' => 'nullable|numeric|min:0|max:999999999',
            'estoque_minimo_unidades' => 'nullable|numeric|min:0|max:99999',
            'estoque_minimo' => 'nullable|numeric|min:0|max:999999999',
            'custo_unitario' => 'required|numeric|min:0|max:999999.99',
            'ativo' => 'sometimes|boolean',
        ]);

        $volumeUnitario = round((float) $validated['volume_unitario'], 3);

        $quantidadeEstoque = isset($validated['quantidade_unidades']) && $validated['quantidade_unidades'] !== null
            ? round((float) $validated['quantidade_unidades'] * $volumeUnitario, 3)
            : round((float) ($validated['quantidade_estoque'] ?? 0), 3);

        $estoqueMinimo = isset($validated['estoque_minimo_unidades']) && $validated['estoque_minimo_unidades'] !== null
            ? round((float) $validated['estoque_minimo_unidades'] * $volumeUnitario, 3)
            : round((float) ($validated['estoque_minimo'] ?? 0), 3);

        $this->validarLimitesDecimais($quantidadeEstoque, $estoqueMinimo, (float) $validated['custo_unitario'], $volumeUnitario);

        $produto = Produto::create([
            'nome' => $validated['nome'],
            'descricao' => $validated['descricao'] ?? null,
            'unidade_medida' => $validated['unidade_medida'],
            'volume_unitario' => $volumeUnitario,
            'quantidade_estoque' => $quantidadeEstoque,
            'estoque_minimo' => $estoqueMinimo,
            'custo_unitario' => round((float) $validated['custo_unitario'], 4),
            'ativo' => $request->boolean('ativo'),
        ]);

        return redirect()->route('produtos.show', $produto)->with('success', 'Produto cadastrado com sucesso!');
    }

    public function show(Produto $produto)
    {
        $produto->load(['movimentacoes' => fn ($q) => $q->with('usuario')->orderByDesc('created_at')]);

        return view('produtos.show', compact('produto'));
    }

    public function edit(Produto $produto)
    {
        return view('produtos.edit', compact('produto'));
    }

    public function update(Request $request, Produto $produto, MovimentarEstoqueService $estoqueService)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:100',
            'descricao' => 'nullable|string|max:500',
            'unidade_medida' => 'required|string|max:10',
            'volume_unitario' => 'required|numeric|min:0.001|max:999999',
            'quantidade_unidades' => 'nullable|numeric|min:0|max:99999',
            'quantidade_estoque' => 'nullable|numeric|min:0|max:999999999',
            'estoque_minimo_unidades' => 'nullable|numeric|min:0|max:99999',
            'estoque_minimo' => 'nullable|numeric|min:0|max:999999999',
            'custo_unitario' => 'required|numeric|min:0|max:999999.99',
            'ativo' => 'sometimes|boolean',
        ]);

        $volumeUnitario = round((float) $validated['volume_unitario'], 3);

        if ($request->filled('quantidade_estoque')) {
            $quantidadeEstoque = round((float) $validated['quantidade_estoque'], 3);
        } elseif ($request->filled('quantidade_unidades')) {
            $quantidadeEstoque = round((float) $validated['quantidade_unidades'] * $volumeUnitario, 3);
        } else {
            $quantidadeEstoque = round((float) $produto->quantidade_estoque, 3);
        }

        if ($request->filled('estoque_minimo')) {
            $estoqueMinimo = round((float) $validated['estoque_minimo'], 3);
        } elseif ($request->filled('estoque_minimo_unidades')) {
            $estoqueMinimo = round((float) $validated['estoque_minimo_unidades'] * $volumeUnitario, 3);
        } else {
            $estoqueMinimo = round((float) $produto->estoque_minimo, 3);
        }

        $this->validarLimitesDecimais($quantidadeEstoque, $estoqueMinimo, (float) $validated['custo_unitario'], $volumeUnitario);

        $ativo = $request->boolean('ativo');

        DB::transaction(function () use ($produto, $validated, $volumeUnitario, $quantidadeEstoque, $estoqueMinimo, $ativo, $estoqueService) {
            $produtoTravado = Produto::lockForUpdate()->findOrFail($produto->id);
            $saldoAnterior = round((float) $produtoTravado->quantidade_estoque, 3);
            $mudouEstoque = (int) round($quantidadeEstoque * 1000) !== (int) round($saldoAnterior * 1000);

            $produtoTravado->update([
                'nome' => $validated['nome'],
                'descricao' => $validated['descricao'] ?? null,
                'unidade_medida' => $validated['unidade_medida'],
                'volume_unitario' => $volumeUnitario,
                'estoque_minimo' => $estoqueMinimo,
                'custo_unitario' => round((float) $validated['custo_unitario'], 4),
                'ativo' => $ativo,
            ]);

            if ($mudouEstoque) {
                $estoqueService->movimentar(
                    produto: $produtoTravado,
                    tipo: 'ajuste',
                    quantidade: $quantidadeEstoque,
                    usuario: Auth::user(),
                    motivo: 'Ajuste direto na edição do produto',
                    permitirProdutoInativo: true
                );
            }
        });

        return redirect()->route('produtos.show', $produto)->with('success', 'Produto atualizado com sucesso!');
    }

    public function destroy(Produto $produto)
    {
        $possuiHistorico = $produto->tarefasProdutos()->withoutGlobalScopes()->exists()
            || $produto->movimentacoes()->withoutGlobalScopes()->exists();

        if ($possuiHistorico) {
            $produto->update(['ativo' => false]);

            return redirect()
                ->route('produtos.index')
                ->with('info', 'O produto possui histórico de movimentações ou uso em tarefas e foi desativado em vez de excluído.');
        }

        try {
            $produto->delete();
        } catch (QueryException $e) {
            $produto->update(['ativo' => false]);

            return redirect()
                ->route('produtos.index')
                ->with('info', 'O produto possui vínculos no histórico e foi desativado em vez de excluído.');
        }

        return redirect()->route('produtos.index')->with('success', 'Produto excluído com sucesso!');
    }

    private function validarLimitesDecimais(float $quantidadeEstoque, float $estoqueMinimo, float $custoUnitario, float $volumeUnitario): void
    {
        if ($quantidadeEstoque > 999999999.999) {
            throw ValidationException::withMessages([
                'quantidade_estoque' => 'O volume total de estoque ultrapassa o limite máximo permitido pelo sistema (999.999.999,999).',
            ]);
        }

        if ($estoqueMinimo > 999999999.999) {
            throw ValidationException::withMessages([
                'estoque_minimo' => 'O estoque mínimo ultrapassa o limite máximo permitido pelo sistema (999.999.999,999).',
            ]);
        }

        if ($volumeUnitario > 0 && ($custoUnitario / $volumeUnitario) > 99999999.9999) {
            throw ValidationException::withMessages([
                'custo_unitario' => 'A relação entre custo unitário e volume unitário ultrapassa o limite máximo suportado.',
            ]);
        }
    }
}
