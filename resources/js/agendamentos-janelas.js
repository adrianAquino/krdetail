function pad2(n) {
    return String(n).padStart(2, '0');
}

function hojeIso() {
    const d = new Date();
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

function parseDateOnly(isoStr) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(isoStr || ''));
    if (!match) return null;
    const d = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), 0, 0, 0, 0);
    return isNaN(d.getTime()) ? null : d;
}

function formatDateOnly(date) {
    return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
}

function addDaysIso(isoStr, delta) {
    const base = parseDateOnly(isoStr) || new Date();
    const copy = new Date(base.getTime());
    copy.setDate(copy.getDate() + delta);
    return formatDateOnly(copy);
}

function clampDateIso(isoStr, minIso, maxIso) {
    if (isoStr < minIso) return minIso;
    if (maxIso && isoStr > maxIso) return maxIso;
    return isoStr;
}

function formatarDuracao(minutosTotais) {
    const minNum = Math.max(0, Number(minutosTotais) || 0);
    const h = Math.floor(minNum / 60);
    const m = minNum % 60;
    if (h > 0) {
        return m > 0 ? `${h}h ${m}min` : `${h}h`;
    }
    return `${m}min`;
}

export function initAgendamentosJanelas() {
    const rootEl = document.getElementById('janelas-disponiveis-root');
    if (!rootEl) return;

    const diasStripEl = document.getElementById('janelas-dias-strip');
    const slotsGridEl = document.getElementById('janelas-slots-grid');
    const loadingEl = document.getElementById('janelas-loading');
    const erroEl = document.getElementById('janelas-erro');
    const resumoDiaEl = document.getElementById('janelas-resumo-dia');
    const infoDuracaoEl = document.getElementById('janelas-info-duracao');
    const selecionadaBadgeEl = document.getElementById('janelas-selecionada-badge');
    const btnPrev = document.getElementById('janelas-btn-prev');
    const btnHoje = document.getElementById('janelas-btn-hoje');
    const btnNext = document.getElementById('janelas-btn-next');

    const inputDataInicio = document.getElementById('data_inicio');
    const veiculoSelect = document.getElementById('veiculo_select');
    const clienteSelect = document.getElementById('cliente_select');
    const chkDeixarVeiculo = document.getElementById('deixar_veiculo');
    const checkboxesServicos = document.querySelectorAll('.service-checkbox');

    if (!diasStripEl || !slotsGridEl || !inputDataInicio) return;

    const cfg = rootEl.dataset;
    const janelasUrl = cfg.janelasUrl || '';
    const empresaIdFixa = cfg.empresaId || '';

    const todayStr = hojeIso();
    const maxDataBaseStr = addDaysIso(todayStr, 180);

    let dataBase = todayStr;
    let diaSelecionado = '';
    let dadosCache = null;
    let abortCtrl = null;
    let debounceTimer = null;

    // Se o formulário já vier pré-preenchido (ex.: clique no calendário ou old('data_inicio')),
    // abre o seletor já posicionado no dia correspondente.
    if (inputDataInicio.value && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(inputDataInicio.value)) {
        const dataDoInput = inputDataInicio.value.slice(0, 10);
        if (dataDoInput >= todayStr) {
            diaSelecionado = dataDoInput;
            const dInput = parseDateOnly(dataDoInput);
            const dToday = parseDateOnly(todayStr);
            if (dInput && dToday) {
                const diffDias = Math.round((dInput.getTime() - dToday.getTime()) / 86400000);
                if (diffDias > 13) {
                    const blocoSemana = Math.floor(diffDias / 7) * 7;
                    dataBase = clampDateIso(addDaysIso(todayStr, blocoSemana), todayStr, maxDataBaseStr);
                }
            }
        }
    }

    function atualizarBotoesNavegacao() {
        if (btnPrev) {
            btnPrev.disabled = dataBase <= todayStr;
        }
        if (btnHoje) {
            btnHoje.disabled = dataBase === todayStr;
        }
        if (btnNext) {
            btnNext.disabled = dataBase >= maxDataBaseStr;
        }
    }

    function atualizarBadgeSelecionada(slotEncontrado, diaObj) {
        if (!selecionadaBadgeEl) return;
        if (!inputDataInicio.value || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(inputDataInicio.value)) {
            selecionadaBadgeEl.classList.add('hidden');
            selecionadaBadgeEl.textContent = '';
            return;
        }

        const [dtPart, hrPart] = inputDataInicio.value.split('T');
        const horaCurtinha = (hrPart || '').slice(0, 5);
        const dtParse = parseDateOnly(dtPart);
        const dtLabel = dtParse ? `${pad2(dtParse.getDate())}/${pad2(dtParse.getMonth() + 1)}` : dtPart;

        if (slotEncontrado && slotEncontrado.disponivel) {
            const sufixoFim = slotEncontrado.pernoite
                ? `término est. ${slotEncontrado.fim_estimado} (pernoite)`
                : `término est. ${slotEncontrado.fim_estimado}`;
            selecionadaBadgeEl.textContent = `Janela selecionada: ${diaObj?.dia_semana ? diaObj.dia_semana + ' ' : ''}${dtLabel} às ${horaCurtinha} (${sufixoFim})`;
            selecionadaBadgeEl.classList.remove('hidden');
        } else if (slotEncontrado && !slotEncontrado.disponivel) {
            selecionadaBadgeEl.textContent = `Horário atual (${dtLabel} às ${horaCurtinha}): ${slotEncontrado.vagas_label}`;
            selecionadaBadgeEl.classList.remove('hidden');
        } else {
            selecionadaBadgeEl.textContent = `Horário informado: ${dtLabel} às ${horaCurtinha}`;
            selecionadaBadgeEl.classList.remove('hidden');
        }
    }

    function renderDiasStrip(dias) {
        diasStripEl.replaceChildren();

        if (!Array.isArray(dias) || dias.length === 0) {
            const p = document.createElement('p');
            p.className = 'text-xs text-muted-foreground py-2';
            p.textContent = 'Nenhuma agenda disponível para esta estética no momento.';
            diasStripEl.appendChild(p);
            return;
        }

        dias.forEach((dia) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            const ativo = dia.data === diaSelecionado;

            let borderCls = 'border-border bg-secondary/30 hover:bg-secondary/60 text-foreground';
            let dotCls = 'bg-emerald-500';
            let statusCurto = `${dia.livres} livres`;

            if (dia.fechado) {
                borderCls = 'border-border/60 bg-secondary/10 text-muted-foreground opacity-65';
                dotCls = 'bg-muted-foreground/50';
                statusCurto = 'Fechado';
            } else if (dia.status === 'passado') {
                borderCls = 'border-border/60 bg-secondary/10 text-muted-foreground opacity-65';
                dotCls = 'bg-muted-foreground/50';
                statusCurto = 'Encerrado';
            } else if (dia.status === 'lotado') {
                borderCls = 'border-destructive/30 bg-destructive/5 text-muted-foreground hover:bg-destructive/10';
                dotCls = 'bg-destructive';
                statusCurto = 'Lotado';
            } else if (dia.status === 'poucas_vagas') {
                dotCls = 'bg-amber-500';
                statusCurto = `${dia.livres} vagas`;
            }

            if (ativo) {
                borderCls = 'border-primary bg-primary/15 text-foreground ring-2 ring-primary/40';
            }

            btn.className =
                `flex flex-col items-center justify-center rounded-xl border px-2.5 py-2 text-center transition-all cursor-pointer min-w-[74px] shrink-0 ${borderCls}`;

            const spanDia = document.createElement('span');
            spanDia.className = 'text-[10px] font-bold uppercase tracking-wider text-muted-foreground';
            spanDia.textContent = dia.dia_semana;

            const spanData = document.createElement('span');
            spanData.className = 'text-sm font-extrabold leading-tight mt-0.5';
            spanData.textContent = dia.label_data;

            const statusRow = document.createElement('span');
            statusRow.className = 'mt-1 inline-flex items-center gap-1 text-[10px] font-medium text-muted-foreground';

            const dot = document.createElement('span');
            dot.className = `inline-block h-1.5 w-1.5 rounded-full ${dotCls}`;

            const spanQtd = document.createElement('span');
            spanQtd.textContent = statusCurto;

            statusRow.appendChild(dot);
            statusRow.appendChild(spanQtd);

            btn.appendChild(spanDia);
            btn.appendChild(spanData);
            btn.appendChild(statusRow);

            btn.addEventListener('click', () => {
                diaSelecionado = dia.data;
                renderDiasStrip(dias);
                renderSlotsDia(dia);
            });

            diasStripEl.appendChild(btn);
        });
    }

    function renderSlotsDia(dia) {
        slotsGridEl.replaceChildren();

        if (!dia) {
            if (resumoDiaEl) resumoDiaEl.textContent = '';
            atualizarBadgeSelecionada(null, null);
            return;
        }

        const valorAtualInput = (inputDataInicio.value || '').slice(0, 16);
        let slotEncontrado = null;

        if (resumoDiaEl) {
            resumoDiaEl.textContent = dia.fechado
                ? `${dia.dia_semana} (${dia.label_data}) — Oficina fechada`
                : `${dia.dia_semana} (${dia.label_data}) — ${dia.livres} ${dia.livres === 1 ? 'janela disponível' : 'janelas disponíveis'}`;
        }

        if (dia.fechado || !Array.isArray(dia.slots) || dia.slots.length === 0) {
            const box = document.createElement('div');
            box.className = 'col-span-full rounded-xl border border-border/70 bg-secondary/20 p-4 text-center text-xs text-muted-foreground';
            box.textContent = 'A estética não realiza atendimentos aos domingos. Selecione um dia entre segunda e sábado.';
            slotsGridEl.appendChild(box);
            atualizarBadgeSelecionada(null, dia);
            return;
        }

        const todosUltrapassam = dia.slots.every(
            (s) => s.motivo === 'ultrapassa_expediente' || s.motivo === 'passado'
        );

        dia.slots.forEach((slot) => {
            const isSelected = valorAtualInput === slot.data_hora_iso;
            if (isSelected) {
                slotEncontrado = slot;
            }

            const btn = document.createElement('button');
            btn.type = 'button';

            if (slot.disponivel) {
                let cls;
                if (isSelected) {
                    cls = 'border-primary bg-primary text-primary-foreground shadow-sm ring-2 ring-primary/40';
                } else if (slot.nivel === 'ultimas_vagas') {
                    cls = 'border-amber-500/40 bg-amber-500/10 text-foreground hover:border-amber-500 hover:bg-amber-500/20';
                } else {
                    cls = 'border-emerald-500/35 bg-emerald-500/10 text-foreground hover:border-emerald-500 hover:bg-emerald-500/20';
                }

                btn.className =
                    `flex flex-col items-start rounded-xl border px-3 py-2 text-left transition-all cursor-pointer ${cls}`;

                const topRow = document.createElement('div');
                topRow.className = 'flex items-baseline justify-between w-full gap-1.5';

                const horaSpan = document.createElement('span');
                horaSpan.className = 'text-sm font-extrabold tracking-tight tabular-nums shrink-0';
                horaSpan.textContent = slot.hora;

                const fimSpan = document.createElement('span');
                fimSpan.className = (isSelected ? 'opacity-90' : 'text-muted-foreground') + ' text-[10px] font-medium whitespace-nowrap tabular-nums';
                fimSpan.textContent = `→ ${slot.fim_estimado}`;

                topRow.appendChild(horaSpan);
                topRow.appendChild(fimSpan);

                const subSpan = document.createElement('span');
                subSpan.className = isSelected
                    ? 'text-[10px] font-semibold mt-0.5 opacity-95'
                    : slot.nivel === 'ultimas_vagas'
                        ? 'text-[10px] font-semibold mt-0.5 text-amber-500'
                        : 'text-[10px] font-semibold mt-0.5 text-emerald-500';
                subSpan.textContent = slot.vagas_label;

                btn.appendChild(topRow);
                btn.appendChild(subSpan);

                btn.addEventListener('click', () => {
                    inputDataInicio.value = slot.data_hora_iso;
                    inputDataInicio.dispatchEvent(new Event('input', { bubbles: true }));
                    inputDataInicio.dispatchEvent(new Event('change', { bubbles: true }));
                    renderSlotsDia(dia);
                });
            } else {
                btn.disabled = true;
                btn.className =
                    'flex flex-col items-start rounded-xl border border-border/50 bg-secondary/20 px-3 py-2 text-left text-muted-foreground opacity-55 cursor-not-allowed';

                const horaSpan = document.createElement('span');
                horaSpan.className = 'text-sm font-bold line-through';
                horaSpan.textContent = slot.hora;

                const motivoSpan = document.createElement('span');
                motivoSpan.className = 'text-[10px] mt-0.5';
                motivoSpan.textContent = slot.vagas_label;

                btn.appendChild(horaSpan);
                btn.appendChild(motivoSpan);
            }

            slotsGridEl.appendChild(btn);
        });

        if (todosUltrapassam && !(chkDeixarVeiculo && chkDeixarVeiculo.checked)) {
            const aviso = document.createElement('div');
            aviso.className = 'col-span-full rounded-xl border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-[11px] text-amber-400';
            aviso.textContent = 'Todos os horários deste dia ultrapassariam o fechamento das 18:00. Selecione uma data com janela livre ou ative a opção de deixar o veículo na estética (pernoite).';
            slotsGridEl.appendChild(aviso);
        }

        atualizarBadgeSelecionada(slotEncontrado, dia);
    }

    async function carregarJanelas() {
        if (!janelasUrl) return;

        if (abortCtrl) {
            abortCtrl.abort();
        }
        abortCtrl = new AbortController();

        atualizarBotoesNavegacao();
        loadingEl?.classList.remove('hidden');
        erroEl?.classList.add('hidden');

        try {
            const params = new URLSearchParams();
            params.set('data_base', dataBase);
            params.set('dias', '14');
            params.set('deixar_veiculo', chkDeixarVeiculo?.checked ? '1' : '0');

            if (veiculoSelect && veiculoSelect.value) {
                params.set('veiculo_id', veiculoSelect.value);
            }
            if (clienteSelect && clienteSelect.value) {
                params.set('cliente_id', clienteSelect.value);
            }
            if (empresaIdFixa) {
                params.set('empresa_id', empresaIdFixa);
            }

            checkboxesServicos.forEach((cb) => {
                if (cb.checked) {
                    params.append('servicos[]', cb.value);
                }
            });

            const sep = janelasUrl.includes('?') ? '&' : '?';
            const response = await fetch(`${janelasUrl}${sep}${params.toString()}`, {
                signal: abortCtrl.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const json = await response.json();
            dadosCache = json;

            if (infoDuracaoEl) {
                if (json.sem_estetica) {
                    infoDuracaoEl.textContent = 'Nenhuma estética vinculada para consultar horários.';
                } else if (json.usando_previa_padrao) {
                    infoDuracaoEl.textContent =
                        'Prévia padrão (30min serviço + 30min margem = 1h). Marque os serviços abaixo para ver a disponibilidade real.';
                } else {
                    infoDuracaoEl.textContent = `Duração considerada para encaixe: ${formatarDuracao(json.duracao_total_minutos)} (${formatarDuracao(json.duracao_servicos_minutos)} de serviços + ${json.gargalo_minutos}min de margem).`;
                }
            }

            const dias = Array.isArray(json.dias) ? json.dias : [];
            const encontrado = dias.find((d) => d.data === diaSelecionado);

            if (!encontrado || encontrado.fechado || encontrado.status === 'passado') {
                const melhorDia =
                    dias.find((d) => !d.fechado && d.status !== 'passado' && d.livres > 0) ||
                    dias.find((d) => !d.fechado && d.status !== 'passado') ||
                    dias[0];
                diaSelecionado = melhorDia ? melhorDia.data : '';
            }

            const diaAtivoObj = dias.find((d) => d.data === diaSelecionado) || null;
            renderDiasStrip(dias);
            renderSlotsDia(diaAtivoObj);
        } catch (err) {
            if (err && err.name === 'AbortError') return;
            erroEl?.classList.remove('hidden');
        } finally {
            loadingEl?.classList.add('hidden');
        }
    }

    function agendarRecarga(atrasoMs = 80) {
        if (debounceTimer) {
            window.clearTimeout(debounceTimer);
        }
        debounceTimer = window.setTimeout(() => {
            carregarJanelas();
        }, atrasoMs);
    }

    checkboxesServicos.forEach((cb) => {
        cb.addEventListener('change', () => agendarRecarga(60));
    });

    if (veiculoSelect) {
        veiculoSelect.addEventListener('change', () => agendarRecarga(40));
    }
    if (clienteSelect) {
        clienteSelect.addEventListener('change', () => agendarRecarga(80));
    }
    if (chkDeixarVeiculo) {
        chkDeixarVeiculo.addEventListener('change', () => agendarRecarga(40));
    }

    inputDataInicio.addEventListener('change', () => {
        if (!dadosCache || !Array.isArray(dadosCache.dias)) return;
        const val = inputDataInicio.value || '';
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(val)) {
            const dtPart = val.slice(0, 10);
            const diaNoCache = dadosCache.dias.find((d) => d.data === dtPart);
            if (diaNoCache) {
                diaSelecionado = dtPart;
                renderDiasStrip(dadosCache.dias);
                renderSlotsDia(diaNoCache);
            } else if (dtPart >= todayStr && dtPart <= maxDataBaseStr) {
                diaSelecionado = dtPart;
                dataBase = dtPart;
                agendarRecarga(20);
            } else {
                atualizarBadgeSelecionada(null, null);
            }
        }
    });

    if (btnPrev) {
        btnPrev.addEventListener('click', () => {
            dataBase = clampDateIso(addDaysIso(dataBase, -7), todayStr, maxDataBaseStr);
            carregarJanelas();
        });
    }
    if (btnHoje) {
        btnHoje.addEventListener('click', () => {
            dataBase = todayStr;
            carregarJanelas();
        });
    }
    if (btnNext) {
        btnNext.addEventListener('click', () => {
            dataBase = clampDateIso(addDaysIso(dataBase, 7), todayStr, maxDataBaseStr);
            carregarJanelas();
        });
    }

    carregarJanelas();
}
