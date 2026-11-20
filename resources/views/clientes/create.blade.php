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
        <form method="POST" action="{{ route('clientes.store') }}" class="space-y-6">
            @csrf

            <!-- Dados Principais -->
            <x-card title="Dados Pessoais / Comerciais" description="Informações de identificação e contato direto">
                <div class="grid gap-4 sm:grid-cols-2">
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

        document.addEventListener('DOMContentLoaded', function () {
            const chk = document.getElementById('criar_usuario');
            if (chk) toggleSenhaCliente(chk.checked);
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
