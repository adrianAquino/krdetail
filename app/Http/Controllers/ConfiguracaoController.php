<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\ClienteEmpresaHistorico;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\LancamentoFinanceiro;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use App\Rules\CpfCnpj;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ConfiguracaoController extends Controller
{
    private const UFS_BRASIL = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO',
        'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI',
        'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    public function index(Request $request)
    {
        /** @var User $usuarioLogado */
        $usuarioLogado = Auth::user();
        $usuarioLogado->load(['pessoa', 'empresa', 'cliente']);

        // Se for Cliente: carrega apenas os dados do próprio perfil, estética atual e histórico de vínculos
        if ($usuarioLogado->isCliente()) {
            $cliente = $usuarioLogado->ensureClienteProfile();
            $cliente->load(['pessoa', 'empresa']);
            $empresaAtual = $cliente->empresa;

            $historicoEmpresas = ClienteEmpresaHistorico::with('empresa')
                ->where(function ($q) use ($usuarioLogado, $cliente) {
                    $q->where('user_id', $usuarioLogado->id)
                        ->orWhere('cliente_id', $cliente->id);
                })
                ->orderByDesc('vinculado_em')
                ->orderByDesc('id')
                ->get();

            return view('configuracoes.index', [
                'isCliente' => true,
                'clientePerfil' => $cliente,
                'empresaAtual' => $empresaAtual,
                'historicoEmpresas' => $historicoEmpresas,
                'empresas' => collect($empresaAtual ? [$empresaAtual] : []),
                'empresasFiltro' => collect(),
                'empresaFiltroEquipeId' => null,
                'usuarios' => collect(),
                'donosDisponiveis' => collect(),
            ]);
        }

        // Equipe interna: filtra empresas conforme o papel
        $empresaCounts = [
            'clientes' => fn ($q) => $q->withoutGlobalScopes(),
            'funcionarios' => fn ($q) => $q->withoutGlobalScopes(),
            'servicos' => fn ($q) => $q->withoutGlobalScopes(),
            'produtos' => fn ($q) => $q->withoutGlobalScopes(),
            'usuarios',
        ];

        $empresaAtivaId = $usuarioLogado->getEmpresaAtivaId();

        if ($usuarioLogado->isAdministrador()) {
            $empresas = Empresa::with('dono')
                ->withCount($empresaCounts)
                ->orderBy('nome_fantasia', 'asc')
                ->get();
        } elseif ($usuarioLogado->isDono()) {
            $empresas = Empresa::with('dono')
                ->withCount($empresaCounts)
                ->where(function ($q) use ($usuarioLogado) {
                    if ($usuarioLogado->empresa_id) {
                        $q->where('id', $usuarioLogado->empresa_id)
                            ->orWhere('dono_id', $usuarioLogado->id);
                    } else {
                        $q->where('dono_id', $usuarioLogado->id);
                    }
                })
                ->orderBy('nome_fantasia', 'asc')
                ->get();
        } elseif ($empresaAtivaId) {
            // Funcionário visualiza apenas a empresa ativa à qual pertence
            $empresas = Empresa::with('dono')
                ->withCount($empresaCounts)
                ->where('id', $empresaAtivaId)
                ->get();
        } else {
            $empresas = collect();
        }

        $empresaSelecionadaId = $request->query('empresa_id');
        $empresaAtual = null;
        $empresaFiltroEquipeId = null;

        if ($empresaSelecionadaId && is_scalar($empresaSelecionadaId)) {
            $empresaAtual = $empresas->firstWhere('id', (int) $empresaSelecionadaId);
            if ($usuarioLogado->isAdministrador() && $empresaAtual) {
                $empresaFiltroEquipeId = (int) $empresaAtual->id;
            }
        }

        if (! $empresaAtual && $empresaAtivaId) {
            $empresaAtual = $empresas->firstWhere('id', $empresaAtivaId);
        }

        if (! $empresaAtual) {
            $empresaAtual = $empresas->first();
        }

        $queryUsuarios = User::with(['empresa', 'funcionario'])->orderBy('name', 'asc');

        if ($usuarioLogado->isAdministrador()) {
            if ($empresaFiltroEquipeId !== null) {
                $queryUsuarios->where('empresa_id', $empresaFiltroEquipeId);
            }
        } elseif ($empresaAtivaId) {
            $queryUsuarios->where(function ($q) use ($empresaAtivaId, $usuarioLogado) {
                $q->where('empresa_id', $empresaAtivaId)
                    ->orWhere('id', $usuarioLogado->id);
            });
        } else {
            $queryUsuarios->where('id', $usuarioLogado->id);
        }

        $usuarios = $queryUsuarios->get()->map(function (User $u) {
            return [
                'id' => $u->id,
                'nome' => $u->name,
                'email' => $u->email,
                'telefone' => $u->telefone ?? '-',
                'tipo' => $u->tipo_usuario ?? 'funcionario',
                'tipo_label' => $u->role_label,
                'empresa_id' => $u->empresa_id,
                'empresa_nome' => $u->empresa?->nome_fantasia ?? ($u->isAdministrador() ? 'Global (Sistema)' : 'Sem empresa vinculada'),
                'cargo' => $u->funcionario?->cargo ?? null,
                'ativo' => (bool) $u->ativo,
            ];
        });

        $donosDisponiveis = $usuarioLogado->isAdministrador()
            ? User::whereIn('tipo_usuario', ['dono', 'administrador', 'funcionario'])
                ->where('ativo', true)
                ->orderBy('name')
                ->get()
            : collect([$usuarioLogado]);

        return view('configuracoes.index', [
            'isCliente' => false,
            'clientePerfil' => null,
            'empresaAtual' => $empresaAtual,
            'historicoEmpresas' => collect(),
            'empresas' => $empresas,
            'empresasFiltro' => $usuarioLogado->isAdministrador() ? $empresas : collect(),
            'empresaFiltroEquipeId' => $empresaFiltroEquipeId,
            'usuarios' => $usuarios,
            'donosDisponiveis' => $donosDisponiveis,
        ]);
    }

    /**
     * Atualiza o próprio perfil do usuário autenticado (Cliente, Funcionário, Dono ou Administrador).
     */
    public function updatePerfil(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $cliente = $user->isCliente()
            ? $user->ensureClienteProfile()
            : Cliente::withoutGlobalScopes()->where('user_id', $user->id)->first();

        $mergePerfil = [
            'cpf_cnpj' => CpfCnpj::normalizar($request->input('cpf_cnpj')),
        ];
        if (is_scalar($request->input('email'))) {
            $mergePerfil['email'] = mb_strtolower(trim((string) $request->input('email')));
        }
        if (is_scalar($request->input('estado'))) {
            $estadoRaw = mb_strtoupper(trim((string) $request->input('estado')));
            $mergePerfil['estado'] = $estadoRaw !== '' ? $estadoRaw : null;
        }
        $request->merge($mergePerfil);

        $rules = [
            'name' => 'required|string|min:3|max:100',
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'telefone' => $cliente ? 'required|string|min:10|max:15' : 'nullable|string|max:15',
            'cpf_cnpj' => [
                'nullable',
                'bail',
                'string',
                'max:18',
                new CpfCnpj,
                Rule::unique('pessoas', 'cpf_cnpj')->ignore($user->pessoa_id),
            ],
            'cep' => 'nullable|string|max:9',
            'logradouro' => 'nullable|string|max:100',
            'numero' => 'nullable|string|max:10',
            'complemento' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:100',
            'estado' => ['nullable', 'string', 'size:2', Rule::in(self::UFS_BRASIL)],
            'password' => 'nullable|string|min:8|max:64|confirmed',
        ];

        if ($request->filled('password')) {
            $rules['current_password'] = 'required|current_password';
        }

        if ($cliente) {
            $rules['cpf_cnpj'][] = Rule::unique('clientes', 'cpf_cnpj')->ignore($cliente->id);
        }

        $validated = $request->validate($rules, [
            'email.unique' => 'Este e-mail já está em uso por outra conta.',
            'cpf_cnpj.unique' => 'Este CPF/CNPJ já está vinculado a outro cadastro.',
            'estado.in' => 'Informe uma sigla de UF válida (ex: SP, RJ).',
            'current_password.required' => 'Informe sua senha atual para definir uma nova senha.',
            'current_password.current_password' => 'A senha atual informada não confere.',
            'password.min' => 'A nova senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da nova senha não confere.',
        ]);

        try {
            DB::transaction(function () use ($request, $user, $cliente, $validated) {
                $dadosUser = [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'telefone' => $validated['telefone'] ?? null,
                ];

                $alterouSenha = ! empty($validated['password']);
                if ($alterouSenha) {
                    $dadosUser['password'] = $validated['password'];
                }

                $user->update($dadosUser);

                if ($alterouSenha) {
                    $user->forceFill(['remember_token' => Str::random(60)])->save();
                }

                if ($user->pessoa_id) {
                    $pessoa = Pessoa::withTrashed()->find($user->pessoa_id);
                    if ($pessoa) {
                        if ($pessoa->trashed()) {
                            $pessoa->restore();
                        }

                        Pessoa::withoutEvents(function () use ($pessoa, $validated) {
                            $pessoa->update([
                                'nome' => $validated['name'],
                                'email' => $validated['email'],
                                'telefone' => $validated['telefone'] ?? null,
                                'cpf_cnpj' => $validated['cpf_cnpj'] ?? null,
                                'cep' => $validated['cep'] ?? null,
                                'logradouro' => $validated['logradouro'] ?? null,
                                'numero' => $validated['numero'] ?? null,
                                'complemento' => $validated['complemento'] ?? null,
                                'bairro' => $validated['bairro'] ?? null,
                                'cidade' => $validated['cidade'] ?? null,
                                'estado' => $validated['estado'] ?? null,
                            ]);
                        });
                    }
                }

                if ($cliente) {
                    $cliente->update([
                        'nome' => $validated['name'],
                        'email' => $validated['email'],
                        'telefone' => $validated['telefone'] ?? '',
                        'cpf_cnpj' => $validated['cpf_cnpj'] ?? null,
                        'cep' => $validated['cep'] ?? null,
                        'logradouro' => $validated['logradouro'] ?? null,
                        'numero' => $validated['numero'] ?? null,
                        'complemento' => $validated['complemento'] ?? null,
                        'bairro' => $validated['bairro'] ?? null,
                        'cidade' => $validated['cidade'] ?? null,
                        'estado' => $validated['estado'] ?? null,
                    ]);
                }

                if ($alterouSenha && $request->hasSession()) {
                    DB::table('sessions')
                        ->where('user_id', $user->id)
                        ->where('id', '!=', $request->session()->getId())
                        ->delete();
                }
            });
        } catch (QueryException $e) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation', 'current_password']))
                ->with('error', 'Não foi possível salvar os dados do perfil devido a conflito de e-mail ou CPF/CNPJ.');
        }

        return redirect()
            ->route('configuracoes.index', ['tab' => 'perfil'])
            ->with('success', 'Seus dados de perfil foram atualizados com sucesso!');
    }

    /**
     * Cadastra uma nova Empresa.
     * Permitido apenas para Dono de Empresa ou Administrador Geral do Sistema.
     */
    public function storeEmpresa(Request $request)
    {
        /** @var User $usuarioLogado */
        $usuarioLogado = Auth::user();

        if (! $usuarioLogado->isAdministrador() && ! $usuarioLogado->isDono()) {
            return back()->with('error', 'Para cadastrar uma empresa, é necessário estar logado como Dono de Empresa ou Administrador Geral do Sistema.');
        }

        $mergeStoreEmp = [
            'cnpj' => CpfCnpj::normalizar($request->input('cnpj')),
        ];
        if (is_scalar($request->input('email'))) {
            $mergeStoreEmp['email'] = mb_strtolower(trim((string) $request->input('email')));
        }
        if (is_scalar($request->input('uf'))) {
            $ufRaw = mb_strtoupper(trim((string) $request->input('uf')));
            $mergeStoreEmp['uf'] = $ufRaw !== '' ? $ufRaw : null;
        }
        $request->merge($mergeStoreEmp);

        $validated = $request->validate([
            'nome_fantasia' => 'required|string|max:100',
            'razao_social' => 'required|string|max:120',
            'cnpj' => [
                'nullable',
                'bail',
                'string',
                'max:20',
                new CpfCnpj(apenasCnpj: true),
                Rule::unique('empresas', 'cnpj')->whereNull('deleted_at'),
            ],
            'inscricao_estadual' => 'nullable|string|max:20',
            'telefone' => 'nullable|string|max:20',
            'whatsapp' => 'required|string|max:20',
            'email' => 'required|email|max:100',
            'cep' => 'nullable|string|max:10',
            'logradouro' => 'nullable|string|max:100',
            'numero' => 'nullable|string|max:15',
            'complemento' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:100',
            'uf' => ['nullable', 'string', 'size:2', Rule::in(self::UFS_BRASIL)],
            'horario_atendimento' => 'nullable|string|max:100',
            'dono_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where('ativo', true)
                    ->whereIn('tipo_usuario', ['dono', 'funcionario', 'administrador']),
            ],
        ]);

        try {
            $empresa = DB::transaction(function () use ($validated, $usuarioLogado) {
                $donoId = $usuarioLogado->isDono()
                    ? $usuarioLogado->id
                    : ($validated['dono_id'] ?? null);

                $novaEmpresa = Empresa::create([
                    'dono_id' => $donoId,
                    'nome_fantasia' => $validated['nome_fantasia'],
                    'razao_social' => $validated['razao_social'],
                    'cnpj' => $validated['cnpj'] ?? null,
                    'inscricao_estadual' => $validated['inscricao_estadual'] ?? null,
                    'telefone' => $validated['telefone'] ?? null,
                    'whatsapp' => $validated['whatsapp'],
                    'email' => $validated['email'],
                    'cep' => $validated['cep'] ?? null,
                    'logradouro' => $validated['logradouro'] ?? null,
                    'numero' => $validated['numero'] ?? null,
                    'complemento' => $validated['complemento'] ?? null,
                    'bairro' => $validated['bairro'] ?? null,
                    'cidade' => $validated['cidade'] ?? null,
                    'uf' => $validated['uf'] ?? null,
                    'horario_atendimento' => $validated['horario_atendimento'] ?? null,
                    'ativo' => true,
                ]);

                if ($donoId) {
                    $donoUser = User::find($donoId);
                    // Preserva a empresa original caso o Dono já opere outra unidade e nunca vincula empresa fixa a Admin
                    if ($donoUser && ! $donoUser->isAdministrador()) {
                        $dadosDono = ['tipo_usuario' => 'dono'];
                        if (empty($donoUser->empresa_id)) {
                            $dadosDono['empresa_id'] = $novaEmpresa->id;
                        }
                        $donoUser->update($dadosDono);
                    }
                }

                return $novaEmpresa;
            });
        } catch (QueryException $e) {
            return back()
                ->withInput()
                ->with('error', 'Não foi possível cadastrar a empresa. Verifique se o CNPJ informado já está em uso.');
        }

        return redirect()
            ->route('configuracoes.index', ['tab' => 'empresa', 'empresa_id' => $empresa->id])
            ->with('success', "Empresa \"{$empresa->nome_fantasia}\" cadastrada com sucesso!");
    }

    /**
     * Atualiza os dados de uma Empresa existente.
     * Permitido apenas para o Dono da respectiva empresa ou Administrador Geral.
     */
    public function updateEmpresa(Request $request, Empresa $empresa)
    {
        /** @var User $usuarioLogado */
        $usuarioLogado = Auth::user();

        if (! $usuarioLogado->canManageEmpresa($empresa)) {
            return back()->with('error', 'Você não possui permissão para alterar os dados da empresa.');
        }

        $mergeStoreEmp = [
            'cnpj' => CpfCnpj::normalizar($request->input('cnpj')),
        ];
        if (is_scalar($request->input('email'))) {
            $mergeStoreEmp['email'] = mb_strtolower(trim((string) $request->input('email')));
        }
        if (is_scalar($request->input('uf'))) {
            $ufRaw = mb_strtoupper(trim((string) $request->input('uf')));
            $mergeStoreEmp['uf'] = $ufRaw !== '' ? $ufRaw : null;
        }
        $request->merge($mergeStoreEmp);

        $validated = $request->validate([
            'nome_fantasia' => 'required|string|max:100',
            'razao_social' => 'required|string|max:120',
            'cnpj' => [
                'nullable',
                'bail',
                'string',
                'max:20',
                new CpfCnpj(apenasCnpj: true),
                Rule::unique('empresas', 'cnpj')->whereNull('deleted_at')->ignore($empresa->id),
            ],
            'inscricao_estadual' => 'nullable|string|max:20',
            'telefone' => 'nullable|string|max:20',
            'whatsapp' => 'required|string|max:20',
            'email' => 'required|email|max:100',
            'cep' => 'nullable|string|max:10',
            'logradouro' => 'nullable|string|max:100',
            'numero' => 'nullable|string|max:15',
            'complemento' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:100',
            'uf' => ['nullable', 'string', 'size:2', Rule::in(self::UFS_BRASIL)],
            'horario_atendimento' => 'nullable|string|max:100',
            'dono_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where('ativo', true)
                    ->whereIn('tipo_usuario', ['dono', 'funcionario', 'administrador']),
            ],
        ]);

        try {
            DB::transaction(function () use ($empresa, $validated, $usuarioLogado) {
                $dados = [
                    'nome_fantasia' => $validated['nome_fantasia'],
                    'razao_social' => $validated['razao_social'],
                    'cnpj' => $validated['cnpj'] ?? null,
                    'inscricao_estadual' => $validated['inscricao_estadual'] ?? null,
                    'telefone' => $validated['telefone'] ?? null,
                    'whatsapp' => $validated['whatsapp'],
                    'email' => $validated['email'],
                    'cep' => $validated['cep'] ?? null,
                    'logradouro' => $validated['logradouro'] ?? null,
                    'numero' => $validated['numero'] ?? null,
                    'complemento' => $validated['complemento'] ?? null,
                    'bairro' => $validated['bairro'] ?? null,
                    'cidade' => $validated['cidade'] ?? null,
                    'uf' => $validated['uf'] ?? null,
                    'horario_atendimento' => $validated['horario_atendimento'] ?? null,
                ];

                if ($usuarioLogado->isAdministrador() && array_key_exists('dono_id', $validated)) {
                    $dados['dono_id'] = $validated['dono_id'];
                }

                $empresa->update($dados);

                if (! empty($dados['dono_id'])) {
                    $donoUser = User::find($dados['dono_id']);
                    if ($donoUser && ! $donoUser->isAdministrador()) {
                        $dadosDono = ['tipo_usuario' => 'dono'];
                        if (empty($donoUser->empresa_id)) {
                            $dadosDono['empresa_id'] = $empresa->id;
                        }
                        $donoUser->update($dadosDono);
                    }
                }
            });
        } catch (QueryException $e) {
            return back()
                ->withInput()
                ->with('error', 'Não foi possível atualizar os dados da empresa. Verifique o CNPJ informado.');
        }

        return redirect()
            ->route('configuracoes.index', ['tab' => 'empresa', 'empresa_id' => $empresa->id])
            ->with('success', 'Dados da empresa atualizados com sucesso!');
    }

    /**
     * Exclui uma Empresa.
     * Permitido apenas para o Dono da respectiva empresa ou Administrador Geral.
     */
    public function destroyEmpresa(Empresa $empresa)
    {
        /** @var User $usuarioLogado */
        $usuarioLogado = Auth::user();

        if (! $usuarioLogado->canManageEmpresa($empresa)) {
            return redirect()
                ->route('configuracoes.index', ['tab' => 'empresa'])
                ->with('error', 'Você não possui permissão para excluir esta empresa.');
        }

        $vinculos = [];

        if (Cliente::withoutGlobalScopes()->where('empresa_id', $empresa->id)->exists()) {
            $vinculos[] = 'clientes';
        }
        if (
            Funcionario::withoutGlobalScopes()->where('empresa_id', $empresa->id)->exists()
            || User::where('empresa_id', $empresa->id)->exists()
        ) {
            $vinculos[] = 'funcionários/usuários';
        }
        if (Servico::withoutGlobalScopes()->where('empresa_id', $empresa->id)->exists()) {
            $vinculos[] = 'serviços';
        }
        if (Produto::withoutGlobalScopes()->where('empresa_id', $empresa->id)->exists()) {
            $vinculos[] = 'produtos em estoque';
        }
        if (Agendamento::withoutGlobalScopes()->where('empresa_id', $empresa->id)->exists()) {
            $vinculos[] = 'agendamentos';
        }
        if (LancamentoFinanceiro::withoutGlobalScopes()->where('empresa_id', $empresa->id)->exists()) {
            $vinculos[] = 'lançamentos financeiros';
        }

        if (! empty($vinculos)) {
            return redirect()
                ->route('configuracoes.index', ['tab' => 'empresa'])
                ->with('error', 'Não é possível excluir a empresa "' . $empresa->nome_fantasia . '", pois ela possui registros vinculados (' . implode(', ', $vinculos) . '). Remova ou transfira os vínculos antes de excluir.');
        }

        if (Empresa::count() <= 1) {
            return redirect()
                ->route('configuracoes.index', ['tab' => 'empresa'])
                ->with('error', 'Não é possível excluir a única empresa ativa do sistema.');
        }

        try {
            $nome = $empresa->nome_fantasia;
            $empresa->delete();

            return redirect()
                ->route('configuracoes.index', ['tab' => 'empresa'])
                ->with('success', "Empresa \"{$nome}\" excluída com sucesso!");
        } catch (QueryException $e) {
            return redirect()
                ->route('configuracoes.index', ['tab' => 'empresa'])
                ->with('error', 'Não é possível excluir esta empresa pois existem restrições de integridade referencial no banco de dados.');
        }
    }

    /**
     * Alterna a estética ativa operada pelo Dono da Empresa,
     * isolando imediatamente todas as listagens e operações para a unidade escolhida.
     */
    public function selecionarEmpresa(Request $request, Empresa $empresa)
    {
        /** @var User $usuarioLogado */
        $usuarioLogado = Auth::user();

        if ($usuarioLogado->isAdministrador()) {
            return back()->with('info', 'O Administrador Geral pode alternar a empresa diretamente no filtro de cada módulo.');
        }

        if (! $usuarioLogado->isDono()) {
            return back()->with('error', 'Apenas o Dono da Empresa pode alternar a estética ativa.');
        }

        if (! $usuarioLogado->canManageEmpresa($empresa)) {
            return back()->with('error', 'Você não possui permissão para acessar esta estética.');
        }

        DB::transaction(function () use ($usuarioLogado, $empresa) {
            $usuarioLogado->update(['empresa_id' => $empresa->id]);

            if ($usuarioLogado->isDono()) {
                Funcionario::withoutEvents(function () use ($usuarioLogado, $empresa) {
                    Funcionario::withoutGlobalScopes()->updateOrCreate(
                        ['user_id' => $usuarioLogado->id],
                        [
                            'empresa_id' => $empresa->id,
                            'cargo' => 'Proprietário / Especialista',
                            'ativo' => true,
                        ]
                    );
                });
            }
        });

        $mensagem = "Estética ativa alterada para \"{$empresa->nome_fantasia}\". Agora você está visualizando somente os dados desta unidade.";

        if ($request->input('redirect_to') === 'configuracoes') {
            return redirect()
                ->route('configuracoes.index', ['tab' => 'empresa'])
                ->with('success', $mensagem);
        }

        return back()->with('success', $mensagem);
    }

    /**
     * Cadastra um novo usuário no sistema.
     */
    public function storeUsuario(Request $request)
    {
        /** @var User $usuarioLogado */
        $usuarioLogado = Auth::user();

        if (! $usuarioLogado->canManageUsers()) {
            return back()->with('error', 'Você não possui permissão para cadastrar outros usuários.');
        }

        $empresaAtivaId = $usuarioLogado->getEmpresaAtivaId();
        if (! $usuarioLogado->isAdministrador() && ! $empresaAtivaId) {
            return back()->with('error', 'Seu usuário não está vinculado a uma empresa ativa.');
        }

        if (is_scalar($request->input('email'))) {
            $request->merge([
                'email' => mb_strtolower(trim((string) $request->input('email'))),
            ]);
        }

        // Administrador não pode cadastrar outro Administrador; Dono pode cadastrar apenas Funcionário ou Cliente.
        $rolesPermitidos = $usuarioLogado->isAdministrador()
            ? ['dono', 'funcionario', 'cliente']
            : ['funcionario', 'cliente'];

        $validated = $request->validate([
            'name' => 'required|string|min:3|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'telefone' => 'nullable|string|max:15',
            'tipo_usuario' => ['required', Rule::in($rolesPermitidos)],
            'empresa_id' => 'nullable|exists:empresas,id',
            'cargo' => 'nullable|string|max:80',
            'password' => 'required|string|min:8|max:64',
        ], [
            'tipo_usuario.in' => $usuarioLogado->isAdministrador()
                ? 'Não é permitido cadastrar outro Administrador Geral pelo sistema.'
                : 'Um dono de estética só pode cadastrar Funcionários ou Clientes.',
        ]);

        try {
            DB::transaction(function () use ($validated, $usuarioLogado, $empresaAtivaId) {
                $empresaId = $usuarioLogado->isAdministrador()
                    ? ($validated['empresa_id'] ?? null)
                    : $empresaAtivaId;

                $user = User::create([
                    'empresa_id' => $empresaId,
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'telefone' => $validated['telefone'] ?? null,
                    'tipo_usuario' => $validated['tipo_usuario'],
                    'password' => $validated['password'],
                    'ativo' => true,
                ]);

                if (in_array($validated['tipo_usuario'], ['dono', 'funcionario'], true)) {
                    $cargoPadrao = $validated['tipo_usuario'] === 'dono'
                        ? 'Proprietário / Especialista'
                        : 'Operador de Detalhamento';

                    Funcionario::withoutGlobalScopes()->updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'empresa_id' => $empresaId,
                            'cargo' => ! empty($validated['cargo']) ? $validated['cargo'] : $cargoPadrao,
                            'ativo' => true,
                        ]
                    );
                } elseif ($validated['tipo_usuario'] === 'cliente') {
                    $user->ensureClienteProfile();
                }
            });
        } catch (QueryException $e) {
            return back()
                ->withInput($request->except('password'))
                ->with('error', 'Não foi possível cadastrar o usuário. Verifique se o e-mail já está em uso.');
        }

        return redirect()
            ->route('configuracoes.index', ['tab' => 'equipe'])
            ->with('success', 'Usuário cadastrado com sucesso!');
    }

    /**
     * Atualiza dados de um usuário.
     */
    public function updateUsuario(Request $request, User $user)
    {
        /** @var User $usuarioLogado */
        $usuarioLogado = Auth::user();

        if (! $usuarioLogado->canManageUsers()) {
            return back()->with('error', 'Um funcionário não pode alterar dados de outros funcionários ou usuários.');
        }

        if ($usuarioLogado->isAdministrador() && $user->isAdministrador() && (int) $user->id !== (int) $usuarioLogado->id) {
            return back()->with('error', 'Não é permitido alterar outro Administrador Geral pelo sistema.');
        }

        if ($usuarioLogado->isDono()) {
            $empresaAtivaId = $usuarioLogado->getEmpresaAtivaId();
            $mesmaEmpresa = $empresaAtivaId !== null && (int) $user->empresa_id === (int) $empresaAtivaId;
            $podeGerenciarAlvo = $mesmaEmpresa
                && ! $user->isAdministrador()
                && (! $user->isDono() || (int) $user->id === (int) $usuarioLogado->id);

            if (! $podeGerenciarAlvo) {
                return back()->with('error', 'Você só pode gerenciar funcionários e clientes vinculados à sua estética ativa.');
            }
        }

        if (is_scalar($request->input('email'))) {
            $request->merge([
                'email' => mb_strtolower(trim((string) $request->input('email'))),
            ]);
        }

        $ehProprioUsuario = (int) $user->id === (int) $usuarioLogado->id;
        if ($usuarioLogado->isAdministrador()) {
            $rolesPermitidos = $ehProprioUsuario ? ['administrador'] : ['dono', 'funcionario', 'cliente'];
        } else {
            $rolesPermitidos = $ehProprioUsuario ? ['dono'] : ['funcionario', 'cliente'];
        }

        $validated = $request->validate([
            'name' => 'required|string|min:3|max:100',
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'telefone' => 'nullable|string|max:15',
            'tipo_usuario' => ['required', Rule::in($rolesPermitidos)],
            'empresa_id' => 'nullable|exists:empresas,id',
            'cargo' => 'nullable|string|max:80',
            'password' => 'nullable|string|min:8|max:64',
        ], [
            'tipo_usuario.in' => $usuarioLogado->isAdministrador()
                ? 'Não é permitido atribuir o perfil de Administrador Geral pelo sistema.'
                : 'Um dono de estética só pode atribuir perfis de Funcionário ou Cliente.',
        ]);

        if ($ehProprioUsuario && $validated['tipo_usuario'] !== $user->tipo_usuario) {
            return back()->with('error', 'Você não pode alterar seu próprio perfil de acesso por esta tela.');
        }

        try {
            DB::transaction(function () use ($user, $validated, $usuarioLogado) {
                if ($validated['tipo_usuario'] === 'administrador') {
                    $empresaId = null;
                } elseif ($usuarioLogado->isAdministrador()) {
                    $empresaId = array_key_exists('empresa_id', $validated)
                        ? ($validated['empresa_id'] !== null ? (int) $validated['empresa_id'] : null)
                        : $user->empresa_id;
                } else {
                    $empresaId = $usuarioLogado->getEmpresaAtivaId();
                }

                $dados = [
                    'empresa_id' => $empresaId,
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'telefone' => $validated['telefone'] ?? null,
                    'tipo_usuario' => $validated['tipo_usuario'],
                ];

                if (! empty($validated['password'])) {
                    $dados['password'] = $validated['password'];
                }

                $user->update($dados);

                if (! empty($validated['password'])) {
                    $user->forceFill(['remember_token' => Str::random(60)])->save();
                }

                if (in_array($validated['tipo_usuario'], ['dono', 'funcionario'], true)) {
                    $cargoPadrao = $validated['tipo_usuario'] === 'dono'
                        ? 'Proprietário / Especialista'
                        : 'Operador de Detalhamento';

                    $dadosFunc = [
                        'empresa_id' => $empresaId,
                        'ativo' => (bool) $user->ativo,
                    ];
                    if (! empty($validated['cargo'])) {
                        $dadosFunc['cargo'] = $validated['cargo'];
                    } elseif (! $user->funcionario) {
                        $dadosFunc['cargo'] = $cargoPadrao;
                    }

                    Funcionario::withoutGlobalScopes()->updateOrCreate(
                        ['user_id' => $user->id],
                        $dadosFunc
                    );
                } else {
                    Funcionario::withoutGlobalScopes()->where('user_id', $user->id)->update(['ativo' => false]);

                    if ($validated['tipo_usuario'] === 'cliente') {
                        $user->ensureClienteProfile();
                    }
                }

                if (! empty($validated['password'])) {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }
            });
        } catch (QueryException $e) {
            return back()
                ->withInput($request->except('password'))
                ->with('error', 'Não foi possível atualizar o usuário. Verifique se o e-mail já está em uso.');
        }

        return redirect()
            ->route('configuracoes.index', ['tab' => 'equipe'])
            ->with('success', 'Dados do usuário atualizados com sucesso!');
    }

    public function toggleStatusUsuario(User $user)
    {
        /** @var User $usuarioLogado */
        $usuarioLogado = Auth::user();

        if (! $usuarioLogado->canManageUsers()) {
            return back()->with('error', 'Você não possui permissão para alterar o status de outros usuários.');
        }

        if ((int) $user->id === (int) $usuarioLogado->id) {
            return back()->with('error', 'Você não pode desativar sua própria conta em uso.');
        }

        if ($usuarioLogado->isDono()) {
            $empresaAtivaId = $usuarioLogado->getEmpresaAtivaId();
            if ($empresaAtivaId === null || $user->isAdministrador() || $user->isDono() || (int) $user->empresa_id !== (int) $empresaAtivaId) {
                return back()->with('error', 'Você só pode gerenciar funcionários e clientes vinculados à sua estética ativa.');
            }

            if (Empresa::where('id', $empresaAtivaId)->where('dono_id', $user->id)->exists()) {
                return back()->with('error', 'Não é permitido desativar o proprietário titular da empresa.');
            }
        }

        $novoStatus = ! $user->ativo;
        if (! $novoStatus) {
            $user->forceFill([
                'ativo' => false,
                'remember_token' => Str::random(60),
            ])->save();
        } else {
            $user->update(['ativo' => true]);
        }

        if (! $novoStatus) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        Funcionario::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->update(['ativo' => $novoStatus]);

        $acao = $novoStatus ? 'ativado' : 'desativado';

        return redirect()
            ->route('configuracoes.index', ['tab' => 'equipe'])
            ->with('success', "Acesso do usuário {$user->name} {$acao} com sucesso!");
    }

    public function destroyUsuario(User $user)
    {
        /** @var User $usuarioLogado */
        $usuarioLogado = Auth::user();

        if (! $usuarioLogado->canManageUsers()) {
            return back()->with('error', 'Você não possui permissão para excluir usuários.');
        }

        if ((int) $user->id === (int) $usuarioLogado->id) {
            return back()->with('error', 'Você não pode excluir sua própria conta.');
        }

        if ($usuarioLogado->isDono()) {
            $empresaAtivaId = $usuarioLogado->getEmpresaAtivaId();
            if ($empresaAtivaId === null || $user->isAdministrador() || $user->isDono() || (int) $user->empresa_id !== (int) $empresaAtivaId) {
                return back()->with('error', 'Você só pode gerenciar funcionários e clientes vinculados à sua estética ativa.');
            }
        }

        if (Empresa::where('dono_id', $user->id)->exists()) {
            return back()->with('error', 'Não é possível excluir um usuário que é proprietário titular de uma empresa. Transfira a titularidade ou desative o acesso.');
        }

        $func = Funcionario::withoutGlobalScopes()->where('user_id', $user->id)->first();
        if ($func && $func->tarefas()->withoutGlobalScopes()->exists()) {
            return back()->with('error', 'Não é possível excluir este colaborador pois ele já possui tarefas executadas no histórico. Desative o acesso dele em vez de excluir.');
        }

        if ($user->movimentacoesEstoque()->withoutGlobalScopes()->exists() || $user->lancamentosFinanceiros()->withoutGlobalScopes()->exists()) {
            return back()->with('error', 'Não é possível excluir este usuário pois ele possui movimentações financeiras ou de estoque registradas. Desative o acesso dele em vez de excluir.');
        }

        $nome = $user->name;

        try {
            DB::transaction(function () use ($user) {
                $pessoaId = $user->pessoa_id;

                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->delete();

                if ($pessoaId) {
                    $aindaEmUso = User::where('pessoa_id', $pessoaId)->exists()
                        || Cliente::withoutGlobalScopes()->where('pessoa_id', $pessoaId)->exists();

                    if (! $aindaEmUso) {
                        Pessoa::find($pessoaId)?->delete();
                    }
                }
            });
        } catch (QueryException $e) {
            return back()->with('error', 'Não é possível excluir este usuário pois existem vínculos ativos no banco de dados. Desative o acesso dele.');
        }

        return redirect()
            ->route('configuracoes.index', ['tab' => 'equipe'])
            ->with('success', "Usuário {$nome} removido com sucesso!");
    }
}
