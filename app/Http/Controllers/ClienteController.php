<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\ClienteEmpresaHistorico;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\User;
use App\Rules\CpfCnpj;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
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
        $filtroAdmin = $this->resolverFiltroEmpresaAdmin($request);
        $query = Cliente::with(['user', 'empresa'])->withCount('veiculos');

        $busca = trim($this->inputString($request, 'busca'));
        if ($busca !== '') {
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('telefone', 'like', "%{$busca}%")
                    ->orWhere('email', 'like', "%{$busca}%")
                    ->orWhere('cpf_cnpj', 'like', "%{$busca}%");
            });
        }

        $acesso = $this->inputString($request, 'acesso');
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

        return view('clientes.index', array_merge(compact('clientes'), $filtroAdmin));
    }

    public function create(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isAdmin = $user instanceof User && $user->isAdministrador();
        $empresas = $isAdmin ? Empresa::orderBy('nome_fantasia')->get() : collect();
        $empresaSelecionadaId = $isAdmin ? ($request->integer('empresa_id') ?: null) : null;

        return view('clientes.create', compact('isAdmin', 'empresas', 'empresaSelecionadaId'));
    }

    /**
     * Busca uma conta de usuário cliente existente por correspondência exata de e-mail ou CPF/CNPJ
     * para autopreenchimento no cadastro de clientes (sem listar clientes de outras empresas).
     */
    public function buscarUsuarioExistente(Request $request): JsonResponse
    {
        /** @var User|null $usuarioAtual */
        $usuarioAtual = Auth::user();
        $isAdmin = $usuarioAtual instanceof User && $usuarioAtual->isAdministrador();
        $empresaAlvoId = $isAdmin
            ? ($request->integer('empresa_id') ?: null)
            : $usuarioAtual?->getEmpresaAtivaId();

        if (! $isAdmin && ! $empresaAlvoId) {
            return response()->json([
                'found' => false,
                'message' => 'Seu usuário não está vinculado a uma estética ativa.',
            ], 403);
        }

        $termoRaw = $request->query('termo');
        if (! is_scalar($termoRaw) || trim((string) $termoRaw) === '') {
            return response()->json([
                'found' => false,
                'message' => 'Informe um e-mail ou CPF/CNPJ completo para buscar.',
            ], 422);
        }

        $termoTrim = trim((string) $termoRaw);
        $emailNormalizado = mb_strtolower($termoTrim);
        $isEmail = filter_var($emailNormalizado, FILTER_VALIDATE_EMAIL) !== false;
        $cpfNormalizado = CpfCnpj::normalizar($termoTrim);
        $cpfLimpo = preg_replace('/[^0-9A-Za-z]/', '', strtoupper($termoTrim)) ?? '';
        $isDocumentoCompleto = ! $isEmail && ((strlen($cpfLimpo) === 11 && ctype_digit($cpfLimpo)) || strlen($cpfLimpo) === 14);

        if (! $isEmail && ! $isDocumentoCompleto) {
            return response()->json([
                'found' => false,
                'message' => 'Informe um e-mail válido ou um CPF (11 dígitos) / CNPJ (14 caracteres) completo.',
            ], 422);
        }

        $usuario = User::with('pessoa')
            ->where('tipo_usuario', 'cliente')
            ->when($isEmail, function ($q) use ($emailNormalizado) {
                $q->whereRaw('LOWER(email) = ?', [$emailNormalizado]);
            }, function ($q) use ($cpfNormalizado, $cpfLimpo) {
                $q->where(function ($sub) use ($cpfNormalizado, $cpfLimpo) {
                    $sub->whereHas('pessoa', function ($pq) use ($cpfNormalizado, $cpfLimpo) {
                        $pq->where('cpf_cnpj', $cpfNormalizado);
                        if ($cpfLimpo !== '' && $cpfLimpo !== $cpfNormalizado) {
                            $pq->orWhere('cpf_cnpj', $cpfLimpo);
                        }
                    })->orWhereHas('cliente', function ($cq) use ($cpfNormalizado, $cpfLimpo) {
                        $cq->withoutGlobalScopes()->where(function ($docQ) use ($cpfNormalizado, $cpfLimpo) {
                            $docQ->where('cpf_cnpj', $cpfNormalizado);
                            if ($cpfLimpo !== '' && $cpfLimpo !== $cpfNormalizado) {
                                $docQ->orWhere('cpf_cnpj', $cpfLimpo);
                            }
                        });
                    });
                });
            })
            ->first();

        if (! $usuario) {
            return response()->json([
                'found' => false,
                'status' => 'nao_encontrado',
                'message' => 'Nenhuma conta de usuário cliente foi encontrada com esse e-mail ou CPF/CNPJ. Prossiga com o cadastro normal.',
            ]);
        }

        $clienteExistente = Cliente::withoutGlobalScopes()
            ->with('pessoa')
            ->where('user_id', $usuario->id)
            ->first();

        $pessoa = $clienteExistente?->pessoa ?? $usuario->pessoa;

        $dados = [
            'nome' => $clienteExistente?->nome ?: ($pessoa?->nome ?: $usuario->name),
            'cpf_cnpj' => $clienteExistente?->cpf_cnpj ?: ($pessoa?->cpf_cnpj ?? ''),
            'telefone' => $clienteExistente?->telefone ?: ($pessoa?->telefone ?: ($usuario->telefone ?? '')),
            'email' => $clienteExistente?->email ?: ($pessoa?->email ?: $usuario->email),
            'cep' => $clienteExistente?->cep ?: ($pessoa?->cep ?? ''),
            'logradouro' => $clienteExistente?->logradouro ?: ($pessoa?->logradouro ?? ''),
            'numero' => $clienteExistente?->numero ?: ($pessoa?->numero ?? ''),
            'complemento' => $clienteExistente?->complemento ?: ($pessoa?->complemento ?? ''),
            'bairro' => $clienteExistente?->bairro ?: ($pessoa?->bairro ?? ''),
            'cidade' => $clienteExistente?->cidade ?: ($pessoa?->cidade ?? ''),
            'estado' => $clienteExistente?->estado ?: ($pessoa?->estado ?? ''),
        ];

        $empresaDoClienteId = $clienteExistente?->empresa_id ?? $usuario->empresa_id;

        if ($empresaAlvoId && $empresaDoClienteId && (int) $empresaDoClienteId === (int) $empresaAlvoId) {
            return response()->json([
                'found' => true,
                'status' => 'ja_vinculado_esta_empresa',
                'message' => 'Este cliente já está cadastrado e vinculado à sua estética.',
                'cliente_id' => $clienteExistente?->id,
                'cliente_url' => $clienteExistente ? route('clientes.show', $clienteExistente->id) : null,
                'dados' => $dados,
            ]);
        }

        if ($empresaDoClienteId !== null) {
            return response()->json([
                'found' => true,
                'status' => 'vinculado_outra_empresa',
                'message' => 'Conta de usuário encontrada! Este cliente atualmente possui vínculo em outra estética. Deseja incluir os dados dele no formulário? Ao salvar, o vínculo ativo passará para a sua estética (preservando o histórico anterior, sem precisar criar senha).',
                'dados' => $dados,
            ]);
        }

        return response()->json([
            'found' => true,
            'status' => 'disponivel',
            'message' => 'Conta de usuário encontrada! Deseja incluir os dados deste usuário no formulário de cadastro? Ao salvar, a conta será vinculada à sua estética (não é necessário criar senha).',
            'dados' => $dados,
        ]);
    }

    public function store(Request $request)
    {
        $criarUsuario = $request->boolean('criar_usuario');

        $mergeCli = [
            'cpf_cnpj' => CpfCnpj::normalizar($request->input('cpf_cnpj')),
        ];
        if (is_scalar($request->input('nome'))) {
            $mergeCli['nome'] = preg_replace('/\s+/', ' ', trim((string) $request->input('nome'))) ?? '';
        }
        if (is_scalar($request->input('email'))) {
            $emailRaw = mb_strtolower(trim((string) $request->input('email')));
            $mergeCli['email'] = $emailRaw !== '' ? $emailRaw : null;
        }
        if (is_scalar($request->input('estado'))) {
            $estadoRaw = mb_strtoupper(trim((string) $request->input('estado')));
            $mergeCli['estado'] = $estadoRaw !== '' ? $estadoRaw : null;
        }
        $request->merge($mergeCli);

        /** @var User|null $usuarioAtual */
        $usuarioAtual = Auth::user();
        $isAdmin = $usuarioAtual instanceof User && $usuarioAtual->isAdministrador();
        $empresaAtivaId = $isAdmin
            ? ($request->integer('empresa_id') ?: null)
            : $usuarioAtual?->getEmpresaAtivaId();

        $emailInput = $request->input('email');
        $cpfInput = $request->input('cpf_cnpj');
        $cpfDigitos = is_string($cpfInput) && $cpfInput !== ''
            ? (preg_replace('/[^0-9A-Za-z]/', '', strtoupper($cpfInput)) ?? '')
            : '';

        // Localiza usuário ou cadastro de cliente já existente cujo e-mail ou CPF/CNPJ coincida
        $usuarioClienteMatch = null;
        if ($emailInput !== null || $cpfInput !== null) {
            $usuarioClienteMatch = User::where('tipo_usuario', 'cliente')
                ->where(function ($q) use ($emailInput, $cpfInput, $cpfDigitos) {
                    if ($emailInput !== null) {
                        $q->whereRaw('LOWER(email) = ?', [$emailInput]);
                    }
                    if ($cpfInput !== null) {
                        $q->orWhereHas('pessoa', function ($pq) use ($cpfInput, $cpfDigitos) {
                            $pq->where('cpf_cnpj', $cpfInput);
                            if ($cpfDigitos !== '' && $cpfDigitos !== $cpfInput) {
                                $pq->orWhere('cpf_cnpj', $cpfDigitos);
                            }
                        });
                    }
                })
                ->first();
        }

        $clienteMatch = null;
        if ($usuarioClienteMatch) {
            $clienteMatch = Cliente::withoutGlobalScopes()
                ->where('user_id', $usuarioClienteMatch->id)
                ->first();
        }

        if (! $clienteMatch && ($emailInput !== null || $cpfInput !== null)) {
            $clienteMatch = Cliente::withoutGlobalScopes()
                ->where(function ($q) use ($empresaAtivaId) {
                    $q->whereNull('empresa_id');
                    if ($empresaAtivaId) {
                        $q->orWhere('empresa_id', '!=', $empresaAtivaId);
                    }
                })
                ->where(function ($q) use ($emailInput, $cpfInput, $cpfDigitos) {
                    if ($emailInput !== null) {
                        $q->whereRaw('LOWER(email) = ?', [$emailInput]);
                    }
                    if ($cpfInput !== null) {
                        $q->orWhere('cpf_cnpj', $cpfInput);
                        if ($cpfDigitos !== '' && $cpfDigitos !== $cpfInput) {
                            $q->orWhere('cpf_cnpj', $cpfDigitos);
                        }
                    }
                })
                ->first();

            if ($clienteMatch && ! $usuarioClienteMatch && $clienteMatch->user_id) {
                $usuarioClienteMatch = User::where('tipo_usuario', 'cliente')
                    ->where('id', $clienteMatch->user_id)
                    ->first();
            }
        }

        $podeVincularMatch = ($usuarioClienteMatch !== null || $clienteMatch !== null)
            && (
                ! $clienteMatch
                || $clienteMatch->empresa_id === null
                || ($empresaAtivaId && (int) $clienteMatch->empresa_id !== (int) $empresaAtivaId)
            );

        $ignoreClienteId = $podeVincularMatch ? $clienteMatch?->id : null;
        $ignorePessoaId = $podeVincularMatch ? ($clienteMatch?->pessoa_id ?? $usuarioClienteMatch?->pessoa_id) : null;
        $ignoreUserId = $podeVincularMatch ? $usuarioClienteMatch?->id : null;

        $emailRule = $criarUsuario
            ? ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($ignoreUserId)]
            : ($podeVincularMatch && $usuarioClienteMatch
                ? ['nullable', 'email', 'max:100', Rule::unique('users', 'email')->ignore($ignoreUserId)]
                : ['nullable', 'email', 'max:100']);

        if (! $podeVincularMatch && $empresaAtivaId) {
            $emailRule[] = $this->regraEmailUnicoClientePorEmpresa((int) $empresaAtivaId);
        }

        $telefoneRules = ['required', 'string', 'min:10', 'max:15'];
        if (! $podeVincularMatch && $empresaAtivaId) {
            $telefoneRules[] = $this->regraNomeETelefoneUnicosClientePorEmpresa(
                (int) $empresaAtivaId,
                is_scalar($request->input('nome')) ? (string) $request->input('nome') : ''
            );
        }

        $rules = [
            'nome' => 'required|string|min:3|max:100',
            'email' => $emailRule,
            'telefone' => $telefoneRules,
            'cpf_cnpj' => [
                'nullable',
                'bail',
                'string',
                'max:18',
                new CpfCnpj,
                Rule::unique('clientes', 'cpf_cnpj')->ignore($ignoreClienteId),
                Rule::unique('pessoas', 'cpf_cnpj')->ignore($ignorePessoaId),
            ],
            'cep' => 'nullable|string|max:9',
            'logradouro' => 'nullable|string|max:100',
            'numero' => 'nullable|string|max:10',
            'complemento' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:100',
            'estado' => ['nullable', 'string', 'size:2', Rule::in(self::UFS_BRASIL)],
        ];

        if ($criarUsuario && ! ($podeVincularMatch && $usuarioClienteMatch)) {
            $rules['password'] = 'required|string|min:8|max:64|confirmed';
        }

        if ($isAdmin) {
            $rules['empresa_id'] = 'required|integer|exists:empresas,id';
        }

        $validated = $request->validate($rules, [
            'empresa_id.required' => 'Selecione a empresa proprietária deste cliente.',
        ]);

        if ($isAdmin) {
            $empresaAtivaId = (int) $validated['empresa_id'];
        }

        $vinculouContaExistente = false;

        try {
            $cliente = DB::transaction(function () use (
                $validated,
                $criarUsuario,
                $empresaAtivaId,
                $podeVincularMatch,
                $usuarioClienteMatch,
                $clienteMatch,
                &$vinculouContaExistente
            ) {
                $dadosCliente = collect($validated)->except(['password', 'password_confirmation'])->toArray();
                if ($empresaAtivaId) {
                    $dadosCliente['empresa_id'] = $empresaAtivaId;
                }

                if ($podeVincularMatch) {
                    $pessoaIdExistente = $clienteMatch?->pessoa_id ?? $usuarioClienteMatch?->pessoa_id;
                    $pessoa = $pessoaIdExistente ? Pessoa::withTrashed()->find($pessoaIdExistente) : null;

                    $dadosPessoa = [
                        'nome' => $validated['nome'],
                        'cpf_cnpj' => $validated['cpf_cnpj'] ?? $pessoa?->cpf_cnpj,
                        'telefone' => $validated['telefone'],
                        'email' => $validated['email'] ?? $pessoa?->email ?? $usuarioClienteMatch?->email,
                        'cep' => $validated['cep'] ?? $pessoa?->cep,
                        'logradouro' => $validated['logradouro'] ?? $pessoa?->logradouro,
                        'numero' => $validated['numero'] ?? $pessoa?->numero,
                        'complemento' => $validated['complemento'] ?? $pessoa?->complemento,
                        'bairro' => $validated['bairro'] ?? $pessoa?->bairro,
                        'cidade' => $validated['cidade'] ?? $pessoa?->cidade,
                        'estado' => $validated['estado'] ?? $pessoa?->estado,
                    ];

                    if ($pessoa) {
                        if ($pessoa->trashed()) {
                            $pessoa->restore();
                        }
                        Pessoa::withoutEvents(fn () => $pessoa->update($dadosPessoa));
                    } else {
                        $pessoa = Pessoa::withoutEvents(fn () => Pessoa::create($dadosPessoa));
                    }

                    $dadosCliente['pessoa_id'] = $pessoa->id;
                    $dadosCliente['email'] = $dadosPessoa['email'];
                    $dadosCliente['cpf_cnpj'] = $dadosPessoa['cpf_cnpj'];

                    if ($usuarioClienteMatch) {
                        $vinculouContaExistente = true;
                        $dadosCliente['user_id'] = $usuarioClienteMatch->id;
                        $usuarioClienteMatch->update([
                            'pessoa_id' => $pessoa->id,
                            'empresa_id' => $empresaAtivaId,
                            'name' => $validated['nome'],
                            'email' => $dadosPessoa['email'] ?: $usuarioClienteMatch->email,
                            'telefone' => $validated['telefone'],
                            'ativo' => true,
                        ]);
                    } elseif ($criarUsuario) {
                        $novoUser = User::create([
                            'pessoa_id' => $pessoa->id,
                            'empresa_id' => $empresaAtivaId,
                            'name' => $validated['nome'],
                            'email' => $validated['email'],
                            'telefone' => $validated['telefone'],
                            'password' => $validated['password'],
                            'tipo_usuario' => 'cliente',
                            'ativo' => true,
                        ]);
                        $dadosCliente['user_id'] = $novoUser->id;
                    }

                    if ($clienteMatch) {
                        // Preserva a estética antiga nos agendamentos históricos antes de alterar empresa_id
                        if ($clienteMatch->empresa_id) {
                            Agendamento::withoutGlobalScopes()
                                ->where('cliente_id', $clienteMatch->id)
                                ->whereNull('empresa_id')
                                ->update(['empresa_id' => $clienteMatch->empresa_id]);
                        }

                        // Usa withoutEvents quando há mudança de empresa_id pois BelongsToEmpresa::updating bloqueia transferências comuns
                        if ($clienteMatch->empresa_id !== null && (int) $clienteMatch->empresa_id !== (int) $empresaAtivaId) {
                            Cliente::withoutEvents(fn () => $clienteMatch->update($dadosCliente));
                        } else {
                            $clienteMatch->update($dadosCliente);
                        }
                        $clienteResultado = $clienteMatch;
                    } else {
                        $clienteResultado = Cliente::create($dadosCliente);
                    }

                    if ($empresaAtivaId) {
                        ClienteEmpresaHistorico::registrarVinculo(
                            $clienteResultado->user_id ? (int) $clienteResultado->user_id : null,
                            (int) $clienteResultado->id,
                            (int) $empresaAtivaId
                        );
                    }

                    return $clienteResultado;
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

        $mensagemSucesso = $vinculouContaExistente
            ? 'Cliente cadastrado e vinculado automaticamente à conta de usuário existente!'
            : 'Cliente cadastrado com sucesso!';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $mensagemSucesso,
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

        return redirect()->route('clientes.show', $cliente)->with('success', $mensagemSucesso);
    }

    public function show(Cliente $cliente)
    {
        $cliente->load([
            'empresa',
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
        $cliente->load(['empresa', 'user']);

        return view('clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $criarUsuario = $request->boolean('criar_usuario') && empty($cliente->user_id);

        $mergeCli = [
            'cpf_cnpj' => CpfCnpj::normalizar($request->input('cpf_cnpj')),
        ];
        if (is_scalar($request->input('nome'))) {
            $mergeCli['nome'] = preg_replace('/\s+/', ' ', trim((string) $request->input('nome'))) ?? '';
        }
        if (is_scalar($request->input('email'))) {
            $emailRaw = mb_strtolower(trim((string) $request->input('email')));
            $mergeCli['email'] = $emailRaw !== '' ? $emailRaw : null;
        }
        if (is_scalar($request->input('estado'))) {
            $estadoRaw = mb_strtoupper(trim((string) $request->input('estado')));
            $mergeCli['estado'] = $estadoRaw !== '' ? $estadoRaw : null;
        }
        $request->merge($mergeCli);

        $emailRules = ['nullable', 'email', 'max:100'];
        if ($criarUsuario) {
            $emailRules = ['required', 'email', 'max:100', Rule::unique('users', 'email')];
        } elseif ($cliente->user_id) {
            $emailRules = ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($cliente->user_id)];
        }

        if ($cliente->empresa_id !== null) {
            $emailRules[] = $this->regraEmailUnicoClientePorEmpresa((int) $cliente->empresa_id, $cliente->id);
        }

        $telefoneRules = ['required', 'string', 'min:10', 'max:15'];
        if ($cliente->empresa_id !== null) {
            $telefoneRules[] = $this->regraNomeETelefoneUnicosClientePorEmpresa(
                (int) $cliente->empresa_id,
                is_scalar($request->input('nome')) ? (string) $request->input('nome') : '',
                $cliente->id
            );
        }

        $rules = [
            'nome' => 'required|string|min:3|max:100',
            'email' => $emailRules,
            'telefone' => $telefoneRules,
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

        if ($cliente->user_id || $possuiVinculos) {
            DB::transaction(function () use ($cliente) {
                if ($cliente->empresa_id) {
                    Agendamento::withoutGlobalScopes()
                        ->where('cliente_id', $cliente->id)
                        ->whereNull('empresa_id')
                        ->update(['empresa_id' => $cliente->empresa_id]);

                    ClienteEmpresaHistorico::encerrarVinculosAtivos(
                        $cliente->user_id ? (int) $cliente->user_id : null,
                        (int) $cliente->id,
                        (int) $cliente->empresa_id
                    );
                }

                Cliente::withoutEvents(fn () => $cliente->update(['empresa_id' => null]));
                if ($cliente->user_id) {
                    User::withoutEvents(fn () => User::where('id', $cliente->user_id)->update(['empresa_id' => null]));
                }
            });

            return redirect()
                ->route('clientes.index')
                ->with('success', 'Cliente desvinculado da estética com sucesso! A conta de usuário, veículos e histórico de serviços foram preservados.');
        }

        try {
            DB::transaction(function () use ($cliente) {
                $pessoaId = $cliente->pessoa_id;

                $cliente->delete();

                // Se a Pessoa não possuir nenhum outro usuário ativo ou cliente, realiza soft delete (liberando CPF/CNPJ)
                if ($pessoaId) {
                    $aindaEmUso = Cliente::withoutGlobalScopes()->where('pessoa_id', $pessoaId)->exists()
                        || User::where('pessoa_id', $pessoaId)->where('ativo', true)->exists();

                    if (! $aindaEmUso) {
                        Pessoa::find($pessoaId)?->delete();
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

    private function regraEmailUnicoClientePorEmpresa(int $empresaId, ?int $ignorarId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($empresaId, $ignorarId): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }

            $jaExiste = Cliente::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->when($ignorarId !== null, fn ($q) => $q->where('id', '!=', $ignorarId))
                ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($value), 'UTF-8')])
                ->exists();

            if ($jaExiste) {
                $fail('Já existe um cliente cadastrado com este e-mail nesta empresa.');
            }
        };
    }

    private function regraNomeETelefoneUnicosClientePorEmpresa(int $empresaId, string $nomeNormalizado, ?int $ignorarId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($empresaId, $nomeNormalizado, $ignorarId): void {
            if ($nomeNormalizado === '' || ! is_string($value)) {
                return;
            }

            $digitosTelefone = preg_replace('/\D/', '', $value) ?? '';
            if ($digitosTelefone === '') {
                return;
            }

            $candidatos = Cliente::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->when($ignorarId !== null, fn ($q) => $q->where('id', '!=', $ignorarId))
                ->whereRaw('LOWER(nome) = ?', [mb_strtolower($nomeNormalizado, 'UTF-8')])
                ->get(['id', 'telefone']);

            foreach ($candidatos as $cand) {
                $candDigitos = preg_replace('/\D/', '', (string) $cand->telefone) ?? '';
                if ($candDigitos !== '' && $candDigitos === $digitosTelefone) {
                    $fail('Já existe um cliente cadastrado com este nome e telefone nesta empresa.');
                    return;
                }
            }
        };
    }
}
