<?php

namespace App\Http\Controllers;

use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Services\MovimentarEstoqueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EstoqueController extends Controller
{
    public function index(Request $request)
    {
        $todosProdutos = Produto::orderBy('nome', 'asc')->get();

        $produtosAtivos = $todosProdutos->where('ativo', true)->values();
        $produtosInativos = $todosProdutos->where('ativo', false)->values();

        $totalItens = $todosProdutos->count();
        $itensAtivos = $produtosAtivos->count();
        $itensInativos = $produtosInativos->count();

        $itensCriticos = $produtosAtivos->filter(fn ($p) => $p->quantidade_estoque <= $p->estoque_minimo)->count();
        $valorTotalEstoque = $produtosAtivos->sum(fn ($p) => $p->valor_total_estoque);

        $kpis = [
            'total_itens' => $totalItens,
            'itens_ativos' => $itensAtivos,
            'itens_inativos' => $itensInativos,
            'itens_criticos' => $itensCriticos,
            'valor_total_estoque' => $valorTotalEstoque,
        ];

        $movimentacoes = MovimentacaoEstoque::with(['produto', 'usuario'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $tabAtiva = $request->query('tab', 'produtos');

        return view('estoque.index', [
            'produtos' => $produtosAtivos,
            'produtosAtivos' => $produtosAtivos,
            'produtosInativos' => $produtosInativos,
            'kpis' => $kpis,
            'movimentacoes' => $movimentacoes,
            'tabAtiva' => $tabAtiva,
        ]);
    }

    public function movimentar(Request $request, MovimentarEstoqueService $service)
    {
        $validated = $request->validate([
            'produto_id' => 'required|exists:produtos,id',
            'tipo_movimentacao' => 'required|in:entrada,saida,ajuste',
            'modo_quantidade' => 'nullable|in:volume,unidades',
            'quantidade' => 'required|numeric|min:0.001',
            'motivo' => 'required|string|max:255',
            'observacoes' => 'nullable|string',
        ]);

        try {
            $produto = Produto::findOrFail($validated['produto_id']);
            $modo = $validated['modo_quantidade'] ?? 'volume';
            $volUnit = (float) ($produto->volume_unitario > 0 ? $produto->volume_unitario : 1);

            $quantidadeVolume = $modo === 'unidades'
                ? (float) $validated['quantidade'] * $volUnit
                : (float) $validated['quantidade'];

            $service->movimentar(
                produto: $produto,
                tipo: $validated['tipo_movimentacao'],
                quantidade: $quantidadeVolume,
                usuario: Auth::user(),
                motivo: $validated['motivo'],
                observacoes: $validated['observacoes'] ?? null
            );

            return back()->with('success', 'Movimentação de estoque registrada com sucesso!');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
