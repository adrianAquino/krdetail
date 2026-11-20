<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pessoa;
use App\Models\User;
use App\Rules\CpfCnpj;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    private const UFS_BRASIL = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO',
        'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI',
        'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    public function index(Request $request)
    {
        $query = Cliente::with('user')->withCount('veiculos');

        $busca = $request->string('busca')->trim()->toString();
        if ($busca !== '') {
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('telefone', 'like', "%{$busca}%")
                    ->orWhere('email', 'like', "%{$busca}%")
                    ->orWhere('cpf_cnpj', 'like', "%{$busca}%");
            });
        }

        $acesso = $request->string('acesso')->toString();
        if (in_array($acesso, ['ativo', 'com'], true)) {
            $query->whereNotNull('user_id')
                ->whereHas('user', fn ($q) => $q->where('ativo', true));
        } elseif (in_array($acesso, ['inativo', 'sem_acesso', 'sem'], true)) {
            $query->where(function ($q) {
                $q->whereNull('user_id')
                    ->orWhereHas('user', fn ($uq) => $uq->where('ativo', false));
            });
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

        $emailRaw = $request->string('email')->trim()->lower()->toString();
        $estadoRaw = $request->string('estado')->trim()->upper()->toString();
        $request->merge([
            'email' => $emailRaw !== '' ? $emailRaw : null,
            'cpf_cnpj' => CpfCnpj::normalizar($request->input('cpf_cnpj')),
            'estado' => $estadoRaw !== '' ? $estadoRaw : null,
        ]);

        $rules = [
            'nome' => 'required|string|min:3|max:100',
            'email' => $criarUsuario
                ? 'required|email|max:100|unique:users,email'
                : 'nullable|email|max:100',
            'telefone' => 'required|string|min:10|max:15',
            'cpf_cnpj' => [
                'nullable',
                'bail',
                'string',
                'max:18',
                new CpfCnpj,
                Rule::unique('clientes', 'cpf_cnpj'),
                Rule::unique('pessoas', 'cpf_cnpj'),
            ],
            'cep' => 'nullable|string|max:9',
            'logradouro' => 'nullable|string|max:100',
            'numero' => 'nullable|string|max:10',
            'complemento' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:100',
            'estado' => ['nullable', 'string', 'size:2', Rule::in(self::UFS_BRASIL)],
        ];

        if ($criarUsuario) {
            $rules['password'] = 'required|string|min:8|max:64|confirmed';
        }

        $validated = $request->validate($rules);

        /** @var User|null $usuarioAtual */
        $usuarioAtual = Auth::user();
        $empresaAtivaId = $usuarioAtual?->getEmpresaAtivaId();

        try {
            $cliente = DB::transaction(function () use ($validated, $criarUsuario, $empresaAtivaId) {
                $dadosCliente = collect($validated)->except(['password', 'password_confirmation'])->toArray();
                if ($empresaAtivaId) {
                    $dadosCliente['empresa_id'] = $empresaAtivaId;
                }

                // Seção 9.3 (c): Cria o registro único na tabela `pessoas` compartilhado entre `clientes` e `users`
                $pessoa = Pessoa::create([
                    'nome' => $validated['nome'],
                    'cpf_cnpj' => $validated['cpf_cnpj'] ?? null,
                    'telefone' => $validated['telefone'],
                    'email' => $validated['email'] ?? null,
                    'cep' => $validated['cep'] ?? null,
                    'logradouro' => $validated['logradouro'] ?? null,
                    'numero' => $validated['numero'] ?? null,
                    'complemento' => $validated['complemento'] ?? null,
                    'bairro' => $validated['bairro'] ?? null,
                    'cidade' => $validated['cidade'] ?? null,
                    'estado' => $validated['estado'] ?? null,
                ]);

                $dadosCliente['pessoa_id'] = $pessoa->id;

                if ($criarUsuario) {
                    $user = User::create([
                        'pessoa_id' => $pessoa->id,
                        'empresa_id' => $empresaAtivaId,
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
        } catch (QueryException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Não foi possível cadastrar o cliente. E-mail ou CPF/CNPJ já está em uso.',
                ], 422);
            }

            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', 'Não foi possível cadastrar o cliente. Verifique se o e-mail ou CPF/CNPJ já existe.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Cliente cadastrado com sucesso!',
                'cliente' => [
                    'id' => $cliente->id,
                    'nome' => $cliente->nome,
                    'cpf_cnpj' => $cliente->cpf_cnpj,
                    'telefone' => $cliente->telefone,
                ],
            ], 201);
        }

        if ($request->input('redirect_to') === 'veiculos.create') {
            return redirect()
                ->route('veiculos.create', ['cliente_id' => $cliente->id])
                ->with('success', 'Cliente cadastrado e selecionado com sucesso!');
        }

        return redirect()->route('clientes.show', $cliente)->with('success', 'Cliente cadastrado com sucesso!');
    }

    public function show(Cliente $cliente)
    {
        $cliente->load([
            'user',
            'veiculos',
            'agendamentos' => function ($q) {
                $q->with(['veiculo', 'servicos', 'ordemServico'])->orderBy('data_inicio', 'desc');
            },
        ]);

        return view('clientes.show', compact('cliente'));
    }

    public function edit(Cliente $cliente)
    {
        $cliente->load('user');

        return view('clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $criarUsuario = $request->boolean('criar_usuario') && empty($cliente->user_id);

        $emailRaw = $request->string('email')->trim()->lower()->toString();
        $estadoRaw = $request->string('estado')->trim()->upper()->toString();
        $request->merge([
            'email' => $emailRaw !== '' ? $emailRaw : null,
            'cpf_cnpj' => CpfCnpj::normalizar($request->input('cpf_cnpj')),
            'estado' => $estadoRaw !== '' ? $estadoRaw : null,
        ]);

        $emailRules = ['nullable', 'email', 'max:100'];
        if ($criarUsuario) {
            $emailRules = ['required', 'email', 'max:100', Rule::unique('users', 'email')];
        } elseif ($cliente->user_id) {
            $emailRules = ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($cliente->user_id)];
        }

        $rules = [
            'nome' => 'required|string|min:3|max:100',
            'email' => $emailRules,
            'telefone' => 'required|string|min:10|max:15',
            'cpf_cnpj' => [
                'nullable',
                'bail',
                'string',
                'max:18',
                new CpfCnpj,
                Rule::unique('clientes', 'cpf_cnpj')->ignore($cliente->id),
                Rule::unique('pessoas', 'cpf_cnpj')->ignore($cliente->pessoa_id),
            ],
            'cep' => 'nullable|string|max:9',
            'logradouro' => 'nullable|string|max:100',
            'numero' => 'nullable|string|max:10',
            'complemento' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:100',
            'estado' => ['nullable', 'string', 'size:2', Rule::in(self::UFS_BRASIL)],
        ];

        if ($criarUsuario) {
            $rules['password'] = 'required|string|min:8|max:64|confirmed';
        }

        $validated = $request->validate($rules);

        try {
            DB::transaction(function () use ($cliente, $validated, $criarUsuario) {
                $dadosCliente = collect($validated)->except(['password', 'password_confirmation'])->toArray();

                $dadosPessoa = [
                    'nome' => $validated['nome'],
                    'cpf_cnpj' => $validated['cpf_cnpj'] ?? null,
                    'telefone' => $validated['telefone'],
                    'email' => $validated['email'] ?? ($cliente->user?->email),
                    'cep' => $validated['cep'] ?? null,
                    'logradouro' => $validated['logradouro'] ?? null,
                    'numero' => $validated['numero'] ?? null,
                    'complemento' => $validated['complemento'] ?? null,
                    'bairro' => $validated['bairro'] ?? null,
                    'cidade' => $validated['cidade'] ?? null,
                    'estado' => $validated['estado'] ?? null,
                ];

                $pessoa = $cliente->pessoa_id
                    ? Pessoa::withTrashed()->find($cliente->pessoa_id)
                    : null;

                if ($pessoa) {
                    if ($pessoa->trashed()) {
                        $pessoa->restore();
                    }
                    Pessoa::withoutEvents(fn () => $pessoa->update($dadosPessoa));
                } else {
                    $pessoa = Pessoa::withoutEvents(fn () => Pessoa::create($dadosPessoa));
                    $dadosCliente['pessoa_id'] = $pessoa->id;
                }

                if ($criarUsuario) {
                    if (User::where('pessoa_id', $pessoa->id)->exists()) {
                        throw new \DomainException('Já existe uma conta de usuário vinculada a esta pessoa.');
                    }

                    // Preserva obrigatoriamente a empresa original do cliente!
                    $user = User::create([
                        'pessoa_id' => $pessoa->id,
                        'empresa_id' => $cliente->empresa_id,
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
                        'pessoa_id' => $pessoa->id,
                        'name' => $validated['nome'],
                        'email' => $validated['email'],
                        'telefone' => $validated['telefone'],
                    ]);
                }

                $cliente->update($dadosCliente);
            });
        } catch (\DomainException $e) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', $e->getMessage());
        } catch (QueryException $e) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', 'Não foi possível atualizar os dados do cliente devido a conflito de e-mail ou CPF/CNPJ.');
        }

        return redirect()->route('clientes.show', $cliente)->with('success', 'Dados do cliente atualizados com sucesso!');
    }

    public function destroy(Cliente $cliente)
    {
        $possuiVinculos = $cliente->veiculos()->withoutGlobalScopes()->exists()
            || $cliente->agendamentos()->withoutGlobalScopes()->exists()
            || $cliente->notificacoes()->exists();

        if ($possuiVinculos) {
            return redirect()
                ->route('clientes.index')
                ->with('error', 'Não é possível excluir um cliente que possui veículos, agendamentos ou notificações vinculadas.');
        }

        try {
            DB::transaction(function () use ($cliente) {
                $userId = $cliente->user_id;
                $pessoaId = $cliente->pessoa_id;

                $cliente->delete();

                // Desativa o usuário de portal vinculado (para não ressuscitar o cliente no próximo login)
                if ($userId) {
                    $user = User::find($userId);
                    if ($user && $user->isCliente()) {
                        DB::table('sessions')->where('user_id', $user->id)->delete();
                        $user->update([
                            'ativo' => false,
                            'remember_token' => Str::random(60),
                        ]);
                    }
                }

                // Se a Pessoa não possuir nenhum outro usuário ativo ou cliente, realiza soft delete (liberando CPF/CNPJ)
                if ($pessoaId) {
                    $aindaEmUso = Cliente::withoutGlobalScopes()->where('pessoa_id', $pessoaId)->exists()
                        || User::where('pessoa_id', $pessoaId)->where('ativo', true)->exists();

                    if (! $aindaEmUso) {
                        Pessoa::where('id', $pessoaId)->delete();
                    }
                }
            });
        } catch (QueryException $e) {
            return redirect()
                ->route('clientes.index')
                ->with('error', 'Não foi possível excluir este cliente pois há registros dependentes vinculados.');
        }

        return redirect()->route('clientes.index')->with('success', 'Cliente removido com sucesso!');
    }
}
