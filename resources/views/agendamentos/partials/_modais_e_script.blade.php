@props([
    'podeCadastrarVeiculo' => true,
    'ignorarAgendamentoId' => null,
])

@if($podeCadastrarVeiculo)
    <!-- Modal de Cadastro Rápido de Veículo (sem sair da tela de Agendamento) -->
    <x-modal id="modal-novo-veiculo" title="Cadastrar Novo Veículo" maxWidth="2xl">
        <form id="form-modal-veiculo" method="POST" action="{{ route('veiculos.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="cliente_id" id="modal_veiculo_cliente_id" value="" />

            <!-- Aviso Explícito de Atribuição ao Cliente Selecionado -->
            <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-xs text-amber-300 flex items-start gap-2.5">
                <x-icon name="info" class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" />
                <div>
                    <p class="font-bold text-amber-200">Aviso de Vinculação de Veículo</p>
                    <p class="mt-0.5">
                        Este veículo será cadastrado e atribuído diretamente ao cliente selecionado:
                        <strong id="modal_veiculo_cliente_nome" class="text-white underline">Cliente selecionado</strong>.
                    </p>
                </div>
            </div>

            <!-- Erros de validação AJAX do Modal -->
            <div id="modal-veiculo-erros" class="hidden rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-xs text-destructive">
                <p class="font-semibold mb-1">Verifique os campos abaixo:</p>
                <ul id="modal-veiculo-erros-lista" class="list-disc list-inside space-y-0.5"></ul>
            </div>

            <div class="grid gap-3.5 sm:grid-cols-2">
                <div>
                    <x-input
                        name="placa"
                        id="modal_veiculo_placa"
                        label="Placa do Veículo"
                        placeholder="Ex: ABC1D23 ou ABC-1234"
                        class="uppercase font-mono tracking-wider font-bold"
                        minlength="7"
                        maxlength="8"
                        required
                    />
                </div>

                <div>
                    <x-input
                        name="ano"
                        id="modal_veiculo_ano"
                        type="number"
                        label="Ano de Fabricação"
                        placeholder="2023"
                        min="1950"
                        max="{{ date('Y') + 1 }}"
                    />
                </div>

                <div>
                    <x-input
                        name="marca"
                        id="modal_veiculo_marca"
                        label="Marca / Fabricante"
                        placeholder="Ex: BMW, Audi, Toyota"
                        maxlength="50"
                        required
                    />
                </div>

                <div>
                    <x-input
                        name="modelo"
                        id="modal_veiculo_modelo"
                        label="Modelo / Versão"
                        placeholder="Ex: 320i M Sport, Corolla"
                        maxlength="80"
                        required
                    />
                </div>

                <div class="sm:col-span-2">
                    <x-input
                        name="cor"
                        id="modal_veiculo_cor"
                        label="Cor / Acabamento"
                        placeholder="Ex: Preto Metálico, Branco Pérola"
                        maxlength="40"
                    />
                </div>

                <div class="sm:col-span-2">
                    <x-textarea
                        name="observacoes"
                        id="modal_veiculo_observacoes"
                        label="Observações / Cuidados Especiais"
                        placeholder="Ex: Pintura vitrificada, detalhes internos..."
                        maxlength="500"
                        rows="2"
                    />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                <x-button type="button" onclick="window.closeModal('modal-novo-veiculo')" variant="outline" size="sm">
                    Cancelar
                </x-button>
                <x-button type="submit" id="btn-salvar-veiculo-modal" variant="primary" size="sm" icon="save">
                    Cadastrar e Selecionar Veículo
                </x-button>
            </div>
        </form>
    </x-modal>
@endif

<!-- Modal Calendário de Disponibilidade por Serviços e Boxes -->
<x-modal id="modal-calendario-agenda" title="Calendário de Disponibilidade da Estética" maxWidth="4xl">
    <div class="space-y-4">
        <!-- Barra de Resumo dos Serviços, Boxes e Pernoite -->
        <div class="rounded-xl border border-border bg-secondary/30 p-3.5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 text-xs">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-foreground">Tempo necessário:</span>
                    <span id="cal-resumo-duracao" class="px-2 py-0.5 rounded-full bg-primary/15 text-primary font-bold">1h 30min</span>
                    <span class="text-muted-foreground">• Capacidade: <strong class="text-foreground">4 boxes simultâneos</strong></span>
                    <span id="cal-veiculo-badge" class="hidden px-2 py-0.5 rounded-full bg-secondary border border-border text-foreground font-mono"></span>
                </div>
                <p class="text-[11px] text-muted-foreground">
                    Os dias e horários disponíveis abaixo já consideram o tempo total dos serviços escolhidos (+30 min de margem), a lotação dos boxes e eventuais conflitos do veículo.
                </p>
            </div>

            <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-border bg-card cursor-pointer shrink-0">
                <input type="checkbox" id="modal_cal_deixar_veiculo" class="h-4 w-4 rounded border-border bg-input text-primary focus:ring-primary" />
                <span class="text-xs font-medium text-foreground">Deixar veículo na estética (pernoite)</span>
            </label>
        </div>

        <div id="cal-alerta-pernoite" class="hidden rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-300 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <span>Existem horários disponíveis se o veículo puder ficar na estética para conclusão no próximo dia útil.</span>
            <button type="button" id="btn-cal-ativar-pernoite" class="px-3 py-1 rounded-lg bg-amber-500 text-slate-950 font-bold text-xs hover:bg-amber-400 transition-colors cursor-pointer shrink-0">
                Ativar Pernoite
            </button>
        </div>

        <div id="cal-erro-carregamento" class="hidden rounded-xl border border-destructive/40 bg-destructive/10 p-3 text-xs text-destructive"></div>

        <div class="grid gap-5 lg:grid-cols-12">
            <!-- Calendário Mensal (7 colunas) -->
            <div class="lg:col-span-7 space-y-3">
                <div class="flex items-center justify-between">
                    <button type="button" id="cal-mes-anterior" class="px-2.5 py-1.5 rounded-lg border border-border bg-secondary/40 hover:bg-secondary text-xs font-medium text-foreground cursor-pointer">
                        &larr; Anterior
                    </button>
                    <h4 id="cal-mes-titulo" class="text-sm font-bold text-foreground capitalize">Carregando...</h4>
                    <button type="button" id="cal-mes-proximo" class="px-2.5 py-1.5 rounded-lg border border-border bg-secondary/40 hover:bg-secondary text-xs font-medium text-foreground cursor-pointer">
                        Próximo &rarr;
                    </button>
                </div>

                <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold text-muted-foreground pb-1">
                    <div>Dom</div>
                    <div>Seg</div>
                    <div>Ter</div>
                    <div>Qua</div>
                    <div>Qui</div>
                    <div>Sex</div>
                    <div>Sáb</div>
                </div>

                <div id="cal-grid-dias" class="grid grid-cols-7 gap-1.5 min-h-[240px]"></div>

                <!-- Legenda -->
                <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-border/60 text-[11px] text-muted-foreground">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Disponível
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span> Só c/ pernoite
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500/70"></span> Boxes lotados
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-zinc-600"></span> Fechado / Passado
                    </span>
                </div>
            </div>

            <!-- Grade de Horários do Dia Selecionado (5 colunas) -->
            <div class="lg:col-span-5 flex flex-col border border-border rounded-xl bg-secondary/20 p-3.5">
                <div class="pb-2.5 mb-2.5 border-b border-border flex items-center justify-between">
                    <div>
                        <h5 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Horários do Dia</h5>
                        <p id="cal-dia-selecionado-label" class="text-sm font-bold text-foreground mt-0.5">Selecione um dia no calendário</p>
                    </div>
                </div>

                <div id="cal-lista-slots" class="space-y-2 overflow-y-auto max-h-[320px] pr-1 text-xs">
                    <p class="text-muted-foreground italic text-center py-8">
                        Clique em um dia disponível no calendário ao lado para visualizar os horários.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-modal>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const formAgendamento = document.getElementById('form-agendamento');
        if (!formAgendamento) return;

        const formCfg = formAgendamento.dataset || {};
        const clientesVeiculos = JSON.parse(formCfg.clientesVeiculos || '{}');
        const rotaDisponibilidade = formCfg.rotaDisponibilidade || '';
        const ignorarAgendamentoId = formCfg.ignorarAgendamentoId || '';
        const valorOriginalInicio = formCfg.valorOriginalInicio || '';
        const targetVeiculoPreInit = formCfg.targetVeiculoPre || '';

        const clienteSelect = document.getElementById('cliente_select');
        const veiculoSelect = document.getElementById('veiculo_select');
        const veiculoAviso = document.getElementById('veiculo_aviso');
        const statusSelect = document.getElementById('status_select');
        const inputDataInicio = document.getElementById('data_inicio');
        const inputDataFim = document.getElementById('data_fim');
        const erroDataInicio = document.getElementById('erro_data_inicio');
        const avisoUltrapassaExpediente = document.getElementById('aviso_ultrapassa_expediente');
        const chkDeixarVeiculo = document.getElementById('deixar_veiculo');

        const checkboxes = document.querySelectorAll('.service-checkbox');
        const listaResumo = document.getElementById('resumo-servicos-lista');
        const resumoValorTotal = document.getElementById('resumo-valor-total');
        const resumoTempoServicos = document.getElementById('resumo-tempo-servicos');
        const resumoGargalo = document.getElementById('resumo-gargalo');
        const resumoTempoTotal = document.getElementById('resumo-tempo-total');
        const resumoDataFim = document.getElementById('resumo-data-fim');

        const GARGALO_MINUTOS = 30;
        let totalMinutosComGargaloAtual = 0;
        let veiculoSelecionadoAtual = targetVeiculoPreInit;

        function obterClienteSelecionadoInfo() {
            if (!clienteSelect) {
                return { id: '', nome: 'Cliente selecionado' };
            }
            const id = clienteSelect.value || '';
            let nome = 'Cliente selecionado';
            if (clienteSelect.tagName === 'SELECT') {
                const opt = clienteSelect.options[clienteSelect.selectedIndex];
                nome = opt?.dataset?.nome || opt?.textContent?.split('—')[0]?.trim() || 'Cliente selecionado';
            } else {
                nome = clienteSelect.dataset?.nome || 'Você (sua conta)';
            }
            return { id, nome };
        }

        function renderAvisoVeiculo(semVeiculos) {
            if (!veiculoAviso) return;
            const infoCli = obterClienteSelecionadoInfo();
            veiculoAviso.replaceChildren();

            const wrapper = document.createElement('span');
            wrapper.className = 'inline-flex flex-wrap items-center gap-1';

            if (semVeiculos) {
                wrapper.appendChild(document.createTextNode('Nenhum veículo cadastrado. '));
            } else {
                wrapper.appendChild(document.createTextNode('Não encontrou o veículo desejado? '));
            }

            if (document.getElementById('modal-novo-veiculo')) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'text-primary font-semibold underline hover:text-primary/80 cursor-pointer';
                btn.textContent = semVeiculos ? 'Cadastrar veículo agora' : 'Cadastrar novo veículo';
                btn.addEventListener('click', window.abrirModalNovoVeiculo);
                wrapper.appendChild(btn);

                const avisoAtribuicao = document.createElement('span');
                avisoAtribuicao.className = 'block w-full text-[11px] text-amber-400/90 mt-0.5';
                avisoAtribuicao.textContent = `Atenção: o veículo cadastrado por aqui será atribuído ao cliente selecionado (${infoCli.nome}).`;
                veiculoAviso.appendChild(wrapper);
                veiculoAviso.appendChild(avisoAtribuicao);
                return;
            }

            veiculoAviso.appendChild(wrapper);
        }

        function atualizarVeiculos() {
            if (!clienteSelect || !veiculoSelect) return;
            const clienteId = clienteSelect.value;
            const veiculos = clientesVeiculos[clienteId] || [];
            const jaSelecionado = veiculoSelect.value || veiculoSelecionadoAtual;

            veiculoSelect.replaceChildren();

            if (veiculos.length === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'Nenhum veículo cadastrado';
                veiculoSelect.appendChild(opt);
                renderAvisoVeiculo(true);
            } else {
                const optDefault = document.createElement('option');
                optDefault.value = '';
                optDefault.textContent = 'Selecione o veículo (' + veiculos.length + ' disponível(is))';
                veiculoSelect.appendChild(optDefault);

                veiculos.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = String(v.id);
                    opt.textContent = `${v.placa} — ${v.marca} ${v.modelo}`;
                    if (jaSelecionado && String(jaSelecionado) === String(v.id)) {
                        opt.selected = true;
                    }
                    veiculoSelect.appendChild(opt);
                });

                if (veiculos.length === 1 && !jaSelecionado) {
                    veiculoSelect.value = String(veiculos[0].id);
                }

                renderAvisoVeiculo(false);
            }
        }

        if (clienteSelect) {
            clienteSelect.addEventListener('change', function () {
                veiculoSelecionadoAtual = '';
                if (veiculoSelect) veiculoSelect.value = '';
                atualizarVeiculos();
            });
            atualizarVeiculos();
        }
        if (veiculoSelect) {
            veiculoSelect.addEventListener('change', function () {
                veiculoSelecionadoAtual = veiculoSelect.value;
            });
        }

        // === Modal Rápido de Cadastro de Veículo ===
        window.abrirModalNovoVeiculo = function () {
            const infoCli = obterClienteSelecionadoInfo();
            if (!infoCli.id) {
                alert('Selecione primeiro o cliente para vincular o veículo.');
                return;
            }
            const hiddenCliente = document.getElementById('modal_veiculo_cliente_id');
            const labelCliente = document.getElementById('modal_veiculo_cliente_nome');
            const errosEl = document.getElementById('modal-veiculo-erros');
            if (hiddenCliente) hiddenCliente.value = infoCli.id;
            if (labelCliente) labelCliente.textContent = infoCli.nome;
            if (errosEl) errosEl.classList.add('hidden');
            window.openModal('modal-novo-veiculo');
        };

        const formModalVeiculo = document.getElementById('form-modal-veiculo');
        if (formModalVeiculo) {
            formModalVeiculo.setAttribute('data-allow-resubmit', '1');
            const errosContainer = document.getElementById('modal-veiculo-erros');
            const errosLista = document.getElementById('modal-veiculo-erros-lista');
            const btnSalvarVeic = document.getElementById('btn-salvar-veiculo-modal');
            const alertaCriado = document.getElementById('alerta-veiculo-criado');
            const alertaCriadoTexto = document.getElementById('alerta-veiculo-criado-texto');

            function renderErrosVeiculo(msgs) {
                if (!errosContainer || !errosLista) return;
                errosLista.replaceChildren();
                msgs.forEach(m => {
                    const li = document.createElement('li');
                    li.textContent = String(m);
                    errosLista.appendChild(li);
                });
                errosContainer.classList.remove('hidden');
            }

            formModalVeiculo.addEventListener('submit', async function (e) {
                e.preventDefault();
                errosContainer?.classList.add('hidden');
                if (btnSalvarVeic) btnSalvarVeic.disabled = true;

                try {
                    const formData = new FormData(formModalVeiculo);
                    const response = await fetch(formModalVeiculo.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        const msgs = [];
                        if (data.errors) {
                            Object.values(data.errors).forEach(arr => arr.forEach(msg => msgs.push(msg)));
                        } else if (data.message) {
                            msgs.push(data.message);
                        }
                        renderErrosVeiculo(msgs.length ? msgs : ['Verifique os dados informados para o veículo.']);
                        return;
                    }

                    if (data.veiculo) {
                        const cid = String(data.veiculo.cliente_id);
                        if (!clientesVeiculos[cid]) {
                            clientesVeiculos[cid] = [];
                        }
                        clientesVeiculos[cid].push({
                            id: data.veiculo.id,
                            placa: data.veiculo.placa,
                            marca: data.veiculo.marca,
                            modelo: data.veiculo.modelo,
                        });
                        veiculoSelecionadoAtual = String(data.veiculo.id);
                        atualizarVeiculos();
                        if (veiculoSelect) {
                            veiculoSelect.value = String(data.veiculo.id);
                        }
                    }

                    const infoCli = obterClienteSelecionadoInfo();
                    formModalVeiculo.reset();
                    window.closeModal('modal-novo-veiculo');

                    if (alertaCriado && alertaCriadoTexto && data.veiculo) {
                        alertaCriadoTexto.textContent = `Veículo ${data.veiculo.placa} (${data.veiculo.marca} ${data.veiculo.modelo}) cadastrado, atribuído a ${infoCli.nome} e selecionado automaticamente!`;
                        alertaCriado.classList.remove('hidden');
                    }
                } catch (err) {
                    renderErrosVeiculo(['Ocorreu um erro ao salvar o veículo. Tente novamente.']);
                } finally {
                    if (btnSalvarVeic) btnSalvarVeic.disabled = false;
                }
            });
        }

        // === Cálculos de Duração & Previsão de Término (com suporte a pernoite) ===
        function formatarDuracao(minutosTotais) {
            if (minutosTotais <= 0) return '0min';
            const horas = Math.floor(minutosTotais / 60);
            const minutos = minutosTotais % 60;
            if (horas > 0) {
                return minutos > 0 ? `${horas}h ${minutos}min` : `${horas}h 00min`;
            }
            return `${minutos}min`;
        }

        function calcularFimComExpediente(inicio, duracaoMinutos, deixarVeiculo) {
            if (!deixarVeiculo) {
                return new Date(inicio.getTime() + duracaoMinutos * 60000);
            }

            let restante = duracaoMinutos;
            let cursor = new Date(inicio.getTime());
            let guard = 0;

            while (restante > 0 && guard < 60) {
                guard++;
                if (cursor.getDay() === 0) {
                    cursor.setDate(cursor.getDate() + 1);
                    cursor.setHours(8, 0, 0, 0);
                    continue;
                }

                const abertura = new Date(cursor.getTime());
                abertura.setHours(8, 0, 0, 0);
                const fechamento = new Date(cursor.getTime());
                fechamento.setHours(18, 0, 0, 0);

                if (cursor < abertura) {
                    cursor = abertura;
                }
                if (cursor >= fechamento) {
                    cursor.setDate(cursor.getDate() + 1);
                    cursor.setHours(8, 0, 0, 0);
                    continue;
                }

                const dispHoje = Math.floor((fechamento.getTime() - cursor.getTime()) / 60000);
                if (dispHoje <= 0) {
                    cursor.setDate(cursor.getDate() + 1);
                    cursor.setHours(8, 0, 0, 0);
                    continue;
                }

                if (restante <= dispHoje) {
                    return new Date(cursor.getTime() + restante * 60000);
                }

                restante -= dispHoje;
                cursor.setDate(cursor.getDate() + 1);
                cursor.setHours(8, 0, 0, 0);
            }

            return cursor;
        }

        function atualizarPrevisaoConclusao() {
            if (!inputDataInicio || !inputDataInicio.value || totalMinutosComGargaloAtual <= 0) {
                if (inputDataFim) inputDataFim.value = '';
                if (resumoDataFim) resumoDataFim.textContent = '--/--/---- --:--';
                avisoUltrapassaExpediente?.classList.add('hidden');
                return;
            }

            const inicio = new Date(inputDataInicio.value);
            if (isNaN(inicio.getTime())) {
                if (inputDataFim) inputDataFim.value = '';
                if (resumoDataFim) resumoDataFim.textContent = '--/--/---- --:--';
                avisoUltrapassaExpediente?.classList.add('hidden');
                return;
            }

            const deixarVeiculo = Boolean(chkDeixarVeiculo && chkDeixarVeiculo.checked);
            const fimMesmoDia = new Date(inicio.getTime() + totalMinutosComGargaloAtual * 60000);
            const limiteMesmoDia = new Date(inicio.getTime());
            limiteMesmoDia.setHours(18, 0, 0, 0);

            const ultrapassa = fimMesmoDia > limiteMesmoDia;
            if (avisoUltrapassaExpediente) {
                avisoUltrapassaExpediente.classList.toggle('hidden', !ultrapassa || deixarVeiculo);
            }

            const fim = calcularFimComExpediente(inicio, totalMinutosComGargaloAtual, deixarVeiculo);
            const pad = n => String(n).padStart(2, '0');

            const valorLocal = `${fim.getFullYear()}-${pad(fim.getMonth() + 1)}-${pad(fim.getDate())}T${pad(fim.getHours())}:${pad(fim.getMinutes())}`;
            const valorExibicao = `${pad(fim.getDate())}/${pad(fim.getMonth() + 1)}/${fim.getFullYear()} às ${pad(fim.getHours())}:${pad(fim.getMinutes())}` + (deixarVeiculo && fim.toDateString() !== inicio.toDateString() ? ' (pernoite)' : '');

            if (inputDataFim) inputDataFim.value = valorLocal;
            if (resumoDataFim) resumoDataFim.textContent = valorExibicao;
        }

        function atualizarResumoServicos() {
            let totalValor = 0;
            let totalMinutosServicos = 0;
            const selecionados = [];

            checkboxes.forEach(cb => {
                const label = cb.closest('.service-checkbox-label');
                if (cb.checked) {
                    label?.classList.add('border-primary', 'bg-primary/10');
                    const preco = parseFloat(cb.dataset.preco) || 0;
                    const tempo = parseInt(cb.dataset.tempo) || 0;
                    totalValor += preco;
                    totalMinutosServicos += tempo;
                    selecionados.push({
                        id: cb.value,
                        nome: cb.dataset.nome,
                        preco: preco,
                        tempoStr: cb.dataset.tempoStr
                    });
                } else {
                    label?.classList.remove('border-primary', 'bg-primary/10');
                }
            });

            if (listaResumo) {
                listaResumo.replaceChildren();
                if (selecionados.length === 0) {
                    const emptyP = document.createElement('p');
                    emptyP.className = 'text-muted-foreground italic text-center py-4';
                    emptyP.textContent = 'Nenhum serviço selecionado ainda.';
                    listaResumo.appendChild(emptyP);
                    totalMinutosComGargaloAtual = 0;
                } else {
                    selecionados.forEach(s => {
                        const row = document.createElement('div');
                        row.className = 'flex items-center justify-between py-1.5 border-b border-border/50';

                        const info = document.createElement('div');
                        const nomeSpan = document.createElement('span');
                        nomeSpan.className = 'font-medium text-foreground';
                        nomeSpan.textContent = s.nome;
                        const tempoSpan = document.createElement('span');
                        tempoSpan.className = 'text-[10px] text-muted-foreground block';
                        tempoSpan.textContent = s.tempoStr;
                        info.appendChild(nomeSpan);
                        info.appendChild(tempoSpan);

                        const valorSpan = document.createElement('span');
                        valorSpan.className = 'font-bold text-foreground';
                        valorSpan.textContent = `R$ ${s.preco.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

                        row.appendChild(info);
                        row.appendChild(valorSpan);
                        listaResumo.appendChild(row);
                    });
                    totalMinutosComGargaloAtual = totalMinutosServicos + GARGALO_MINUTOS;
                }
            } else {
                totalMinutosComGargaloAtual = parseInt(formCfg.duracaoOsFixa || '0', 10) || 0;
            }

            if (resumoTempoServicos) resumoTempoServicos.textContent = formatarDuracao(totalMinutosServicos);
            if (resumoGargalo) resumoGargalo.textContent = selecionados.length > 0 ? `+${GARGALO_MINUTOS}min` : '0min';
            if (resumoTempoTotal) resumoTempoTotal.textContent = formatarDuracao(totalMinutosComGargaloAtual);
            if (resumoValorTotal) resumoValorTotal.textContent = `R$ ${totalValor.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

            atualizarPrevisaoConclusao();
        }

        if (checkboxes.length > 0) {
            checkboxes.forEach(cb => cb.addEventListener('change', atualizarResumoServicos));
            atualizarResumoServicos();
        }

        if (chkDeixarVeiculo) {
            chkDeixarVeiculo.addEventListener('change', atualizarPrevisaoConclusao);
        }

        function validarDatas() {
            if (statusSelect && statusSelect.value === 'cancelado') {
                erroDataInicio?.classList.add('hidden');
                inputDataInicio?.classList.remove('border-destructive');
                inputDataInicio?.setCustomValidity('');
                atualizarPrevisaoConclusao();
                return true;
            }

            let valido = true;
            const agora = new Date();
            agora.setSeconds(0, 0);
            const limiteMinimo = new Date(agora.getTime() - 60000);

            if (inputDataInicio && inputDataInicio.value) {
                const dtInicio = new Date(inputDataInicio.value);
                const hora = dtInicio.getHours();
                const minuto = dtInicio.getMinutes();
                const domingo = dtInicio.getDay() === 0;
                const foraExpediente = hora < 8 || hora >= 18;
                const alterado = !valorOriginalInicio || inputDataInicio.value !== valorOriginalInicio;

                if (alterado && dtInicio < limiteMinimo) {
                    if (erroDataInicio) {
                        erroDataInicio.textContent = 'A data e o horário não podem ser inferiores à data e horário atuais.';
                        erroDataInicio.classList.remove('hidden');
                    }
                    inputDataInicio.classList.add('border-destructive');
                    inputDataInicio.setCustomValidity('A data e o horário não podem ser inferiores à data e horário atuais.');
                    valido = false;
                } else if (domingo) {
                    if (erroDataInicio) {
                        erroDataInicio.textContent = 'A oficina não realiza atendimentos aos domingos. Escolha entre segunda e sábado.';
                        erroDataInicio.classList.remove('hidden');
                    }
                    inputDataInicio.classList.add('border-destructive');
                    inputDataInicio.setCustomValidity('A oficina não realiza atendimentos aos domingos.');
                    valido = false;
                } else if (foraExpediente) {
                    if (erroDataInicio) {
                        erroDataInicio.textContent = 'Horário de entrada fora do expediente comercial (08:00 às 17:59).';
                        erroDataInicio.classList.remove('hidden');
                    }
                    inputDataInicio.classList.add('border-destructive');
                    inputDataInicio.setCustomValidity('Horário fora do expediente comercial (08:00 às 18:00).');
                    valido = false;
                } else {
                    erroDataInicio?.classList.add('hidden');
                    inputDataInicio.classList.remove('border-destructive');
                    inputDataInicio.setCustomValidity('');
                }
            }

            atualizarPrevisaoConclusao();
            return valido;
        }

        if (inputDataInicio) {
            inputDataInicio.addEventListener('change', validarDatas);
            inputDataInicio.addEventListener('input', validarDatas);
        }
        if (statusSelect) {
            statusSelect.addEventListener('change', validarDatas);
        }
        formAgendamento.addEventListener('submit', function (e) {
            if (!validarDatas()) {
                e.preventDefault();
            }
        });

        // === Modal Calendário Interativo de Disponibilidade ===
        let calMesAtual = new Date();
        calMesAtual.setDate(1);
        let calDadosMes = null;
        let calDiaSelecionado = null;

        const elCalMesTitulo = document.getElementById('cal-mes-titulo');
        const elCalGridDias = document.getElementById('cal-grid-dias');
        const elCalListaSlots = document.getElementById('cal-lista-slots');
        const elCalDiaLabel = document.getElementById('cal-dia-selecionado-label');
        const elCalResumoDuracao = document.getElementById('cal-resumo-duracao');
        const elCalVeiculoBadge = document.getElementById('cal-veiculo-badge');
        const elCalModalPernoite = document.getElementById('modal_cal_deixar_veiculo');
        const elCalAlertaPernoite = document.getElementById('cal-alerta-pernoite');
        const elCalErro = document.getElementById('cal-erro-carregamento');

        function formatarMesISO(dt) {
            return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}`;
        }

        function obterServicosIdsSelecionados() {
            const ids = [];
            checkboxes.forEach(cb => {
                if (cb.checked && cb.value) ids.push(cb.value);
            });
            return ids;
        }

        async function carregarDisponibilidadeMes() {
            if (!rotaDisponibilidade || !elCalGridDias) return;
            elCalErro?.classList.add('hidden');
            elCalGridDias.innerHTML = '<div class="col-span-7 py-10 text-center text-xs text-muted-foreground">Calculando disponibilidade dos boxes...</div>';

            const duracao = totalMinutosComGargaloAtual > 0 ? totalMinutosComGargaloAtual : 90;
            if (elCalResumoDuracao) {
                elCalResumoDuracao.textContent = formatarDuracao(duracao);
            }

            const params = new URLSearchParams();
            params.set('mes', formatarMesISO(calMesAtual));
            params.set('duracao_minutos', String(duracao));
            params.set('deixar_veiculo', (elCalModalPernoite && elCalModalPernoite.checked) ? '1' : '0');
            if (clienteSelect && clienteSelect.value) {
                params.set('cliente_id', clienteSelect.value);
            }
            if (veiculoSelect && veiculoSelect.value) {
                params.set('veiculo_id', veiculoSelect.value);
            }
            if (ignorarAgendamentoId) {
                params.set('ignorar_agendamento_id', ignorarAgendamentoId);
            }
            obterServicosIdsSelecionados().forEach(id => params.append('servicos_ids[]', id));

            try {
                const resp = await fetch(`${rotaDisponibilidade}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await resp.json();
                if (!resp.ok) {
                    throw new Error(json.message || 'Erro ao carregar agenda.');
                }
                calDadosMes = json;
                if (elCalResumoDuracao && json.duracao_minutos) {
                    elCalResumoDuracao.textContent = formatarDuracao(json.duracao_minutos);
                }
                if (elCalVeiculoBadge) {
                    if (json.veiculo_placa) {
                        elCalVeiculoBadge.textContent = `Veículo: ${json.veiculo_placa}`;
                        elCalVeiculoBadge.classList.remove('hidden');
                    } else {
                        elCalVeiculoBadge.classList.add('hidden');
                    }
                }
                renderCalendarioMes();
            } catch (err) {
                if (elCalErro) {
                    elCalErro.textContent = err.message || 'Não foi possível carregar os horários disponíveis.';
                    elCalErro.classList.remove('hidden');
                }
                elCalGridDias.replaceChildren();
            }
        }

        function renderCalendarioMes() {
            if (!calDadosMes || !elCalGridDias) return;
            const nomeMes = calMesAtual.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
            if (elCalMesTitulo) elCalMesTitulo.textContent = nomeMes;

            elCalGridDias.replaceChildren();

            const diasObj = calDadosMes.dias || {};
            const chaves = Object.keys(diasObj).sort();
            if (chaves.length === 0) return;

            const primeiroDiaSemana = diasObj[chaves[0]].dia_semana;
            for (let i = 0; i < primeiroDiaSemana; i++) {
                const vazio = document.createElement('div');
                vazio.className = 'h-14 rounded-lg bg-secondary/10';
                elCalGridDias.appendChild(vazio);
            }

            let encontrouAlgumApenasPernoite = false;
            let primeiroDiaClicavel = null;

            chaves.forEach(dataKey => {
                const d = diasObj[dataKey];
                const btn = document.createElement('button');
                btn.type = 'button';

                const isSelected = calDiaSelecionado === dataKey;
                let corClasses = 'bg-secondary/20 border-border/50 text-muted-foreground opacity-50 cursor-not-allowed';
                let badgeText = d.status === 'fechado' ? 'Dom' : (d.status === 'passado' ? '' : 'Indisp.');
                let clicavel = false;

                if (d.status === 'disponivel') {
                    clicavel = true;
                    if (!primeiroDiaClicavel) primeiroDiaClicavel = dataKey;
                    corClasses = isSelected
                        ? 'bg-emerald-500/25 border-emerald-400 text-foreground ring-2 ring-emerald-500 cursor-pointer'
                        : 'bg-emerald-500/10 border-emerald-500/30 hover:bg-emerald-500/20 text-foreground cursor-pointer';
                    badgeText = `${d.slots_livres_count} hor.`;
                } else if (d.status === 'apenas_pernoite') {
                    clicavel = true;
                    encontrouAlgumApenasPernoite = true;
                    if (!primeiroDiaClicavel) primeiroDiaClicavel = dataKey;
                    corClasses = isSelected
                        ? 'bg-amber-500/25 border-amber-400 text-foreground ring-2 ring-amber-500 cursor-pointer'
                        : 'bg-amber-500/10 border-amber-500/30 hover:bg-amber-500/20 text-foreground cursor-pointer';
                    badgeText = 'Pernoite';
                } else if (d.status === 'lotado') {
                    corClasses = 'bg-rose-500/10 border-rose-500/30 text-rose-300/70 cursor-not-allowed';
                    badgeText = 'Lotado';
                }

                btn.className = `h-14 rounded-lg border p-1.5 flex flex-col items-center justify-between transition-all ${corClasses}`;
                btn.disabled = !clicavel;

                const numSpan = document.createElement('span');
                numSpan.className = 'text-xs font-bold leading-none';
                numSpan.textContent = String(d.dia);
                btn.appendChild(numSpan);

                if (badgeText) {
                    const infoSpan = document.createElement('span');
                    infoSpan.className = 'text-[9px] font-semibold leading-none';
                    infoSpan.textContent = badgeText;
                    btn.appendChild(infoSpan);
                }

                if (clicavel) {
                    btn.addEventListener('click', function () {
                        calDiaSelecionado = dataKey;
                        renderCalendarioMes();
                        renderSlotsDia(dataKey);
                    });
                }

                elCalGridDias.appendChild(btn);
            });

            if (elCalAlertaPernoite) {
                const mostrarAlertaPernoite = encontrouAlgumApenasPernoite && !(elCalModalPernoite && elCalModalPernoite.checked);
                elCalAlertaPernoite.classList.toggle('hidden', !mostrarAlertaPernoite);
            }

            if (calDiaSelecionado && diasObj[calDiaSelecionado] && (diasObj[calDiaSelecionado].status === 'disponivel' || diasObj[calDiaSelecionado].status === 'apenas_pernoite')) {
                renderSlotsDia(calDiaSelecionado);
            } else if (primeiroDiaClicavel) {
                calDiaSelecionado = primeiroDiaClicavel;
                renderSlotsDia(primeiroDiaClicavel);
            } else {
                renderSlotsDia(null);
            }
        }

        function renderSlotsDia(dataKey) {
            if (!elCalListaSlots || !elCalDiaLabel) return;
            elCalListaSlots.replaceChildren();

            if (!dataKey || !calDadosMes?.dias?.[dataKey]) {
                elCalDiaLabel.textContent = 'Nenhum dia disponível neste mês';
                const p = document.createElement('p');
                p.className = 'text-muted-foreground italic text-center py-8';
                p.textContent = 'Sem horários livres para a configuração selecionada.';
                elCalListaSlots.appendChild(p);
                return;
            }

            const [ano, mes, dia] = dataKey.split('-').map(Number);
            const dtObj = new Date(ano, mes - 1, dia);
            elCalDiaLabel.textContent = dtObj.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });

            const slots = calDadosMes.dias[dataKey].slots || [];
            if (slots.length === 0) {
                const p = document.createElement('p');
                p.className = 'text-muted-foreground italic text-center py-8';
                p.textContent = 'Nenhum horário restante para esta data.';
                elCalListaSlots.appendChild(p);
                return;
            }

            slots.forEach(slot => {
                const card = document.createElement('button');
                card.type = 'button';

                const selecionavel = slot.disponivel;
                card.disabled = !selecionavel;
                card.className = selecionavel
                    ? 'w-full text-left p-2.5 rounded-lg border border-border bg-card hover:border-primary hover:bg-primary/5 transition-all flex items-center justify-between gap-2 cursor-pointer'
                    : 'w-full text-left p-2.5 rounded-lg border border-border/40 bg-secondary/10 opacity-60 flex items-center justify-between gap-2 cursor-not-allowed';

                const esq = document.createElement('div');
                esq.className = 'min-w-0';

                const linhaHora = document.createElement('div');
                linhaHora.className = 'flex items-center gap-2';
                const horaSpan = document.createElement('span');
                horaSpan.className = 'font-bold text-sm text-foreground';
                horaSpan.textContent = slot.hora;
                linhaHora.appendChild(horaSpan);

                if (slot.requer_pernoite) {
                    const pBadge = document.createElement('span');
                    pBadge.className = 'text-[10px] px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-400 font-semibold';
                    pBadge.textContent = 'Pernoite';
                    linhaHora.appendChild(pBadge);
                }

                const prevP = document.createElement('p');
                prevP.className = 'text-[11px] text-muted-foreground mt-0.5';
                prevP.textContent = slot.motivo_indisponivel
                    ? slot.motivo_indisponivel
                    : `Término previsto: ${slot.fim_formatado}`;

                esq.appendChild(linhaHora);
                esq.appendChild(prevP);

                const dir = document.createElement('span');
                dir.className = 'shrink-0 text-[10px] font-bold px-2 py-1 rounded-full ' + (
                    slot.conflito_veiculo
                        ? 'bg-rose-500/15 text-rose-400'
                        : (slot.boxes_livres === 0
                            ? 'bg-rose-500/15 text-rose-400'
                            : (selecionavel ? 'bg-emerald-500/15 text-emerald-400' : 'bg-amber-500/15 text-amber-400'))
                );
                dir.textContent = slot.conflito_veiculo
                    ? 'Veículo ocupado'
                    : (slot.boxes_livres > 0 ? `${slot.boxes_livres}/4 boxes` : 'Lotado');

                card.appendChild(esq);
                card.appendChild(dir);

                if (selecionavel) {
                    card.addEventListener('click', function () {
                        if (inputDataInicio) {
                            inputDataInicio.value = slot.inicio_iso;
                        }
                        if (chkDeixarVeiculo && elCalModalPernoite) {
                            chkDeixarVeiculo.checked = Boolean(slot.requer_pernoite || elCalModalPernoite.checked);
                        }
                        validarDatas();
                        window.closeModal('modal-calendario-agenda');
                    });
                }

                elCalListaSlots.appendChild(card);
            });
        }

        window.abrirCalendarioAgenda = function () {
            if (elCalModalPernoite && chkDeixarVeiculo) {
                elCalModalPernoite.checked = chkDeixarVeiculo.checked;
            }
            if (inputDataInicio && inputDataInicio.value) {
                const dt = new Date(inputDataInicio.value);
                if (!isNaN(dt.getTime())) {
                    calMesAtual = new Date(dt.getFullYear(), dt.getMonth(), 1);
                    calDiaSelecionado = inputDataInicio.value.slice(0, 10);
                }
            }
            window.openModal('modal-calendario-agenda');
            carregarDisponibilidadeMes();
        };

        document.getElementById('cal-mes-anterior')?.addEventListener('click', function () {
            calMesAtual = new Date(calMesAtual.getFullYear(), calMesAtual.getMonth() - 1, 1);
            calDiaSelecionado = null;
            carregarDisponibilidadeMes();
        });

        document.getElementById('cal-mes-proximo')?.addEventListener('click', function () {
            calMesAtual = new Date(calMesAtual.getFullYear(), calMesAtual.getMonth() + 1, 1);
            calDiaSelecionado = null;
            carregarDisponibilidadeMes();
        });

        elCalModalPernoite?.addEventListener('change', function () {
            if (chkDeixarVeiculo) {
                chkDeixarVeiculo.checked = elCalModalPernoite.checked;
                atualizarPrevisaoConclusao();
            }
            carregarDisponibilidadeMes();
        });

        document.getElementById('btn-cal-ativar-pernoite')?.addEventListener('click', function () {
            if (elCalModalPernoite) {
                elCalModalPernoite.checked = true;
            }
            if (chkDeixarVeiculo) {
                chkDeixarVeiculo.checked = true;
                atualizarPrevisaoConclusao();
            }
            carregarDisponibilidadeMes();
        });
    });
</script>
@endpush
