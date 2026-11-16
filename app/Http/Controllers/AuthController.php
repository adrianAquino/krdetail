<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pessoa;
use App\Models\User;
use App\Rules\CpfCnpj;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt([...$credentials, 'ativo' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();

            /** @var User|null $user */
            $user = Auth::user();
            if ($user instanceof User && $user->isCliente()) {
                $user->ensureClienteProfile();
            }

            return redirect()->intended('/dashboard')->with('success', 'Login realizado com sucesso!');
        }

        return back()->withErrors(['email' => 'Credenciais inválidas ou conta desativada.'])->onlyInput('email');
    }

    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $emailNormalizado = strtolower(trim((string) $request->input('email')));

        // Verifica se já existe um cadastro de cliente com este e-mail na oficina
        $clienteExistente = $emailNormalizado !== ''
            ? Cliente::whereRaw('LOWER(email) = ?', [$emailNormalizado])->first()
            : null;

        $validated = $request->validate([
            'name' => 'required|string|min:3|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'telefone' => 'required|string|min:10|max:15',
            'cpf_cnpj' => [
                'nullable',
                'string',
                'max:18',
                new CpfCnpj,
                Rule::unique('clientes', 'cpf_cnpj')->ignore($clienteExistente?->id),
                Rule::unique('pessoas', 'cpf_cnpj')->ignore($clienteExistente?->pessoa_id),
            ],
            'password' => 'required|string|min:8|max:64|confirmed',
        ], [
            'email.unique' => 'Este e-mail já possui uma conta de acesso cadastrada.',
            'cpf_cnpj.unique' => 'Este CPF/CNPJ já está vinculado a outro cadastro.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ]);

        $foiVinculado = false;

        $user = DB::transaction(function () use ($validated, $clienteExistente, &$foiVinculado) {
            if ($clienteExistente) {
                $foiVinculado = true;

                // Seção 9.3 (c): Reaproveita ou cria a Pessoa do cliente existente para compartilhar entre User e Cliente
                $pessoa = $clienteExistente->pessoa_id
                    ? Pessoa::find($clienteExistente->pessoa_id)
                    : null;

                if ($pessoa) {
                    $pessoa->update([
                        'nome' => $clienteExistente->nome ?: $validated['name'],
                        'email' => $validated['email'],
                        'telefone' => $validated['telefone'] ?: $clienteExistente->telefone,
                        'cpf_cnpj' => $clienteExistente->cpf_cnpj ?: ($validated['cpf_cnpj'] ?? null),
                    ]);
                } else {
                    $pessoa = Pessoa::create([
                        'nome' => $clienteExistente->nome ?: $validated['name'],
                        'email' => $validated['email'],
                        'telefone' => $validated['telefone'] ?: $clienteExistente->telefone,
                        'cpf_cnpj' => $clienteExistente->cpf_cnpj ?: ($validated['cpf_cnpj'] ?? null),
                    ]);
                }

                $novoUsuario = User::create([
                    'pessoa_id' => $pessoa->id,
                    'name' => $pessoa->nome,
                    'email' => $validated['email'],
                    'telefone' => $pessoa->telefone,
                    'password' => $validated['password'],
                    'tipo_usuario' => 'cliente',
                    'ativo' => true,
                ]);

                $clienteExistente->update([
                    'pessoa_id' => $pessoa->id,
                    'user_id' => $novoUsuario->id,
                    'nome' => $pessoa->nome,
                    'email' => $validated['email'],
                    'telefone' => $pessoa->telefone,
                    'cpf_cnpj' => $pessoa->cpf_cnpj,
                ]);
            } else {
                $pessoa = Pessoa::create([
                    'nome' => $validated['name'],
                    'email' => $validated['email'],
                    'telefone' => $validated['telefone'],
                    'cpf_cnpj' => $validated['cpf_cnpj'] ?? null,
                ]);

                $novoUsuario = User::create([
                    'pessoa_id' => $pessoa->id,
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'telefone' => $validated['telefone'],
                    'password' => $validated['password'],
                    'tipo_usuario' => 'cliente',
                    'ativo' => true,
                ]);

                Cliente::create([
                    'pessoa_id' => $pessoa->id,
                    'user_id' => $novoUsuario->id,
                    'nome' => $validated['name'],
                    'email' => $validated['email'],
                    'telefone' => $validated['telefone'],
                    'cpf_cnpj' => $validated['cpf_cnpj'] ?? null,
                ]);
            }

            return $novoUsuario;
        });

        Auth::login($user);
        $request->session()->regenerate();

        $mensagem = $foiVinculado
            ? 'Conta criada com sucesso! Encontramos seu cadastro na oficina e vinculamos seu histórico de veículos e serviços.'
            : 'Cadastro realizado com sucesso! Bem-vindo(a) ao Portal do Cliente.';

        return redirect()->route('dashboard')->with('success', $mensagem);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'Você saiu da sua conta!');
    }
}
