@php
    $isCliente = auth()->check() && auth()->user()->isCliente();
@endphp

<x-layouts.app title="Novo Veículo">
    <x-header
        title="Cadastrar Veículo"
        :subtitle="$isCliente ? 'Informe os dados do seu veículo para agendar serviços e acompanhar o histórico.' : 'Associe um novo veículo ao cliente correspondente e registre suas especificações.'"
    >
        <x-slot:actions>
            <x-button href="{{ route('veiculos.index') }}" variant="outline" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    <div class="max-w-2xl">
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
                                        {{ $cliente->nome }} ({{ $cliente->cpf_cnpj ?? 'Sem documento' }})
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

                <!-- Dados Principais -->
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground mb-3 pb-1.5 border-b border-border">
                        Dados Pessoais / Comerciais
                    </h4>
                    <div class="grid gap-3.5 sm:grid-cols-2">
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

                const formModalCliente = document.getElementById('form-modal-cliente');
                const errosContainer = document.getElementById('modal-cliente-erros');
                const errosLista = document.getElementById('modal-cliente-erros-lista');
                const selectCliente = document.getElementById('cliente_id');
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

                            if (data.cliente && selectCliente) {
                                const doc = data.cliente.cpf_cnpj ? data.cliente.cpf_cnpj : 'Sem documento';
                                const option = new Option(`${data.cliente.nome} (${doc})`, data.cliente.id, true, true);
                                selectCliente.add(option);
                                selectCliente.value = data.cliente.id;
                            }

                            formModalCliente.reset();
                            toggleSenhaModalCliente(false);
                            window.closeModal('modal-novo-cliente');

                            if (alertaCriado && alertaCriadoTexto && data.cliente) {
                                alertaCriadoTexto.textContent = `Cliente "${data.cliente.nome}" cadastrado e selecionado automaticamente!`;
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
