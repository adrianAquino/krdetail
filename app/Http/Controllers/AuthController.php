<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\User;
use App\Rules\CpfCnpj;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        if (is_scalar($request->input('email'))) {
            $request->merge([
                'email' => mb_strtolower(trim((string) $request->input('email'))),
            ]);
        }

        $credentials = $request->validate([
            'email' => 'required|email|max:100',
            'password' => 'required|string|max:64',
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
        $emailNormalizado = mb_strtolower(trim($this->inputString($request, 'email')));
        $cpfCnpjRaw = $request->input('cpf_cnpj');
        $cpfCnpjNormalizado = CpfCnpj::normalizar($cpfCnpjRaw);

        $mergeRegister = [
            'cpf_cnpj' => is_string($cpfCnpjRaw) && trim($cpfCnpjRaw) !== '' ? $cpfCnpjNormalizado : null,
        ];
        if (is_scalar($request->input('email'))) {
            $mergeRegister['email'] = $emailNormalizado;
        }
        $request->merge($mergeRegister);

        // Verifica se já existe um cadastro presencial de cliente (sem user_id) com este e-mail
        $clienteExistente = $emailNormalizado !== ''
            ? Cliente::withoutGlobalScopes()
                ->whereNull('user_id')
                ->whereRaw('LOWER(email) = ?', [$emailNormalizado])
                ->first()
            : null;

        $validated = $request->validate([
            'name' => 'required|string|min:3|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'telefone' => 'required|string|min:10|max:15',
            'cpf_cnpj' => [
                'nullable',
                'bail',
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

        // Proteção contra Account Takeover: vincula cadastro presencial somente mediante confirmação
        // de dado secundário (CPF/CNPJ cadastrado, ou telefone cadastrado quando não houver CPF/CNPJ).
        if ($clienteExistente) {
            $apenasDigitos = fn (?string $v): string => preg_replace('/[^0-9A-Za-z]/', '', strtoupper((string) $v)) ?? '';

            if (! empty($clienteExistente->cpf_cnpj)) {
                if (empty($validated['cpf_cnpj']) || $apenasDigitos($validated['cpf_cnpj']) !== $apenasDigitos($clienteExistente->cpf_cnpj)) {
                    throw ValidationException::withMessages([
                        'cpf_cnpj' => 'Este e-mail já está pré-cadastrado na oficina. Para vincular sua conta com segurança, informe o mesmo CPF/CNPJ cadastrado.',
                    ]);
                }
            } elseif (! empty($clienteExistente->telefone)) {
                $telDigitosCliente = preg_replace('/\D/', '', (string) $clienteExistente->telefone) ?? '';
                $telDigitosInput = preg_replace('/\D/', '', (string) $validated['telefone']) ?? '';
                if ($telDigitosCliente !== '' && $telDigitosInput !== $telDigitosCliente) {
                    throw ValidationException::withMessages([
                        'telefone' => 'Este e-mail já está pré-cadastrado na oficina. Para vincular sua conta com segurança, informe o mesmo telefone cadastrado.',
                    ]);
                }
            }

            if ($clienteExistente->pessoa_id && User::where('pessoa_id', $clienteExistente->pessoa_id)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Este cadastro já está vinculado a uma conta de usuário existente.',
                ]);
            }
        }

        $foiVinculado = false;

        try {
            $user = DB::transaction(function () use ($validated, $clienteExistente, &$foiVinculado) {
                if ($clienteExistente) {
                    $foiVinculado = true;

                    $pessoa = $clienteExistente->pessoa_id
                        ? Pessoa::withTrashed()->find($clienteExistente->pessoa_id)
                        : null;

                    if ($pessoa) {
                        if ($pessoa->trashed()) {
                            $pessoa->restore();
                        }
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

                    $empresaOriginalId = $clienteExistente->empresa_id
                        ?: Empresa::query()->orderBy('id')->value('id');

                    $novoUsuario = User::create([
                        'pessoa_id' => $pessoa->id,
                        'empresa_id' => $empresaOriginalId,
                        'name' => $pessoa->nome,
                        'email' => $validated['email'],
                        'telefone' => $pessoa->telefone,
                        'password' => $validated['password'],
                        'tipo_usuario' => 'cliente',
                        'ativo' => true,
                    ]);

                    $clienteExistente->update([
                        'pessoa_id' => $pessoa->id,
                        'empresa_id' => $empresaOriginalId,
                        'user_id' => $novoUsuario->id,
                        'nome' => $pessoa->nome,
                        'email' => $validated['email'],
                        'telefone' => $pessoa->telefone,
                        'cpf_cnpj' => $pessoa->cpf_cnpj,
                    ]);
                } else {
                    $empresaPadraoId = Empresa::query()->orderBy('id')->value('id');

                    $pessoa = Pessoa::create([
                        'nome' => $validated['name'],
                        'email' => $validated['email'],
                        'telefone' => $validated['telefone'],
                        'cpf_cnpj' => $validated['cpf_cnpj'] ?? null,
                    ]);

                    $novoUsuario = User::create([
                        'pessoa_id' => $pessoa->id,
                        'empresa_id' => $empresaPadraoId,
                        'name' => $validated['name'],
                        'email' => $validated['email'],
                        'telefone' => $validated['telefone'],
                        'password' => $validated['password'],
                        'tipo_usuario' => 'cliente',
                        'ativo' => true,
                    ]);

                    Cliente::withoutGlobalScopes()->create([
                        'pessoa_id' => $pessoa->id,
                        'empresa_id' => $empresaPadraoId,
                        'user_id' => $novoUsuario->id,
                        'nome' => $validated['name'],
                        'email' => $validated['email'],
                        'telefone' => $validated['telefone'],
                        'cpf_cnpj' => $validated['cpf_cnpj'] ?? null,
                    ]);
                }

                return $novoUsuario;
            });
        } catch (QueryException $e) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['email' => 'Não foi possível concluir o cadastro com os dados informados. Verifique se o e-mail ou CPF/CNPJ já está em uso.']);
        }

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
        if (is_scalar($request->input('email'))) {
            $request->merge([
                'email' => mb_strtolower(trim((string) $request->input('email'))),
            ]);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:100',
        ], [
            'email.required' => 'Informe o e-mail cadastrado.',
            'email.email' => 'Informe um endereço de e-mail válido.',
        ]);

        $mensagemGenerica = 'Se houver uma conta ativa cadastrada com este e-mail, enviaremos as instruções de redefinição de senha! Verifique sua caixa de entrada e a pasta de spam.';

        $user = User::whereRaw('LOWER(email) = ?', [$validated['email']])
            ->where('ativo', true)
            ->first();

        if (! $user) {
            return back()->with('success', $mensagemGenerica);
        }

        $broker = Password::broker();
        if ($broker->getRepository()->recentlyCreatedToken($user)) {
            return back()->with('success', $mensagemGenerica);
        }

        $token = $broker->createToken($user);

        try {
            $user->sendPasswordResetNotification($token);
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar e-mail de redefinição de senha.', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            Password::deleteToken($user);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Não foi possível enviar o e-mail de redefinição no momento. Tente novamente em instantes.',
                ]);
        }

        return back()->with('success', $mensagemGenerica);
    }

    public function showResetPasswordForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => trim($this->inputString($request, 'email')),
        ]);
    }

    public function resetPassword(Request $request)
    {
        if (is_scalar($request->input('email'))) {
            $request->merge([
                'email' => mb_strtolower(trim((string) $request->input('email'))),
            ]);
        }

        $validated = $request->validate([
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

        $usuarioAtivo = User::whereRaw('LOWER(email) = ?', [$validated['email']])
            ->where('ativo', true)
            ->first();

        if (! $usuarioAtivo) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Este link de redefinição de senha é inválido ou a conta está desativada.',
                ]);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // Encerra sessões ativas anteriores após redefinição de senha
                DB::table('sessions')->where('user_id', $user->id)->delete();

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
