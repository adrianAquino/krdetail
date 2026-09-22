<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $query = Cliente::withCount('veiculos');

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
            if ($request->input('acesso') === 'com') {
                $query->whereNotNull('user_id');
            } elseif ($request->input('acesso') === 'sem') {
                $query->whereNull('user_id');
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
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'required|string|max:30',
            'cpf_cnpj' => 'nullable|string|max:30',
            'cep' => 'nullable|string|max:20',
            'logradouro' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:2',
        ]);

        $cliente = Cliente::create($validated);

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

       

        return view('clientes.show', compact(
            'cliente'
        ));
    }

    public function edit(Cliente $cliente)
    {
        return view('clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'required|string|max:30',
            'cpf_cnpj' => 'nullable|string|max:30',
            'cep' => 'nullable|string|max:20',
            'logradouro' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:2',
        ]);

        $cliente->update($validated);

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
