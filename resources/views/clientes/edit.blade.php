<x-layouts.app :title="'Editar: ' . ($cliente->nome ?? 'Cliente')">
    <x-header
        :title="'Editar Cliente: ' . ($cliente->nome ?? '')"
        subtitle="Atualize os dados cadastrais, endereço ou credenciais de acesso."
    >
        <x-slot:actions>
            <x-button href="{{ route('clientes.show', $cliente->id ?? 1) }}" variant="outline" size="sm" icon="eye">
                Ver Ficha
            </x-button>
            <x-button href="{{ route('clientes.index') }}" variant="ghost" size="sm" icon="chevron-left">
                Voltar à Lista
            </x-button>
        </x-slot:actions>
    </x-header>

    <div class="max-w-3xl">
        <form method="POST" action="{{ route('clientes.update', $cliente->id ?? 1) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Dados Principais -->
            <x-card title="Dados Pessoais / Comerciais" description="Informações de identificação e contato direto">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input
                            name="nome"
                            label="Nome Completo / Razão Social"
                            :value="$cliente->nome ?? ''"
                            required
                        />
                    </div>

                    <div>
                        <x-input
                            name="cpf_cnpj"
                            label="CPF ou CNPJ"
                            :value="$cliente->cpf_cnpj ?? ''"
                        />
                    </div>

                    <div>
                        <x-input
                            name="telefone"
                            label="Telefone / WhatsApp"
                            :value="$cliente->telefone ?? ''"
                            required
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input
                            type="email"
                            name="email"
                            label="E-mail"
                            :value="$cliente->email ?? ''"
                        />
                    </div>
                </div>
            </x-card>

            <!-- Endereço -->
            <x-card title="Endereço" description="Localização e dados para atendimento">
                <div class="grid gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-2">
                        <x-input
                            name="cep"
                            label="CEP"
                            :value="$cliente->cep ?? ''"
                        />
                    </div>

                    <div class="sm:col-span-4">
                        <x-input
                            name="logradouro"
                            label="Logradouro"
                            :value="$cliente->logradouro ?? ''"
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input
                            name="numero"
                            label="Número"
                            :value="$cliente->numero ?? ''"
                        />
                    </div>

                    <div class="sm:col-span-4">
                        <x-input
                            name="complemento"
                            label="Complemento"
                            :value="$cliente->complemento ?? ''"
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input
                            name="bairro"
                            label="Bairro"
                            :value="$cliente->bairro ?? ''"
                        />
                    </div>

                    <div class="sm:col-span-3">
                        <x-input
                            name="cidade"
                            label="Cidade"
                            :value="$cliente->cidade ?? ''"
                        />
                    </div>

                    <div class="sm:col-span-1">
                        <x-input
                            name="estado"
                            label="UF"
                            :value="$cliente->estado ?? ''"
                            maxlength="2"
                        />
                    </div>
                </div>
            </x-card>

            <!-- Acesso ao Sistema -->
            <x-card title="Conta de Acesso ao Sistema" description="Situação do usuário no sistema">
                @if(!empty($cliente->user_id))
                    <div class="p-3.5 rounded-lg border border-success/30 bg-success/5 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <x-icon name="check-circle" class="w-5 h-5 text-success" />
                            <div>
                                <p class="text-sm font-semibold text-foreground">Este cliente possui acesso ativo</p>
                                <p class="text-xs text-muted-foreground">Usuário ID: #{{ $cliente->user_id }} vinculado a este registro.</p>
                            </div>
                        </div>
                        <x-badge-status status="acesso_ativo" />
                    </div>
                @else
                    <div class="space-y-4">
                        <div class="p-3.5 rounded-lg border border-border bg-secondary/30 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Cliente sem conta de usuário</p>
                                <p class="text-xs text-muted-foreground">O cliente não possui login cadastrado no sistema.</p>
                            </div>
                            <x-badge-status status="sem_acesso" />
                        </div>

                        <div class="flex items-start gap-3 p-3 rounded-lg border border-border">
                            <input
                                type="checkbox"
                                id="criar_usuario"
                                name="criar_usuario"
                                value="1"
                                class="mt-1 h-4 w-4 rounded border-border bg-input text-primary focus:ring-primary"
                                onchange="document.getElementById('senha_container').classList.toggle('hidden', !this.checked)"
                            />
                            <label for="criar_usuario" class="text-xs text-foreground cursor-pointer">
                                <span class="font-semibold block text-sm">Criar conta de acesso agora</span>
                                <span class="text-muted-foreground">Será criada uma conta de acesso para este cliente com perfil "cliente".</span>
                            </label>
                        </div>

                        <div id="senha_container" class="hidden grid gap-4 sm:grid-cols-2 pt-2">
                            <x-input
                                type="password"
                                name="password"
                                label="Senha de Acesso"
                                placeholder="Mínimo 8 caracteres"
                            />
                            <x-input
                                type="password"
                                name="password_confirmation"
                                label="Confirmar Senha"
                                placeholder="Repita a senha"
                            />
                        </div>
                    </div>
                @endif
            </x-card>

            <!-- Form Actions -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <x-button href="{{ route('clientes.index') }}" variant="outline" size="md">
                    Cancelar
                </x-button>
                <x-button type="submit" variant="primary" size="md" icon="save">
                    Salvar Alterações
                </x-button>
            </div>
        </form>
    </div>
</x-layouts.app>