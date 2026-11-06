<!-- Widget VLibras Oficial -->
<div vw class="enabled">
    <div vw-access-button class="active"></div>
    <div vw-plugin-wrapper>
        <div class="vw-plugin-top-wrapper"></div>
    </div>
</div>
<script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
<script>
    if (window.VLibras) {
        new window.VLibras.Widget('https://vlibras.gov.br/app');
    }
</script>

<!-- Central de Acessibilidade & Leitor de Tela (Ancorada no Canto Inferior Direito, colada na base da tela) -->
<div
    id="a11y-widget-container"
    class="print:hidden"
    style="position: fixed; bottom: 0; right: 20px; z-index: 9999; display: flex; flex-direction: column; align-items: flex-end;"
>
    <!-- Painel Expansível (abre logo acima da aba retangular, deslocado da lateral do VLibras) -->
    <div
        id="a11y-panel"
        role="dialog"
        aria-label="Menu de Acessibilidade e Leitor de Tela"
        class="hidden mb-2 w-72 rounded-2xl p-4 shadow-2xl space-y-4"
        style="margin-right: 44px;"
    >
        <div class="flex items-center justify-between border-b pb-2.5 a11y-panel-divider">
            <div class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg a11y-accent-badge">
                    <x-icon name="accessibility" class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider">Acessibilidade</h3>
                    <p class="text-[11px] a11y-muted-text">Leitor de Tela & Fonte</p>
                </div>
            </div>
            <button
                type="button"
                id="a11y-close-btn"
                aria-label="Fechar painel de acessibilidade"
                class="rounded-lg p-1.5 mr-2 a11y-muted-text hover:opacity-80 transition-opacity cursor-pointer"
            >
                <x-icon name="x" class="w-4 h-4" />
            </button>
        </div>

        <!-- Leitor de Tela (Web Speech API) -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold">Leitor de Tela (pt-BR)</span>
                <span id="a11y-voice-badge" class="text-[10px] font-bold px-2 py-0.5 rounded-full border">
                    Desativado
                </span>
            </div>

            <button
                type="button"
                id="a11y-voice-toggle"
                class="a11y-primary-action flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-xs font-bold transition-all cursor-pointer"
            >
                <x-icon name="headphones" class="w-4 h-4" />
                <span id="a11y-voice-toggle-text">Ativar Leitor ao Clicar / Tab</span>
            </button>

            <div class="grid grid-cols-2 gap-2 pt-0.5">
                <button
                    type="button"
                    id="a11y-read-page-btn"
                    class="a11y-secondary-btn flex items-center justify-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[11px] font-semibold transition-colors cursor-pointer"
                >
                    <x-icon name="volume-2" class="w-3.5 h-3.5" />
                    <span>Ouvir Página</span>
                </button>

                <button
                    type="button"
                    id="a11y-stop-voice-btn"
                    class="a11y-secondary-btn flex items-center justify-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[11px] font-semibold transition-colors cursor-pointer"
                >
                    <x-icon name="volume-x" class="w-3.5 h-3.5" />
                    <span>Parar Voz</span>
                </button>
            </div>

            <p class="text-[11px] a11y-muted-text leading-relaxed">
                Quando ativo, clique, selecione um texto ou navegue com <kbd class="px-1 py-0.5 rounded border font-mono text-[10px]">Tab</kbd> para ouvir. Atalho: <kbd class="px-1 py-0.5 rounded border font-mono text-[10px]">Alt+V</kbd>.
            </p>
        </div>

        <!-- Controle de Tamanho de Fonte -->
        <div class="space-y-2 border-t pt-3 a11y-panel-divider">
            <div class="flex items-center justify-between text-xs">
                <span class="font-semibold">Tamanho da Fonte</span>
                <span id="a11y-font-label" class="text-xs font-mono font-extrabold">100%</span>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <button
                    type="button"
                    id="a11y-font-dec"
                    aria-label="Diminuir tamanho da fonte"
                    class="a11y-secondary-btn rounded-lg py-1.5 text-xs font-bold transition-colors cursor-pointer"
                >
                    A-
                </button>
                <button
                    type="button"
                    id="a11y-font-reset"
                    aria-label="Tamanho padrão da fonte"
                    class="a11y-secondary-btn rounded-lg py-1.5 text-xs font-semibold transition-colors cursor-pointer"
                >
                    Padrão
                </button>
                <button
                    type="button"
                    id="a11y-font-inc"
                    aria-label="Aumentar tamanho da fonte"
                    class="a11y-secondary-btn rounded-lg py-1.5 text-xs font-bold transition-colors cursor-pointer"
                >
                    A+
                </button>
            </div>
        </div>
    </div>

    <!-- Aba Retangular Horizontal com o Símbolo Universal de Acessibilidade da ONU -->
    <button
        type="button"
        id="a11y-fab-btn"
        aria-label="Abrir menu de acessibilidade e leitor de tela (Alt + V)"
        data-tooltip="Acessibilidade e Leitor de Tela (Alt + V)"
        class="focus:outline-none transition-all cursor-pointer flex items-center justify-center relative"
    >
        <x-icon name="accessibility" class="w-5 h-5 shrink-0" />
    </button>
</div>

<style>
    /*
     * Azul do botão (#1996e3) com efeito hover de azul mais claro e brilho sutil no ícone:
     */
    #a11y-fab-btn {
        position: relative;
        height: 34px;
        width: 56px;
        padding: 0 14px;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
        background-color: #1996e3;
        color: #ffffff;
        border: none;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.2);
        transition: background-color 0.25s ease, transform 0.2s ease, box-shadow 0.25s ease;
    }

    /* Tooltip instantâneo ao passar o mouse (substitui o title nativo que tem atraso do navegador) */
    #a11y-fab-btn::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: calc(100% + 8px);
        right: 0;
        white-space: nowrap;
        background-color: #111827;
        color: #ffffff;
        font-size: 12px;
        font-weight: 500;
        line-height: 1.2;
        padding: 6px 10px;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        pointer-events: none;
        opacity: 0;
        transform: translateY(4px);
        transition: opacity 0.1s ease, transform 0.1s ease;
        z-index: 60;
    }

    #a11y-fab-btn:hover::after,
    #a11y-fab-btn:focus-visible::after {
        opacity: 1;
        transform: translateY(0);
    }

    html.dark #a11y-fab-btn {
        background-color: #1996e3;
        color: #ffffff;
        border: none;
        box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.35);
    }

    #a11y-fab-btn svg {
        opacity: 0.92;
        transition: opacity 0.25s ease, filter 0.25s ease, transform 0.25s ease;
    }

    #a11y-fab-btn:hover,
    html.dark #a11y-fab-btn:hover {
        background-color: #2ea7f0;
        transform: translateY(-2px);
        box-shadow: 0 -3px 10px rgba(25, 150, 227, 0.25);
    }

    #a11y-fab-btn:hover svg {
        opacity: 1;
        filter: drop-shadow(0 0 1.5px rgba(255, 255, 255, 0.28));
        transform: scale(1.02);
    }

    #a11y-fab-btn:focus-visible {
        outline: 3px solid #60a5fa;
        outline-offset: 2px;
    }

    /* Painel com alto contraste garantido em ambos os temas */
    #a11y-panel {
        background-color: #ffffff;
        color: #09090b;
        border: 2px solid #1D4A7F;
    }

    html.dark #a11y-panel {
        background-color: #111827;
        color: #f9fafb;
        border: 2px solid #2E6CB8;
    }

    .a11y-panel-divider {
        border-color: #d1d5db;
    }

    html.dark .a11y-panel-divider {
        border-color: #374151;
    }

    .a11y-muted-text {
        color: #374151;
    }

    html.dark .a11y-muted-text {
        color: #d1d5db;
    }

    .a11y-accent-badge {
        background-color: #1D4A7F;
        color: #ffffff;
    }

    html.dark .a11y-accent-badge {
        background-color: #2E6CB8;
        color: #ffffff;
    }

    .a11y-primary-action {
        background-color: #1D4A7F;
        color: #ffffff;
        border: 1px solid #163A63;
    }

    .a11y-primary-action:hover {
        background-color: #2E6CB8;
    }

    html.dark .a11y-primary-action {
        background-color: #1D4A7F;
        color: #ffffff;
        border: 1px solid #2E6CB8;
    }

    html.dark .a11y-primary-action:hover {
        background-color: #2E6CB8;
    }

    .a11y-secondary-btn {
        background-color: #f3f4f6;
        color: #111827;
        border: 1px solid #9ca3af;
    }

    .a11y-secondary-btn:hover {
        background-color: #e5e7eb;
    }

    html.dark .a11y-secondary-btn {
        background-color: #1f2937;
        color: #f9fafb;
        border: 1px solid #4b5563;
    }

    html.dark .a11y-secondary-btn:hover {
        background-color: #374151;
    }

    .a11y-reading-highlight {
        outline: 3px solid #f59e0b !important;
        outline-offset: 2px !important;
        border-radius: 6px;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const panel = document.getElementById('a11y-panel');
        const fabBtn = document.getElementById('a11y-fab-btn');
        const closeBtn = document.getElementById('a11y-close-btn');
        const voiceToggleBtn = document.getElementById('a11y-voice-toggle');
        const voiceToggleText = document.getElementById('a11y-voice-toggle-text');
        const voiceBadge = document.getElementById('a11y-voice-badge');
        const readPageBtn = document.getElementById('a11y-read-page-btn');
        const stopVoiceBtn = document.getElementById('a11y-stop-voice-btn');

        const fontDecBtn = document.getElementById('a11y-font-dec');
        const fontResetBtn = document.getElementById('a11y-font-reset');
        const fontIncBtn = document.getElementById('a11y-font-inc');
        const fontLabel = document.getElementById('a11y-font-label');

        let voiceEnabled = localStorage.getItem('krdetail-voice-reader') === 'true';
        let fontScale = parseInt(localStorage.getItem('krdetail-font-scale') || '100', 10);
        let highlightedEl = null;

        function applyFontScale() {
            document.documentElement.style.fontSize = fontScale + '%';
            if (fontLabel) {
                fontLabel.textContent = fontScale + '%';
            }
            localStorage.setItem('krdetail-font-scale', String(fontScale));
        }

        applyFontScale();

        function clearHighlight() {
            if (highlightedEl) {
                highlightedEl.classList.remove('a11y-reading-highlight');
                highlightedEl = null;
            }
        }

        function speakText(text, elementToHighlight = null) {
            if (!('speechSynthesis' in window) || !text) return;

            const cleanText = text.replace(/\s+/g, ' ').trim();
            if (!cleanText) return;

            window.speechSynthesis.cancel();
            clearHighlight();

            const utterance = new SpeechSynthesisUtterance(cleanText);
            utterance.lang = 'pt-BR';
            utterance.rate = 1.05;

            const voices = window.speechSynthesis.getVoices();
            const ptVoice = voices.find(v => v.lang && v.lang.toLowerCase().startsWith('pt-br'))
                || voices.find(v => v.lang && v.lang.toLowerCase().startsWith('pt'));
            if (ptVoice) {
                utterance.voice = ptVoice;
            }

            if (elementToHighlight) {
                highlightedEl = elementToHighlight;
                highlightedEl.classList.add('a11y-reading-highlight');
            }

            utterance.onend = clearHighlight;
            utterance.onerror = clearHighlight;

            window.speechSynthesis.speak(utterance);
        }

        function syncVoiceUI() {
            if (!voiceBadge || !voiceToggleText) return;
            if (voiceEnabled) {
                voiceBadge.textContent = 'Ativo';
                voiceBadge.style.backgroundColor = '#065f46';
                voiceBadge.style.color = '#ffffff';
                voiceBadge.style.borderColor = '#047857';
                voiceToggleText.textContent = 'Desativar Leitor de Voz';
            } else {
                voiceBadge.textContent = 'Desativado';
                voiceBadge.style.backgroundColor = 'transparent';
                voiceBadge.style.color = 'inherit';
                voiceBadge.style.borderColor = '#9ca3af';
                voiceToggleText.textContent = 'Ativar Leitor ao Clicar / Tab';
            }
        }

        syncVoiceUI();

        function toggleVoiceReader() {
            voiceEnabled = !voiceEnabled;
            localStorage.setItem('krdetail-voice-reader', voiceEnabled ? 'true' : 'false');
            syncVoiceUI();

            if (voiceEnabled) {
                speakText('Leitor de voz ativado. Clique ou navegue com a tecla Tab sobre os elementos para ouvir.');
            } else {
                speakText('Leitor de voz desativado.');
                setTimeout(() => window.speechSynthesis && window.speechSynthesis.cancel(), 1400);
            }
        }

        function extractReadableText(el) {
            if (!el) return '';

            const ariaLabel = el.getAttribute('aria-label') || el.getAttribute('title');
            if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
                let labelText = ariaLabel || '';
                if (!labelText && el.id) {
                    const labelEl = document.querySelector(`label[for="${el.id}"]`);
                    if (labelEl) labelText = labelEl.innerText;
                }
                if (!labelText) {
                    const parentLabel = el.closest('label');
                    if (parentLabel) labelText = parentLabel.innerText;
                }
                const placeholder = el.getAttribute('placeholder') || '';
                const val = el.tagName === 'SELECT'
                    ? (el.options[el.selectedIndex]?.text || '')
                    : (el.value || '');
                return `Campo ${labelText || placeholder}. ${val ? 'Valor atual: ' + val : ''}`;
            }

            if (ariaLabel && !el.innerText.trim()) {
                return ariaLabel;
            }

            return el.innerText || ariaLabel || '';
        }

        function readCurrentPageSummary() {
            const main = document.querySelector('main') || document.body;
            const alerts = Array.from(document.querySelectorAll('[role="alert"]'))
                .map(a => a.innerText.trim())
                .filter(Boolean)
                .join('. ');
            const headings = Array.from(main.querySelectorAll('h1, h2, h3'))
                .map(h => h.innerText.trim())
                .filter(Boolean)
                .slice(0, 6)
                .join('. ');

            const fullText = [
                'Resumo da página:',
                alerts ? 'Aviso na tela: ' + alerts : '',
                headings || main.innerText.slice(0, 400)
            ].filter(Boolean).join('. ');

            speakText(fullText, main);
        }

        if (fabBtn && panel) {
            fabBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                panel.classList.toggle('hidden');
            });
        }

        if (closeBtn && panel) {
            closeBtn.addEventListener('click', function () {
                panel.classList.add('hidden');
            });
        }

        if (voiceToggleBtn) {
            voiceToggleBtn.addEventListener('click', toggleVoiceReader);
        }

        if (readPageBtn) {
            readPageBtn.addEventListener('click', readCurrentPageSummary);
        }

        if (stopVoiceBtn) {
            stopVoiceBtn.addEventListener('click', function () {
                if ('speechSynthesis' in window) {
                    window.speechSynthesis.cancel();
                }
                clearHighlight();
            });
        }

        if (fontDecBtn) {
            fontDecBtn.addEventListener('click', function () {
                fontScale = Math.max(85, fontScale - 5);
                applyFontScale();
            });
        }

        if (fontResetBtn) {
            fontResetBtn.addEventListener('click', function () {
                fontScale = 100;
                applyFontScale();
            });
        }

        if (fontIncBtn) {
            fontIncBtn.addEventListener('click', function () {
                fontScale = Math.min(125, fontScale + 5);
                applyFontScale();
            });
        }

        // Leitura ao focar com Tab quando o Leitor de Voz estiver ativo
        document.addEventListener('focusin', function (e) {
            if (!voiceEnabled) return;
            const target = e.target;
            if (!target || target.closest('#a11y-widget-container')) return;

            const text = extractReadableText(target);
            if (text) {
                speakText(text, target);
            }
        });

        // Leitura ao clicar ou selecionar texto quando o Leitor de Voz estiver ativo
        document.addEventListener('click', function (e) {
            if (!voiceEnabled) return;
            if (e.target.closest('#a11y-widget-container')) return;

            const selection = window.getSelection ? window.getSelection().toString().trim() : '';
            if (selection.length > 1) {
                speakText(selection);
                return;
            }

            const readableTarget = e.target.closest('button, a, h1, h2, h3, h4, p, td, th, label, [role="alert"], input, select, textarea');
            if (readableTarget) {
                const text = extractReadableText(readableTarget);
                if (text) {
                    speakText(text, readableTarget);
                }
            }
        });

        // Atalhos de teclado: Alt + V (liga/desliga voz) e Alt + L (ler resumo da página)
        document.addEventListener('keydown', function (e) {
            if (e.altKey && e.key.toLowerCase() === 'v') {
                e.preventDefault();
                toggleVoiceReader();
            } else if (e.altKey && e.key.toLowerCase() === 'l') {
                e.preventDefault();
                readCurrentPageSummary();
            }
        });
    });
</script>
