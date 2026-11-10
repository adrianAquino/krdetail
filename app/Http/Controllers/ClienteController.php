<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\User;
use App\Rules\CpfCnpj;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $query = Cliente::with('user')->withCount('veiculos');

        if ($request->filled('busca')) {
            $busca = $request->input('busca');
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('telefone', 'like', "%{$busca}%")
                    ->orWhere('email', 'like', "%{$busca}%")
                    ->orWhere('cpf_cnpj', 'like', "%{$busca}%");
            });
        }

        if ($request->filled('acesso')) {
            $acesso = $request->input('acesso');
            if (in_array($acesso, ['ativo', 'com'], true)) {
                $query->whereNotNull('user_id')
                    ->whereHas('user', fn ($q) => $q->where('ativo', true));
            } elseif (in_array($acesso, ['inativo', 'sem_acesso', 'sem'], true)) {
                $query->where(function ($q) {
                    $q->whereNull('user_id')
                        ->orWhereHas('user', fn ($uq) => $uq->where('ativo', false));
                });
            }
        }

        $clientes = $query->orderBy('nome', 'asc')->paginate(10)->withQueryString();

        return view('clientes.index', compact('clientes'));
    }

    public function create()
    {
        return view('clientes.create');
    }

    public function store(Request $request)
    {
        $criarUsuario = $request->boolean('criar_usuario');

        $rules = [
            'nome' => 'required|string|min:3|max:100',
            'email' => $criarUsuario
                ? 'required|email|max:100|unique:users,email'
                : 'nullable|email|max:100',
            'telefone' => 'required|string|min:10|max:15',
            'cpf_cnpj' => ['nullable', 'string', 'max:18', new CpfCnpj],
            'cep' => 'nullable|string|max:9',
            'logradouro' => 'nullable|string|max:100',
            'numero' => 'nullable|string|max:10',
            'complemento' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:2',
        ];

        if ($criarUsuario) {
            $rules['password'] = 'required|string|min:8|max:64|confirmed';
        }

        $validated = $request->validate($rules);

        $cliente = DB::transaction(function () use ($validated, $criarUsuario) {
            $dadosCliente = collect($validated)->except(['password', 'password_confirmation'])->toArray();

            if ($criarUsuario) {
                $user = User::create([
                    'name' => $validated['nome'],
                    'email' => $validated['email'],
                    'telefone' => $validated['telefone'],
                    'password' => $validated['password'],
                    'tipo_usuario' => 'cliente',
                    'ativo' => true,
                ]);

                $dadosCliente['user_id'] = $user->id;
            }

            return Cliente::create($dadosCliente);
        });

        return redirect()->route('clientes.show', $cliente)->with('success', 'Cliente cadastrado com sucesso!');
    }

    public function show(Cliente $cliente)
    {
        $cliente->load([
            'user',
            'veiculos',
            'agendamentos' => function ($q) {
                $q->with(['veiculo', 'servicos', 'ordemServico'])->orderBy('data_inicio', 'desc');
            }
        ]);

        return view('clientes.show', compact('cliente'));
    }

    public function edit(Cliente $cliente)
    {
        return view('clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $criarUsuario = $request->boolean('criar_usuario') && empty($cliente->user_id);

        $rules = [
            'nome' => 'required|string|min:3|max:100',
            'email' => $criarUsuario
                ? ['required', 'email', 'max:100', Rule::unique('users', 'email')]
                : 'nullable|email|max:100',
            'telefone' => 'required|string|min:10|max:15',
            'cpf_cnpj' => ['nullable', 'string', 'max:18', new CpfCnpj],
            'cep' => 'nullable|string|max:9',
            'logradouro' => 'nullable|string|max:100',
            'numero' => 'nullable|string|max:10',
            'complemento' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:2',
        ];

        if ($criarUsuario) {
            $rules['password'] = 'required|string|min:8|max:64|confirmed';
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($cliente, $validated, $criarUsuario) {
            $dadosCliente = collect($validated)->except(['password', 'password_confirmation'])->toArray();

            if ($criarUsuario) {
                $user = User::create([
                    'name' => $validated['nome'],
                    'email' => $validated['email'],
                    'telefone' => $validated['telefone'],
                    'password' => $validated['password'],
                    'tipo_usuario' => 'cliente',
                    'ativo' => true,
                ]);

                $dadosCliente['user_id'] = $user->id;
            } elseif ($cliente->user) {
                $cliente->user->update([
                    'name' => $validated['nome'],
                    'email' => $validated['email'] ?? $cliente->user->email,
                    'telefone' => $validated['telefone'],
                ]);
            }

            $cliente->update($dadosCliente);
        });

        return redirect()->route('clientes.show', $cliente)->with('success', 'Dados do cliente atualizados com sucesso!');
    }

    public function destroy(Cliente $cliente)
    {
        if ($cliente->veiculos()->count() > 0 || $cliente->agendamentos()->count() > 0) {
            return redirect()->route('clientes.index')->with('error', 'Não é possível excluir um cliente que possui veículos ou agendamentos vinculados.');
        }

        $cliente->delete();

        return redirect()->route('clientes.index')->with('success', 'Cliente removido com sucesso!');
    }
}
