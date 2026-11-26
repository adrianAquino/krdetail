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

    $tabsValidas = $isCliente
        ? ($empresaAtual ? ['perfil', 'oficina'] : ['perfil'])
        : ['empresa', 'equipe', 'perfil', 'preferencias', 'sistema'];
    $tabRequisitada = request()->string('tab')->toString();
    $tabPadrao = in_array($tabRequisitada, $tabsValidas, true)
        ? $tabRequisitada
        : ($isCliente ? 'perfil' : 'empresa');
@endphp

<div
    id="config-tabs-root"
    class="space-y-6"
    data-tabs-validas="{{ json_encode($tabsValidas) }}"
    data-tab-padrao="{{ $tabPadrao }}"
    data-modal-alvo-erro="{{ (string) old('_modal_target', '') }}"
>
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
                    {{ $isAdmin ? 'Listagem de Empresas' : 'Dados da Empresa' }}
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
        <!-- ABA 1: DADOS DA EMPRESA -->
        <div id="tab-empresa" class="config-content space-y-6 hidden">
            @if($isFuncionario)
                <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center gap-3 text-xs text-amber-400">
                    <x-icon name="info" class="w-4 h-4 shrink-0" />
                    <span>Modo somente leitura: como <strong>Funcionário</strong>, você pode consultar os dados da empresa, mas apenas o <strong>Dono da Empresa</strong> ou o <strong>Administrador Geral</strong> podem alterá-los.</span>
                </div>
            @endif

            @if($isAdmin)
                <!-- VISÃO ADMINISTRADOR GERAL: LISTAGEM DE EMPRESAS + CRUD VIA MODAIS -->
                <x-card>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
                        <div>
                            <h2 class="text-base font-semibold text-foreground flex items-center gap-2">
                                <x-icon name="briefcase" class="w-5 h-5 text-primary" />
                                Empresas Cadastradas no Sistema ({{ $empresas->count() }})
                            </h2>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                Visualize, cadastre, altere ou exclua empresas. A exclusão é bloqueada automaticamente se houver registros vinculados.
                            </p>
                        </div>

                        <x-button type="button" variant="primary" size="sm" onclick="openModal('modal-nova-empresa')" class="gap-1.5 self-start sm:self-auto">
                            <x-icon name="plus" class="w-4 h-4" />
                            Nova Empresa
                        </x-button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-secondary/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider border-b border-border">
                                <tr>
                                    <th class="px-4 py-3">Empresa</th>
                                    <th class="px-4 py-3">CNPJ / IE</th>
                                    <th class="px-4 py-3">Dono Responsável</th>
                                    <th class="px-4 py-3">Contato</th>
                                    <th class="px-4 py-3">Cidade / UF</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-xs">
                                @forelse($empresas as $empItem)
                                    <tr class="hover:bg-secondary/20 transition-colors">
                                        <td class="px-4 py-3.5">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0">
                                                    <x-icon name="briefcase" class="w-4 h-4" />
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-foreground text-sm">{{ $empItem->nome_fantasia }}</p>
                                                    <p class="text-[11px] text-muted-foreground truncate max-w-[260px]">{{ $empItem->razao_social }}</p>
                                                    <p class="text-[10px] text-muted-foreground/80 mt-0.5">
                                                        {{ $empItem->clientes_count ?? 0 }} cliente(s) • {{ $empItem->funcionarios_count ?? 0 }} colaborador(es) • {{ $empItem->servicos_count ?? 0 }} serviço(s)
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap">
                                            <p class="font-medium text-foreground">{{ $empItem->cnpj ?: 'Não informado' }}</p>
                                            <p class="text-[11px] text-muted-foreground">IE: {{ $empItem->inscricao_estadual ?: '-' }}</p>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            @if($empItem->dono)
                                                <p class="font-medium text-foreground">{{ $empItem->dono->name }}</p>
                                                <p class="text-[11px] text-muted-foreground">{{ $empItem->dono->email }}</p>
                                            @else
                                                <span class="text-muted-foreground italic">Não atribuído</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap">
                                            <p class="text-foreground">{{ $empItem->whatsapp }}</p>
                                            <p class="text-[11px] text-muted-foreground">{{ $empItem->email }}</p>
                                        </td>
                                        <td class="px-4 py-3.5 text-muted-foreground whitespace-nowrap">
                                            {{ $empItem->cidade ? $empItem->cidade . ($empItem->uf ? '/' . $empItem->uf : '') : '-' }}
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap">
                                            @if($empItem->ativo)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    Ativa
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-zinc-500/10 text-zinc-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-zinc-500"></span>
                                                    Inativa
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    onclick="openModal('modal-visualizar-empresa-{{ $empItem->id }}')"
                                                    class="p-1.5 rounded-md text-muted-foreground hover:text-primary hover:bg-primary/10 transition-colors cursor-pointer"
                                                    title="Visualizar Empresa"
                                                    aria-label="Visualizar {{ $empItem->nome_fantasia }}"
                                                >
                                                    <x-icon name="eye" class="w-4 h-4" />
                                                </button>

                                                <button
                                                    type="button"
                                                    onclick="openModal('modal-editar-empresa-{{ $empItem->id }}')"
                                                    class="p-1.5 rounded-md text-muted-foreground hover:text-foreground hover:bg-secondary transition-colors cursor-pointer"
                                                    title="Alterar Empresa"
                                                    aria-label="Alterar {{ $empItem->nome_fantasia }}"
                                                >
                                                    <x-icon name="edit" class="w-4 h-4" />
                                                </button>

                                                <form method="POST"
                                                      action="{{ route('configuracoes.empresas.destroy', $empItem->id) }}"
                                                      class="inline"
                                                      onsubmit="return confirm('Tem certeza que deseja excluir a empresa &quot;{{ addslashes($empItem->nome_fantasia) }}&quot;?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button
                                                        type="submit"
                                                        class="p-1.5 rounded-md text-muted-foreground hover:text-rose-400 hover:bg-rose-500/10 transition-colors cursor-pointer"
                                                        title="Excluir Empresa"
                                                        aria-label="Excluir {{ $empItem->nome_fantasia }}"
                                                    >
                                                        <x-icon name="trash-2" class="w-4 h-4" />
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">
                                            Nenhuma empresa cadastrada no sistema até o momento.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @else
                <!-- VISÃO DONO DA EMPRESA / FUNCIONÁRIO: APRESENTAÇÃO DOS DADOS DA EMPRESA -->
                @if($isDono && $empresas->count() > 1)
                    <x-card>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                            <div>
                                <h2 class="text-sm font-semibold text-foreground">Suas Estéticas ({{ $empresas->count() }})</h2>
                                <p class="text-xs text-muted-foreground">Alterne entre suas estéticas para operar e visualizar apenas os dados da unidade selecionada.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @php $empresaAtivaDonoId = $usuarioLogado->getEmpresaAtivaId(); @endphp
                            @foreach($empresas as $empItem)
                                @php
                                    $ehAtiva = (int) $empresaAtivaDonoId === (int) $empItem->id;
                                    $selecionada = $empresaAtual && (int) $empresaAtual->id === (int) $empItem->id;
                                @endphp
                                <div class="p-3 rounded-xl border transition-all flex flex-col justify-between gap-3 {{ $ehAtiva ? 'border-primary bg-primary/5 ring-1 ring-primary/30' : ($selecionada ? 'border-border bg-secondary/40' : 'border-border bg-secondary/20') }}">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-sm text-foreground truncate">{{ $empItem->nome_fantasia }}</p>
                                            <p class="text-[11px] text-muted-foreground truncate">{{ $empItem->cnpj ?: 'CNPJ não informado' }}</p>
                                        </div>
                                        @if($ehAtiva)
                                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 font-semibold shrink-0">Em operação</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-border/50">
                                        <a href="{{ route('configuracoes.index', ['tab' => 'empresa', 'empresa_id' => $empItem->id]) }}"
                                           class="text-[11px] font-medium text-muted-foreground hover:text-foreground underline">
                                            Ver ficha
                                        </a>
                                        @if(!$ehAtiva)
                                            <form method="POST" action="{{ route('configuracoes.empresas.selecionar', $empItem->id) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="redirect_to" value="configuracoes" />
                                                <button type="submit"
                                                        class="text-[11px] font-semibold px-2.5 py-1 rounded-lg bg-primary text-primary-foreground hover:bg-primary/90 transition-colors cursor-pointer">
                                                    Trocar para esta estética
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                @endif

                @if($empresaAtual)
                    <!-- Cartão de Apresentação Principal -->
                    <x-card>
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl bg-primary/15 border border-primary/25 flex items-center justify-center text-primary shrink-0">
                                    <x-icon name="briefcase" class="w-6 h-6" />
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-lg font-bold text-foreground">{{ $empresaAtual->nome_fantasia }}</h2>
                                        @if($empresaAtual->ativo)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                Ativa
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-zinc-500/10 text-zinc-400 border border-zinc-500/20">
                                                Inativa
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-sm text-muted-foreground mt-0.5">{{ $empresaAtual->razao_social }}</p>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-xs text-muted-foreground">
                                        <span><strong>Clientes:</strong> {{ $empresaAtual->clientes_count ?? 0 }}</span>
                                        <span><strong>Colaboradores:</strong> {{ $empresaAtual->funcionarios_count ?? 0 }}</span>
                                        <span><strong>Serviços:</strong> {{ $empresaAtual->servicos_count ?? 0 }}</span>
                                        <span><strong>Produtos:</strong> {{ $empresaAtual->produtos_count ?? 0 }}</span>
                                    </div>
                                </div>
                            </div>

                            @if($isDono)
                                <div class="flex flex-wrap items-center gap-2 self-start lg:self-center">
                                    <x-button type="button" variant="primary" size="sm" onclick="openModal('modal-editar-empresa-{{ $empresaAtual->id }}')" class="gap-1.5">
                                        <x-icon name="edit" class="w-4 h-4" />
                                        Editar Dados
                                    </x-button>

                                    <x-button type="button" variant="outline" size="sm" onclick="openModal('modal-nova-empresa')" class="gap-1.5">
                                        <x-icon name="plus" class="w-4 h-4" />
                                        Nova Empresa
                                    </x-button>

                                    <form method="POST"
                                          action="{{ route('configuracoes.empresas.destroy', $empresaAtual->id) }}"
                                          class="inline"
                                          onsubmit="return confirm('Tem certeza que deseja excluir esta empresa? A operação será bloqueada caso existam registros vinculados.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-400 border border-rose-500/30 bg-rose-500/10 hover:bg-rose-500/20 transition-colors cursor-pointer">
                                            <x-icon name="trash-2" class="w-4 h-4" />
                                            Excluir
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </x-card>

                    <!-- Blocos de Apresentação Detalhada -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <x-card>
                            <div class="flex items-center gap-2 pb-3 mb-4 border-b border-border">
                                <x-icon name="shield" class="w-5 h-5 text-primary" />
                                <div>
                                    <h3 class="text-sm font-semibold text-foreground">Identificação da Oficina</h3>
                                    <p class="text-xs text-muted-foreground">Dados exibidos em relatórios, agendamentos e ordens de serviço.</p>
                                </div>
                            </div>

                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                                    <dt class="text-muted-foreground">Nome Fantasia</dt>
                                    <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $empresaAtual->nome_fantasia }}</dd>
                                </div>
                                <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                                    <dt class="text-muted-foreground">Razão Social</dt>
                                    <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $empresaAtual->razao_social }}</dd>
                                </div>
                                <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                                    <dt class="text-muted-foreground">CNPJ</dt>
                                    <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $empresaAtual->cnpj ?: 'Não informado' }}</dd>
                                </div>
                                <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                                    <dt class="text-muted-foreground">Inscrição Estadual</dt>
                                    <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $empresaAtual->inscricao_estadual ?: 'Não informada' }}</dd>
                                </div>
                                <div class="p-3 rounded-lg bg-secondary/25 border border-border/60 sm:col-span-2">
                                    <dt class="text-muted-foreground">Dono / Responsável Principal</dt>
                                    <dd class="font-semibold text-foreground text-sm mt-0.5">
                                        {{ $empresaAtual->dono?->name ?? 'Não atribuído' }}
                                        @if($empresaAtual->dono?->email)
                                            <span class="text-xs font-normal text-muted-foreground">({{ $empresaAtual->dono->email }})</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </x-card>

                        <x-card>
                            <div class="flex items-center gap-2 pb-3 mb-4 border-b border-border">
                                <x-icon name="phone" class="w-5 h-5 text-primary" />
                                <div>
                                    <h3 class="text-sm font-semibold text-foreground">Canais de Contato & Atendimento</h3>
                                    <p class="text-xs text-muted-foreground">Meios oficiais para comunicação com clientes e fornecedores.</p>
                                </div>
                            </div>

                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                                    <dt class="text-muted-foreground">WhatsApp Comercial</dt>
                                    <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $empresaAtual->whatsapp }}</dd>
                                </div>
                                <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                                    <dt class="text-muted-foreground">Telefone Fixo</dt>
                                    <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $empresaAtual->telefone ?: 'Não informado' }}</dd>
                                </div>
                                <div class="p-3 rounded-lg bg-secondary/25 border border-border/60 sm:col-span-2">
                                    <dt class="text-muted-foreground">E-mail Comercial</dt>
                                    <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $empresaAtual->email }}</dd>
                                </div>
                                <div class="p-3 rounded-lg bg-secondary/25 border border-border/60 sm:col-span-2">
                                    <dt class="text-muted-foreground">Horário de Atendimento</dt>
                                    <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $empresaAtual->horario_atendimento ?: 'Não informado' }}</dd>
                                </div>
                            </dl>
                        </x-card>
                    </div>

                    <x-card>
                        <div class="flex items-center gap-2 pb-3 mb-4 border-b border-border">
                            <x-icon name="map-pin" class="w-5 h-5 text-primary" />
                            <div>
                                <h3 class="text-sm font-semibold text-foreground">Endereço da Sede</h3>
                                <p class="text-xs text-muted-foreground">Localização física para recepção dos veículos.</p>
                            </div>
                        </div>

                        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                            <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                                <dt class="text-muted-foreground">CEP</dt>
                                <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $empresaAtual->cep ?: '-' }}</dd>
                            </div>
                            <div class="p-3 rounded-lg bg-secondary/25 border border-border/60 sm:col-span-2">
                                <dt class="text-muted-foreground">Logradouro</dt>
                                <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $empresaAtual->logradouro ?: '-' }}</dd>
                            </div>
                            <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                                <dt class="text-muted-foreground">Número</dt>
                                <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $empresaAtual->numero ?: '-' }}</dd>
                            </div>
                            <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                                <dt class="text-muted-foreground">Complemento</dt>
                                <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $empresaAtual->complemento ?: '-' }}</dd>
                            </div>
                            <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                                <dt class="text-muted-foreground">Bairro</dt>
                                <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $empresaAtual->bairro ?: '-' }}</dd>
                            </div>
                            <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                                <dt class="text-muted-foreground">Cidade</dt>
                                <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $empresaAtual->cidade ?: '-' }}</dd>
                            </div>
                            <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                                <dt class="text-muted-foreground">UF</dt>
                                <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $empresaAtual->uf ?: '-' }}</dd>
                            </div>
                        </dl>
                    </x-card>
                @else
                    <x-card>
                        <div class="py-10 text-center space-y-3">
                            <p class="text-sm text-muted-foreground">Nenhuma empresa vinculada ao seu perfil ainda.</p>
                            @if($podeCriarEmpresa)
                                <x-button type="button" variant="primary" size="sm" onclick="openModal('modal-nova-empresa')" class="gap-1.5">
                                    <x-icon name="plus" class="w-4 h-4" />
                                    Nova Empresa
                                </x-button>
                            @endif
                        </div>
                    </x-card>
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

                @if($isAdmin)
                    <x-admin-empresa-filter
                        :action="route('configuracoes.index')"
                        :empresas="$empresasFiltro ?? collect()"
                        :empresaSelecionadaId="$empresaFiltroEquipeId ?? null"
                        :preserve="['tab' => 'equipe']"
                        label="Filtrar Equipe por Empresa"
                    />
                @endif

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
                                            {{ mb_strtoupper(mb_substr($user['nome'], 0, 2, 'UTF-8'), 'UTF-8') }}
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
                                    @php
                                        $podeEditarLinha = $podeGerenciarUsuarios
                                            && ($isAdmin ? $user['tipo'] !== 'administrador' : !in_array($user['tipo'], ['administrador', 'dono'], true));
                                    @endphp
                                    @if((int) $user['id'] === (int) $usuarioLogado->id)
                                        <button type="button"
                                                onclick="switchConfigTab('perfil')"
                                                class="text-[11px] font-semibold text-primary hover:underline cursor-pointer">
                                            Editar Meu Perfil
                                        </button>
                                    @elseif($podeEditarLinha)
                                        <button
                                            type="button"
                                            onclick="openModal('modal-editar-usuario-{{ $user['id'] }}')"
                                            class="p-1 text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                            title="Editar Usuário"
                                        >
                                            <x-icon name="edit" class="w-4 h-4" />
                                        </button>

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
                                                <x-icon name="trash-2" class="w-4 h-4" />
                                            </button>
                                        </form>
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
        <!-- Cartão de Apresentação Principal do Perfil -->
        <x-card>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-full bg-primary/15 border border-primary/25 flex items-center justify-center text-primary font-bold text-base shrink-0">
                        {{ mb_strtoupper(mb_substr($usuarioLogado->name, 0, 2, 'UTF-8'), 'UTF-8') }}
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-bold text-foreground">{{ $usuarioLogado->name }}</h2>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-primary/10 text-primary border border-primary/20">
                                <x-icon name="shield" class="w-3 h-3" />
                                {{ $usuarioLogado->role_label }}
                            </span>
                        </div>
                        <p class="text-sm text-muted-foreground mt-0.5">{{ $usuarioLogado->email }}</p>
                        @if($usuarioLogado->empresa)
                            <p class="text-xs text-muted-foreground mt-1">
                                <strong>Empresa Vinculada:</strong> {{ $usuarioLogado->empresa->nome_fantasia }}
                            </p>
                        @endif
                    </div>
                </div>

                <div class="self-start sm:self-center">
                    <x-button type="button" variant="primary" size="sm" onclick="openModal('modal-editar-meu-perfil')" class="gap-1.5">
                        <x-icon name="edit" class="w-4 h-4" />
                        Editar Meu Perfil
                    </x-button>
                </div>
            </div>
        </x-card>

        <!-- Blocos de Apresentação (Somente Leitura) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card>
                <div class="flex items-center gap-2 pb-3 mb-4 border-b border-border">
                    <x-icon name="user" class="w-5 h-5 text-primary" />
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">Meus Dados Pessoais</h3>
                        <p class="text-xs text-muted-foreground">Informações de identificação e contato cadastradas.</p>
                    </div>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                        <dt class="text-muted-foreground">Nome Completo</dt>
                        <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $usuarioLogado->name }}</dd>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                        <dt class="text-muted-foreground">CPF / CNPJ</dt>
                        <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $cpfCnpjAtual ?: 'Não informado' }}</dd>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                        <dt class="text-muted-foreground">E-mail de Acesso</dt>
                        <dd class="font-semibold text-foreground text-sm mt-0.5 break-all">{{ $usuarioLogado->email }}</dd>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                        <dt class="text-muted-foreground">Telefone / WhatsApp</dt>
                        <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $usuarioLogado->telefone ?: 'Não informado' }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card>
                <div class="flex items-center gap-2 pb-3 mb-4 border-b border-border">
                    <x-icon name="map-pin" class="w-5 h-5 text-primary" />
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">Meu Endereço</h3>
                        <p class="text-xs text-muted-foreground">Endereço sincronizado automaticamente com seu cadastro unificado.</p>
                    </div>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                        <dt class="text-muted-foreground">CEP</dt>
                        <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $cepAtual ?: '-' }}</dd>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/25 border border-border/60 sm:col-span-2">
                        <dt class="text-muted-foreground">Logradouro</dt>
                        <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $logradouroAtual ?: '-' }}</dd>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                        <dt class="text-muted-foreground">Número</dt>
                        <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $numeroAtual ?: '-' }}</dd>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                        <dt class="text-muted-foreground">Complemento</dt>
                        <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $complementoAtual ?: '-' }}</dd>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                        <dt class="text-muted-foreground">Bairro</dt>
                        <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $bairroAtual ?: '-' }}</dd>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/25 border border-border/60 sm:col-span-2">
                        <dt class="text-muted-foreground">Cidade</dt>
                        <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $cidadeAtual ?: '-' }}</dd>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/25 border border-border/60">
                        <dt class="text-muted-foreground">UF</dt>
                        <dd class="font-semibold text-foreground text-sm mt-0.5">{{ $estadoAtual ?: '-' }}</dd>
                    </div>
                </dl>
            </x-card>
        </div>

        @if($isCliente)
            @php
                $historicoLista = $historicoEmpresas ?? collect();
            @endphp
            <x-card>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-3 mb-4 border-b border-border">
                    <div class="flex items-center gap-2">
                        <x-icon name="briefcase" class="w-5 h-5 text-primary" />
                        <div>
                            <h3 class="text-sm font-semibold text-foreground">Vínculo com Estética Automotiva &amp; Histórico</h3>
                            <p class="text-xs text-muted-foreground">Acompanhe a estética à qual sua conta está vinculada atualmente e seu histórico anterior.</p>
                        </div>
                    </div>
                    @if($empresaAtual)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 self-start sm:self-auto">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Vinculado atualmente
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20 self-start sm:self-auto">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            Sem vínculo ativo no momento
                        </span>
                    @endif
                </div>

                <div class="space-y-4">
                    <div class="p-3.5 rounded-xl border {{ $empresaAtual ? 'border-primary/30 bg-primary/5' : 'border-amber-500/30 bg-amber-500/5' }}">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-1">Estética Vinculada Atualmente</p>
                        @if($empresaAtual)
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div>
                                    <p class="font-bold text-sm text-foreground">{{ $empresaAtual->nome_fantasia }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ $empresaAtual->razao_social }}
                                        @if($empresaAtual->cidade)
                                            • {{ $empresaAtual->cidade }}{{ $empresaAtual->uf ? '/' . $empresaAtual->uf : '' }}
                                        @endif
                                    </p>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    <span>WhatsApp: <strong class="text-foreground">{{ $empresaAtual->whatsapp ?: '-' }}</strong></span>
                                </div>
                            </div>
                        @else
                            <p class="text-sm font-semibold text-foreground">Nenhuma estética vinculada no momento</p>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                Você pode gerenciar seus veículos e consultar serviços já realizados, mas só poderá solicitar novos agendamentos quando um dono de estética cadastrar ou vincular seus dados.
                            </p>
                        @endif
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2.5">Histórico de Vínculos com Estéticas</h4>
                        @if($historicoLista->isNotEmpty())
                            <div class="space-y-2">
                                @foreach($historicoLista as $hist)
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 p-3 rounded-lg bg-secondary/25 border border-border/60 text-xs">
                                        <div>
                                            <p class="font-semibold text-foreground text-sm">
                                                {{ $hist->empresa?->nome_fantasia ?? 'Estética #' . $hist->empresa_id }}
                                            </p>
                                            <p class="text-muted-foreground">
                                                Vinculado em {{ $hist->vinculado_em?->format('d/m/Y H:i') ?? '-' }}
                                                @if($hist->desvinculado_em)
                                                    • Encerrado em {{ $hist->desvinculado_em->format('d/m/Y H:i') }}
                                                @endif
                                            </p>
                                        </div>
                                        @if($hist->desvinculado_em === null)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 self-start sm:self-auto">
                                                Vínculo Ativo
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-zinc-500/10 text-zinc-400 border border-zinc-500/20 self-start sm:self-auto">
                                                Vínculo Anterior
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-muted-foreground italic p-3 rounded-lg bg-secondary/20 border border-border/50">
                                Você ainda não esteve vinculado a nenhuma estética automotiva.
                            </p>
                        @endif
                    </div>
                </div>
            </x-card>
        @endif
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
        <!-- ABA 3: PREFERÊNCIAS & ALERTAS (SOMENTE LEITURA) -->
        <div id="tab-preferencias" class="config-content space-y-6 hidden">
            <x-card>
                <div class="flex items-center justify-between gap-2 pb-4 mb-4 border-b border-border">
                    <div class="flex items-center gap-2">
                        <x-icon name="bell" class="w-5 h-5 text-primary" />
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">Alertas e Notificações Operacionais</h2>
                            <p class="text-xs text-muted-foreground">Disparos e avisos automáticos ativos por padrão no sistema.</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-secondary text-muted-foreground border border-border">
                        Padrão do Sistema
                    </span>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="flex items-center justify-between p-3.5 rounded-lg bg-secondary/20 border border-border">
                        <div class="space-y-0.5 pr-4">
                            <p class="text-sm font-medium text-foreground">Alerta de Estoque Crítico</p>
                            <p class="text-muted-foreground">Exibe aviso no dashboard quando um insumo ativo está abaixo ou igual ao estoque mínimo configurado.</p>
                        </div>
                        <span class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            Ativo
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-3.5 rounded-lg bg-secondary/20 border border-border">
                        <div class="space-y-0.5 pr-4">
                            <p class="text-sm font-medium text-foreground">Registro de Notificações Operacionais</p>
                            <p class="text-muted-foreground">Registra automaticamente notificações para o cliente no histórico do sistema quando o serviço é concluído.</p>
                        </div>
                        <span class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            Ativo
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-3.5 rounded-lg bg-secondary/20 border border-border">
                        <div class="space-y-0.5 pr-4">
                            <p class="text-sm font-medium text-foreground">Estorno Automático de Insumos em Cancelamentos</p>
                            <p class="text-muted-foreground">Devolve ao estoque os produtos já lançados caso o agendamento seja cancelado antes da conclusão das tarefas.</p>
                        </div>
                        <span class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            Ativo
                        </span>
                    </div>
                </div>
            </x-card>

            <x-card>
                <div class="flex items-center gap-2 pb-4 mb-4 border-b border-border">
                    <x-icon name="clock" class="w-5 h-5 text-primary" />
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Parâmetros de Atendimento & Agenda</h2>
                        <p class="text-xs text-muted-foreground">Regras operacionais fixas aplicadas no controle de agendamentos e capacidade da oficina.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                    <div class="p-3.5 rounded-lg bg-secondary/20 border border-border">
                        <p class="text-muted-foreground">Horário Comercial</p>
                        <p class="font-bold text-foreground text-sm mt-1">Seg a Sáb, 08:00 às 18:00</p>
                    </div>

                    <div class="p-3.5 rounded-lg bg-secondary/20 border border-border">
                        <p class="text-muted-foreground">Boxes Simultâneos (Capacidade)</p>
                        <p class="font-bold text-foreground text-sm mt-1">4 boxes simultâneos</p>
                    </div>

                    <div class="p-3.5 rounded-lg bg-secondary/20 border border-border">
                        <p class="text-muted-foreground">Gargalo Operacional Padrão</p>
                        <p class="font-bold text-foreground text-sm mt-1">+30 minutos por atendimento</p>
                    </div>
                </div>
            </x-card>
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

@if(!$isCliente)
    @foreach($empresas as $empItem)
        @php
            $podeEditarEstaEmpresa = $usuarioLogado && $usuarioLogado->canManageEmpresa($empItem);
        @endphp

        <!-- Modal Visualizar Empresa -->
        <x-modal id="modal-visualizar-empresa-{{ $empItem->id }}" :title="'Detalhes da Empresa: ' . $empItem->nome_fantasia" maxWidth="3xl">
            <div class="space-y-5 text-xs">
                <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-border">
                    <div>
                        <p class="text-sm font-bold text-foreground">{{ $empItem->nome_fantasia }}</p>
                        <p class="text-xs text-muted-foreground">{{ $empItem->razao_social }}</p>
                    </div>
                    @if($empItem->ativo)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Ativa
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-zinc-500/10 text-zinc-400 border border-zinc-500/20">
                            Inativa
                        </span>
                    @endif
                </div>

                <!-- Resumo de Vínculos -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <span class="text-muted-foreground block">Clientes</span>
                        <span class="font-bold text-foreground text-base">{{ $empItem->clientes_count ?? 0 }}</span>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <span class="text-muted-foreground block">Colaboradores</span>
                        <span class="font-bold text-foreground text-base">{{ $empItem->funcionarios_count ?? 0 }}</span>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <span class="text-muted-foreground block">Serviços</span>
                        <span class="font-bold text-foreground text-base">{{ $empItem->servicos_count ?? 0 }}</span>
                    </div>
                    <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                        <span class="text-muted-foreground block">Produtos</span>
                        <span class="font-bold text-foreground text-base">{{ $empItem->produtos_count ?? 0 }}</span>
                    </div>
                </div>

                <!-- Identificação da Oficina -->
                <div class="space-y-2.5">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-primary flex items-center gap-1.5">
                        <x-icon name="shield" class="w-3.5 h-3.5" />
                        Identificação da Oficina
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="p-2.5 rounded-lg bg-secondary/20 border border-border/60">
                            <span class="text-muted-foreground block">CNPJ</span>
                            <span class="font-semibold text-foreground">{{ $empItem->cnpj ?: 'Não informado' }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-secondary/20 border border-border/60">
                            <span class="text-muted-foreground block">Inscrição Estadual</span>
                            <span class="font-semibold text-foreground">{{ $empItem->inscricao_estadual ?: '-' }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-secondary/20 border border-border/60 sm:col-span-2">
                            <span class="text-muted-foreground block">Dono / Responsável Principal</span>
                            <span class="font-semibold text-foreground">
                                {{ $empItem->dono?->name ?? 'Não atribuído' }}
                                @if($empItem->dono?->email)
                                    ({{ $empItem->dono->email }})
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Canais de Contato -->
                <div class="space-y-2.5">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-primary flex items-center gap-1.5">
                        <x-icon name="phone" class="w-3.5 h-3.5" />
                        Canais de Contato
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="p-2.5 rounded-lg bg-secondary/20 border border-border/60">
                            <span class="text-muted-foreground block">Telefone Fixo</span>
                            <span class="font-semibold text-foreground">{{ $empItem->telefone ?: '-' }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-secondary/20 border border-border/60">
                            <span class="text-muted-foreground block">WhatsApp Comercial</span>
                            <span class="font-semibold text-foreground">{{ $empItem->whatsapp }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-secondary/20 border border-border/60">
                            <span class="text-muted-foreground block">E-mail Comercial</span>
                            <span class="font-semibold text-foreground break-all">{{ $empItem->email }}</span>
                        </div>
                    </div>
                </div>

                <!-- Endereço da Sede -->
                <div class="space-y-2.5">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-primary flex items-center gap-1.5">
                        <x-icon name="map-pin" class="w-3.5 h-3.5" />
                        Endereço da Sede & Horário
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="p-2.5 rounded-lg bg-secondary/20 border border-border/60">
                            <span class="text-muted-foreground block">CEP</span>
                            <span class="font-semibold text-foreground">{{ $empItem->cep ?: '-' }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-secondary/20 border border-border/60 sm:col-span-2">
                            <span class="text-muted-foreground block">Logradouro</span>
                            <span class="font-semibold text-foreground">{{ $empItem->logradouro ?: '-' }}, {{ $empItem->numero ?: 'S/N' }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-secondary/20 border border-border/60">
                            <span class="text-muted-foreground block">Complemento</span>
                            <span class="font-semibold text-foreground">{{ $empItem->complemento ?: '-' }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-secondary/20 border border-border/60">
                            <span class="text-muted-foreground block">Bairro</span>
                            <span class="font-semibold text-foreground">{{ $empItem->bairro ?: '-' }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-secondary/20 border border-border/60">
                            <span class="text-muted-foreground block">Cidade / UF</span>
                            <span class="font-semibold text-foreground">{{ $empItem->cidade ?: '-' }}{{ $empItem->uf ? '/' . $empItem->uf : '' }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-secondary/20 border border-border/60 sm:col-span-3">
                            <span class="text-muted-foreground block">Horário de Atendimento</span>
                            <span class="font-semibold text-foreground">{{ $empItem->horario_atendimento ?: '-' }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-border">
                    <x-button type="button" variant="outline" onclick="closeModal('modal-visualizar-empresa-{{ $empItem->id }}')">
                        Fechar
                    </x-button>
                    @if($podeEditarEstaEmpresa)
                        <x-button type="button" variant="primary" class="gap-1.5" onclick="closeModal('modal-visualizar-empresa-{{ $empItem->id }}'); openModal('modal-editar-empresa-{{ $empItem->id }}');">
                            <x-icon name="edit" class="w-4 h-4" />
                            Alterar Dados
                        </x-button>
                    @endif
                </div>
            </div>
        </x-modal>

        @if($podeEditarEstaEmpresa)
            <!-- Modal Editar Empresa -->
            <x-modal id="modal-editar-empresa-{{ $empItem->id }}" :title="'Alterar Empresa: ' . $empItem->nome_fantasia" maxWidth="3xl">
                <form action="{{ route('configuracoes.empresas.update', $empItem->id) }}" method="POST" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div class="space-y-3">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-primary flex items-center gap-1.5 pb-1 border-b border-border/60">
                            <x-icon name="shield" class="w-3.5 h-3.5" />
                            Identificação da Oficina
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-input :id="'edit_emp_' . $empItem->id . '_nome_fantasia'" name="nome_fantasia" label="Nome Fantasia" :value="$empItem->nome_fantasia" maxlength="100" required />
                            <x-input :id="'edit_emp_' . $empItem->id . '_razao_social'" name="razao_social" label="Razão Social" :value="$empItem->razao_social" maxlength="120" required />
                            <x-input :id="'edit_emp_' . $empItem->id . '_cnpj'" name="cnpj" label="CNPJ" :value="$empItem->cnpj" maxlength="18" placeholder="00.000.000/0000-00" />
                            <x-input :id="'edit_emp_' . $empItem->id . '_inscricao_estadual'" name="inscricao_estadual" label="Inscrição Estadual" :value="$empItem->inscricao_estadual" maxlength="20" />

                            @if($isAdmin)
                                <div class="sm:col-span-2">
                                    <x-select :id="'edit_emp_' . $empItem->id . '_dono_id'" name="dono_id" label="Dono da Empresa (Responsável Principal)" placeholder="Selecione o dono responsável">
                                        @foreach($donosDisponiveis as $donoOpcao)
                                            <option value="{{ $donoOpcao->id }}" {{ (int) $empItem->dono_id === (int) $donoOpcao->id ? 'selected' : '' }}>
                                                {{ $donoOpcao->name }} ({{ $donoOpcao->email }} - {{ $donoOpcao->role_label }})
                                            </option>
                                        @endforeach
                                    </x-select>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-primary flex items-center gap-1.5 pb-1 border-b border-border/60">
                            <x-icon name="phone" class="w-3.5 h-3.5" />
                            Canais de Contato
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <x-input :id="'edit_emp_' . $empItem->id . '_telefone'" name="telefone" label="Telefone Fixo" :value="$empItem->telefone" maxlength="15" />
                            <x-input :id="'edit_emp_' . $empItem->id . '_whatsapp'" name="whatsapp" label="WhatsApp Comercial" :value="$empItem->whatsapp" maxlength="15" required />
                            <x-input :id="'edit_emp_' . $empItem->id . '_email'" type="email" name="email" label="E-mail Comercial" :value="$empItem->email" maxlength="100" required />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-primary flex items-center gap-1.5 pb-1 border-b border-border/60">
                            <x-icon name="map-pin" class="w-3.5 h-3.5" />
                            Endereço da Sede
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <x-input :id="'edit_emp_' . $empItem->id . '_cep'" name="cep" label="CEP" :value="$empItem->cep" maxlength="9" placeholder="00000-000" onblur="window.consultarViaCepInput(this);" />
                            <div class="sm:col-span-2">
                                <x-input :id="'edit_emp_' . $empItem->id . '_logradouro'" name="logradouro" label="Logradouro" :value="$empItem->logradouro" maxlength="100" />
                            </div>
                            <x-input :id="'edit_emp_' . $empItem->id . '_numero'" name="numero" label="Número" :value="$empItem->numero" maxlength="10" />
                            <x-input :id="'edit_emp_' . $empItem->id . '_complemento'" name="complemento" label="Complemento" :value="$empItem->complemento" maxlength="100" />
                            <x-input :id="'edit_emp_' . $empItem->id . '_bairro'" name="bairro" label="Bairro" :value="$empItem->bairro" maxlength="100" />
                            <x-input :id="'edit_emp_' . $empItem->id . '_cidade'" name="cidade" label="Cidade" :value="$empItem->cidade" maxlength="100" />
                            <x-input :id="'edit_emp_' . $empItem->id . '_uf'" name="uf" label="UF" :value="$empItem->uf" maxlength="2" />
                            <x-input :id="'edit_emp_' . $empItem->id . '_horario_atendimento'" name="horario_atendimento" label="Horário de Atendimento" :value="$empItem->horario_atendimento" maxlength="100" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-border">
                        <x-button type="button" variant="outline" onclick="closeModal('modal-editar-empresa-{{ $empItem->id }}')">
                            Cancelar
                        </x-button>
                        <x-button type="submit" variant="primary" class="gap-1.5">
                            <x-icon name="save" class="w-4 h-4" />
                            Salvar Alterações
                        </x-button>
                    </div>
                </form>
            </x-modal>
        @endif
    @endforeach
@endif

@if($podeCriarEmpresa)
    <!-- Modal Cadastrar Nova Empresa -->
    <x-modal id="modal-nova-empresa" title="Cadastrar Nova Empresa" maxWidth="3xl">
        <form action="{{ route('configuracoes.empresas.store') }}" method="POST" class="space-y-5">
            @csrf

            <div class="space-y-3">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-primary flex items-center gap-1.5 pb-1 border-b border-border/60">
                    <x-icon name="shield" class="w-3.5 h-3.5" />
                    Identificação da Oficina
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-input id="nova_emp_nome_fantasia" name="nome_fantasia" label="Nome Fantasia" placeholder="Ex: Kraus Detail - Unidade 2" maxlength="100" required />
                    <x-input id="nova_emp_razao_social" name="razao_social" label="Razão Social" placeholder="Ex: Kraus Estética Automotiva LTDA" maxlength="120" required />
                    <x-input id="nova_emp_cnpj" name="cnpj" label="CNPJ" placeholder="00.000.000/0000-00" maxlength="18" />
                    <x-input id="nova_emp_inscricao_estadual" name="inscricao_estadual" label="Inscrição Estadual" placeholder="ISENTO" maxlength="20" />

                    @if($isAdmin)
                        <div class="sm:col-span-2">
                            <x-select id="nova_emp_dono_id" name="dono_id" label="Dono da Empresa (Responsável Principal)" placeholder="Selecione o dono responsável">
                                @foreach($donosDisponiveis as $donoOpcao)
                                    <option value="{{ $donoOpcao->id }}">{{ $donoOpcao->name }} ({{ $donoOpcao->email }} - {{ $donoOpcao->role_label }})</option>
                                @endforeach
                            </x-select>
                        </div>
                    @endif
                </div>
            </div>

            <div class="space-y-3">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-primary flex items-center gap-1.5 pb-1 border-b border-border/60">
                    <x-icon name="phone" class="w-3.5 h-3.5" />
                    Canais de Contato
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <x-input id="nova_emp_telefone" name="telefone" label="Telefone Fixo" placeholder="(11) 3214-5500" maxlength="15" />
                    <x-input id="nova_emp_whatsapp" name="whatsapp" label="WhatsApp Comercial" placeholder="(11) 98765-4321" maxlength="15" required />
                    <x-input id="nova_emp_email" type="email" name="email" label="E-mail Comercial" placeholder="contato@empresa.com.br" maxlength="100" required />
                </div>
            </div>

            <div class="space-y-3">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-primary flex items-center gap-1.5 pb-1 border-b border-border/60">
                    <x-icon name="map-pin" class="w-3.5 h-3.5" />
                    Endereço da Sede
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <x-input id="nova_emp_cep" name="cep" label="CEP" placeholder="00000-000" maxlength="9" onblur="window.consultarViaCepInput(this);" />
                    <div class="sm:col-span-2">
                        <x-input id="nova_emp_logradouro" name="logradouro" label="Logradouro" placeholder="Avenida / Rua" maxlength="100" />
                    </div>
                    <x-input id="nova_emp_numero" name="numero" label="Número" placeholder="100" maxlength="10" />
                    <x-input id="nova_emp_complemento" name="complemento" label="Complemento" placeholder="Galpão / Sala" maxlength="100" />
                    <x-input id="nova_emp_bairro" name="bairro" label="Bairro" placeholder="Centro" maxlength="100" />
                    <x-input id="nova_emp_cidade" name="cidade" label="Cidade" placeholder="São Paulo" maxlength="100" />
                    <x-input id="nova_emp_uf" name="uf" label="UF" placeholder="SP" maxlength="2" />
                    <x-input id="nova_emp_horario_atendimento" name="horario_atendimento" label="Horário de Atendimento" value="Segunda a Sábado, 08:00 às 18:00" maxlength="100" />
                </div>
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

<!-- Modal Editar Meu Perfil (Cliente, Funcionário, Dono e Administrador) -->
<x-modal id="modal-editar-meu-perfil" title="Editar Meu Perfil" maxWidth="3xl">
    @php $isModalPerfil = old('_modal_target') === 'editar-meu-perfil'; @endphp
    <form action="{{ route('configuracoes.perfil.update') }}" method="POST" class="space-y-5">
        @csrf
        @method('PUT')
        <input type="hidden" name="_modal_target" value="editar-meu-perfil" />

        <div class="space-y-3">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-primary flex items-center gap-1.5 pb-1 border-b border-border/60">
                <x-icon name="user" class="w-3.5 h-3.5" />
                Meus Dados Pessoais
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input id="meu_perfil_name" name="name" label="Nome Completo" :value="$isModalPerfil ? old('name', $usuarioLogado->name) : $usuarioLogado->name" minlength="3" maxlength="100" required />
                <x-input id="meu_perfil_cpf_cnpj" name="cpf_cnpj" label="CPF / CNPJ" :value="$isModalPerfil ? old('cpf_cnpj', $cpfCnpjAtual) : $cpfCnpjAtual" maxlength="18" placeholder="000.000.000-00" />
                <x-input id="meu_perfil_email" type="email" name="email" label="E-mail de Acesso" :value="$isModalPerfil ? old('email', $usuarioLogado->email) : $usuarioLogado->email" maxlength="100" required />
                <x-input id="meu_perfil_telefone" name="telefone" label="Telefone / WhatsApp" :value="$isModalPerfil ? old('telefone', $usuarioLogado->telefone) : $usuarioLogado->telefone" maxlength="15" placeholder="(11) 99999-9999" />
            </div>
        </div>

        <div class="space-y-3">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-primary flex items-center gap-1.5 pb-1 border-b border-border/60">
                <x-icon name="map-pin" class="w-3.5 h-3.5" />
                Meu Endereço
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-input id="meu_perfil_cep" name="cep" label="CEP" :value="$isModalPerfil ? old('cep', $cepAtual) : $cepAtual" maxlength="9" placeholder="00000-000" onblur="window.consultarViaCepInput(this);" />
                <div class="sm:col-span-2">
                    <x-input id="meu_perfil_logradouro" name="logradouro" label="Logradouro" :value="$isModalPerfil ? old('logradouro', $logradouroAtual) : $logradouroAtual" maxlength="100" />
                </div>
                <x-input id="meu_perfil_numero" name="numero" label="Número" :value="$isModalPerfil ? old('numero', $numeroAtual) : $numeroAtual" maxlength="10" />
                <x-input id="meu_perfil_complemento" name="complemento" label="Complemento" :value="$isModalPerfil ? old('complemento', $complementoAtual) : $complementoAtual" maxlength="100" />
                <x-input id="meu_perfil_bairro" name="bairro" label="Bairro" :value="$isModalPerfil ? old('bairro', $bairroAtual) : $bairroAtual" maxlength="100" />
                <x-input id="meu_perfil_cidade" name="cidade" label="Cidade" :value="$isModalPerfil ? old('cidade', $cidadeAtual) : $cidadeAtual" maxlength="100" />
                <x-input id="meu_perfil_estado" name="estado" label="UF" :value="$isModalPerfil ? old('estado', $estadoAtual) : $estadoAtual" maxlength="2" placeholder="SP" />
            </div>
        </div>

        <div class="space-y-3">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-primary flex items-center gap-1.5 pb-1 border-b border-border/60">
                <x-icon name="shield" class="w-3.5 h-3.5" />
                Segurança & Senha
            </h4>
            <p class="text-xs text-muted-foreground">Preencha os campos abaixo apenas se desejar alterar sua senha atual.</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-input id="meu_perfil_current_password" type="password" name="current_password" label="Senha Atual" maxlength="64" placeholder="••••••••" />
                <x-input id="meu_perfil_password" type="password" name="password" label="Nova Senha" minlength="8" maxlength="64" placeholder="Mín. 8 caracteres" />
                <x-input id="meu_perfil_password_confirmation" type="password" name="password_confirmation" label="Confirmar Nova Senha" minlength="8" maxlength="64" placeholder="Repita a senha" />
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-border">
            <x-button type="button" variant="outline" onclick="closeModal('modal-editar-meu-perfil')">
                Cancelar
            </x-button>
            <x-button type="submit" variant="primary" class="gap-1.5">
                <x-icon name="save" class="w-4 h-4" />
                Salvar Meu Perfil
            </x-button>
        </div>
    </form>
</x-modal>

@if($podeGerenciarUsuarios)
    <!-- Modais de Edição de Usuário (renderizados fora da tabela HTML para estrutura válida) -->
    @foreach(($usuarios ?? collect()) as $user)
        @php
            $podeRenderModalEdit = (int) $user['id'] !== (int) $usuarioLogado->id
                && ($isAdmin ? $user['tipo'] !== 'administrador' : !in_array($user['tipo'], ['administrador', 'dono'], true));
        @endphp
        @if($podeRenderModalEdit)
            @php $isModalEditUser = old('_modal_target') === 'editar-usuario-' . $user['id']; @endphp
            <x-modal id="modal-editar-usuario-{{ $user['id'] }}" :title="'Editar Usuário: ' . $user['nome']">
                <form action="{{ route('configuracoes.usuarios.update', $user['id']) }}" method="POST" class="space-y-4 text-left">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_modal_target" value="editar-usuario-{{ $user['id'] }}" />
                    <x-input :id="'edit_user_name_' . $user['id']" name="name" label="Nome Completo" :value="$isModalEditUser ? old('name', $user['nome']) : $user['nome']" minlength="3" maxlength="100" required />
                    <x-input :id="'edit_user_email_' . $user['id']" type="email" name="email" label="E-mail" :value="$isModalEditUser ? old('email', $user['email']) : $user['email']" maxlength="100" required />
                    <x-input :id="'edit_user_telefone_' . $user['id']" name="telefone" label="Telefone / WhatsApp" :value="$isModalEditUser ? old('telefone', '') : ($user['telefone'] !== '-' ? $user['telefone'] : '')" maxlength="15" />

                    @php $tipoUserModal = $isModalEditUser ? old('tipo_usuario', $user['tipo']) : $user['tipo']; @endphp
                    <x-select :id="'edit_user_tipo_' . $user['id']" name="tipo_usuario" label="Perfil de Acesso" required>
                        @if($isAdmin)
                            <option value="dono" {{ $tipoUserModal === 'dono' ? 'selected' : '' }}>Dono da Empresa (Gestão + Execução de Serviços)</option>
                        @endif
                        <option value="funcionario" {{ $tipoUserModal === 'funcionario' ? 'selected' : '' }}>Funcionário / Operador de Detalhamento</option>
                        <option value="cliente" {{ $tipoUserModal === 'cliente' ? 'selected' : '' }}>Cliente (Acesso ao Portal)</option>
                    </x-select>

                    @if($isAdmin)
                        @php $empUserModal = $isModalEditUser ? old('empresa_id', $user['empresa_id']) : $user['empresa_id']; @endphp
                        <x-select :id="'edit_user_empresa_' . $user['id']" name="empresa_id" label="Empresa Vinculada">
                            <option value="">Sem vínculo fixo (Global)</option>
                            @foreach($empresas as $empOpcao)
                                <option value="{{ $empOpcao->id }}" {{ (int) $empUserModal === (int) $empOpcao->id ? 'selected' : '' }}>
                                    {{ $empOpcao->nome_fantasia }}
                                </option>
                            @endforeach
                        </x-select>
                    @endif

                    <x-input :id="'edit_user_cargo_' . $user['id']" name="cargo" label="Cargo / Especialidade (Para Dono ou Funcionário)" :value="$isModalEditUser ? old('cargo', $user['cargo'] ?? '') : ($user['cargo'] ?? '')" maxlength="80" placeholder="Ex: Proprietário / Especialista" />

                    <x-input :id="'edit_user_password_' . $user['id']" type="password" name="password" label="Nova Senha (deixe em branco para manter a atual)" minlength="8" maxlength="64" placeholder="••••••••" />

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
        @endif
    @endforeach

    <!-- Modal Novo Usuário -->
    @php $isModalNovoUser = old('_modal_target') === 'novo-usuario'; @endphp
    <x-modal id="modal-novo-usuario" title="Cadastrar Novo Usuário">
        <form action="{{ route('configuracoes.usuarios.store') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="_modal_target" value="novo-usuario" />
            <x-input id="novo_user_name" name="name" label="Nome Completo" placeholder="Ex: Rodrigo Fonseca" :value="$isModalNovoUser ? old('name') : ''" minlength="3" maxlength="100" required />
            <x-input id="novo_user_email" type="email" name="email" label="E-mail" placeholder="usuario@krausdetail.com.br" :value="$isModalNovoUser ? old('email') : ''" maxlength="100" required />
            <x-input id="novo_user_telefone" name="telefone" label="Telefone / WhatsApp" placeholder="(11) 99999-9999" :value="$isModalNovoUser ? old('telefone') : ''" maxlength="15" />

            @php $tipoNovoUser = $isModalNovoUser ? old('tipo_usuario', 'funcionario') : 'funcionario'; @endphp
            <x-select id="novo_user_tipo_usuario" name="tipo_usuario" label="Perfil de Acesso" required>
                <option value="funcionario" {{ $tipoNovoUser === 'funcionario' ? 'selected' : '' }}>Funcionário / Operador de Detalhamento</option>
                @if($isAdmin)
                    <option value="dono" {{ $tipoNovoUser === 'dono' ? 'selected' : '' }}>Dono da Empresa (Gestão + Execução de Serviços)</option>
                @endif
                <option value="cliente" {{ $tipoNovoUser === 'cliente' ? 'selected' : '' }}>Cliente (Acesso ao Portal)</option>
            </x-select>

            @if($isAdmin)
                @php $empNovoUser = $isModalNovoUser ? old('empresa_id') : null; @endphp
                <x-select id="novo_user_empresa_id" name="empresa_id" label="Empresa Vinculada" placeholder="Sem vínculo inicial (Criará empresa depois)">
                    @foreach($empresas as $empOpcao)
                        <option value="{{ $empOpcao->id }}" {{ (string) $empNovoUser === (string) $empOpcao->id ? 'selected' : '' }}>
                            {{ $empOpcao->nome_fantasia }}
                        </option>
                    @endforeach
                </x-select>
            @endif

            <x-input id="novo_user_cargo" name="cargo" label="Cargo / Especialidade (Para Dono ou Funcionário)" placeholder="Ex: Operador de Detalhamento" :value="$isModalNovoUser ? old('cargo') : ''" maxlength="80" />

            <x-input id="novo_user_password" type="password" name="password" label="Senha Temporária (mín. 8 caracteres)" placeholder="••••••••" minlength="8" maxlength="64" required />

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
function getConfigDataset() {
    return document.getElementById('config-tabs-root')?.dataset || {};
}

function switchConfigTab(tab) {
    const cfg = getConfigDataset();
    const tabs = JSON.parse(cfg.tabsValidas || '["empresa","equipe","perfil","oficina","preferencias","sistema"]');
    const fallbackTab = cfg.tabPadrao || 'perfil';
    const destino = tabs.includes(tab) && document.getElementById(`tab-${tab}`)
        ? tab
        : fallbackTab;

    tabs.forEach(t => {
        const content = document.getElementById(`tab-${t}`);
        const btn = document.getElementById(`tab-${t}-btn`);

        if (!content || !btn) return;

        if (t === destino) {
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
    const cfg = getConfigDataset();
    const defaultTab = cfg.tabPadrao || 'perfil';
    switchConfigTab(defaultTab);

    const modalAlvoErro = cfg.modalAlvoErro || '';
    if (modalAlvoErro && typeof window.openModal === 'function') {
        window.openModal('modal-' + modalAlvoErro);
    }
});
</script>
@endsection
