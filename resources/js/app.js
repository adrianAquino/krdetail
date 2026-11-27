import { initAgendamentosCalendario } from './agendamentos-calendario';
import { initAgendamentosJanelas } from './agendamentos-janelas';

document.addEventListener('DOMContentLoaded', () => {
    initAgendamentosCalendario();
    initAgendamentosJanelas();
});
