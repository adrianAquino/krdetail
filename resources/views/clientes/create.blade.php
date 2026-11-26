<x-layouts.app title="Novo Cliente">
    <x-header
        title="Cadastrar Cliente"
        subtitle="Preencha os dados cadastrais do cliente e endereço de contato."
    >
        <x-slot:actions>
            <x-button href="{{ route('clientes.index') }}" variant="outline" size="sm" icon="chevron-left">
                Voltar
            </x-button>
        </x-slot:actions>
    </x-header>

    <div class="max-w-3xl">
        <form id="form-cadastro-cliente" method="POST" action="{{ route('clientes.store') }}" class="space-y-6">
            @csrf

            <!-- Busca Rápida de Conta Existente -->
            <x-card title="Cliente já possui conta?" description="Informe o e-mail completo ou CPF/CNPJ para localizar a conta e decidir se deseja incluir os dados no formulário.">
                <div class="space-y-3">
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                        <div class="flex-1">
                            <input
                                type="text"
                                id="busca_usuario_cliente"
                                placeholder="Digite o e-mail completo ou CPF/CNPJ (ex: cliente@email.com ou 000.000.000-00)"
                                maxlength="100"
                                autocomplete="off"
                                class="block w-full rounded-lg bg-input/40 border border-border px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:border-ring focus:ring-ring/40 focus:outline-none focus:ring-2 transition-all"
                            />
                        </div>
                        <x-button type="button" id="btn-buscar-usuario-cliente" variant="outline" size="sm" icon="search" class="shrink-0">
                            Buscar dados
                        </x-button>
                    </div>
                    <div id="resultado-busca-usuario" class="hidden rounded-lg border p-3 text-xs"></div>
                </div>
            </x-card>

            <!-- Dados Principais -->
            <x-card title="Dados Pessoais / Comerciais" description="Informações de identificação e contato direto">
                <div class="grid gap-4 sm:grid-cols-2">
                    @if($isAdmin ?? false)
                        <div class="sm:col-span-2">
                            <x-select name="empresa_id" label="Empresa Proprietária" required>
                                <option value="">Selecione a empresa</option>
                                @foreach($empresas ?? [] as $emp)
                                    <option value="{{ $emp->id }}" {{ (string) old('empresa_id', $empresaSelecionadaId ?? '') === (string) $emp->id ? 'selected' : '' }}>
                                        {{ $emp->nome_fantasia }}
                                    </option>
                                @endforeach
                            </x-select>
                        </div>
                    @endif

                    <div class="sm:col-span-2">
                        <x-input
                            name="nome"
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
                            label="CPF ou CNPJ"
                            maxlength="18"
                            placeholder="000.000.000-00 ou 00.000.000/0000-00"
                        />
                    </div>

                    <div>
                        <x-input
                            name="telefone"
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
                            label="E-mail"
                            maxlength="100"
                            placeholder="cliente@exemplo.com"
                        />
                    </div>
                </div>
            </x-card>

            <!-- Endereço -->
            <x-card title="Endereço" description="Localização e dados para faturamento ou atendimento">
                <div class="grid gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-2">
                        <x-input
                            name="cep"
                            label="CEP"
                            maxlength="9"
                            placeholder="00000-000"
                            id="cep"
                            onblur="pesquisacep(this.value);"
                        />
                    </div>

                    <div class="sm:col-span-4">
                        <x-input
                            name="logradouro"
                            label="Logradouro / Rua / Avenida"
                            maxlength="100"
                            placeholder="Ex: Av. Paulista"
                            id="rua"
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input
                            name="numero"
                            label="Número"
                            maxlength="10"
                            placeholder="123"
                        />
                    </div>

                    <div class="sm:col-span-4">
                        <x-input
                            name="complemento"
                            label="Complemento"
                            maxlength="100"
                            placeholder="Apto 42, Bloco B"
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input
                            name="bairro"
                            label="Bairro"
                            maxlength="100"
                            placeholder="Bela Vista"
                            id="bairro"
                        />
                    </div>

                    <div class="sm:col-span-3">
                        <x-input
                            name="cidade"
                            label="Cidade"
                            maxlength="100"
                            placeholder="São Paulo"
                            id="cidade"
                        />
                    </div>

                    <div class="sm:col-span-1">
                        <x-input
                            name="estado"
                            label="UF"
                            placeholder="SP"
                            maxlength="2"
                            id="uf"
                        />
                    </div>
                </div>
            </x-card>

            <!-- Acesso ao Sistema -->
            <x-card title="Conta de Acesso ao Sistema" description="Permite que o cliente consulte seus agendamentos e veículos pelo portal">
                <div class="space-y-4">
                    <div class="flex items-start gap-3 p-3.5 rounded-lg border border-border bg-secondary/30">
                        <input
                            type="checkbox"
                            id="criar_usuario"
                            name="criar_usuario"
                            value="1"
                            {{ old('criar_usuario') ? 'checked' : '' }}
                            class="mt-1 h-4 w-4 rounded border-border bg-input text-primary focus:ring-primary"
                            onchange="toggleSenhaCliente(this.checked)"
                        />
                        <label for="criar_usuario" class="text-xs text-foreground cursor-pointer">
                            <span class="font-semibold block text-sm">Criar conta de acesso para este cliente</span>
                            <span class="text-muted-foreground">O cliente poderá acessar a plataforma para consultar o andamento de seus serviços e histórico.</span>
                        </label>
                    </div>

                    <div id="senha_container" class="{{ old('criar_usuario') ? '' : 'hidden' }} grid gap-4 sm:grid-cols-2 pt-2">
                        <x-input
                            type="password"
                            name="password"
                            label="Senha Inicial"
                            minlength="8"
                            maxlength="64"
                            placeholder="Mínimo de 8 caracteres"
                        />
                        <x-input
                            type="password"
                            name="password_confirmation"
                            label="Confirmar Senha"
                            minlength="8"
                            maxlength="64"
                            placeholder="Repita a senha"
                        />
                    </div>

                    <p id="aviso-conta-ja-existe" class="hidden text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                        Este usuário já possui conta de acesso na plataforma; ela será vinculada automaticamente à sua estética ao salvar (não é necessário criar senha).
                    </p>
                </div>
            </x-card>

            <!-- Form Actions -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <x-button href="{{ route('clientes.index') }}" variant="outline" size="md">
                    Cancelar
                </x-button>
                <x-button type="submit" variant="primary" size="md" icon="save">
                    Salvar Cliente
                </x-button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function toggleSenhaCliente(ativo) {
            const container = document.getElementById('senha_container');
            if (!container) return;
            container.classList.toggle('hidden', !ativo);
            container.querySelectorAll('input').forEach(function (inp) {
                inp.disabled = !ativo;
            });
        }

        function fecharResultadoBuscaCliente() {
            const box = document.getElementById('resultado-busca-usuario');
            if (!box) return;
            box.replaceChildren();
            box.classList.add('hidden');
        }

        function formatarResumoEnderecoBusca(dados) {
            if (!dados) return '';
            const partesLinha1 = [dados.logradouro, dados.numero, dados.complemento].filter(Boolean);
            const partesLinha2 = [dados.bairro, dados.cidade && dados.estado ? `${dados.cidade}/${dados.estado}` : (dados.cidade || dados.estado), dados.cep].filter(Boolean);
            const resumo = [partesLinha1.join(', '), partesLinha2.join(' • ')].filter(Boolean).join(' — ');
            return resumo;
        }

        function criarBlocoPreviewUsuarioBusca(dados) {
            const cardDados = document.createElement('div');
            cardDados.className = 'rounded-md border border-border/60 bg-background/80 p-2.5 grid gap-1.5 sm:grid-cols-2 text-[11px] text-foreground';

            const itens = [
                ['Nome', dados.nome || '-'],
                ['E-mail', dados.email || '-'],
                ['CPF/CNPJ', dados.cpf_cnpj || 'Não informado'],
                ['Telefone', dados.telefone || 'Não informado'],
            ];

            const enderecoResumo = formatarResumoEnderecoBusca(dados);
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

        function aplicarDadosUsuarioNoFormularioPagina(dados, status) {
            const form = document.getElementById('form-cadastro-cliente');
            const chk = document.getElementById('criar_usuario');
            const aviso = document.getElementById('aviso-conta-ja-existe');

            if (form && dados) {
                ['nome', 'cpf_cnpj', 'telefone', 'email', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado'].forEach(function (campo) {
                    const el = form.querySelector('[name="' + campo + '"]');
                    if (el && dados[campo] !== undefined && dados[campo] !== null) {
                        el.value = String(dados[campo]);
                    }
                });
            }

            if (chk) {
                chk.checked = false;
                chk.disabled = true;
                toggleSenhaCliente(false);
            }
            if (aviso) aviso.classList.remove('hidden');

            const mensagemConfirmacao = status === 'vinculado_outra_empresa'
                ? 'Dados incluídos no formulário! Ao salvar, esta conta passará a ter vínculo ativo com a sua estética (preservando o histórico anterior).'
                : 'Dados incluídos no formulário! Confira as informações abaixo e clique em Salvar Cliente para vincular esta conta à sua estética.';

            renderResultadoBuscaCliente('sucesso', mensagemConfirmacao, { mostrarLimpar: true });
        }

        function renderResultadoBuscaCliente(tipo, mensagem, opcoes) {
            const box = document.getElementById('resultado-busca-usuario');
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
            btnFecharX.addEventListener('click', fecharResultadoBuscaCliente);
            header.appendChild(btnFecharX);

            box.appendChild(header);

            if (opcoes && opcoes.previewDados) {
                box.appendChild(criarBlocoPreviewUsuarioBusca(opcoes.previewDados));
            }

            const acoes = document.createElement('div');
            acoes.className = 'flex flex-wrap items-center gap-2 pt-0.5';

            if (opcoes && opcoes.confirmarInclusao && opcoes.previewDados) {
                const btnIncluir = document.createElement('button');
                btnIncluir.type = 'button';
                btnIncluir.textContent = 'Incluir dados no formulário';
                btnIncluir.className = 'px-3 py-1.5 rounded-md bg-primary text-primary-foreground font-semibold hover:bg-primary/90 transition-colors cursor-pointer';
                btnIncluir.addEventListener('click', function () {
                    aplicarDadosUsuarioNoFormularioPagina(opcoes.previewDados, opcoes.status);
                });
                acoes.appendChild(btnIncluir);

                const btnNaoIncluir = document.createElement('button');
                btnNaoIncluir.type = 'button';
                btnNaoIncluir.textContent = 'Não incluir';
                btnNaoIncluir.className = 'px-3 py-1.5 rounded-md border border-border bg-background/80 text-foreground font-medium hover:bg-secondary transition-colors cursor-pointer';
                btnNaoIncluir.addEventListener('click', fecharResultadoBuscaCliente);
                acoes.appendChild(btnNaoIncluir);
            }

            if (opcoes && opcoes.clienteUrl) {
                const link = document.createElement('a');
                link.href = opcoes.clienteUrl;
                link.textContent = 'Ver cadastro do cliente';
                link.className = 'px-3 py-1.5 rounded-md bg-primary text-primary-foreground font-semibold hover:bg-primary/90 transition-colors';
                acoes.appendChild(link);

                const btnFechar = document.createElement('button');
                btnFechar.type = 'button';
                btnFechar.textContent = 'Fechar';
                btnFechar.className = 'px-3 py-1.5 rounded-md border border-border bg-background/80 text-foreground font-medium hover:bg-secondary transition-colors cursor-pointer';
                btnFechar.addEventListener('click', fecharResultadoBuscaCliente);
                acoes.appendChild(btnFechar);
            }

            if (opcoes && opcoes.mostrarLimpar) {
                const btnLimpar = document.createElement('button');
                btnLimpar.type = 'button';
                btnLimpar.textContent = 'Limpar dados preenchidos';
                btnLimpar.className = 'font-semibold underline text-foreground hover:text-primary cursor-pointer';
                btnLimpar.addEventListener('click', limparBuscaUsuarioClientePagina);
                acoes.appendChild(btnLimpar);
            }

            if (acoes.childNodes.length > 0) {
                box.appendChild(acoes);
            }

            box.classList.remove('hidden');
        }

        function limparBuscaUsuarioClientePagina() {
            const form = document.getElementById('form-cadastro-cliente');
            const inputBusca = document.getElementById('busca_usuario_cliente');
            const chk = document.getElementById('criar_usuario');
            const aviso = document.getElementById('aviso-conta-ja-existe');

            if (inputBusca) inputBusca.value = '';
            fecharResultadoBuscaCliente();
            if (form) {
                ['nome', 'cpf_cnpj', 'telefone', 'email', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado'].forEach(function (campo) {
                    const el = form.querySelector('[name="' + campo + '"]');
                    if (el) el.value = '';
                });
            }
            if (chk) {
                chk.disabled = false;
                chk.checked = false;
                toggleSenhaCliente(false);
            }
            if (aviso) aviso.classList.add('hidden');
        }

        async function buscarUsuarioClientePagina() {
            const inputBusca = document.getElementById('busca_usuario_cliente');
            const btnBuscar = document.getElementById('btn-buscar-usuario-cliente');
            const form = document.getElementById('form-cadastro-cliente');

            const termo = (inputBusca?.value || '').trim();
            if (!termo) {
                renderResultadoBuscaCliente('erro', 'Informe um e-mail completo ou CPF/CNPJ para buscar.');
                return;
            }

            const empresaSelect = form?.querySelector('[name="empresa_id"]');
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
                    renderResultadoBuscaCliente('erro', data.message || 'Não foi possível realizar a busca.');
                    return;
                }

                if (!data.found) {
                    renderResultadoBuscaCliente('neutro', data.message || 'Nenhuma conta encontrada.');
                    return;
                }

                if (data.status === 'ja_vinculado_esta_empresa') {
                    renderResultadoBuscaCliente('aviso', data.message, {
                        previewDados: data.dados,
                        clienteUrl: data.cliente_url,
                    });
                    return;
                }

                renderResultadoBuscaCliente(
                    data.status === 'vinculado_outra_empresa' ? 'aviso' : 'sucesso',
                    data.message,
                    {
                        previewDados: data.dados,
                        confirmarInclusao: true,
                        status: data.status,
                    }
                );
            } catch (e) {
                renderResultadoBuscaCliente('erro', 'Erro ao consultar conta de usuário. Tente novamente.');
            } finally {
                if (btnBuscar) btnBuscar.disabled = false;
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const chk = document.getElementById('criar_usuario');
            if (chk) toggleSenhaCliente(chk.checked);

            const btnBuscar = document.getElementById('btn-buscar-usuario-cliente');
            const inputBusca = document.getElementById('busca_usuario_cliente');

            if (btnBuscar) {
                btnBuscar.addEventListener('click', buscarUsuarioClientePagina);
            }
            if (inputBusca) {
                inputBusca.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        buscarUsuarioClientePagina();
                    }
                });
            }
        });

        async function pesquisacep(valor) {
            const cep = (valor || '').replace(/\D/g, '');
            if (cep === '') {
                return;
            }
            if (!/^[0-9]{8}$/.test(cep)) {
                alert('Formato de CEP inválido.');
                return;
            }

            const ruaEl = document.getElementById('rua');
            const bairroEl = document.getElementById('bairro');
            const cidadeEl = document.getElementById('cidade');
            const ufEl = document.getElementById('uf');
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), 5000);

            try {
                const response = await fetch('https://viacep.com.br/ws/' + cep + '/json/', {
                    signal: controller.signal,
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) {
                    return;
                }
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
    </script>
    @endpush
</x-layouts.app>
