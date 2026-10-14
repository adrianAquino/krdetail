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
        $produtos = Produto::orderBy('nome', 'asc')->get();

        $totalItens = $produtos->count();
        $itensCriticos = $produtos->filter(fn ($p) => $p->quantidade_estoque <= $p->estoque_minimo)->count();
        $valorTotalEstoque = $produtos->sum(fn ($p) => $p->quantidade_estoque * $p->custo_unitario);

        $kpis = [
            'total_itens' => $totalItens,
            'itens_criticos' => $itensCriticos,
            'valor_total_estoque' => $valorTotalEstoque,
        ];

        $movimentacoes = MovimentacaoEstoque::with(['produto', 'usuario'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $tabAtiva = $request->query('tab', 'produtos');
    

        return view('estoque.index', compact('produtos', 'kpis', 'movimentacoes', 'tabAtiva'));
    }

    public function movimentar(Request $request, MovimentarEstoqueService $service)
    {
        $validated = $request->validate([
            'produto_id' => 'required|exists:produtos,id',
            'tipo_movimentacao' => 'required|in:entrada,saida,ajuste',
            'quantidade' => 'required|numeric|min:0.01',
            'motivo' => 'required|string|max:255',
            'observacoes' => 'nullable|string',
        ]);

        try {
            $service->movimentar(
                produto: $validated['produto_id'],
                tipo: $validated['tipo_movimentacao'],
                quantidade: (float) $validated['quantidade'],
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
