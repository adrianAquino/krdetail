<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use App\Rules\CpfCnpj;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConfiguracaoController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $usuarioLogado */
        $usuarioLogado = Auth::user();
        $usuarioLogado->load(['pessoa', 'empresa', 'cliente']);

        // Se for Cliente: carrega apenas os dados do próprio perfil e informações de contato da oficina vinculada
        if ($usuarioLogado->isCliente()) {
            $cliente = $usuarioLogado->ensureClienteProfile();
            $cliente->load(['pessoa', 'empresa']);
            $empresaAtual = $cliente->empresa ?? $usuarioLogado->empresa ?? Empresa::first();

            return view('configuracoes.index', [
                'isCliente' => true,
                'clientePerfil' => $cliente,
                'empresaAtual' => $empresaAtual,
                'empresas' => collect($empresaAtual ? [$empresaAtual] : []),
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

        if ($usuarioLogado->isAdministrador()) {
            $empresas = Empresa::with('dono')
                ->withCount($empresaCounts)
                ->orderBy('nome_fantasia', 'asc')
                ->get();
        } elseif ($usuarioLogado->isDono()) {
            $empresas = Empresa::with('dono')
                ->withCount($empresaCounts)
                ->where(function ($q) use ($usuarioLogado) {
                    $q->where('id', $usuarioLogado->empresa_id)
                        ->orWhere('dono_id', $usuarioLogado->id);
                })
                ->orderBy('nome_fantasia', 'asc')
                ->get();
        } else {
            // Funcionário visualiza apenas a empresa à qual pertence
            $empresas = Empresa::with('dono')
                ->withCount($empresaCounts)
                ->where('id', $usuarioLogado->getEmpresaAtivaId())
                ->get();
        }

        $empresaSelecionadaId = $request->query('empresa_id');
        $empresaAtual = null;

        if ($empresaSelecionadaId) {
            $empresaAtual = $empresas->firstWhere('id', (int) $empresaSelecionadaId);
        }

        if (!$empresaAtual) {
            $empresaAtual = $empresas->firstWhere('id', $usuarioLogado->getEmpresaAtivaId()) ?? $empresas->first();
        }

        // Listagem de usuários:
        // - Administrador Geral vê todos os usuários do sistema
        // - Dono da Empresa e Funcionário veem apenas os usuários vinculados à sua empresa
        $queryUsuarios = User::with(['empresa', 'funcionario'])->orderBy('name', 'asc');

        if (!$usuarioLogado->isAdministrador()) {
            $queryUsuarios->where('empresa_id', $usuarioLogado->getEmpresaAtivaId());
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
                'empresa_nome' => $u->empresa?->nome_fantasia ?? ($u->isAdministrador() ? 'Global (Sistema)' : '-'),
                'cargo' => $u->funcionario?->cargo ?? null,
                'ativo' => (bool) $u->ativo,
            ];
        });

        $donosDisponiveis = $usuarioLogado->isAdministrador()
            ? User::whereIn('tipo_usuario', ['dono', 'administrador', 'funcionario'])->orderBy('name')->get()
            : collect([$usuarioLogado]);

        return view('configuracoes.index', [
            'isCliente' => false,
            'clientePerfil' => null,
            'empresaAtual' => $empresaAtual,
            'empresas' => $empresas,
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

        $rules = [
            'name' => 'required|string|min:3|max:100',
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'telefone' => 'nullable|string|max:15',
            'cpf_cnpj' => [
                'nullable',
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
            'estado' => 'nullable|string|max:2',
            'password' => 'nullable|string|min:6|max:64|confirmed',
        ];

        if ($cliente) {
            $rules['cpf_cnpj'][] = Rule::unique('clientes', 'cpf_cnpj')->ignore($cliente->id);
        }

        $validated = $request->validate($rules, [
            'email.unique' => 'Este e-mail já está em uso por outra conta.',
            'cpf_cnpj.unique' => 'Este CPF/CNPJ já está vinculado a outro cadastro.',
            'password.min' => 'A nova senha deve ter pelo menos 6 caracteres.',
            'password.confirmed' => 'A confirmação da nova senha não confere.',
        ]);

        DB::transaction(function () use ($user, $cliente, $validated) {
            $dadosUser = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'telefone' => $validated['telefone'] ?? null,
            ];

            if (!empty($validated['password'])) {
                $dadosUser['password'] = $validated['password'];
            }

            $user->update($dadosUser);

            if ($user->pessoa_id) {
                Pessoa::where('id', $user->pessoa_id)->update([
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
                    'estado' => isset($validated['estado']) ? strtoupper($validated['estado']) : null,
                ]);
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
                    'estado' => isset($validated['estado']) ? strtoupper($validated['estado']) : null,
                ]);
            }
        });

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

        if (!$usuarioLogado->isAdministrador() && !$usuarioLogado->isDono()) {
            return back()->with('error', 'Para cadastrar uma empresa, é necessário estar logado como Dono de Empresa ou Administrador Geral do Sistema.');
        }

        $validated = $request->validate([
            'nome_fantasia' => 'required|string|max:100',
            'razao_social' => 'required|string|max:120',
            'cnpj' => 'nullable|string|max:20|unique:empresas,cnpj',
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
            'uf' => 'nullable|string|max:2',
            'horario_atendimento' => 'nullable|string|max:100',
            'dono_id' => 'nullable|exists:users,id',
        ]);

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
                'uf' => isset($validated['uf']) ? strtoupper($validated['uf']) : null,
                'horario_atendimento' => $validated['horario_atendimento'] ?? null,
                'ativo' => true,
            ]);

            if ($donoId) {
                $donoUser = User::find($donoId);
                if ($donoUser) {
                    $dadosDono = ['empresa_id' => $novaEmpresa->id];
                    if (!$donoUser->isAdministrador()) {
                        $dadosDono['tipo_usuario'] = 'dono';
                    }
                    $donoUser->update($dadosDono);
                }
            }

            return $novaEmpresa;
        });

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

        if (!$usuarioLogado->canManageEmpresa($empresa)) {
            return back()->with('error', 'Você não possui permissão para alterar os dados da empresa.');
        }

        $validated = $request->validate([
            'nome_fantasia' => 'required|string|max:100',
            'razao_social' => 'required|string|max:120',
            'cnpj' => ['nullable', 'string', 'max:20', Rule::unique('empresas', 'cnpj')->ignore($empresa->id)],
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
            'uf' => 'nullable|string|max:2',
            'horario_atendimento' => 'nullable|string|max:100',
            'dono_id' => 'nullable|exists:users,id',
        ]);

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
                'uf' => isset($validated['uf']) ? strtoupper($validated['uf']) : null,
                'horario_atendimento' => $validated['horario_atendimento'] ?? null,
            ];

            if ($usuarioLogado->isAdministrador() && array_key_exists('dono_id', $validated)) {
                $dados['dono_id'] = $validated['dono_id'];
            }

            $empresa->update($dados);

            if (!empty($dados['dono_id'])) {
                $donoUser = User::find($dados['dono_id']);
                if ($donoUser && !$donoUser->isAdministrador()) {
                    $donoUser->update([
                        'tipo_usuario' => 'dono',
                        'empresa_id' => $empresa->id,
                    ]);
                }
            }
        });

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

        if (!$usuarioLogado->canManageEmpresa($empresa)) {
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

        if (!empty($vinculos)) {
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
     * Cadastra um novo usuário no sistema.
     * Funcionários e Clientes não podem criar usuários.
     * Dono só pode criar usuários para sua própria empresa.
     */
    public function storeUsuario(Request $request)
    {
        /** @var User $usuarioLogado */
        $usuarioLogado = Auth::user();

        if (!$usuarioLogado->canManageUsers()) {
            return back()->with('error', 'Você não possui permissão para cadastrar outros usuários.');
        }

        $rolesPermitidos = $usuarioLogado->isAdministrador()
            ? ['administrador', 'dono', 'funcionario', 'cliente']
            : ['dono', 'funcionario', 'cliente'];

        $validated = $request->validate([
            'name' => 'required|string|min:3|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'telefone' => 'nullable|string|max:15',
            'tipo_usuario' => ['required', Rule::in($rolesPermitidos)],
            'empresa_id' => 'nullable|exists:empresas,id',
            'cargo' => 'nullable|string|max:80',
            'password' => 'required|string|min:6|max:64',
        ]);

        DB::transaction(function () use ($validated, $usuarioLogado) {
            $empresaId = $usuarioLogado->isAdministrador()
                ? ($validated['empresa_id'] ?? $usuarioLogado->getEmpresaAtivaId())
                : $usuarioLogado->getEmpresaAtivaId();

            if ($validated['tipo_usuario'] === 'administrador') {
                $empresaId = null;
            }

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
                        'cargo' => !empty($validated['cargo']) ? $validated['cargo'] : $cargoPadrao,
                        'ativo' => true,
                    ]
                );
            } elseif ($validated['tipo_usuario'] === 'cliente') {
                $user->ensureClienteProfile();
            }
        });

        return redirect()
            ->route('configuracoes.index', ['tab' => 'equipe'])
            ->with('success', 'Usuário cadastrado com sucesso!');
    }

    /**
     * Atualiza dados de um usuário.
     * Funcionário não pode alterar dados de outros funcionários.
     * Dono só pode alterar usuários da própria empresa.
     */
    public function updateUsuario(Request $request, User $user)
    {
        /** @var User $usuarioLogado */
        $usuarioLogado = Auth::user();

        if (!$usuarioLogado->canManageUsers()) {
            return back()->with('error', 'Um funcionário não pode alterar dados de outros funcionários ou usuários.');
        }

        if ($usuarioLogado->isDono()) {
            if ($user->isAdministrador() || (int) $user->empresa_id !== (int) $usuarioLogado->getEmpresaAtivaId()) {
                return back()->with('error', 'Você só pode gerenciar usuários vinculados à sua empresa.');
            }
        }

        $rolesPermitidos = $usuarioLogado->isAdministrador()
            ? ['administrador', 'dono', 'funcionario', 'cliente']
            : ['dono', 'funcionario', 'cliente'];

        $validated = $request->validate([
            'name' => 'required|string|min:3|max:100',
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'telefone' => 'nullable|string|max:15',
            'tipo_usuario' => ['required', Rule::in($rolesPermitidos)],
            'empresa_id' => 'nullable|exists:empresas,id',
            'cargo' => 'nullable|string|max:80',
            'password' => 'nullable|string|min:6|max:64',
        ]);

        DB::transaction(function () use ($user, $validated, $usuarioLogado) {
            $empresaId = $usuarioLogado->isAdministrador()
                ? ($validated['empresa_id'] ?? $user->empresa_id ?? $usuarioLogado->getEmpresaAtivaId())
                : $usuarioLogado->getEmpresaAtivaId();

            if ($validated['tipo_usuario'] === 'administrador') {
                $empresaId = null;
            }

            $dados = [
                'empresa_id' => $empresaId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'telefone' => $validated['telefone'] ?? null,
                'tipo_usuario' => $validated['tipo_usuario'],
            ];

            if (!empty($validated['password'])) {
                $dados['password'] = $validated['password'];
            }

            $user->update($dados);

            if (in_array($validated['tipo_usuario'], ['dono', 'funcionario'], true)) {
                $cargoPadrao = $validated['tipo_usuario'] === 'dono'
                    ? 'Proprietário / Especialista'
                    : 'Operador de Detalhamento';

                $dadosFunc = [
                    'empresa_id' => $empresaId,
                    'ativo' => (bool) $user->ativo,
                ];
                if (!empty($validated['cargo'])) {
                    $dadosFunc['cargo'] = $validated['cargo'];
                } elseif (!$user->funcionario) {
                    $dadosFunc['cargo'] = $cargoPadrao;
                }

                Funcionario::withoutGlobalScopes()->updateOrCreate(
                    ['user_id' => $user->id],
                    $dadosFunc
                );
            } else {
                // Se deixou de ser dono/funcionário, inativa o registro de prestador de serviço
                Funcionario::withoutGlobalScopes()->where('user_id', $user->id)->update(['ativo' => false]);

                if ($validated['tipo_usuario'] === 'cliente') {
                    $user->ensureClienteProfile();
                }
            }
        });

        return redirect()
            ->route('configuracoes.index', ['tab' => 'equipe'])
            ->with('success', 'Dados do usuário atualizados com sucesso!');
    }

    public function toggleStatusUsuario(User $user)
    {
        /** @var User $usuarioLogado */
        $usuarioLogado = Auth::user();

        if (!$usuarioLogado->canManageUsers()) {
            return back()->with('error', 'Você não possui permissão para alterar o status de outros usuários.');
        }

        if ((int) $user->id === (int) $usuarioLogado->id) {
            return back()->with('error', 'Você não pode desativar sua própria conta em uso.');
        }

        if ($usuarioLogado->isDono()) {
            if ($user->isAdministrador() || (int) $user->empresa_id !== (int) $usuarioLogado->getEmpresaAtivaId()) {
                return back()->with('error', 'Você só pode gerenciar usuários vinculados à sua empresa.');
            }
        }

        $novoStatus = !$user->ativo;
        $user->update(['ativo' => $novoStatus]);

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

        if (!$usuarioLogado->canManageUsers()) {
            return back()->with('error', 'Você não possui permissão para excluir usuários.');
        }

        if ((int) $user->id === (int) $usuarioLogado->id) {
            return back()->with('error', 'Você não pode excluir sua própria conta.');
        }

        if ($usuarioLogado->isDono()) {
            if ($user->isAdministrador() || (int) $user->empresa_id !== (int) $usuarioLogado->getEmpresaAtivaId()) {
                return back()->with('error', 'Você só pode gerenciar usuários vinculados à sua empresa.');
            }
        }

        $func = Funcionario::withoutGlobalScopes()->where('user_id', $user->id)->first();
        if ($func && $func->tarefas()->withoutGlobalScopes()->exists()) {
            return back()->with('error', 'Não é possível excluir este colaborador pois ele já possui tarefas executadas no histórico. Desative o acesso dele em vez de excluir.');
        }

        if ($user->movimentacoesEstoque()->withoutGlobalScopes()->exists() || $user->lancamentosFinanceiros()->withoutGlobalScopes()->exists()) {
            return back()->with('error', 'Não é possível excluir este usuário pois ele possui movimentações financeiras ou de estoque registradas. Desative o acesso dele em vez de excluir.');
        }

        $nome = $user->name;
        $user->delete();

        return redirect()
            ->route('configuracoes.index', ['tab' => 'equipe'])
            ->with('success', "Usuário {$nome} removido com sucesso!");
    }
}
