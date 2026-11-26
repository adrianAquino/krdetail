@php
    $isCliente = auth()->check() && auth()->user()->isCliente();
@endphp

<x-layouts.app title="Novo Veículo">
    <x-header
        title="Cadastrar Veículo"
        :subtitle="$isCliente ? 'Informe os dados do seu veículo para agendar serviços e acompanhar o histórico.' : 'Associe um novo veículo ao cliente correspondente e registre suas especificações.'"
    >
        <x-slot:actions>
            <x-button href="{{ route('veiculos.index', ($isAdmin ?? false) && !empty($empresaSelecionadaId) ? ['empresa_id' => $empresaSelecionadaId] : []) }}" variant="outline" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    <div class="max-w-2xl">
        @if($isAdmin ?? false)
            <x-admin-empresa-filter
                :action="route('veiculos.create')"
                :empresas="$empresas ?? collect()"
                :empresaSelecionadaId="$empresaSelecionadaId ?? null"
                label="Filtrar Clientes por Empresa"
            />
        @endif

        <!-- Alerta dinâmico quando um cliente é cadastrado pelo modal -->
        <div id="alerta-cliente-criado" class="mb-6 hidden">
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-400 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 text-xs font-semibold">
                    <x-icon name="check-circle" class="w-5 h-5 text-emerald-500 shrink-0" />
                    <span id="alerta-cliente-criado-texto">Cliente cadastrado e selecionado com sucesso!</span>
                </div>
                <button type="button" onclick="document.getElementById('alerta-cliente-criado').classList.add('hidden')" class="text-emerald-400/70 hover:text-emerald-400 cursor-pointer">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>
        </div>

        <form method="POST" action="{{ route('veiculos.store') }}" class="space-y-6">
            @csrf

            <x-card title="Identificação do Veículo" :description="$isCliente ? 'Dados principais de emplacamento e fabricação' : 'Todo veículo registrado deve pertencer a um cliente cadastrado'">
                <div class="space-y-4">
                    @if($isCliente)
                        <input type="hidden" name="cliente_id" value="{{ $selectedClienteId }}" />
                    @else
                        <div>
                            <x-select
                                name="cliente_id"
                                id="cliente_id"
                                label="Cliente Proprietário"
                                required
                            >
                                @foreach($clientes as $cliente)
                                    <option value="{{ $cliente->id }}" {{ old('cliente_id', $selectedClienteId) == $cliente->id ? 'selected' : '' }}>
                                        {{ $cliente->nome }} ({{ $cliente->cpf_cnpj ?? 'Sem documento' }})@if(($isAdmin ?? false) && ($cliente->empresa ?? null)) — [{{ $cliente->empresa->nome_fantasia }}]@endif
                                    </option>
                                @endforeach
                            </x-select>
                            <p class="text-[11px] text-muted-foreground mt-1.5 flex items-center gap-1">
                                <span>Não encontrou o cliente?</span>
                                <button
                                    type="button"
                                    onclick="window.openModal('modal-novo-cliente')"
                                    class="text-primary font-semibold hover:underline inline-flex items-center gap-1 cursor-pointer"
                                >
                                    <x-icon name="plus" class="w-3.5 h-3.5" />
                                    Cadastre um novo cliente primeiro
                                </button>
                            </p>
                        </div>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input
                                name="placa"
                                label="Placa do Veículo"
                                placeholder="Ex: ABC1D23 ou ABC-1234"
                                value="{{ old('placa') }}"
                                class="uppercase font-mono tracking-wider font-bold"
                                minlength="7"
                                maxlength="8"
                                required
                            />
                        </div>

                        <div>
                            <x-input
                                name="ano"
                                type="number"
                                label="Ano de Fabricação"
                                placeholder="2023"
                                value="{{ old('ano') }}"
                                min="1950"
                                max="{{ date('Y') + 1 }}"
                            />
                        </div>
                    </div>
                </div>
            </x-card>

            <x-card title="Especificações do Veículo" description="Dados visuais para identificação e histórico">
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input
                                name="marca"
                                label="Marca / Fabricante"
                                placeholder="Ex: BMW, Audi, Porsche, Toyota"
                                value="{{ old('marca') }}"
                                maxlength="50"
                                required
                            />
                        </div>

                        <div>
                            <x-input
                                name="modelo"
                                label="Modelo / Versão"
                                placeholder="Ex: 320i M Sport, Corolla Cross"
                                value="{{ old('modelo') }}"
                                maxlength="80"
                                required
                            />
                        </div>
                    </div>

                    <div>
                        <x-input
                            name="cor"
                            label="Cor / Acabamento da Pintura"
                            placeholder="Ex: Preto Metálico, Azul Portimão, Branco Pérola"
                            value="{{ old('cor') }}"
                            maxlength="40"
                        />
                    </div>

                    <div>
                        <x-textarea
                            name="observacoes"
                            label="Observações / Cuidados Especiais"
                            placeholder="Ex: Pintura vitrificada anteriormente, repintura no para-choque traseiro..."
                            value="{{ old('observacoes') }}"
                            maxlength="500"
                            rows="3"
                        />
                    </div>
                </div>
            </x-card>

            <!-- Form Actions -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <x-button href="{{ route('veiculos.index') }}" variant="outline" size="md">
                    Cancelar
                </x-button>
                <x-button type="submit" variant="primary" size="md" icon="save">
                    Cadastrar Veículo
                </x-button>
            </div>
        </form>
    </div>

    @if(!$isCliente)
        <!-- Modal de Cadastro Rápido de Cliente (sem sair da tela de Novo Veículo) -->
        <x-modal id="modal-novo-cliente" title="Cadastrar Novo Cliente" maxWidth="max-w-2xl">
            <form id="form-modal-cliente" method="POST" action="{{ route('clientes.store') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="redirect_to" value="veiculos.create" />

                <!-- Container de erros de validação do Modal -->
                <div id="modal-cliente-erros" class="hidden rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-xs text-destructive">
                    <p class="font-semibold mb-1">Verifique os campos abaixo:</p>
                    <ul id="modal-cliente-erros-lista" class="list-disc list-inside space-y-0.5"></ul>
                </div>

                <!-- Busca Rápida de Conta Existente (Opcional) -->
                <div class="rounded-lg border border-border bg-secondary/20 p-3.5 space-y-2.5">
                    <div>
                        <h4 class="text-xs font-semibold text-foreground">Cliente já possui conta? (Opcional)</h4>
                        <p class="text-[11px] text-muted-foreground">Informe o e-mail completo ou CPF/CNPJ para localizar a conta e confirmar se deseja preencher o formulário.</p>
                    </div>
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                        <div class="flex-1">
                            <input
                                type="text"
                                id="modal_busca_usuario_cliente"
                                placeholder="E-mail completo ou CPF/CNPJ"
                                maxlength="100"
                                autocomplete="off"
                                class="block w-full rounded-lg bg-input/40 border border-border px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:border-ring focus:ring-ring/40 focus:outline-none focus:ring-2 transition-all"
                            />
                        </div>
                        <x-button type="button" id="btn-modal-buscar-usuario" variant="outline" size="sm" icon="search" class="shrink-0">
                            Buscar dados
                        </x-button>
                    </div>
                    <div id="modal-resultado-busca-usuario" class="hidden rounded-lg border p-2.5 text-xs"></div>
                </div>

                <!-- Dados Principais -->
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground mb-3 pb-1.5 border-b border-border">
                        Dados Pessoais / Comerciais
                    </h4>
                    <div class="grid gap-3.5 sm:grid-cols-2">
                        @if($isAdmin ?? false)
                            <div class="sm:col-span-2">
                                <x-select name="empresa_id" id="modal_cliente_empresa_id" label="Empresa Proprietária" required>
                                    <option value="">Selecione a empresa</option>
                                    @foreach($empresas ?? [] as $empModal)
                                        <option value="{{ $empModal->id }}" {{ (string) ($empresaSelecionadaId ?? '') === (string) $empModal->id ? 'selected' : '' }}>
                                            {{ $empModal->nome_fantasia }}
                                        </option>
                                    @endforeach
                                </x-select>
                            </div>
                        @endif

                        <div class="sm:col-span-2">
                            <x-input
                                name="nome"
                                id="modal_cliente_nome"
                                label="Nome Completo / Razão Social"
                                minlength="3"
                                maxlength="100"
                                placeholder="Ex: João da Silva"
                                required
                            />
                        </div>

                        <div>
                            <x-input
                                name="cpf_cnpj"
                                id="modal_cliente_cpf_cnpj"
                                label="CPF ou CNPJ"
                                maxlength="18"
                                placeholder="000.000.000-00 ou 00.000.000/0000-00"
                            />
                        </div>

                        <div>
                            <x-input
                                name="telefone"
                                id="modal_cliente_telefone"
                                label="Telefone / WhatsApp"
                                minlength="10"
                                maxlength="15"
                                placeholder="(11) 90000-0000"
                                required
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input
                                type="email"
                                name="email"
                                id="modal_cliente_email"
                                label="E-mail"
                                maxlength="100"
                                placeholder="cliente@exemplo.com"
                            />
                        </div>
                    </div>
                </div>

                <!-- Endereço -->
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground mb-3 pb-1.5 border-b border-border">
                        Endereço (Opcional)
                    </h4>
                    <div class="grid gap-3.5 sm:grid-cols-6">
                        <div class="sm:col-span-2">
                            <x-input
                                name="cep"
                                id="modal_cep"
                                label="CEP"
                                maxlength="9"
                                placeholder="00000-000"
                                onblur="pesquisacepModal(this.value);"
                            />
                        </div>

                        <div class="sm:col-span-4">
                            <x-input
                                name="logradouro"
                                id="modal_rua"
                                label="Logradouro / Rua / Avenida"
                                maxlength="100"
                                placeholder="Ex: Av. Paulista"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input
                                name="numero"
                                id="modal_numero"
                                label="Número"
                                maxlength="10"
                                placeholder="123"
                            />
                        </div>

                        <div class="sm:col-span-4">
                            <x-input
                                name="complemento"
                                id="modal_complemento"
                                label="Complemento"
                                maxlength="100"
                                placeholder="Apto 42, Bloco B"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input
                                name="bairro"
                                id="modal_bairro"
                                label="Bairro"
                                maxlength="100"
                                placeholder="Bela Vista"
                            />
                        </div>

                        <div class="sm:col-span-3">
                            <x-input
                                name="cidade"
                                id="modal_cidade"
                                label="Cidade"
                                maxlength="100"
                                placeholder="São Paulo"
                            />
                        </div>

                        <div class="sm:col-span-1">
                            <x-input
                                name="estado"
                                id="modal_uf"
                                label="UF"
                                maxlength="2"
                                placeholder="SP"
                            />
                        </div>
                    </div>
                </div>

                <!-- Conta de Acesso -->
                <div>
                    <div class="flex items-start gap-3 p-3 rounded-lg border border-border bg-secondary/30">
                        <input
                            type="checkbox"
                            id="modal_criar_usuario"
                            name="criar_usuario"
                            value="1"
                            class="mt-1 h-4 w-4 rounded border-border bg-input text-primary focus:ring-primary"
                            onchange="toggleSenhaModalCliente(this.checked)"
                        />
                        <label for="modal_criar_usuario" class="text-xs text-foreground cursor-pointer">
                            <span class="font-semibold block text-sm">Criar conta de acesso para este cliente</span>
                            <span class="text-muted-foreground">Permite que o cliente consulte o andamento de seus serviços pelo portal.</span>
                        </label>
                    </div>

                    <div id="modal_senha_container" class="hidden grid gap-3.5 sm:grid-cols-2 pt-3">
                        <x-input
                            type="password"
                            name="password"
                            id="modal_password"
                            label="Senha Inicial"
                            minlength="8"
                            maxlength="64"
                            placeholder="Mínimo de 8 caracteres"
                        />
                        <x-input
                            type="password"
                            name="password_confirmation"
                            id="modal_password_confirmation"
                            label="Confirmar Senha"
                            minlength="8"
                            maxlength="64"
                            placeholder="Repita a senha"
                        />
                    </div>

                    <p id="modal-aviso-conta-ja-existe" class="hidden text-xs text-emerald-600 dark:text-emerald-400 font-medium mt-2">
                        Este usuário já possui conta de acesso na plataforma; ela será vinculada automaticamente à sua estética ao salvar (não é necessário criar senha).
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                    <x-button type="button" onclick="window.closeModal('modal-novo-cliente')" variant="outline" size="sm">
                        Cancelar
                    </x-button>
                    <x-button type="submit" id="btn-salvar-cliente-modal" variant="primary" size="sm" icon="save">
                        Salvar Cliente
                    </x-button>
                </div>
            </form>
        </x-modal>

        @push('scripts')
        <script>
            function toggleSenhaModalCliente(ativo) {
                const container = document.getElementById('modal_senha_container');
                if (!container) return;
                container.classList.toggle('hidden', !ativo);
                container.querySelectorAll('input').forEach(function (inp) {
                    inp.disabled = !ativo;
                });
            }

            function renderModalClienteErros(mensagens) {
                const errosContainer = document.getElementById('modal-cliente-erros');
                const errosLista = document.getElementById('modal-cliente-erros-lista');
                if (!errosContainer || !errosLista) return;
                errosLista.replaceChildren();
                mensagens.forEach(function (msg) {
                    const li = document.createElement('li');
                    li.textContent = String(msg);
                    errosLista.appendChild(li);
                });
                errosContainer.classList.remove('hidden');
            }

            function selecionarClienteNoFormularioVeiculo(clienteId, nomeCliente, documento) {
                const selectCliente = document.getElementById('cliente_id');
                if (!selectCliente || !clienteId) return;

                let optionExistente = Array.from(selectCliente.options).find(function (opt) {
                    return String(opt.value) === String(clienteId);
                });

                if (!optionExistente) {
                    const doc = documento ? documento : 'Sem documento';
                    const nome = nomeCliente ? nomeCliente : 'Cliente';
                    optionExistente = new Option(`${nome} (${doc})`, clienteId, true, true);
                    selectCliente.add(optionExistente);
                }
                selectCliente.value = String(clienteId);
            }

            function limparBuscaUsuarioModal() {
                const inputBusca = document.getElementById('modal_busca_usuario_cliente');
                const box = document.getElementById('modal-resultado-busca-usuario');
                const chkModal = document.getElementById('modal_criar_usuario');
                const aviso = document.getElementById('modal-aviso-conta-ja-existe');

                if (inputBusca) inputBusca.value = '';
                if (box) {
                    box.replaceChildren();
                    box.classList.add('hidden');
                }
                [
                    'modal_cliente_nome',
                    'modal_cliente_cpf_cnpj',
                    'modal_cliente_telefone',
                    'modal_cliente_email',
                    'modal_cep',
                    'modal_rua',
                    'modal_numero',
                    'modal_complemento',
                    'modal_bairro',
                    'modal_cidade',
                    'modal_uf',
                ].forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });

                if (chkModal) {
                    chkModal.disabled = false;
                    chkModal.checked = false;
                    toggleSenhaModalCliente(false);
                }
                if (aviso) aviso.classList.add('hidden');
            }

            function fecharResultadoBuscaUsuarioModal() {
                const box = document.getElementById('modal-resultado-busca-usuario');
                if (!box) return;
                box.replaceChildren();
                box.classList.add('hidden');
            }

            function formatarResumoEnderecoModal(dados) {
                if (!dados) return '';
                const partesLinha1 = [dados.logradouro, dados.numero, dados.complemento].filter(Boolean);
                const partesLinha2 = [dados.bairro, dados.cidade && dados.estado ? `${dados.cidade}/${dados.estado}` : (dados.cidade || dados.estado), dados.cep].filter(Boolean);
                return [partesLinha1.join(', '), partesLinha2.join(' • ')].filter(Boolean).join(' — ');
            }

            function criarBlocoPreviewUsuarioModal(dados) {
                const cardDados = document.createElement('div');
                cardDados.className = 'rounded-md border border-border/60 bg-background/80 p-2.5 grid gap-1.5 sm:grid-cols-2 text-[11px] text-foreground';

                const itens = [
                    ['Nome', dados.nome || '-'],
                    ['E-mail', dados.email || '-'],
                    ['CPF/CNPJ', dados.cpf_cnpj || 'Não informado'],
                    ['Telefone', dados.telefone || 'Não informado'],
                ];

                const enderecoResumo = formatarResumoEnderecoModal(dados);
                if (enderecoResumo) {
                    itens.push(['Endereço', enderecoResumo, true]);
                }

                itens.forEach(function (item) {
                    const wrapper = document.createElement('div');
                    if (item[2]) wrapper.className = 'sm:col-span-2';

                    const rotulo = document.createElement('span');
                    rotulo.className = 'text-muted-foreground font-medium';
                    rotulo.textContent = item[0] + ': ';

                    const valor = document.createElement('span');
                    valor.className = 'font-semibold';
                    valor.textContent = String(item[1]);

                    wrapper.appendChild(rotulo);
                    wrapper.appendChild(valor);
                    cardDados.appendChild(wrapper);
                });

                return cardDados;
            }

            function aplicarDadosUsuarioNoModalVeiculo(dados, status) {
                const chkModal = document.getElementById('modal_criar_usuario');
                const aviso = document.getElementById('modal-aviso-conta-ja-existe');

                if (dados) {
                    const mapaCampos = {
                        nome: 'modal_cliente_nome',
                        cpf_cnpj: 'modal_cliente_cpf_cnpj',
                        telefone: 'modal_cliente_telefone',
                        email: 'modal_cliente_email',
                        cep: 'modal_cep',
                        logradouro: 'modal_rua',
                        numero: 'modal_numero',
                        complemento: 'modal_complemento',
                        bairro: 'modal_bairro',
                        cidade: 'modal_cidade',
                        estado: 'modal_uf',
                    };
                    Object.entries(mapaCampos).forEach(function ([campo, elementId]) {
                        const el = document.getElementById(elementId);
                        if (el && dados[campo] !== undefined && dados[campo] !== null) {
                            el.value = String(dados[campo]);
                        }
                    });
                }

                if (chkModal) {
                    chkModal.checked = false;
                    chkModal.disabled = true;
                    toggleSenhaModalCliente(false);
                }
                if (aviso) aviso.classList.remove('hidden');

                const mensagemConfirmacao = status === 'vinculado_outra_empresa'
                    ? 'Dados incluídos no formulário! Ao salvar, esta conta passará a ter vínculo ativo com a sua estética (preservando o histórico anterior).'
                    : 'Dados incluídos no formulário! Confira as informações abaixo e clique em Salvar Cliente para concluir.';

                renderModalResultadoBuscaUsuario('sucesso', mensagemConfirmacao, { mostrarLimpar: true });
            }

            function renderModalResultadoBuscaUsuario(tipo, mensagem, opcoes) {
                const box = document.getElementById('modal-resultado-busca-usuario');
                if (!box) return;

                box.replaceChildren();
                box.className = 'rounded-lg border p-3 text-xs space-y-2.5';

                if (tipo === 'sucesso') {
                    box.classList.add('border-emerald-500/40', 'bg-emerald-500/10', 'text-emerald-700', 'dark:text-emerald-300');
                } else if (tipo === 'aviso') {
                    box.classList.add('border-amber-500/40', 'bg-amber-500/10', 'text-amber-700', 'dark:text-amber-300');
                } else if (tipo === 'erro') {
                    box.classList.add('border-destructive/40', 'bg-destructive/10', 'text-destructive');
                } else {
                    box.classList.add('border-border', 'bg-secondary/40', 'text-muted-foreground');
                }

                const header = document.createElement('div');
                header.className = 'flex items-start justify-between gap-2';

                const p = document.createElement('p');
                p.className = 'font-medium leading-relaxed';
                p.textContent = String(mensagem || '');
                header.appendChild(p);

                const btnFecharX = document.createElement('button');
                btnFecharX.type = 'button';
                btnFecharX.textContent = '×';
                btnFecharX.setAttribute('aria-label', 'Fechar');
                btnFecharX.setAttribute('title', 'Fechar');
                btnFecharX.className = 'shrink-0 w-6 h-6 -mt-0.5 -mr-0.5 rounded-md inline-flex items-center justify-center text-base leading-none text-current/70 hover:text-current hover:bg-background/40 transition-colors cursor-pointer';
                btnFecharX.addEventListener('click', fecharResultadoBuscaUsuarioModal);
                header.appendChild(btnFecharX);

                box.appendChild(header);

                if (opcoes && opcoes.previewDados) {
                    box.appendChild(criarBlocoPreviewUsuarioModal(opcoes.previewDados));
                }

                const acoes = document.createElement('div');
                acoes.className = 'flex flex-wrap items-center gap-2 pt-0.5';

                if (opcoes && opcoes.confirmarInclusao && opcoes.previewDados) {
                    const btnIncluir = document.createElement('button');
                    btnIncluir.type = 'button';
                    btnIncluir.textContent = 'Incluir dados no formulário';
                    btnIncluir.className = 'px-3 py-1.5 rounded-md bg-primary text-primary-foreground font-semibold hover:bg-primary/90 transition-colors cursor-pointer';
                    btnIncluir.addEventListener('click', function () {
                        aplicarDadosUsuarioNoModalVeiculo(opcoes.previewDados, opcoes.status);
                    });
                    acoes.appendChild(btnIncluir);

                    const btnNaoIncluir = document.createElement('button');
                    btnNaoIncluir.type = 'button';
                    btnNaoIncluir.textContent = 'Não incluir';
                    btnNaoIncluir.className = 'px-3 py-1.5 rounded-md border border-border bg-background/80 text-foreground font-medium hover:bg-secondary transition-colors cursor-pointer';
                    btnNaoIncluir.addEventListener('click', fecharResultadoBuscaUsuarioModal);
                    acoes.appendChild(btnNaoIncluir);
                }

                if (opcoes && opcoes.clienteId) {
                    const btnSelecionar = document.createElement('button');
                    btnSelecionar.type = 'button';
                    btnSelecionar.textContent = 'Selecionar este cliente';
                    btnSelecionar.className = 'px-3 py-1.5 rounded-md bg-primary text-primary-foreground font-semibold hover:bg-primary/90 transition-colors cursor-pointer';
                    btnSelecionar.addEventListener('click', function () {
                        selecionarClienteNoFormularioVeiculo(
                            opcoes.clienteId,
                            opcoes.previewDados?.nome,
                            opcoes.previewDados?.cpf_cnpj
                        );
                        const alertaCriado = document.getElementById('alerta-cliente-criado');
                        const alertaCriadoTexto = document.getElementById('alerta-cliente-criado-texto');
                        if (alertaCriado && alertaCriadoTexto) {
                            alertaCriadoTexto.textContent = `Cliente "${opcoes.previewDados?.nome || 'selecionado'}" encontrado e selecionado automaticamente!`;
                            alertaCriado.classList.remove('hidden');
                        }
                        limparBuscaUsuarioModal();
                        window.closeModal('modal-novo-cliente');
                    });
                    acoes.appendChild(btnSelecionar);

                    const btnFechar = document.createElement('button');
                    btnFechar.type = 'button';
                    btnFechar.textContent = 'Fechar';
                    btnFechar.className = 'px-3 py-1.5 rounded-md border border-border bg-background/80 text-foreground font-medium hover:bg-secondary transition-colors cursor-pointer';
                    btnFechar.addEventListener('click', fecharResultadoBuscaUsuarioModal);
                    acoes.appendChild(btnFechar);
                }

                if (opcoes && opcoes.mostrarLimpar) {
                    const btnLimpar = document.createElement('button');
                    btnLimpar.type = 'button';
                    btnLimpar.textContent = 'Limpar dados preenchidos';
                    btnLimpar.className = 'font-semibold underline text-foreground hover:text-primary cursor-pointer';
                    btnLimpar.addEventListener('click', limparBuscaUsuarioModal);
                    acoes.appendChild(btnLimpar);
                }

                if (acoes.childNodes.length > 0) {
                    box.appendChild(acoes);
                }

                box.classList.remove('hidden');
            }

            async function buscarUsuarioClienteModal() {
                const inputBusca = document.getElementById('modal_busca_usuario_cliente');
                const btnBuscar = document.getElementById('btn-modal-buscar-usuario');
                const empresaSelect = document.getElementById('modal_cliente_empresa_id');

                const termo = (inputBusca?.value || '').trim();
                if (!termo) {
                    renderModalResultadoBuscaUsuario('erro', 'Informe um e-mail completo ou CPF/CNPJ para buscar.');
                    return;
                }

                const empresaId = empresaSelect ? empresaSelect.value : '';
                const url = @json(route('clientes.buscar-usuario'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES)
                    + '?termo=' + encodeURIComponent(termo)
                    + (empresaId ? '&empresa_id=' + encodeURIComponent(empresaId) : '');

                if (btnBuscar) btnBuscar.disabled = true;

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        renderModalResultadoBuscaUsuario('erro', data.message || 'Não foi possível realizar a busca.');
                        return;
                    }

                    if (!data.found) {
                        renderModalResultadoBuscaUsuario('neutro', data.message || 'Nenhuma conta encontrada.');
                        return;
                    }

                    if (data.status === 'ja_vinculado_esta_empresa') {
                        renderModalResultadoBuscaUsuario('aviso', data.message, {
                            clienteId: data.cliente_id,
                            previewDados: data.dados,
                        });
                        return;
                    }

                    renderModalResultadoBuscaUsuario(
                        data.status === 'vinculado_outra_empresa' ? 'aviso' : 'sucesso',
                        data.message,
                        {
                            previewDados: data.dados,
                            confirmarInclusao: true,
                            status: data.status,
                        }
                    );
                } catch (e) {
                    renderModalResultadoBuscaUsuario('erro', 'Erro ao consultar conta de usuário. Tente novamente.');
                } finally {
                    if (btnBuscar) btnBuscar.disabled = false;
                }
            }

            async function pesquisacepModal(valor) {
                const cep = (valor || '').replace(/\D/g, '');
                if (cep === '') {
                    return;
                }
                if (!/^[0-9]{8}$/.test(cep)) {
                    alert('Formato de CEP inválido.');
                    return;
                }

                const ruaEl = document.getElementById('modal_rua');
                const bairroEl = document.getElementById('modal_bairro');
                const cidadeEl = document.getElementById('modal_cidade');
                const ufEl = document.getElementById('modal_uf');
                const controller = new AbortController();
                const timer = setTimeout(() => controller.abort(), 5000);

                try {
                    const response = await fetch('https://viacep.com.br/ws/' + cep + '/json/', {
                        signal: controller.signal,
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!response.ok) return;
                    const conteudo = await response.json();
                    if (conteudo && !conteudo.erro) {
                        if (ruaEl && conteudo.logradouro !== undefined) ruaEl.value = conteudo.logradouro || '';
                        if (bairroEl && conteudo.bairro !== undefined) bairroEl.value = conteudo.bairro || '';
                        if (cidadeEl && conteudo.localidade !== undefined) cidadeEl.value = conteudo.localidade || '';
                        if (ufEl && conteudo.uf !== undefined) ufEl.value = (conteudo.uf || '').toUpperCase().slice(0, 2);
                    } else {
                        alert('CEP não encontrado.');
                    }
                } catch (e) {
                    if (e.name !== 'AbortError') {
                        console.warn('Falha ao consultar ViaCEP:', e);
                    }
                } finally {
                    clearTimeout(timer);
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                const chkModal = document.getElementById('modal_criar_usuario');
                if (chkModal) toggleSenhaModalCliente(chkModal.checked);

                const btnModalBuscar = document.getElementById('btn-modal-buscar-usuario');
                const inputModalBusca = document.getElementById('modal_busca_usuario_cliente');
                if (btnModalBuscar) {
                    btnModalBuscar.addEventListener('click', buscarUsuarioClienteModal);
                }
                if (inputModalBusca) {
                    inputModalBusca.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            buscarUsuarioClienteModal();
                        }
                    });
                }

                const formModalCliente = document.getElementById('form-modal-cliente');
                const errosContainer = document.getElementById('modal-cliente-erros');
                const errosLista = document.getElementById('modal-cliente-erros-lista');
                const alertaCriado = document.getElementById('alerta-cliente-criado');
                const alertaCriadoTexto = document.getElementById('alerta-cliente-criado-texto');
                const btnSalvar = document.getElementById('btn-salvar-cliente-modal');

                if (formModalCliente) {
                    formModalCliente.setAttribute('data-allow-resubmit', '1');
                    formModalCliente.addEventListener('submit', async function (e) {
                        e.preventDefault();

                        errosContainer.classList.add('hidden');
                        errosLista.replaceChildren();
                        if (btnSalvar) btnSalvar.disabled = true;

                        try {
                            const formData = new FormData(formModalCliente);
                            const response = await fetch(formModalCliente.action, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: formData,
                            });

                            const data = await response.json();

                            if (!response.ok) {
                                const mensagens = [];
                                if (data.errors) {
                                    Object.values(data.errors).forEach(arr => {
                                        arr.forEach(msg => mensagens.push(msg));
                                    });
                                } else if (data.message) {
                                    mensagens.push(data.message);
                                }

                                renderModalClienteErros(mensagens.length ? mensagens : ['Verifique os dados informados.']);
                                return;
                            }

                            if (data.cliente) {
                                selecionarClienteNoFormularioVeiculo(
                                    data.cliente.id,
                                    data.cliente.nome,
                                    data.cliente.cpf_cnpj
                                );
                            }

                            formModalCliente.reset();
                            limparBuscaUsuarioModal();
                            window.closeModal('modal-novo-cliente');

                            if (alertaCriado && alertaCriadoTexto && data.cliente) {
                                alertaCriadoTexto.textContent = data.message || `Cliente "${data.cliente.nome}" cadastrado e selecionado automaticamente!`;
                                alertaCriado.classList.remove('hidden');
                            }
                        } catch (err) {
                            renderModalClienteErros(['Ocorreu um erro ao salvar o cliente. Tente novamente.']);
                        } finally {
                            if (btnSalvar) btnSalvar.disabled = false;
                        }
                    });
                }
            });
        </script>
        @endpush
    @endif
</x-layouts.app>
