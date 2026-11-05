@extends('layouts.app')

@section('title', 'Configurações')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
            <x-icon name="settings" class="w-7 h-7 text-primary" />
            Configurações
        </h1>
        <p class="text-sm text-muted-foreground mt-1">
            Gerencie os dados da empresa, permissões da equipe, alertas e preferências operacionais do sistema.
        </p>
    </div>

    <!-- Navegação por Abas -->
    <div class="border-b border-border">
        <nav class="flex space-x-6 overflow-x-auto" aria-label="Abas de Configurações">
            <button type="button" 
                    id="tab-empresa-btn" 
                    onclick="switchConfigTab('empresa')" 
                    class="tab-btn active py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 border-primary text-primary whitespace-nowrap transition-colors">
                <x-icon name="briefcase" class="w-4 h-4" />
                Dados da Empresa
            </button>
            <button type="button" 
                    id="tab-equipe-btn" 
                    onclick="switchConfigTab('equipe')" 
                    class="tab-btn py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 border-transparent text-muted-foreground hover:text-foreground hover:border-border whitespace-nowrap transition-colors">
                <x-icon name="users" class="w-4 h-4" />
                Usuários & Equipe
            </button>
            <button type="button" 
                    id="tab-preferencias-btn" 
                    onclick="switchConfigTab('preferencias')" 
                    class="tab-btn py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 border-transparent text-muted-foreground hover:text-foreground hover:border-border whitespace-nowrap transition-colors">
                <x-icon name="sliders" class="w-4 h-4" />
                Preferências & Alertas
            </button>
            <button type="button" 
                    id="tab-sistema-btn" 
                    onclick="switchConfigTab('sistema')" 
                    class="tab-btn py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 border-transparent text-muted-foreground hover:text-foreground hover:border-border whitespace-nowrap transition-colors">
                <x-icon name="info" class="w-4 h-4" />
                Informações do Sistema
            </button>
        </nav>
    </div>

    <!-- ABA 1: DADOS DA EMPRESA -->
    <div id="tab-empresa" class="config-content space-y-6">
        <form action="#" method="POST" class="space-y-6">
            @csrf
            <x-card>
                <div class="flex items-center gap-2 pb-4 mb-4 border-b border-border">
                    <x-icon name="shield" class="w-5 h-5 text-primary" />
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Identificação da Oficina</h2>
                        <p class="text-xs text-muted-foreground">Estes dados aparecem nos relatórios, fichas de agendamento e ordens de serviço.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input name="nome_fantasia" label="Nome Fantasia" value="Kraus Detail - Estética Automotiva" required />
                    <x-input name="razao_social" label="Razão Social" value="Kraus Estética Automotiva Especializada LTDA" required />
                    <x-input name="cnpj" label="CNPJ" value="48.192.304/0001-89" placeholder="00.000.000/0000-00" />
                    <x-input name="inscricao_estadual" label="Inscrição Estadual" value="ISENTO" />
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
                    <x-input name="telefone" label="Telefone Fixo" value="(11) 3214-5500" />
                    <x-input name="whatsapp" label="WhatsApp Comercial" value="(11) 98765-4321" required />
                    <x-input type="email" name="email" label="E-mail Comercial" value="contato@krausdetail.com.br" required />
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
                    <x-input name="cep" label="CEP" value="04538-133" />
                    <div class="sm:col-span-2">
                        <x-input name="logradouro" label="Logradouro" value="Avenida dos Bandeirantes" />
                    </div>
                    <x-input name="numero" label="Número" value="2500" />
                    <x-input name="complemento" label="Complemento" value="Galpão B" />
                    <x-input name="bairro" label="Bairro" value="Vila Olímpia" />
                    <x-input name="cidade" label="Cidade" value="São Paulo" />
                    <x-input name="uf" label="UF" value="SP" />
                    <x-input name="horario_atendimento" label="Horário de Atendimento" value="Segunda a Sábado, 08:00 às 18:00" />
                </div>
            </x-card>

            <div class="flex justify-end">
                <x-button type="submit" variant="primary" class="gap-2">
                    <x-icon name="check" class="w-4 h-4" />
                    Salvar Dados da Empresa
                </x-button>
            </div>
        </form>
    </div>

    <!-- ABA 2: USUÁRIOS & EQUIPE -->
    <div id="tab-equipe" class="config-content space-y-6 hidden">
        <!-- Cards Informativos dos Papéis -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 rounded-lg bg-secondary/30 border border-border">
                <div class="flex items-center gap-2 text-foreground font-semibold text-sm mb-1">
                    <x-icon name="shield" class="w-4 h-4 text-emerald-400" />
                    Administrador
                </div>
                <p class="text-xs text-muted-foreground">Acesso irrestrito a todos os módulos, relatórios financeiros, configurações e gestão de usuários.</p>
            </div>

            <div class="p-4 rounded-lg bg-secondary/30 border border-border">
                <div class="flex items-center gap-2 text-foreground font-semibold text-sm mb-1">
                    <x-icon name="tool" class="w-4 h-4 text-sky-400" />
                    Funcionário / Operador
                </div>
                <p class="text-xs text-muted-foreground">Acesso ao painel operacional de Ordens de Serviço, início/conclusão de tarefas e registro de produtos.</p>
            </div>

            <div class="p-4 rounded-lg bg-secondary/30 border border-border">
                <div class="flex items-center gap-2 text-foreground font-semibold text-sm mb-1">
                    <x-icon name="user" class="w-4 h-4 text-purple-400" />
                    Cliente (Portal)
                </div>
                <p class="text-xs text-muted-foreground">Acesso opcional para clientes acompanharem o status dos seus veículos e histórico de manutenções.</p>
            </div>
        </div>

        <x-card>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-sm font-semibold text-foreground">Colaboradores e Contas Cadastradas</h2>
                    <p class="text-xs text-muted-foreground">Gerencie quem tem permissão para acessar o sistema.</p>
                </div>
                <x-button variant="primary" size="sm" onclick="openModal('modal-novo-usuario')" class="gap-1.5 self-start sm:self-auto">
                    <x-icon name="plus" class="w-4 h-4" />
                    Novo Usuário
                </x-button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-secondary/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider border-b border-border">
                        <tr>
                            <th class="px-4 py-3">Usuário</th>
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
                                        <p class="font-medium text-foreground">{{ $user['nome'] }}</p>
                                        <p class="text-[11px] text-muted-foreground">{{ $user['email'] }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ $user['telefone'] }}</td>
                            <td class="px-4 py-3">
                                @if($user['tipo'] === 'administrador')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <x-icon name="shield" class="w-3 h-3" />
                                        Administrador
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
                                @if(!empty($user['id']))
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
                                            class="p-1 text-muted-foreground {{ $user['ativo'] ? 'hover:text-rose-400' : 'hover:text-emerald-400' }} transition-colors ml-1 cursor-pointer"
                                            title="{{ $user['ativo'] ? 'Desativar Acesso' : 'Ativar Acesso' }}"
                                        >
                                            <x-icon :name="$user['ativo'] ? 'user-x' : 'user-check'" class="w-4 h-4" />
                                        </button>
                                    </form>

                                    <!-- Modal Editar Usuário -->
                                    <x-modal id="modal-editar-usuario-{{ $user['id'] }}" :title="'Editar Usuário: ' . $user['nome']">
                                        <form action="{{ route('configuracoes.usuarios.update', $user['id']) }}" method="POST" class="space-y-4 text-left">
                                            @csrf
                                            @method('PUT')
                                            <x-input name="name" label="Nome Completo" :value="$user['nome']" required />
                                            <x-input type="email" name="email" label="E-mail" :value="$user['email']" required />
                                            <x-input name="telefone" label="Telefone / WhatsApp" :value="$user['telefone'] !== '-' ? $user['telefone'] : ''" />

                                            <x-select name="tipo_usuario" label="Perfil de Acesso" required>
                                                <option value="funcionario" {{ $user['tipo'] === 'funcionario' ? 'selected' : '' }}>Funcionário / Operador de Detalhamento</option>
                                                <option value="administrador" {{ $user['tipo'] === 'administrador' ? 'selected' : '' }}>Administrador Geral</option>
                                                <option value="cliente" {{ $user['tipo'] === 'cliente' ? 'selected' : '' }}>Cliente (Acesso ao Portal)</option>
                                            </x-select>

                                            <x-input type="password" name="password" label="Nova Senha (deixe em branco para manter a atual)" placeholder="••••••••" />

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
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-muted-foreground">
                                Nenhum usuário cadastrado até o momento.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

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
                        <input type="checkbox" name="alerta_estoque" checked class="w-5 h-5 rounded border-border text-primary focus:ring-primary bg-secondary cursor-pointer">
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-lg bg-secondary/20 border border-border">
                        <div class="space-y-0.5">
                            <label class="text-sm font-medium text-foreground">Aviso ao Cliente via WhatsApp (Agendamento Confirmado)</label>
                            <p class="text-xs text-muted-foreground">Gerar link ou disparo automático com os detalhes do agendamento e horário previsto de chegada.</p>
                        </div>
                        <input type="checkbox" name="notif_agendamento" checked class="w-5 h-5 rounded border-border text-primary focus:ring-primary bg-secondary cursor-pointer">
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-lg bg-secondary/20 border border-border">
                        <div class="space-y-0.5">
                            <label class="text-sm font-medium text-foreground">Aviso de Veículo Pronto (Ordem de Serviço Concluída)</label>
                            <p class="text-xs text-muted-foreground">Notificar o cliente assim que todas as tarefas forem finalizadas e a OS for encerrada pela oficina.</p>
                        </div>
                        <input type="checkbox" name="notif_os_concluida" checked class="w-5 h-5 rounded border-border text-primary focus:ring-primary bg-secondary cursor-pointer">
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
                    <x-select name="intervalo_agenda" label="Grade de Horários">
                        <option value="15">A cada 15 minutos</option>
                        <option value="30" selected>A cada 30 minutos (Recomendado)</option>
                        <option value="60">A cada 1 hora</option>
                    </x-select>

                    <x-input type="number" name="capacidade_boxes" label="Boxes de Detalhamento Simultâneos" value="4" min="1" max="10" />

                    <x-select name="antecedencia_cancelamento" label="Antecedência Mínima para Cancelar">
                        <option value="2">2 horas antes</option>
                        <option value="12" selected>12 horas antes</option>
                        <option value="24">24 horas antes</option>
                    </x-select>
                </div>
            </x-card>

            <div class="flex justify-end">
                <x-button type="submit" variant="primary" class="gap-2">
                    <x-icon name="check" class="w-4 h-4" />
                    Salvar Preferências
                </x-button>
            </div>
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
                    <p class="text-muted-foreground">Nome da Aplicação</p>
                    <p class="font-bold text-foreground text-sm mt-0.5">Kraus Detail - Gestão Automotiva</p>
                </div>
                <div class="p-3 rounded-lg bg-secondary/30 border border-border">
                    <p class="text-muted-foreground">Versão da Plataforma</p>
                    <p class="font-bold text-foreground text-sm mt-0.5">v1.0.0 (Blade Edition)</p>
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
                    <p class="text-muted-foreground">Módulo Financeiro</p>
                    <p class="font-bold text-foreground text-sm mt-0.5">Lançamentos Diretos (Receitas/Despesas)</p>
                </div>
            </div>
        </x-card>

        <x-card>
            <div class="flex items-center gap-2 pb-4 mb-4 border-b border-border">
                <x-icon name="database" class="w-5 h-5 text-primary" />
                <div>
                    <h2 class="text-sm font-semibold text-foreground">Integridade das Tabelas e Domínio</h2>
                    <p class="text-xs text-muted-foreground">Resumo dos modelos cadastrados no banco de dados.</p>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                <div class="p-3 rounded bg-secondary/20 border border-border">
                    <span class="text-muted-foreground block">Clientes</span>
                    <span class="font-bold text-foreground text-base">Ativo</span>
                </div>
                <div class="p-3 rounded bg-secondary/20 border border-border">
                    <span class="text-muted-foreground block">Veículos</span>
                    <span class="font-bold text-foreground text-base">Ativo</span>
                </div>
                <div class="p-3 rounded bg-secondary/20 border border-border">
                    <span class="text-muted-foreground block">Serviços</span>
                    <span class="font-bold text-foreground text-base">Ativo</span>
                </div>
                <div class="p-3 rounded bg-secondary/20 border border-border">
                    <span class="text-muted-foreground block">Agendamentos</span>
                    <span class="font-bold text-foreground text-base">Ativo</span>
                </div>
                <div class="p-3 rounded bg-secondary/20 border border-border">
                    <span class="text-muted-foreground block">Ordens de Serviço</span>
                    <span class="font-bold text-foreground text-base">Ativo</span>
                </div>
                <div class="p-3 rounded bg-secondary/20 border border-border">
                    <span class="text-muted-foreground block">Tarefas / Insumos</span>
                    <span class="font-bold text-foreground text-base">Ativo</span>
                </div>
                <div class="p-3 rounded bg-secondary/20 border border-border">
                    <span class="text-muted-foreground block">Estoque / Movimentações</span>
                    <span class="font-bold text-foreground text-base">Ativo</span>
                </div>
                <div class="p-3 rounded bg-secondary/20 border border-border">
                    <span class="text-muted-foreground block">Lançamentos Financeiros</span>
                    <span class="font-bold text-foreground text-base">Ativo</span>
                </div>
            </div>
        </x-card>
    </div>
</div>

<!-- Modal Novo Usuário -->
<x-modal id="modal-novo-usuario" title="Cadastrar Novo Usuário">
    <form action="{{ route('configuracoes.usuarios.store') }}" method="POST" class="space-y-4">
        @csrf
        <x-input name="name" label="Nome Completo" placeholder="Ex: Rodrigo Fonseca" required />
        <x-input type="email" name="email" label="E-mail" placeholder="usuario@krausdetail.com.br" required />
        <x-input name="telefone" label="Telefone / WhatsApp" placeholder="(11) 99999-9999" />
        
        <x-select name="tipo_usuario" label="Perfil de Acesso" required>
            <option value="funcionario" selected>Funcionário / Operador de Detalhamento</option>
            <option value="administrador">Administrador Geral</option>
            <option value="cliente">Cliente (Acesso ao Portal)</option>
        </x-select>

        <x-input type="password" name="password" label="Senha Temporária (mín. 6 caracteres)" placeholder="••••••••" required />

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

<script>
function switchConfigTab(tab) {
    const tabs = ['empresa', 'equipe', 'preferencias', 'sistema'];
    
    tabs.forEach(t => {
        const content = document.getElementById(`tab-${t}`);
        const btn = document.getElementById(`tab-${t}-btn`);
        
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
    const params = new URLSearchParams(window.location.search);
    const activeTab = params.get('tab');
    if (activeTab && ['empresa', 'equipe', 'preferencias', 'sistema'].includes(activeTab)) {
        switchConfigTab(activeTab);
    }
});
</script>
@endsection
