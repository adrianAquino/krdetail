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

        $this->normalizarNome($request);
        $empresaAlvoId = $isAdmin
            ? ($request->integer('empresa_id') ?: null)
            : $user?->getEmpresaAtivaId();

        $rules = [
            'nome' => [
                'required',
                'string',
                'max:100',
                $this->regraNomeUnicoPorEmpresa($empresaAlvoId),
            ],
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

        try {
            Servico::create($validated);
        } catch (QueryException $e) {
            return back()
                ->withInput()
                ->withErrors(['nome' => 'Já existe um serviço cadastrado com este nome nesta empresa (verifique também os serviços inativos).']);
        }

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
        $this->normalizarNome($request);

        $validated = $request->validate([
            'nome' => [
                'required',
                'string',
                'max:100',
                $this->regraNomeUnicoPorEmpresa($servico->empresa_id, $servico->id),
            ],
            'descricao' => 'nullable|string|max:500',
            'preco_base' => 'required|numeric|min:0|max:99999.99',
            'tempo_estimado_minutos' => 'required|integer|min:10|max:1440',
            'ativo' => 'sometimes|boolean',
        ]);

        $validated['ativo'] = $request->boolean('ativo');

        try {
            $servico->update($validated);
        } catch (QueryException $e) {
            return back()
                ->withInput()
                ->withErrors(['nome' => 'Já existe um serviço cadastrado com este nome nesta empresa (verifique também os serviços inativos).']);
        }

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

    private function normalizarNome(Request $request): void
    {
        if (is_scalar($request->input('nome'))) {
            $nomeNormalizado = preg_replace('/\s+/', ' ', trim((string) $request->input('nome'))) ?? '';
            $request->merge(['nome' => $nomeNormalizado]);
        }
    }

    private function regraNomeUnicoPorEmpresa(?int $empresaId, ?int $ignorarId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($empresaId, $ignorarId): void {
            if ($empresaId === null || ! is_string($value) || $value === '') {
                return;
            }

            $jaExiste = Servico::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->when($ignorarId !== null, fn ($q) => $q->where('id', '!=', $ignorarId))
                ->whereRaw('LOWER(nome) = ?', [mb_strtolower($value, 'UTF-8')])
                ->exists();

            if ($jaExiste) {
                $fail('Já existe um serviço cadastrado com este nome nesta empresa (verifique também os serviços inativos).');
            }
        };
    }
}
