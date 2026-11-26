<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class VeiculoController extends Controller
{
    public function index(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user instanceof User && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            $query = Veiculo::with(['cliente.empresa'])
                ->withCount(['agendamentos' => fn ($q) => $q->where('cliente_id', $cliente->id)])
                ->where('cliente_id', $cliente->id);
            $filtroAdmin = [
                'isAdmin' => false,
                'empresasFiltro' => collect(),
                'empresaFiltroId' => null,
                'empresaFiltroAtual' => null,
            ];
        } else {
            $filtroAdmin = $this->resolverFiltroEmpresaAdmin($request);
            $query = Veiculo::with(['cliente.empresa'])->withCount('agendamentos');
        }

        $busca = trim($this->inputString($request, 'busca'));
        if ($busca !== '') {
            $query->where(function ($q) use ($busca, $user) {
                $q->where('placa', 'like', "%{$busca}%")
                    ->orWhere('marca', 'like', "%{$busca}%")
                    ->orWhere('modelo', 'like', "%{$busca}%");

                if (! ($user instanceof User) || ! $user->isCliente()) {
                    $q->orWhereHas('cliente', fn ($cq) => $cq->where('nome', 'like', "%{$busca}%"));
                }
            });
        }

        $veiculos = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        return view('veiculos.index', array_merge(compact('veiculos'), $filtroAdmin));
    }

    public function create(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isAdmin = $user instanceof User && $user->isAdministrador();
        $empresas = collect();
        $empresaSelecionadaId = null;

        if ($user instanceof User && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            $clientes = collect([$cliente]);
            $selectedClienteId = $cliente->id;
        } else {
            if ($isAdmin) {
                $empresas = Empresa::orderBy('nome_fantasia')->get();
                $empReq = $request->integer('empresa_id') ?: null;
                $empresaSelecionadaId = ($empReq && $empresas->contains('id', $empReq)) ? $empReq : null;
            }

            $clientesQuery = Cliente::with('empresa')->orderBy('nome');
            if ($isAdmin && $empresaSelecionadaId) {
                $clientesQuery->where('empresa_id', $empresaSelecionadaId);
            }
            $clientes = $clientesQuery->get();
            $selectedClienteId = $request->query('cliente_id');
        }

        return view('veiculos.create', compact('clientes', 'selectedClienteId', 'isAdmin', 'empresas', 'empresaSelecionadaId'));
    }

    public function store(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user instanceof User && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            $request->merge(['cliente_id' => $cliente->id]);
        }

        if (is_scalar($request->input('placa'))) {
            $placaNormalizada = strtoupper((string) preg_replace('/[\s\-]+/', '', (string) $request->input('placa')));
            $request->merge(['placa' => $placaNormalizada]);
        }

        $clienteRule = Rule::exists('clientes', 'id');
        if ($user instanceof User && ($user->isDono() || $user->isFuncionario())) {
            $empresaId = $user->getEmpresaAtivaId();
            $clienteRule = $clienteRule->where('empresa_id', $empresaId ?? -1);
        }

        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', $clienteRule],
            'placa' => [
                'required',
                'bail',
                'string',
                'size:7',
                'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/',
                Rule::unique('veiculos', 'placa'),
            ],
            'marca' => 'required|string|max:50',
            'modelo' => 'required|string|max:80',
            'cor' => 'nullable|string|max:40',
            'ano' => 'nullable|integer|min:1950|max:' . (date('Y') + 1),
            'observacoes' => 'nullable|string|max:500',
        ], [
            'placa.size' => 'A placa deve conter exatamente 7 caracteres (sem hífen).',
            'placa.regex' => 'Informe uma placa válida no padrão brasileiro ou Mercosul (ex: ABC1D23 ou ABC1234).',
            'placa.unique' => 'Já existe um veículo cadastrado com esta placa.',
            'cliente_id.exists' => 'O cliente selecionado é inválido ou não pertence à sua empresa.',
        ]);

        if ($user instanceof User && $user->isCliente()) {
            $validated['cliente_id'] = $user->ensureClienteProfile()->id;
        }

        try {
            $veiculo = Veiculo::create($validated);
        } catch (QueryException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Não foi possível cadastrar o veículo. Verifique se a placa já está cadastrada.',
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', 'Não foi possível cadastrar o veículo. Verifique se a placa já está cadastrada.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Veículo cadastrado com sucesso!',
                'veiculo' => [
                    'id' => $veiculo->id,
                    'cliente_id' => (int) $veiculo->cliente_id,
                    'placa' => $veiculo->placa,
                    'marca' => $veiculo->marca,
                    'modelo' => $veiculo->modelo,
                    'cor' => $veiculo->cor,
                    'ano' => $veiculo->ano,
                ],
            ], 201);
        }

        return redirect()->route('veiculos.show', $veiculo)->with('success', 'Veículo cadastrado com sucesso!');
    }

    public function show(Veiculo $veiculo)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user instanceof User && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            if ((int) $veiculo->cliente_id !== (int) $cliente->id) {
                abort(403, 'Você não tem permissão para visualizar este veículo.');
            }

            $veiculo->load([
                'cliente.empresa',
                'agendamentos' => function ($q) use ($cliente) {
                    $q->where('cliente_id', $cliente->id)
                        ->with(['servicos', 'ordemServico.tarefas'])
                        ->orderByDesc('data_inicio');
                },
            ]);
        } else {
            $veiculo->load(['cliente.empresa', 'agendamentos.servicos', 'agendamentos.ordemServico.tarefas']);
        }

        return view('veiculos.show', compact('veiculo'));
    }

    public function edit(Veiculo $veiculo)
    {
        /** @var User|null $user */
        $user = Auth::user();
        $veiculo->load('cliente.empresa');

        if ($user instanceof User && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            if ((int) $veiculo->cliente_id !== (int) $cliente->id) {
                abort(403, 'Você não tem permissão para editar este veículo.');
            }
            $clientes = collect([$cliente]);
        } else {
            $clientesQuery = Cliente::with('empresa')->orderBy('nome');
            if ($user instanceof User && $user->isAdministrador() && $veiculo->cliente?->empresa_id) {
                $clientesQuery->where('empresa_id', $veiculo->cliente->empresa_id);
            }
            $clientes = $clientesQuery->get();
        }

        return view('veiculos.edit', compact('veiculo', 'clientes'));
    }

    public function update(Request $request, Veiculo $veiculo)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user instanceof User && $user->isCliente()) {
            $cliente = $user->ensureClienteProfile();
            if ((int) $veiculo->cliente_id !== (int) $cliente->id) {
                abort(403, 'Você não tem permissão para atualizar este veículo.');
            }
            $request->merge(['cliente_id' => $cliente->id]);
        }

        if (is_scalar($request->input('placa'))) {
            $placaNormalizada = strtoupper((string) preg_replace('/[\s\-]+/', '', (string) $request->input('placa')));
            $request->merge(['placa' => $placaNormalizada]);
        }

        $clienteRule = Rule::exists('clientes', 'id');
        if ($user instanceof User && ($user->isDono() || $user->isFuncionario())) {
            $empresaId = $user->getEmpresaAtivaId();
            $clienteRule = $clienteRule->where('empresa_id', $empresaId ?? -1);
        }

        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', $clienteRule],
            'placa' => [
                'required',
                'bail',
                'string',
                'size:7',
                'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/',
                Rule::unique('veiculos', 'placa')->ignore($veiculo->id),
            ],
            'marca' => 'required|string|max:50',
            'modelo' => 'required|string|max:80',
            'cor' => 'nullable|string|max:40',
            'ano' => 'nullable|integer|min:1950|max:' . (date('Y') + 1),
            'observacoes' => 'nullable|string|max:500',
        ], [
            'placa.size' => 'A placa deve conter exatamente 7 caracteres (sem hífen).',
            'placa.regex' => 'Informe uma placa válida no padrão brasileiro ou Mercosul (ex: ABC1D23 ou ABC1234).',
            'placa.unique' => 'Já existe outro veículo cadastrado com esta placa.',
            'cliente_id.exists' => 'O cliente selecionado é inválido ou não pertence à sua empresa.',
        ]);

        if ($user instanceof User && $user->isCliente()) {
            $validated['cliente_id'] = $user->ensureClienteProfile()->id;
        }

        try {
            $veiculo->update($validated);
        } catch (QueryException $e) {
            return back()
                ->withInput()
                ->with('error', 'Não foi possível atualizar o veículo. Verifique se a placa já está em uso.');
        }

        return redirect()->route('veiculos.show', $veiculo)->with('success', 'Veículo atualizado com sucesso!');
    }

    public function destroy(Veiculo $veiculo)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user instanceof User && $user->isCliente()) {
            abort(403);
        }

        if ($veiculo->agendamentos()->withoutGlobalScopes()->exists()) {
            return redirect()
                ->back()
                ->with('error', 'Não é possível excluir este veículo, pois ele já possui agendamentos ou serviços realizados vinculados ao seu histórico.');
        }

        try {
            $veiculo->delete();
        } catch (QueryException $e) {
            return redirect()
                ->back()
                ->with('error', 'Não é possível excluir este veículo, pois ele possui serviços realizados vinculados.');
        }

        return redirect()->route('veiculos.index')->with('success', 'Veículo removido com sucesso!');
    }
}
