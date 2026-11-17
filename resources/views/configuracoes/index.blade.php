@extends('layouts.app')

@section('title', 'Configurações')

@section('content')
@php
    /** @var \App\Models\User $usuarioLogado */
    $usuarioLogado = auth()->user();
    $isCliente = $isCliente ?? ($usuarioLogado && $usuarioLogado->isCliente());
    $isAdmin = $usuarioLogado && $usuarioLogado->isAdministrador();
    $isDono = $usuarioLogado && $usuarioLogado->isDono();
    $isFuncionario = $usuarioLogado && $usuarioLogado->isFuncionario();

    $podeGerenciarEmpresa = $usuarioLogado && $usuarioLogado->canManageEmpresa($empresaAtual ?? null);
    $podeCriarEmpresa = $isAdmin || $isDono;
    $podeGerenciarUsuarios = $usuarioLogado && $usuarioLogado->canManageUsers();

    $pessoaLogada = $usuarioLogado?->pessoa;
    $cpfCnpjAtual = $clientePerfil?->cpf_cnpj ?? $pessoaLogada?->cpf_cnpj ?? '';
    $cepAtual = $clientePerfil?->cep ?? $pessoaLogada?->cep ?? '';
    $logradouroAtual = $clientePerfil?->logradouro ?? $pessoaLogada?->logradouro ?? '';
    $numeroAtual = $clientePerfil?->numero ?? $pessoaLogada?->numero ?? '';
    $complementoAtual = $clientePerfil?->complemento ?? $pessoaLogada?->complemento ?? '';
    $bairroAtual = $clientePerfil?->bairro ?? $pessoaLogada?->bairro ?? '';
    $cidadeAtual = $clientePerfil?->cidade ?? $pessoaLogada?->cidade ?? '';
    $estadoAtual = $clientePerfil?->estado ?? $pessoaLogada?->estado ?? '';

    $tabPadrao = $isCliente ? 'perfil' : request('tab', 'empresa');
@endphp

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <x-icon name="settings" class="w-7 h-7 text-primary" />
                {{ $isCliente ? 'Configurações & Meu Perfil' : 'Configurações' }}
            </h1>
            <p class="text-sm text-muted-foreground mt-1">
                @if($isCliente)
                    Atualize seus dados cadastrais, endereço e senha de acesso ao Portal do Cliente.
                @elseif($isFuncionario)
                    Consulte os dados da empresa e da equipe ou atualize suas informações de perfil.
                @else
                    Gerencie os dados da empresa, permissões da equipe, perfil e preferências operacionais.
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
                <x-icon name="shield" class="w-3.5 h-3.5" />
                Perfil: {{ $usuarioLogado?->role_label }}
            </span>
        </div>
    </div>

    <!-- Navegação por Abas -->
    <div class="border-b border-border">
        <nav class="flex space-x-6 overflow-x-auto" aria-label="Abas de Configurações">
            @if(!$isCliente)
                <button type="button"
                        id="tab-empresa-btn"
                        onclick="switchConfigTab('empresa')"
                        class="tab-btn py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 border-transparent text-muted-foreground hover:text-foreground hover:border-border whitespace-nowrap transition-colors cursor-pointer">
                    <x-icon name="briefcase" class="w-4 h-4" />
                    Dados da Empresa
                </button>
                <button type="button"
                        id="tab-equipe-btn"
                        onclick="switchConfigTab('equipe')"
                        class="tab-btn py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 border-transparent text-muted-foreground hover:text-foreground hover:border-border whitespace-nowrap transition-colors cursor-pointer">
                    <x-icon name="users" class="w-4 h-4" />
                    Usuários & Equipe
                </button>
            @endif

            <button type="button"
                    id="tab-perfil-btn"
                    onclick="switchConfigTab('perfil')"
                    class="tab-btn py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 border-transparent text-muted-foreground hover:text-foreground hover:border-border whitespace-nowrap transition-colors cursor-pointer">
                <x-icon name="user" class="w-4 h-4" />
                Meu Perfil
            </button>

            @if($isCliente && $empresaAtual)
                <button type="button"
                        id="tab-oficina-btn"
                        onclick="switchConfigTab('oficina')"
                        class="tab-btn py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 border-transparent text-muted-foreground hover:text-foreground hover:border-border whitespace-nowrap transition-colors cursor-pointer">
                    <x-icon name="map-pin" class="w-4 h-4" />
                    Dados da Oficina
                </button>
            @endif

            @if(!$isCliente)
                <button type="button"
                        id="tab-preferencias-btn"
                        onclick="switchConfigTab('preferencias')"
                        class="tab-btn py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 border-transparent text-muted-foreground hover:text-foreground hover:border-border whitespace-nowrap transition-colors cursor-pointer">
                    <x-icon name="sliders" class="w-4 h-4" />
                    Preferências & Alertas
                </button>
                <button type="button"
                        id="tab-sistema-btn"
                        onclick="switchConfigTab('sistema')"
                        class="tab-btn py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 border-transparent text-muted-foreground hover:text-foreground hover:border-border whitespace-nowrap transition-colors cursor-pointer">
                    <x-icon name="info" class="w-4 h-4" />
                    Informações do Sistema
                </button>
            @endif
        </nav>
    </div>

    @if(!$isCliente)
        <!-- ABA 1: DADOS DA EMPRESA (CRUD COMPLETO) -->
        <div id="tab-empresa" class="config-content space-y-6 hidden">
            @if($isFuncionario)
                <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center gap-3 text-xs text-amber-400">
                    <x-icon name="info" class="w-4 h-4 shrink-0" />
                    <span>Modo somente leitura: como <strong>Funcionário</strong>, você pode consultar os dados da empresa, mas apenas o <strong>Dono da Empresa</strong> ou o <strong>Administrador Geral</strong> podem alterá-los.</span>
                </div>
            @endif

            <!-- Barra Superior de Gestão de Empresas (Listagem / Seleção / Criação) -->
            <x-card>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold text-foreground flex items-center gap-2">
                            <x-icon name="briefcase" class="w-4 h-4 text-primary" />
                            Empresas Cadastradas ({{ $empresas->count() }})
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            @if($isAdmin)
                                Como Administrador Geral, você pode visualizar, criar, editar e excluir qualquer empresa do sistema.
                            @elseif($isDono)
                                Gerencie os dados cadastrais da sua empresa ou cadastre uma nova unidade.
                            @else
                                Unidade empresarial à qual seu perfil de colaborador está vinculado.
                            @endif
                        </p>
                    </div>

                    @if($podeCriarEmpresa)
                        <x-button type="button" variant="primary" size="sm" onclick="openModal('modal-nova-empresa')" class="gap-1.5 self-start sm:self-auto">
                            <x-icon name="plus" class="w-4 h-4" />
                            Nova Empresa
                        </x-button>
                    @endif
                </div>

                @if($empresas->count() > 0)
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($empresas as $empItem)
                            @php
                                $selecionada = $empresaAtual && (int) $empresaAtual->id === (int) $empItem->id;
                            @endphp
                            <div class="p-3.5 rounded-xl border transition-all flex items-start justify-between gap-3 {{ $selecionada ? 'border-primary bg-primary/5 ring-1 ring-primary/30' : 'border-border bg-secondary/20 hover:border-border/80' }}">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('configuracoes.index', ['tab' => 'empresa', 'empresa_id' => $empItem->id]) }}"
                                           class="font-semibold text-sm text-foreground hover:text-primary truncate">
                                            {{ $empItem->nome_fantasia }}
                                        </a>
                                        @if($selecionada)
                                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-primary/15 text-primary font-semibold">Selecionada</span>
                                        @endif
                                    </div>
                                    <p class="text-[11px] text-muted-foreground truncate mt-0.5">{{ $empItem->razao_social }}</p>
                                    <p class="text-[11px] text-muted-foreground mt-1">
                                        CNPJ: {{ $empItem->cnpj ?: 'Não informado' }} • Dono: {{ $empItem->dono?->name ?? 'Não atribuído' }}
                                    </p>
                                </div>

                                @if(!$selecionada)
                                    <a href="{{ route('configuracoes.index', ['tab' => 'empresa', 'empresa_id' => $empItem->id]) }}"
                                       class="text-xs font-medium text-primary hover:underline shrink-0">
                                        Abrir
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            @if($empresaAtual)
                <form action="{{ route('configuracoes.empresas.update', $empresaAtual->id) }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <x-card>
                        <div class="flex items-center justify-between gap-2 pb-4 mb-4 border-b border-border">
                            <div class="flex items-center gap-2">
                                <x-icon name="shield" class="w-5 h-5 text-primary" />
                                <div>
                                    <h2 class="text-sm font-semibold text-foreground">Identificação da Oficina ({{ $empresaAtual->nome_fantasia }})</h2>
                                    <p class="text-xs text-muted-foreground">Estes dados aparecem nos relatórios, fichas de agendamento e ordens de serviço.</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-input name="nome_fantasia" label="Nome Fantasia" :value="old('nome_fantasia', $empresaAtual->nome_fantasia)" maxlength="100" required :disabled="!$podeGerenciarEmpresa" />
                            <x-input name="razao_social" label="Razão Social" :value="old('razao_social', $empresaAtual->razao_social)" maxlength="120" required :disabled="!$podeGerenciarEmpresa" />
                            <x-input name="cnpj" label="CNPJ" :value="old('cnpj', $empresaAtual->cnpj)" maxlength="18" placeholder="00.000.000/0000-00" :disabled="!$podeGerenciarEmpresa" />
                            <x-input name="inscricao_estadual" label="Inscrição Estadual" :value="old('inscricao_estadual', $empresaAtual->inscricao_estadual)" maxlength="20" :disabled="!$podeGerenciarEmpresa" />

                            @if($isAdmin)
                                <div class="md:col-span-2">
                                    <x-select name="dono_id" label="Dono da Empresa (Responsável Principal)">
                                        <option value="">Selecione o dono responsável</option>
                                        @foreach($donosDisponiveis as $donoOpcao)
                                            <option value="{{ $donoOpcao->id }}" {{ (int) old('dono_id', $empresaAtual->dono_id) === (int) $donoOpcao->id ? 'selected' : '' }}>
                                                {{ $donoOpcao->name }} ({{ $donoOpcao->email }} - {{ $donoOpcao->role_label }})
                                            </option>
                                        @endforeach
                                    </x-select>
                                </div>
                            @endif
                        </div>
                    </x-card>

                    <x-card>
                        <div class="flex items-center gap-2 pb-4 mb-4 border-b border-border">
                            <x-icon name="phone" class="w-5 h-5 text-primary" />
                            <div>
                                <h2 class="text-sm font-semibold text-foreground">Canais de Contato</h2>
                                <p class="text-xs text-muted-foreground">Canais utilizados para contato com clientes e fornecedores.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <x-input name="telefone" label="Telefone Fixo" :value="old('telefone', $empresaAtual->telefone)" maxlength="15" :disabled="!$podeGerenciarEmpresa" />
                            <x-input name="whatsapp" label="WhatsApp Comercial" :value="old('whatsapp', $empresaAtual->whatsapp)" maxlength="15" required :disabled="!$podeGerenciarEmpresa" />
                            <x-input type="email" name="email" label="E-mail Comercial" :value="old('email', $empresaAtual->email)" maxlength="100" required :disabled="!$podeGerenciarEmpresa" />
                        </div>
                    </x-card>

                    <x-card>
                        <div class="flex items-center gap-2 pb-4 mb-4 border-b border-border">
                            <x-icon name="map-pin" class="w-5 h-5 text-primary" />
                            <div>
                                <h2 class="text-sm font-semibold text-foreground">Endereço da Sede</h2>
                                <p class="text-xs text-muted-foreground">Localização física para recepção dos veículos dos clientes.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <x-input name="cep" label="CEP" :value="old('cep', $empresaAtual->cep)" maxlength="9" :disabled="!$podeGerenciarEmpresa" />
                            <div class="sm:col-span-2">
                                <x-input name="logradouro" label="Logradouro" :value="old('logradouro', $empresaAtual->logradouro)" maxlength="100" :disabled="!$podeGerenciarEmpresa" />
                            </div>
                            <x-input name="numero" label="Número" :value="old('numero', $empresaAtual->numero)" maxlength="10" :disabled="!$podeGerenciarEmpresa" />
                            <x-input name="complemento" label="Complemento" :value="old('complemento', $empresaAtual->complemento)" maxlength="100" :disabled="!$podeGerenciarEmpresa" />
                            <x-input name="bairro" label="Bairro" :value="old('bairro', $empresaAtual->bairro)" maxlength="100" :disabled="!$podeGerenciarEmpresa" />
                            <x-input name="cidade" label="Cidade" :value="old('cidade', $empresaAtual->cidade)" maxlength="100" :disabled="!$podeGerenciarEmpresa" />
                            <x-input name="uf" label="UF" :value="old('uf', $empresaAtual->uf)" maxlength="2" :disabled="!$podeGerenciarEmpresa" />
                            <x-input name="horario_atendimento" label="Horário de Atendimento" :value="old('horario_atendimento', $empresaAtual->horario_atendimento)" maxlength="100" :disabled="!$podeGerenciarEmpresa" />
                        </div>
                    </x-card>

                    @if($podeGerenciarEmpresa)
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                @if($empresas->count() > 1)
                                    <button type="button"
                                            onclick="if(confirm('Tem certeza que deseja excluir esta empresa? Esta ação só será permitida se não houver clientes ou colaboradores vinculados.')) document.getElementById('form-delete-empresa-{{ $empresaAtual->id }}').submit();"
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold text-rose-400 border border-rose-500/30 bg-rose-500/10 hover:bg-rose-500/20 transition-colors cursor-pointer">
                                        <x-icon name="trash" class="w-4 h-4" />
                                        Excluir Empresa
                                    </button>
                                @endif
                            </div>

                            <x-button type="submit" variant="primary" class="gap-2">
                                <x-icon name="check" class="w-4 h-4" />
                                Salvar Dados da Empresa
                            </x-button>
                        </div>
                    @endif
                </form>

                @if($podeGerenciarEmpresa && $empresas->count() > 1)
                    <form id="form-delete-empresa-{{ $empresaAtual->id }}"
                          action="{{ route('configuracoes.empresas.destroy', $empresaAtual->id) }}"
                          method="POST"
                          class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                @endif
            @endif
        </div>

        <!-- ABA 2: USUÁRIOS & EQUIPE -->
        <div id="tab-equipe" class="config-content space-y-6 hidden">
            <!-- Cards Informativos dos 4 Papéis -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-4 rounded-lg bg-secondary/30 border border-border">
                    <div class="flex items-center gap-2 text-foreground font-semibold text-sm mb-1">
                        <x-icon name="shield" class="w-4 h-4 text-emerald-400" />
                        Administrador Geral
                    </div>
                    <p class="text-xs text-muted-foreground">Acesso global ao sistema, criação e gestão de empresas, usuários e todos os módulos.</p>
                </div>

                <div class="p-4 rounded-lg bg-secondary/30 border border-border">
                    <div class="flex items-center gap-2 text-foreground font-semibold text-sm mb-1">
                        <x-icon name="briefcase" class="w-4 h-4 text-amber-400" />
                        Dono da Empresa
                    </div>
                    <p class="text-xs text-muted-foreground">Gerencia dados, equipe, clientes e financeiro da sua empresa, além de poder executar serviços e tarefas.</p>
                </div>

                <div class="p-4 rounded-lg bg-secondary/30 border border-border">
                    <div class="flex items-center gap-2 text-foreground font-semibold text-sm mb-1">
                        <x-icon name="tool" class="w-4 h-4 text-sky-400" />
                        Funcionário
                    </div>
                    <p class="text-xs text-muted-foreground">Atua na operação (OS, tarefas e estoque) e visualiza a equipe, sem permissão para alterar outros funcionários ou a empresa.</p>
                </div>

                <div class="p-4 rounded-lg bg-secondary/30 border border-border">
                    <div class="flex items-center gap-2 text-foreground font-semibold text-sm mb-1">
                        <x-icon name="user" class="w-4 h-4 text-purple-400" />
                        Cliente (Portal)
                    </div>
                    <p class="text-xs text-muted-foreground">Acessa exclusivamente seus próprios veículos, agendamentos e configurações do seu perfil pessoal.</p>
                </div>
            </div>

            <x-card>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Colaboradores e Contas Vinculadas</h2>
                        <p class="text-xs text-muted-foreground">
                            @if($isFuncionario)
                                Visualização dos integrantes da empresa. Para editar seus próprios dados, acesse a aba <strong>Meu Perfil</strong>.
                            @else
                                Gerencie os papéis e permissões de acesso dos usuários {{ $isDono ? 'da sua empresa' : 'do sistema' }}.
                            @endif
                        </p>
                    </div>
                    @if($podeGerenciarUsuarios)
                        <x-button variant="primary" size="sm" onclick="openModal('modal-novo-usuario')" class="gap-1.5 self-start sm:self-auto">
                            <x-icon name="plus" class="w-4 h-4" />
                            Novo Usuário
                        </x-button>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-secondary/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider border-b border-border">
                            <tr>
                                <th class="px-4 py-3">Usuário</th>
                                <th class="px-4 py-3">Empresa</th>
                                <th class="px-4 py-3">Telefone</th>
                                <th class="px-4 py-3">Perfil de Acesso</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-xs">
                            @php
                                $usuariosLista = $usuarios ?? collect();
                            @endphp

                            @forelse($usuariosLista as $user)
                            <tr class="hover:bg-secondary/20 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-secondary flex items-center justify-center font-bold text-xs text-foreground border border-border">
                                            {{ strtoupper(substr($user['nome'], 0, 2)) }}
                                        </div>
                                        <div>
                                            <p class="font-medium text-foreground">
                                                {{ $user['nome'] }}
                                                @if((int) $user['id'] === (int) $usuarioLogado->id)
                                                    <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-primary/15 text-primary font-semibold">Você</span>
                                                @endif
                                            </p>
                                            <p class="text-[11px] text-muted-foreground">{{ $user['email'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ $user['empresa_nome'] }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ $user['telefone'] }}</td>
                                <td class="px-4 py-3">
                                    @if($user['tipo'] === 'administrador')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            <x-icon name="shield" class="w-3 h-3" />
                                            Administrador Geral
                                        </span>
                                    @elseif($user['tipo'] === 'dono')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                            <x-icon name="briefcase" class="w-3 h-3" />
                                            Dono da Empresa
                                        </span>
                                    @elseif($user['tipo'] === 'funcionario')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-500/10 text-sky-400 border border-sky-500/20">
                                            <x-icon name="tool" class="w-3 h-3" />
                                            Funcionário
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-purple-500/10 text-purple-400 border border-purple-500/20">
                                            <x-icon name="user" class="w-3 h-3" />
                                            Cliente
                                        </span>
                                    @endif

                                    @if(!empty($user['cargo']) && in_array($user['tipo'], ['dono', 'funcionario'], true))
                                        <span class="block text-[10px] text-muted-foreground mt-0.5">{{ $user['cargo'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($user['ativo'])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Ativo
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-zinc-500/10 text-zinc-400">
                                            <span class="w-1.5 h-1.5 rounded-full bg-zinc-500"></span>
                                            Inativo
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if($podeGerenciarUsuarios && ($isAdmin || $user['tipo'] !== 'administrador'))
                                        <button
                                            type="button"
                                            onclick="openModal('modal-editar-usuario-{{ $user['id'] }}')"
                                            class="p-1 text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                            title="Editar Usuário"
                                        >
                                            <x-icon name="edit" class="w-4 h-4" />
                                        </button>

                                        @if((int) $user['id'] !== (int) $usuarioLogado->id)
                                            <form method="POST" action="{{ route('configuracoes.usuarios.toggle-status', $user['id']) }}" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    class="p-1 text-muted-foreground {{ $user['ativo'] ? 'hover:text-amber-400' : 'hover:text-emerald-400' }} transition-colors ml-1 cursor-pointer"
                                                    title="{{ $user['ativo'] ? 'Desativar Acesso' : 'Ativar Acesso' }}"
                                                >
                                                    <x-icon :name="$user['ativo'] ? 'user-x' : 'user-check'" class="w-4 h-4" />
                                                </button>
                                            </form>

                                            <form method="POST"
                                                  action="{{ route('configuracoes.usuarios.destroy', $user['id']) }}"
                                                  class="inline"
                                                  onsubmit="return confirm('Deseja realmente excluir este usuário?')">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="p-1 text-muted-foreground hover:text-rose-400 transition-colors ml-1 cursor-pointer"
                                                    title="Excluir Usuário"
                                                >
                                                    <x-icon name="trash" class="w-4 h-4" />
                                                </button>
                                            </form>
                                        @endif

                                        <!-- Modal Editar Usuário -->
                                        <x-modal id="modal-editar-usuario-{{ $user['id'] }}" :title="'Editar Usuário: ' . $user['nome']">
                                            <form action="{{ route('configuracoes.usuarios.update', $user['id']) }}" method="POST" class="space-y-4 text-left">
                                                @csrf
                                                @method('PUT')
                                                <x-input name="name" label="Nome Completo" :value="$user['nome']" minlength="3" maxlength="100" required />
                                                <x-input type="email" name="email" label="E-mail" :value="$user['email']" maxlength="100" required />
                                                <x-input name="telefone" label="Telefone / WhatsApp" :value="$user['telefone'] !== '-' ? $user['telefone'] : ''" maxlength="15" />

                                                <x-select name="tipo_usuario" label="Perfil de Acesso" required>
                                                    <option value="dono" {{ $user['tipo'] === 'dono' ? 'selected' : '' }}>Dono da Empresa (Gestão + Execução de Serviços)</option>
                                                    <option value="funcionario" {{ $user['tipo'] === 'funcionario' ? 'selected' : '' }}>Funcionário / Operador de Detalhamento</option>
                                                    @if($isAdmin)
                                                        <option value="administrador" {{ $user['tipo'] === 'administrador' ? 'selected' : '' }}>Administrador Geral do Sistema</option>
                                                    @endif
                                                    <option value="cliente" {{ $user['tipo'] === 'cliente' ? 'selected' : '' }}>Cliente (Acesso ao Portal)</option>
                                                </x-select>

                                                @if($isAdmin)
                                                    <x-select name="empresa_id" label="Empresa Vinculada">
                                                        <option value="">Sem vínculo fixo (Global)</option>
                                                        @foreach($empresas as $empOpcao)
                                                            <option value="{{ $empOpcao->id }}" {{ (int) $user['empresa_id'] === (int) $empOpcao->id ? 'selected' : '' }}>
                                                                {{ $empOpcao->nome_fantasia }}
                                                            </option>
                                                        @endforeach
                                                    </x-select>
                                                @endif

                                                <x-input name="cargo" label="Cargo / Especialidade (Para Dono ou Funcionário)" :value="$user['cargo'] ?? ''" maxlength="80" placeholder="Ex: Proprietário / Especialista" />

                                                <x-input type="password" name="password" label="Nova Senha (deixe em branco para manter a atual)" minlength="6" maxlength="64" placeholder="••••••••" />

                                                <div class="flex justify-end gap-3 pt-4 border-t border-border">
                                                    <x-button type="button" variant="outline" onclick="closeModal('modal-editar-usuario-{{ $user['id'] }}')">
                                                        Cancelar
                                                    </x-button>
                                                    <x-button type="submit" variant="primary" class="gap-1.5">
                                                        <x-icon name="save" class="w-4 h-4" />
                                                        Salvar Alterações
                                                    </x-button>
                                                </div>
                                            </form>
                                        </x-modal>
                                    @elseif((int) $user['id'] === (int) $usuarioLogado->id)
                                        <button type="button"
                                                onclick="switchConfigTab('perfil')"
                                                class="text-[11px] font-semibold text-primary hover:underline cursor-pointer">
                                            Editar Meu Perfil
                                        </button>
                                    @else
                                        <span class="text-[11px] text-muted-foreground italic">Somente leitura</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-muted-foreground">
                                    Nenhum usuário encontrado.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    @endif

    <!-- ABA MEU PERFIL (DISPONÍVEL PARA CLIENTE, FUNCIONÁRIO, DONO E ADMIN) -->
    <div id="tab-perfil" class="config-content space-y-6 hidden">
        <form action="{{ route('configuracoes.perfil.update') }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <x-card>
                <div class="flex items-center gap-2 pb-4 mb-4 border-b border-border">
                    <x-icon name="user" class="w-5 h-5 text-primary" />
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Meus Dados Pessoais</h2>
                        <p class="text-xs text-muted-foreground">Mantenha seus dados de identificação e contato atualizados.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input name="name" label="Nome Completo" :value="old('name', $usuarioLogado->name)" minlength="3" maxlength="100" required />
                    <x-input name="cpf_cnpj" label="CPF / CNPJ" :value="old('cpf_cnpj', $cpfCnpjAtual)" maxlength="18" placeholder="000.000.000-00" />
                    <x-input type="email" name="email" label="E-mail de Acesso" :value="old('email', $usuarioLogado->email)" maxlength="100" required />
                    <x-input name="telefone" label="Telefone / WhatsApp" :value="old('telefone', $usuarioLogado->telefone)" maxlength="15" placeholder="(11) 99999-9999" />
                </div>
            </x-card>

            <x-card>
                <div class="flex items-center gap-2 pb-4 mb-4 border-b border-border">
                    <x-icon name="map-pin" class="w-5 h-5 text-primary" />
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Meu Endereço</h2>
                        <p class="text-xs text-muted-foreground">Endereço sincronizado automaticamente com seu cadastro unificado.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <x-input name="cep" id="perfil_cep" label="CEP" :value="old('cep', $cepAtual)" maxlength="9" placeholder="00000-000" />
                    <div class="sm:col-span-2">
                        <x-input name="logradouro" id="perfil_logradouro" label="Logradouro" :value="old('logradouro', $logradouroAtual)" maxlength="100" />
                    </div>
                    <x-input name="numero" label="Número" :value="old('numero', $numeroAtual)" maxlength="10" />
                    <x-input name="complemento" label="Complemento" :value="old('complemento', $complementoAtual)" maxlength="100" />
                    <x-input name="bairro" id="perfil_bairro" label="Bairro" :value="old('bairro', $bairroAtual)" maxlength="100" />
                    <x-input name="cidade" id="perfil_cidade" label="Cidade" :value="old('cidade', $cidadeAtual)" maxlength="100" />
                    <x-input name="estado" id="perfil_estado" label="UF" :value="old('estado', $estadoAtual)" maxlength="2" placeholder="SP" />
                </div>
            </x-card>

            <x-card>
                <div class="flex items-center gap-2 pb-4 mb-4 border-b border-border">
                    <x-icon name="lock" class="w-5 h-5 text-primary" />
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Segurança & Senha</h2>
                        <p class="text-xs text-muted-foreground">Preencha apenas se desejar alterar sua senha atual.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input type="password" name="password" label="Nova Senha" minlength="6" maxlength="64" placeholder="••••••••" />
                    <x-input type="password" name="password_confirmation" label="Confirmar Nova Senha" minlength="6" maxlength="64" placeholder="••••••••" />
                </div>
            </x-card>

            <div class="flex justify-end">
                <x-button type="submit" variant="primary" class="gap-2">
                    <x-icon name="check" class="w-4 h-4" />
                    Salvar Meu Perfil
                </x-button>
            </div>
        </form>
    </div>

    @if($isCliente && $empresaAtual)
        <!-- ABA INFORMAÇÕES DA OFICINA PARA O CLIENTE -->
        <div id="tab-oficina" class="config-content space-y-6 hidden">
            <x-card>
                <div class="flex items-center gap-2 pb-4 mb-4 border-b border-border">
                    <x-icon name="briefcase" class="w-5 h-5 text-primary" />
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">{{ $empresaAtual->nome_fantasia }}</h2>
                        <p class="text-xs text-muted-foreground">{{ $empresaAtual->razao_social }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <p class="text-muted-foreground">WhatsApp Comercial</p>
                        <p class="font-bold text-foreground text-sm mt-0.5">{{ $empresaAtual->whatsapp }}</p>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <p class="text-muted-foreground">Telefone Fixo</p>
                        <p class="font-bold text-foreground text-sm mt-0.5">{{ $empresaAtual->telefone ?: '-' }}</p>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <p class="text-muted-foreground">E-mail de Atendimento</p>
                        <p class="font-bold text-foreground text-sm mt-0.5">{{ $empresaAtual->email }}</p>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border sm:col-span-2">
                        <p class="text-muted-foreground">Endereço da Sede</p>
                        <p class="font-bold text-foreground text-sm mt-0.5">
                            {{ $empresaAtual->logradouro }}, {{ $empresaAtual->numero }}
                            {{ $empresaAtual->complemento ? ' - ' . $empresaAtual->complemento : '' }}
                            • {{ $empresaAtual->bairro }} - {{ $empresaAtual->cidade }}/{{ $empresaAtual->uf }} (CEP: {{ $empresaAtual->cep }})
                        </p>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <p class="text-muted-foreground">Horário de Atendimento</p>
                        <p class="font-bold text-foreground text-sm mt-0.5">{{ $empresaAtual->horario_atendimento ?: 'Segunda a Sábado, 08:00 às 18:00' }}</p>
                    </div>
                </div>
            </x-card>
        </div>
    @endif

    @if(!$isCliente)
        <!-- ABA 3: PREFERÊNCIAS & ALERTAS -->
        <div id="tab-preferencias" class="config-content space-y-6 hidden">
            <form action="#" method="POST" class="space-y-6">
                @csrf
                <x-card>
                    <div class="flex items-center gap-2 pb-4 mb-4 border-b border-border">
                        <x-icon name="bell" class="w-5 h-5 text-primary" />
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">Alertas e Notificações Operacionais</h2>
                            <p class="text-xs text-muted-foreground">Configure os disparos automáticos e avisos do sistema.</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 rounded-lg bg-secondary/20 border border-border">
                            <div class="space-y-0.5">
                                <label class="text-sm font-medium text-foreground">Alerta de Estoque Crítico</label>
                                <p class="text-xs text-muted-foreground">Exibir aviso no topo do dashboard quando um insumo estiver abaixo do estoque mínimo configurado.</p>
                            </div>
                            <input type="checkbox" name="alerta_estoque" checked {{ !$podeGerenciarEmpresa ? 'disabled' : '' }} class="w-5 h-5 rounded border-border text-primary focus:ring-primary bg-secondary cursor-pointer">
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-lg bg-secondary/20 border border-border">
                            <div class="space-y-0.5">
                                <label class="text-sm font-medium text-foreground">Aviso ao Cliente via WhatsApp (Agendamento Confirmado)</label>
                                <p class="text-xs text-muted-foreground">Gerar link ou disparo automático com os detalhes do agendamento e horário previsto de chegada.</p>
                            </div>
                            <input type="checkbox" name="notif_agendamento" checked {{ !$podeGerenciarEmpresa ? 'disabled' : '' }} class="w-5 h-5 rounded border-border text-primary focus:ring-primary bg-secondary cursor-pointer">
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-lg bg-secondary/20 border border-border">
                            <div class="space-y-0.5">
                                <label class="text-sm font-medium text-foreground">Aviso de Veículo Pronto (Ordem de Serviço Concluída)</label>
                                <p class="text-xs text-muted-foreground">Notificar o cliente assim que todas as tarefas forem finalizadas e a OS for encerrada pela oficina.</p>
                            </div>
                            <input type="checkbox" name="notif_os_concluida" checked {{ !$podeGerenciarEmpresa ? 'disabled' : '' }} class="w-5 h-5 rounded border-border text-primary focus:ring-primary bg-secondary cursor-pointer">
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <div class="flex items-center gap-2 pb-4 mb-4 border-b border-border">
                        <x-icon name="clock" class="w-5 h-5 text-primary" />
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">Parâmetros de Atendimento & Agenda</h2>
                            <p class="text-xs text-muted-foreground">Regras de agendamento de veículos.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-select name="intervalo_agenda" label="Grade de Horários" :disabled="!$podeGerenciarEmpresa">
                            <option value="15">A cada 15 minutos</option>
                            <option value="30" selected>A cada 30 minutos (Recomendado)</option>
                            <option value="60">A cada 1 hora</option>
                        </x-select>

                        <x-input type="number" name="capacidade_boxes" label="Boxes de Detalhamento Simultâneos" value="4" min="1" max="10" :disabled="!$podeGerenciarEmpresa" />

                        <x-select name="antecedencia_cancelamento" label="Antecedência Mínima para Cancelar" :disabled="!$podeGerenciarEmpresa">
                            <option value="2">2 horas antes</option>
                            <option value="12" selected>12 horas antes</option>
                            <option value="24">24 horas antes</option>
                        </x-select>
                    </div>
                </x-card>

                @if($podeGerenciarEmpresa)
                    <div class="flex justify-end">
                        <x-button type="submit" variant="primary" class="gap-2">
                            <x-icon name="check" class="w-4 h-4" />
                            Salvar Preferências
                        </x-button>
                    </div>
                @endif
            </form>
        </div>

        <!-- ABA 4: INFORMAÇÕES DO SISTEMA -->
        <div id="tab-sistema" class="config-content space-y-6 hidden">
            <x-card>
                <div class="flex items-center gap-2 pb-4 mb-4 border-b border-border">
                    <x-icon name="info" class="w-5 h-5 text-primary" />
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Especificações da Aplicação</h2>
                        <p class="text-xs text-muted-foreground">Ambiente de execução e tecnologias instaladas.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <p class="text-muted-foreground">Empresa Ativa</p>
                        <p class="font-bold text-foreground text-sm mt-0.5">{{ $empresaAtual?->nome_fantasia ?? 'Kraus Detail' }}</p>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <p class="text-muted-foreground">Versão da Plataforma</p>
                        <p class="font-bold text-foreground text-sm mt-0.5">v1.1.0 (Multi-Empresa & Roles)</p>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <p class="text-muted-foreground">Ambiente</p>
                        <span class="inline-flex items-center gap-1 font-semibold text-emerald-400 mt-0.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Produção Local / Desenvolvimento
                        </span>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <p class="text-muted-foreground">Framework Backend</p>
                        <p class="font-bold text-foreground text-sm mt-0.5">Laravel 12 / PHP 8.3</p>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <p class="text-muted-foreground">Motor de Renderização</p>
                        <p class="font-bold text-foreground text-sm mt-0.5">Blade Templates + Tailwind CSS v4</p>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <p class="text-muted-foreground">Controle de Acesso (RBAC)</p>
                        <p class="font-bold text-foreground text-sm mt-0.5">Admin Geral, Dono, Funcionário e Cliente</p>
                    </div>
                </div>
            </x-card>
        </div>
    @endif
</div>

@if($podeCriarEmpresa)
    <!-- Modal Cadastrar Nova Empresa -->
    <x-modal id="modal-nova-empresa" title="Cadastrar Nova Empresa" maxWidth="2xl">
        <form action="{{ route('configuracoes.empresas.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input name="nome_fantasia" label="Nome Fantasia" placeholder="Ex: Kraus Detail - Unidade 2" maxlength="100" required />
                <x-input name="razao_social" label="Razão Social" placeholder="Ex: Kraus Estética Automotiva LTDA" maxlength="120" required />
                <x-input name="cnpj" label="CNPJ" placeholder="00.000.000/0000-00" maxlength="18" />
                <x-input name="inscricao_estadual" label="Inscrição Estadual" placeholder="ISENTO" maxlength="20" />
                <x-input name="telefone" label="Telefone Fixo" placeholder="(11) 3214-5500" maxlength="15" />
                <x-input name="whatsapp" label="WhatsApp Comercial" placeholder="(11) 98765-4321" maxlength="15" required />
                <div class="sm:col-span-2">
                    <x-input type="email" name="email" label="E-mail Comercial" placeholder="contato@empresa.com.br" maxlength="100" required />
                </div>

                @if($isAdmin)
                    <div class="sm:col-span-2">
                        <x-select name="dono_id" label="Dono da Empresa">
                            <option value="">Selecione o dono responsável</option>
                            @foreach($donosDisponiveis as $donoOpcao)
                                <option value="{{ $donoOpcao->id }}">{{ $donoOpcao->name }} ({{ $donoOpcao->email }})</option>
                            @endforeach
                        </x-select>
                    </div>
                @endif

                <x-input name="cep" label="CEP" placeholder="00000-000" maxlength="9" />
                <x-input name="logradouro" label="Logradouro" placeholder="Avenida / Rua" maxlength="100" />
                <x-input name="numero" label="Número" placeholder="100" maxlength="10" />
                <x-input name="complemento" label="Complemento" placeholder="Galpão / Sala" maxlength="100" />
                <x-input name="bairro" label="Bairro" placeholder="Centro" maxlength="100" />
                <x-input name="cidade" label="Cidade" placeholder="São Paulo" maxlength="100" />
                <x-input name="uf" label="UF" placeholder="SP" maxlength="2" />
                <x-input name="horario_atendimento" label="Horário de Atendimento" value="Segunda a Sábado, 08:00 às 18:00" maxlength="100" />
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-border">
                <x-button type="button" variant="outline" onclick="closeModal('modal-nova-empresa')">
                    Cancelar
                </x-button>
                <x-button type="submit" variant="primary" class="gap-1.5">
                    <x-icon name="plus" class="w-4 h-4" />
                    Cadastrar Empresa
                </x-button>
            </div>
        </form>
    </x-modal>
@endif

@if($podeGerenciarUsuarios)
    <!-- Modal Novo Usuário -->
    <x-modal id="modal-novo-usuario" title="Cadastrar Novo Usuário">
        <form action="{{ route('configuracoes.usuarios.store') }}" method="POST" class="space-y-4">
            @csrf
            <x-input name="name" label="Nome Completo" placeholder="Ex: Rodrigo Fonseca" minlength="3" maxlength="100" required />
            <x-input type="email" name="email" label="E-mail" placeholder="usuario@krausdetail.com.br" maxlength="100" required />
            <x-input name="telefone" label="Telefone / WhatsApp" placeholder="(11) 99999-9999" maxlength="15" />

            <x-select name="tipo_usuario" label="Perfil de Acesso" required>
                <option value="funcionario" selected>Funcionário / Operador de Detalhamento</option>
                <option value="dono">Dono da Empresa (Gestão + Execução de Serviços)</option>
                @if($isAdmin)
                    <option value="administrador">Administrador Geral do Sistema</option>
                @endif
                <option value="cliente">Cliente (Acesso ao Portal)</option>
            </x-select>

            @if($isAdmin)
                <x-select name="empresa_id" label="Empresa Vinculada">
                    @foreach($empresas as $empOpcao)
                        <option value="{{ $empOpcao->id }}" {{ $empresaAtual && (int) $empresaAtual->id === (int) $empOpcao->id ? 'selected' : '' }}>
                            {{ $empOpcao->nome_fantasia }}
                        </option>
                    @endforeach
                </x-select>
            @endif

            <x-input name="cargo" label="Cargo / Especialidade (Para Dono ou Funcionário)" placeholder="Ex: Operador de Detalhamento" maxlength="80" />

            <x-input type="password" name="password" label="Senha Temporária (mín. 6 caracteres)" placeholder="••••••••" minlength="6" maxlength="64" required />

            <div class="flex justify-end gap-3 pt-4 border-t border-border">
                <x-button type="button" variant="outline" onclick="closeModal('modal-novo-usuario')">
                    Cancelar
                </x-button>
                <x-button type="submit" variant="primary" class="gap-1.5">
                    <x-icon name="user-plus" class="w-4 h-4" />
                    Criar Usuário
                </x-button>
            </div>
        </form>
    </x-modal>
@endif

<script>
function switchConfigTab(tab) {
    const tabs = ['empresa', 'equipe', 'perfil', 'oficina', 'preferencias', 'sistema'];

    tabs.forEach(t => {
        const content = document.getElementById(`tab-${t}`);
        const btn = document.getElementById(`tab-${t}-btn`);

        if (!content || !btn) return;

        if (t === tab) {
            content.classList.remove('hidden');
            btn.classList.add('border-primary', 'text-primary');
            btn.classList.remove('border-transparent', 'text-muted-foreground');
        } else {
            content.classList.add('hidden');
            btn.classList.remove('border-primary', 'text-primary');
            btn.classList.add('border-transparent', 'text-muted-foreground');
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const defaultTab = @json($tabPadrao);
    switchConfigTab(defaultTab);
});
</script>
@endsection
