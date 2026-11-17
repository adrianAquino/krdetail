<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pessoa;
use App\Models\User;
use App\Rules\CpfCnpj;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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

    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:100',
        ], [
            'email.required' => 'Informe o e-mail cadastrado.',
            'email.email' => 'Informe um endereço de e-mail válido.',
        ]);

        $user = User::where('email', $validated['email'])
            ->where('ativo', true)
            ->first();

        if (!$user) {
            return back()
                ->withErrors(['email' => 'Não encontramos uma conta ativa com este endereço de e-mail.'])
                ->onlyInput('email');
        }

        $token = Password::createToken($user);
        $user->sendPasswordResetNotification($token);

        return back()->with('success', 'Enviamos um link de redefinição de senha para o seu e-mail! Verifique sua caixa de entrada e a pasta de spam.');
    }

    public function showResetPasswordForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|max:100',
            'password' => 'required|string|min:8|max:64|confirmed',
        ], [
            'email.required' => 'Informe o endereço de e-mail.',
            'email.email' => 'Informe um endereço de e-mail válido.',
            'password.required' => 'Informe a nova senha.',
            'password.min' => 'A nova senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da nova senha não confere.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('success', 'Sua senha foi redefinida com sucesso! Faça login com sua nova senha.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => 'Este link de redefinição de senha é inválido ou já expirou. Solicite um novo link.',
            ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'Você saiu da sua conta!');
    }
}
