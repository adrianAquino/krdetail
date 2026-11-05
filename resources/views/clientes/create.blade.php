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
                            placeholder="Ex: João da Silva"
                            required
                        />
                    </div>

                    <div>
                        <x-input
                            name="cpf_cnpj"
                            label="CPF ou CNPJ"
                            placeholder="000.000.000-00"
                        />
                    </div>

                    <div>
                        <x-input
                            name="telefone"
                            label="Telefone / WhatsApp"
                            placeholder="(11) 90000-0000"
                            required
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input
                            type="email"
                            name="email"
                            label="E-mail"
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
                            placeholder="00000-000"
                            id="cep"
                            onblur="pesquisacep(this.value);"
                        />
                    </div>

                    <div class="sm:col-span-4">
                        <x-input
                            name="logradouro"
                            label="Logradouro / Rua / Avenida"
                            placeholder="Ex: Av. Paulista"
                            id="rua"
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input
                            name="numero"
                            label="Número"
                            placeholder="123"
                        />
                    </div>

                    <div class="sm:col-span-4">
                        <x-input
                            name="complemento"
                            label="Complemento"
                            placeholder="Apto 42, Bloco B"
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input
                            name="bairro"
                            label="Bairro"
                            placeholder="Bela Vista"
                            id="bairro"
                        />
                    </div>

                    <div class="sm:col-span-3">
                        <x-input
                            name="cidade"
                            label="Cidade"
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
                            onchange="document.getElementById('senha_container').classList.toggle('hidden', !this.checked)"
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
                            placeholder="Mínimo de 8 caracteres"
                        />
                        <x-input
                            type="password"
                            name="password_confirmation"
                            label="Confirmar Senha"
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
        function limpa_formulário_cep() {
            // Limpa valores do formulário de cep.
            document.getElementById('rua').value = "";
            document.getElementById('bairro').value = "";
            document.getElementById('cidade').value = "";
            document.getElementById('uf').value = "";
        }

        function meu_callback(conteudo) {
            if (!("erro" in conteudo)) {
                // Atualiza os campos com os valores.
                document.getElementById('rua').value = conteudo.logradouro;
                document.getElementById('bairro').value = conteudo.bairro;
                document.getElementById('cidade').value = conteudo.localidade;
                document.getElementById('uf').value = conteudo.uf;
            } else {
                // CEP não Encontrado.
                limpa_formulário_cep();
                alert("CEP não encontrado.");
            }
        }

        function pesquisacep(valor) {
            // Nova variável "cep" somente com dígitos.
            var cep = valor.replace(/\D/g, '');

            // Verifica se campo cep possui valor informado.
            if (cep != "") {
                // Expressão regular para validar o CEP.
                var validacep = /^[0-9]{8}$/;

                // Valida o formato do CEP.
                if (validacep.test(cep)) {
                    // Preenche os campos com "..." enquanto consulta webservice.
                    document.getElementById('rua').value = "...";
                    document.getElementById('bairro').value = "...";
                    document.getElementById('cidade').value = "...";
                    document.getElementById('uf').value = "...";

                    // Cria um elemento javascript.
                    var script = document.createElement('script');

                    // Sincroniza com o callback.
                    script.src = 'https://viacep.com.br/ws/' + cep + '/json/?callback=meu_callback';

                    // Insere script no documento e carrega o conteúdo.
                    document.body.appendChild(script);
                } else {
                    // cep é inválido.
                    limpa_formulário_cep();
                    alert("Formato de CEP inválido.");
                }
            } else {
                // cep sem valor, limpa formulário.
                limpa_formulário_cep();
            }
        }
    </script>
    @endpush
</x-layouts.app>
