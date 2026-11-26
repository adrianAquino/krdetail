<?php

namespace App\Http\Controllers;

use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Services\MovimentarEstoqueService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EstoqueController extends Controller
{
    /**
     * Lista níveis de estoque, produtos inativos e histórico de movimentações.
     */
    public function index(Request $request): View
    {
        $filtroAdmin = $this->resolverFiltroEmpresaAdmin($request);

        $tabRaw = $this->inputString($request, 'tab');
        $tabAtiva = in_array($tabRaw, ['produtos', 'inativos', 'movimentacoes'], true)
            ? $tabRaw
            : 'produtos';

        $busca = trim($this->inputString($request, 'busca'));

        $produtosAtivosBase = Produto::with('empresa')->where('ativo', true);
        $produtosInativosBase = Produto::with('empresa')->where('ativo', false);

        $produtosAtivos = (clone $produtosAtivosBase)
            ->when($busca !== '', fn ($q) => $q->where('nome', 'like', "%{$busca}%"))
            ->orderBy('nome')
            ->get();

        $produtosInativos = (clone $produtosInativosBase)
            ->when($busca !== '', fn ($q) => $q->where('nome', 'like', "%{$busca}%"))
            ->orderBy('nome')
            ->get();

        $movimentacoes = MovimentacaoEstoque::with(['produto.empresa', 'usuario'])
            ->when($busca !== '', function ($q) use ($busca) {
                $q->where(function ($sub) use ($busca) {
                    $sub->where('motivo', 'like', "%{$busca}%")
                        ->orWhereHas('produto', fn ($pq) => $pq->where('nome', 'like', "%{$busca}%"));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $todosAtivos = $produtosAtivosBase->get();
        $itensAtivosCount = $todosAtivos->count();
        $itensInativosCount = $produtosInativosBase->count();

        $itensCriticosCount = $todosAtivos->filter(function (Produto $p) {
            return (float) $p->estoque_minimo > 0 && (float) $p->quantidade_estoque <= (float) $p->estoque_minimo;
        })->count();

        $valorTotalEstoque = round($todosAtivos->sum(function (Produto $p) {
            return (float) $p->quantidade_estoque * (float) ($p->custo_por_volume ?? 0);
        }), 2);

        $kpis = [
            'total_itens' => $itensAtivosCount + $itensInativosCount,
            'itens_ativos' => $itensAtivosCount,
            'itens_inativos' => $itensInativosCount,
            'itens_criticos' => $itensCriticosCount,
            'valor_total_estoque' => $valorTotalEstoque,
        ];

        return view('estoque.index', array_merge(compact(
            'produtosAtivos',
            'produtosInativos',
            'movimentacoes',
            'kpis',
            'tabAtiva',
            'busca'
        ), $filtroAdmin));
    }

    /**
     * Registra uma movimentação manual de estoque (entrada, saída ou ajuste).
     */
    public function movimentar(Request $request, MovimentarEstoqueService $service): RedirectResponse
    {
        $tipoMovimentacao = $this->inputString($request, 'tipo_movimentacao');
        $minQuantidade = $tipoMovimentacao === 'ajuste' ? '0' : '0.001';

        $user = Auth::user();
        $produtoRule = Rule::exists('produtos', 'id');

        if ($user && ($user->isDono() || $user->isFuncionario())) {
            $empresaId = $user->getEmpresaAtivaId();
            $produtoRule = $empresaId !== null
                ? $produtoRule->where('empresa_id', $empresaId)
                : $produtoRule->whereRaw('1 = 0');
        }

        $validated = $request->validate([
            'produto_id' => ['required', 'integer', $produtoRule],
            'tipo_movimentacao' => ['required', Rule::in(['entrada', 'saida', 'ajuste'])],
            'modo_quantidade' => ['nullable', Rule::in(['volume', 'unidades'])],
            'quantidade' => ['required', 'numeric', "min:{$minQuantidade}", 'max:999999999.999'],
            'motivo' => ['required', 'string', 'max:150'],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ]);

        $produto = Produto::findOrFail((int) $validated['produto_id']);
        $modo = $validated['modo_quantidade'] ?? 'volume';
        $quantidadeInformada = (float) $validated['quantidade'];

        $volumeUnitario = (float) ($produto->volume_unitario ?? 1);
        $quantidadeVolume = $modo === 'unidades'
            ? round($quantidadeInformada * max(0.001, $volumeUnitario), 3)
            : round($quantidadeInformada, 3);

        if ($validated['tipo_movimentacao'] !== 'ajuste' && (int) round($quantidadeVolume * 1000) < 1) {
            return back()
                ->withInput()
                ->with('error', 'A quantidade convertida para o estoque deve ser maior que zero (mín. 0,001).');
        }

        if ($quantidadeVolume > MovimentarEstoqueService::QUANTIDADE_MAXIMA) {
            return back()
                ->withInput()
                ->with('error', 'O volume calculado excede o limite máximo suportado pelo estoque.');
        }

        try {
            $service->movimentar(
                produto: $produto,
                tipo: $validated['tipo_movimentacao'],
                quantidade: $quantidadeVolume,
                usuario: $user,
                motivo: $validated['motivo'],
                observacoes: $validated['observacoes'] ?? null
            );

            return back()->with('success', 'Movimentação de estoque registrada com sucesso!');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (QueryException) {
            return back()->withInput()->with('error', 'Não foi possível registrar a movimentação no momento.');
        }
    }
}
