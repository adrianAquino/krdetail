import { Calendar } from '@fullcalendar/core';
import ptBrLocale from '@fullcalendar/core/locales/pt-br';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';

const VIEW_STORAGE_KEY = 'krdetail-agendamentos-view';

function pad2(num) {
    return String(num).padStart(2, '0');
}

function formatDateTimeLocal(date) {
    return (
        date.getFullYear() +
        '-' +
        pad2(date.getMonth() + 1) +
        '-' +
        pad2(date.getDate()) +
        'T' +
        pad2(date.getHours()) +
        ':' +
        pad2(date.getMinutes())
    );
}

function resolverHorarioInicialParaCliqueDia(clickedDate) {
    const agora = new Date();
    const destino = new Date(
        clickedDate.getFullYear(),
        clickedDate.getMonth(),
        clickedDate.getDate(),
        9,
        0,
        0,
        0
    );

    const mesmoDia =
        clickedDate.getFullYear() === agora.getFullYear() &&
        clickedDate.getMonth() === agora.getMonth() &&
        clickedDate.getDate() === agora.getDate();

    if (mesmoDia && destino.getTime() <= agora.getTime()) {
        const cursor = new Date(agora.getTime());
        cursor.setSeconds(0, 0);
        const resto = cursor.getMinutes() % 30;
        cursor.setMinutes(resto === 0 ? cursor.getMinutes() + 30 : cursor.getMinutes() + (30 - resto));

        if (cursor.getHours() < 8) {
            cursor.setHours(8, 0, 0, 0);
        }

        return cursor;
    }

    return destino;
}

export function initAgendamentosCalendario() {
    const rootEl = document.getElementById('agendamentos-calendario-root');
    const listaWrapper = document.getElementById('agendamentos-lista-wrapper');
    const calendarioWrapper = document.getElementById('agendamentos-calendario-wrapper');
    const btnLista = document.getElementById('btn-view-lista');
    const btnCalendario = document.getElementById('btn-view-calendario');
    const feedbackEl = document.getElementById('calendario-feedback');

    if (!rootEl || !listaWrapper || !calendarioWrapper || !btnLista || !btnCalendario) {
        return;
    }

    const cfg = rootEl.dataset;
    const eventosUrl = cfg.eventosUrl || '';
    const createUrl = cfg.createUrl || '';
    const canCreate = cfg.canCreate === '1';
    const filtroStatus = cfg.status || '';
    const filtroBusca = cfg.busca || '';
    const filtroEmpresaId = cfg.empresaId || '';
    const isCliente = cfg.isCliente === '1';

    let calendarInstance = null;
    let feedbackTimer = null;

    function mostrarFeedback(mensagem) {
        if (!feedbackEl) return;
        feedbackEl.textContent = mensagem;
        feedbackEl.classList.remove('hidden');
        if (feedbackTimer) {
            window.clearTimeout(feedbackTimer);
        }
        feedbackTimer = window.setTimeout(() => {
            feedbackEl.classList.add('hidden');
        }, 5000);
    }

    function criarOuAtualizarCalendario() {
        if (calendarInstance) {
            calendarInstance.updateSize();
            return;
        }

        calendarInstance = new Calendar(rootEl, {
            plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
            locale: ptBrLocale,
            buttonText: {
                today: 'Hoje',
                month: 'Mês',
                week: 'Semana',
                day: 'Dia',
                list: 'Agenda',
            },
            initialView: window.innerWidth < 768 ? 'listWeek' : 'timeGridWeek',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            },
            hiddenDays: [0],
            slotMinTime: '08:00:00',
            slotMaxTime: '18:00:00',
            slotDuration: '00:30:00',
            slotLabelInterval: '01:00:00',
            slotLabelFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            },
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            },
            allDaySlot: false,
            nowIndicator: true,
            height: 'auto',
            expandRows: true,
            dayMaxEvents: true,
            navLinks: true,
            selectable: false,
            events: async (fetchInfo, successCallback, failureCallback) => {
                try {
                    const params = new URLSearchParams();
                    params.set('start', fetchInfo.startStr);
                    params.set('end', fetchInfo.endStr);
                    if (filtroStatus) params.set('status', filtroStatus);
                    if (filtroBusca) params.set('busca', filtroBusca);
                    if (filtroEmpresaId) params.set('empresa_id', filtroEmpresaId);

                    const sep = eventosUrl.includes('?') ? '&' : '?';
                    const response = await fetch(`${eventosUrl}${sep}${params.toString()}`, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const data = await response.json();
                    successCallback(Array.isArray(data) ? data : []);
                } catch (error) {
                    mostrarFeedback('Não foi possível carregar os eventos da agenda no momento.');
                    failureCallback(error);
                }
            },
            eventDidMount: (info) => {
                const props = info.event.extendedProps || {};
                if (props.tipo === 'lotado') {
                    info.el.setAttribute('title', 'Horário com todos os boxes ocupados');
                    return;
                }

                const partes = [info.event.title];
                if (props.statusLabel) partes.push(`Status: ${props.statusLabel}`);
                if (props.veiculo) partes.push(`Veículo: ${props.veiculo}`);
                if (props.servicos) partes.push(`Serviços: ${props.servicos}`);
                if (props.empresa) partes.push(`Estética: ${props.empresa}`);
                info.el.setAttribute('title', partes.filter(Boolean).join(' • '));
            },
            eventClick: (info) => {
                if (info.event.display === 'background' || info.event.extendedProps?.tipo === 'lotado') {
                    info.jsEvent.preventDefault();
                    return;
                }
                if (info.event.url) {
                    info.jsEvent.preventDefault();
                    window.location.assign(info.event.url);
                }
            },
            dateClick: (info) => {
                if (!canCreate || !createUrl) {
                    return;
                }

                const targetDate = info.allDay
                    ? resolverHorarioInicialParaCliqueDia(info.date)
                    : new Date(info.date.getTime());

                if (!info.allDay) {
                    const min = targetDate.getMinutes();
                    targetDate.setMinutes(min >= 30 ? 30 : 0, 0, 0);
                }

                if (targetDate.getDay() === 0) {
                    mostrarFeedback('A oficina não realiza atendimentos aos domingos.');
                    return;
                }

                const hora = targetDate.getHours();
                if (hora < 8 || hora >= 18) {
                    mostrarFeedback('Horário fora do expediente comercial (08:00 às 18:00).');
                    return;
                }

                const agoraLimite = new Date(Date.now() - 60000);
                if (targetDate.getTime() < agoraLimite.getTime()) {
                    mostrarFeedback('Escolha uma janela de horário futura para criar o agendamento.');
                    return;
                }

                if (isCliente && calendarInstance) {
                    const faixaLotada = calendarInstance.getEvents().some((ev) => {
                        if (ev.display !== 'background' || ev.extendedProps?.tipo !== 'lotado') {
                            return false;
                        }
                        if (!ev.start || !ev.end) return false;
                        return ev.start.getTime() <= targetDate.getTime() && ev.end.getTime() > targetDate.getTime();
                    });

                    if (faixaLotada) {
                        mostrarFeedback('Esta janela já está com os boxes lotados. Escolha outro horário livre.');
                        return;
                    }
                }

                const dest = new URL(createUrl, window.location.origin);
                dest.searchParams.set('data_inicio', formatDateTimeLocal(targetDate));
                if (filtroEmpresaId) {
                    dest.searchParams.set('empresa_id', filtroEmpresaId);
                }
                window.location.assign(dest.toString());
            },
        });

        calendarInstance.render();
    }

    function sincronizarPreservacaoModo(modo) {
        document.querySelectorAll('input[name="view"].js-agendamentos-view-input').forEach((input) => {
            input.value = modo;
        });

        document.querySelectorAll('a.js-agendamentos-preserve-view').forEach((link) => {
            try {
                const url = new URL(link.href, window.location.origin);
                url.searchParams.set('view', modo);
                link.href = url.pathname + url.search;
            } catch {
                // Ignora links inválidos
            }
        });
    }

    function atualizarEstadoBotoes(modo) {
        const ativoClasses = ['bg-primary', 'text-primary-foreground', 'shadow-sm'];
        const inativoClasses = ['text-muted-foreground', 'hover:text-foreground', 'hover:bg-secondary/60'];

        if (modo === 'calendario') {
            btnCalendario.classList.add(...ativoClasses);
            btnCalendario.classList.remove(...inativoClasses);
            btnCalendario.setAttribute('aria-pressed', 'true');

            btnLista.classList.remove(...ativoClasses);
            btnLista.classList.add(...inativoClasses);
            btnLista.setAttribute('aria-pressed', 'false');
        } else {
            btnLista.classList.add(...ativoClasses);
            btnLista.classList.remove(...inativoClasses);
            btnLista.setAttribute('aria-pressed', 'true');

            btnCalendario.classList.remove(...ativoClasses);
            btnCalendario.classList.add(...inativoClasses);
            btnCalendario.setAttribute('aria-pressed', 'false');
        }
    }

    function ativarModo(modo, atualizarHistorico = true) {
        const modoFinal = modo === 'calendario' ? 'calendario' : 'lista';

        if (modoFinal === 'calendario') {
            listaWrapper.classList.add('hidden');
            calendarioWrapper.classList.remove('hidden');
            criarOuAtualizarCalendario();
        } else {
            calendarioWrapper.classList.add('hidden');
            listaWrapper.classList.remove('hidden');
        }

        atualizarEstadoBotoes(modoFinal);
        sincronizarPreservacaoModo(modoFinal);

        try {
            window.localStorage.setItem(VIEW_STORAGE_KEY, modoFinal);
        } catch {
            // Ignora indisponibilidade de storage
        }

        if (atualizarHistorico) {
            try {
                const urlAtual = new URL(window.location.href);
                urlAtual.searchParams.set('view', modoFinal);
                window.history.replaceState({}, '', urlAtual.pathname + urlAtual.search);
            } catch {
                // Ignora caso browser restrinja replaceState
            }
        }
    }

    btnLista.addEventListener('click', () => ativarModo('lista', true));
    btnCalendario.addEventListener('click', () => ativarModo('calendario', true));

    const paramsUrl = new URLSearchParams(window.location.search);
    const viewParam = paramsUrl.get('view');
    const temPaginaEspecifica = paramsUrl.has('page');

    let modoInicial = cfg.initialViewMode === 'calendario' ? 'calendario' : 'lista';
    if (!viewParam && !temPaginaEspecifica) {
        try {
            const salvo = window.localStorage.getItem(VIEW_STORAGE_KEY);
            if (salvo === 'calendario') {
                modoInicial = 'calendario';
            }
        } catch {
            // Mantém padrão SSR
        }
    }

    ativarModo(modoInicial, viewParam === 'calendario' || modoInicial === 'calendario');
}
