<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'telefone' => 'required|string|max:20',
            'cpf_cnpj' => [
                'nullable',
                'string',
                'max:30',
                new CpfCnpj,
                Rule::unique('clientes', 'cpf_cnpj')->ignore($clienteExistente?->id),
            ],
            'password' => 'required|string|min:8|confirmed',
        ], [
            'email.unique' => 'Este e-mail já possui uma conta de acesso cadastrada.',
            'cpf_cnpj.unique' => 'Este CPF/CNPJ já está vinculado a outro cadastro.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ]);

        $foiVinculado = false;

        $user = DB::transaction(function () use ($validated, $clienteExistente, &$foiVinculado) {
            $novoUsuario = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'telefone' => $validated['telefone'],
                'password' => $validated['password'],
                'tipo_usuario' => 'cliente',
                'ativo' => true,
            ]);

            if ($clienteExistente) {
                $foiVinculado = true;
                $clienteExistente->update([
                    'user_id' => $novoUsuario->id,
                    'nome' => $clienteExistente->nome ?: $validated['name'],
                    'telefone' => $validated['telefone'] ?: $clienteExistente->telefone,
                    'cpf_cnpj' => $clienteExistente->cpf_cnpj ?: ($validated['cpf_cnpj'] ?? null),
                ]);
            } else {
                Cliente::create([
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
