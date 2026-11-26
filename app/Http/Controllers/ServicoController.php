<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServicoController extends Controller
{
    public function index(Request $request)
    {
        $filtroAdmin = $this->resolverFiltroEmpresaAdmin($request);
        $query = Servico::with('empresa');

        $busca = trim($this->inputString($request, 'busca'));
        if ($busca !== '') {
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('descricao', 'like', "%{$busca}%");
            });
        }

        $status = $this->inputString($request, 'status');
        if ($status === 'ativo') {
            $query->where('ativo', true);
        } elseif ($status === 'inativo') {
            $query->where('ativo', false);
        }

        $servicos = $query->orderBy('nome', 'asc')->paginate(12)->withQueryString();

        return view('servicos.index', array_merge(compact('servicos'), $filtroAdmin));
    }

    public function create(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isAdmin = $user instanceof User && $user->isAdministrador();
        $empresas = $isAdmin ? Empresa::orderBy('nome_fantasia')->get() : collect();
        $empresaSelecionadaId = $isAdmin ? ($request->integer('empresa_id') ?: null) : null;

        return view('servicos.create', compact('isAdmin', 'empresas', 'empresaSelecionadaId'));
    }

    public function store(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isAdmin = $user instanceof User && $user->isAdministrador();

        $rules = [
            'nome' => 'required|string|max:100',
            'descricao' => 'nullable|string|max:500',
            'preco_base' => 'required|numeric|min:0|max:99999.99',
            'tempo_estimado_minutos' => 'required|integer|min:10|max:1440',
            'ativo' => 'sometimes|boolean',
        ];

        if ($isAdmin) {
            $rules['empresa_id'] = 'required|integer|exists:empresas,id';
        }

        $validated = $request->validate($rules, [
            'empresa_id.required' => 'Selecione a empresa proprietária deste serviço.',
        ]);

        if (! $isAdmin) {
            unset($validated['empresa_id']);
        }

        $validated['ativo'] = $request->boolean('ativo');

        Servico::create($validated);

        $rotaParams = $isAdmin && ! empty($validated['empresa_id'])
            ? ['empresa_id' => $validated['empresa_id']]
            : [];

        return redirect()->route('servicos.index', $rotaParams)->with('success', 'Serviço cadastrado com sucesso!');
    }

    public function edit(Servico $servico)
    {
        $servico->load('empresa');

        return view('servicos.edit', compact('servico'));
    }

    public function update(Request $request, Servico $servico)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:100',
            'descricao' => 'nullable|string|max:500',
            'preco_base' => 'required|numeric|min:0|max:99999.99',
            'tempo_estimado_minutos' => 'required|integer|min:10|max:1440',
            'ativo' => 'sometimes|boolean',
        ]);

        $validated['ativo'] = $request->boolean('ativo');

        $servico->update($validated);

        return redirect()->route('servicos.index')->with('success', 'Serviço atualizado com sucesso!');
    }

    public function destroy(Servico $servico)
    {
        if ($servico->agendamentosServicos()->exists()) {
            $servico->update(['ativo' => false]);

            return redirect()
                ->route('servicos.index')
                ->with('info', 'O serviço possui histórico e foi desativado em vez de excluído.');
        }

        try {
            $servico->delete();
        } catch (QueryException $e) {
            $servico->update(['ativo' => false]);

            return redirect()
                ->route('servicos.index')
                ->with('info', 'O serviço possui vínculos históricos e foi desativado em vez de excluído.');
        }

        return redirect()->route('servicos.index')->with('success', 'Serviço excluído com sucesso!');
    }
}
