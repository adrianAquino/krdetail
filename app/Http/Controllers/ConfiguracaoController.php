<?php

namespace App\Http\Controllers;

use App\Models\Funcionario;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConfiguracaoController extends Controller
{
    public function index()
    {
        $usuarios = User::orderBy('name', 'asc')->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'nome' => $u->name,
                'email' => $u->email,
                'telefone' => $u->telefone ?? '-',
                'tipo' => $u->tipo_usuario ?? 'funcionario',
                'ativo' => (bool) $u->ativo,
            ];
        });

        return view('configuracoes.index', compact('usuarios'));
    }

    public function storeUsuario(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'telefone' => 'nullable|string|max:20',
            'tipo_usuario' => 'required|in:administrador,funcionario,cliente',
            'password' => 'required|string|min:6',
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'telefone' => $validated['telefone'] ?? null,
                'tipo_usuario' => $validated['tipo_usuario'],
                'password' => $validated['password'],
                'ativo' => true,
            ]);

            if ($validated['tipo_usuario'] === 'funcionario') {
                Funcionario::firstOrCreate(
                    ['user_id' => $user->id],
                    ['cargo' => 'Operador de Detalhamento', 'ativo' => true]
                );
            }
        });

        return redirect()
            ->route('configuracoes.index', ['tab' => 'equipe'])
            ->with('success', 'Usuário cadastrado com sucesso!');
    }

    public function updateUsuario(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'telefone' => 'nullable|string|max:20',
            'tipo_usuario' => 'required|in:administrador,funcionario,cliente',
            'password' => 'nullable|string|min:6',
        ]);

        DB::transaction(function () use ($user, $validated) {
            $dados = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'telefone' => $validated['telefone'] ?? null,
                'tipo_usuario' => $validated['tipo_usuario'],
            ];

            if (!empty($validated['password'])) {
                $dados['password'] = $validated['password'];
            }

            $user->update($dados);

            if ($validated['tipo_usuario'] === 'funcionario') {
                Funcionario::updateOrCreate(
                    ['user_id' => $user->id],
                    ['ativo' => (bool) $user->ativo]
                );
            }
        });

        return redirect()
            ->route('configuracoes.index', ['tab' => 'equipe'])
            ->with('success', 'Dados do usuário atualizados com sucesso!');
    }

    public function toggleStatusUsuario(User $user)
    {
        $novoStatus = !$user->ativo;
        $user->update(['ativo' => $novoStatus]);

        if ($user->funcionario) {
            $user->funcionario->update(['ativo' => $novoStatus]);
        }

        $acao = $novoStatus ? 'ativado' : 'desativado';

        return redirect()
            ->route('configuracoes.index', ['tab' => 'equipe'])
            ->with('success', "Acesso do usuário {$user->name} {$acao} com sucesso!");
    }
}
