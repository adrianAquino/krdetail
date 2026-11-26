<!DOCTYPE html>
<html lang="pt-BR" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@hasSection('title')@yield('title') - KR Detail @else{{ isset($title) ? $title . ' - KR Detail' : 'KR Detail - Sistema de Gestão de Estética Automotiva' }}@endif</title>
    <meta name="description" content="Sistema de gestão e agendamento para estética automotiva profissional">

    <script>
        (function () {
            const savedTheme = localStorage.getItem('krdetail-theme') || 'dark';
            if (savedTheme === 'light') {
                document.documentElement.classList.remove('dark');
            } else {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>

<body
    class="font-sans antialiased bg-background text-foreground min-h-screen selection:bg-primary/20 selection:text-primary transition-colors duration-200">
    <!-- Link de Acessibilidade para Pular Navegação (Teclado / Leitores de Tela) -->
    <a href="#conteudo-principal" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-xl focus:bg-primary focus:px-4 focus:py-2.5 focus:text-xs focus:font-bold focus:text-primary-foreground focus:shadow-lg">
        Pular para o conteúdo principal
    </a>

    <!-- Sidebar Navigation -->
    <x-sidebar />

    <!-- Main Content Area -->
    <main id="conteudo-principal" tabindex="-1" class="min-h-screen bg-background pt-16 lg:pl-64 lg:pt-0 print:pl-0 print:pt-0 flex flex-col focus:outline-none">
        <div class="flex-1 p-4 lg:p-8 max-w-7xl w-full mx-auto">
            <!-- Flash Session Feedback -->
            @if(session('success'))
                <div class="mb-6">
                    <x-alert type="success" :title="session('success')">
                        Ação realizada com sucesso.
                    </x-alert>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6">
                    <x-alert type="error" :title="session('error')" />
                </div>
            @endif

            @if(session('info'))
                <div class="mb-6">
                    <x-alert type="info" :title="session('info')" />
                </div>
            @endif

            @if(session('warning'))
                <div class="mb-6">
                    <x-alert type="warning" :title="session('warning')" />
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6">
                    <x-alert type="error" title="Existem erros no formulário:">
                        <ul class="list-disc list-inside space-y-1 text-xs mt-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                </div>
            @endif

            <!-- Content Slot -->
            {{ $slot ?? '' }}
            @yield('content')
        </div>

        <!-- Footer -->
        <footer class="border-t border-border/50 py-4 px-6 text-center text-xs text-muted-foreground mt-auto">
            <p>&copy; {{ date('Y') }} KR Detail - Gestão de Estética Automotiva. Todos os direitos reservados.</p>
        </footer>
    </main>

    <!-- Recursos de Acessibilidade: VLibras + Leitor de Voz (pt-BR) -->
    <x-vlibras />

    <!-- Global Modal Helpers -->
    <script>
        window.openModal = function (id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
            }
        };

        window.closeModal = function (id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }
        };

        // Fechar modal com tecla ESC ou clicando fora
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('[data-modal]').forEach(modal => {
                    if (!modal.classList.contains('hidden')) {
                        window.closeModal(modal.id);
                    }
                });
            }
        });

        document.addEventListener('click', function (event) {
            if (event.target.hasAttribute('data-modal')) {
                window.closeModal(event.target.id);
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            const modalAutoOpen = document.querySelector('[data-modal-auto-open="true"]');
            if (modalAutoOpen) {
                document.body.classList.add('overflow-hidden');
                const modalBody = modalAutoOpen.querySelector('[data-modal-body]');
                if (modalBody) {
                    modalBody.scrollTop = 0;
                }
            }
        });

        // Proteção global contra duplo envio de formulários POST/PUT/PATCH/DELETE
        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!form || form.hasAttribute('data-allow-resubmit') || (form.method || '').toLowerCase() === 'get') {
                return;
            }
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }
            const modalPai = form.closest('[data-modal]');
            if (modalPai && modalPai.id && !form.querySelector('input[name="_modal_target"]')) {
                const hiddenModalInput = document.createElement('input');
                hiddenModalInput.type = 'hidden';
                hiddenModalInput.name = '_modal_target';
                hiddenModalInput.value = modalPai.id;
                form.appendChild(hiddenModalInput);
            }
            form.dataset.submitting = 'true';
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-70', 'cursor-wait');
            });
            setTimeout(function () {
                if (document.body.contains(form)) {
                    delete form.dataset.submitting;
                    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) {
                        btn.disabled = false;
                        btn.classList.remove('opacity-70', 'cursor-wait');
                    });
                }
            }, 8000);
        });

        // Consulta automática de endereço via ViaCEP (escopo isolado por formulário)
        window.consultarViaCepInput = async function (inputOrEvent) {
            const inputEl = inputOrEvent && inputOrEvent.target ? inputOrEvent.target : inputOrEvent;
            if (!inputEl || typeof inputEl.value !== 'string') {
                return;
            }

            const cep = inputEl.value.replace(/\D/g, '');
            const form = inputEl.closest('form') || document;
            const ruaEl = form.querySelector('input[name="logradouro"]');
            const bairroEl = form.querySelector('input[name="bairro"]');
            const cidadeEl = form.querySelector('input[name="cidade"]');
            const ufEl = form.querySelector('input[name="estado"], input[name="uf"], select[name="estado"], select[name="uf"]');
            const numeroEl = form.querySelector('input[name="numero"]');

            const limparCamposEndereco = function () {
                if (ruaEl) ruaEl.value = '';
                if (bairroEl) bairroEl.value = '';
                if (cidadeEl) cidadeEl.value = '';
                if (ufEl) ufEl.value = '';
            };

            if (cep === '') {
                return;
            }

            if (!/^[0-9]{8}$/.test(cep)) {
                limparCamposEndereco();
                alert('Formato de CEP inválido.');
                return;
            }

            if (inputEl.dataset.viacepLoading === cep) {
                return;
            }
            inputEl.dataset.viacepLoading = cep;
            inputEl.value = cep.slice(0, 5) + '-' + cep.slice(5);

            const valoresAnteriores = {
                rua: ruaEl ? ruaEl.value : '',
                bairro: bairroEl ? bairroEl.value : '',
                cidade: cidadeEl ? cidadeEl.value : '',
                uf: ufEl ? ufEl.value : '',
            };

            if (ruaEl) ruaEl.value = '...';
            if (bairroEl) bairroEl.value = '...';
            if (cidadeEl) cidadeEl.value = '...';
            if (ufEl) ufEl.value = '...';

            const controller = new AbortController();
            const timer = setTimeout(function () {
                controller.abort();
            }, 5000);

            try {
                const response = await fetch('https://viacep.com.br/ws/' + cep + '/json/', {
                    signal: controller.signal,
                    headers: { 'Accept': 'application/json' },
                });

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                const conteudo = await response.json();
                if (conteudo && !conteudo.erro) {
                    if (ruaEl) ruaEl.value = String(conteudo.logradouro || '').slice(0, 100);
                    if (bairroEl) bairroEl.value = String(conteudo.bairro || '').slice(0, 100);
                    if (cidadeEl) cidadeEl.value = String(conteudo.localidade || '').slice(0, 100);
                    if (ufEl) ufEl.value = String(conteudo.uf || '').toUpperCase().slice(0, 2);

                    const modalPai = inputEl.closest('[data-modal]');
                    const modalVisivel = !modalPai || !modalPai.classList.contains('hidden');
                    if (modalVisivel && numeroEl && (!numeroEl.value || numeroEl.value.trim() === '')) {
                        numeroEl.focus();
                    }
                } else {
                    limparCamposEndereco();
                    alert('CEP não encontrado.');
                }
            } catch (e) {
                if (ruaEl && ruaEl.value === '...') ruaEl.value = valoresAnteriores.rua;
                if (bairroEl && bairroEl.value === '...') bairroEl.value = valoresAnteriores.bairro;
                if (cidadeEl && cidadeEl.value === '...') cidadeEl.value = valoresAnteriores.cidade;
                if (ufEl && ufEl.value === '...') ufEl.value = valoresAnteriores.uf;

                if (e.name !== 'AbortError') {
                    console.warn('Falha ao consultar ViaCEP:', e);
                }
                alert('Não foi possível consultar o CEP no momento. Preencha o endereço manualmente.');
            } finally {
                clearTimeout(timer);
                delete inputEl.dataset.viacepLoading;
            }
        };

        // Delegação automática para qualquer input[name="cep"] sem manipulador onblur próprio
        document.addEventListener('focusout', function (event) {
            const target = event.target;
            if (!target || !target.matches || !target.matches('input[name="cep"]')) {
                return;
            }
            if (target.hasAttribute('onblur') || target.dataset.viacepBound === '1') {
                return;
            }
            window.consultarViaCepInput(target);
        });
    </script>

    @stack('scripts')
</body>

</html>
