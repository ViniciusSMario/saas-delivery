/**
 * Atalhos de teclado do painel, declarados na própria tela:
 *
 *   <button data-hotkey="F9 ctrl+enter" data-hotkey-label="Finalizar pedido">...</button>
 *
 * - A tecla clica no elemento (botão, link, opção) ou, se for campo, põe o foco nele.
 *   Várias teclas separadas por espaço. Só vale o elemento visível e habilitado.
 * - Com uma janela aberta (role="dialog" aria-modal="true"), só valem os atalhos de dentro
 *   dela; com o modal de confirmação, a ajuda ou a busca rápida, nenhum.
 * - Letras e "/" não disparam enquanto se digita num campo; F1–F12, Esc e combinações
 *   com Ctrl/Alt disparam sempre.
 * - "?" abre a lista com os atalhos da tela. Atalhos tratados por script próprio (o painel
 *   de pedidos, por exemplo) entram na lista com <template data-hotkey-help="teclas">Texto</template>.
 * - Ctrl+K (busca rápida) fica em partials/command-palette.
 */

const TYPING = 'input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]), textarea, select, [contenteditable="true"]';

const GLOBAL = [
    ['ctrl+k', 'Busca rápida: telas, pedidos, clientes e produtos'],
    ['?', 'Mostrar esta lista'],
    ['esc', 'Fechar janela ou cancelar'],
];

const NAMES = { ctrl: 'Ctrl', alt: 'Alt', shift: 'Shift', meta: '⌘', enter: 'Enter', esc: 'Esc', escape: 'Esc', arrowup: '↑', arrowdown: '↓', arrowleft: '←', arrowright: '→', space: 'Espaço', delete: 'Del', backspace: '⌫' };

const isMac = /Mac|iPhone|iPad/.test(navigator.platform);

/** "ctrl+Enter" → { ctrl: true, key: 'enter' }; no Mac, ctrl também aceita ⌘. */
function parse(combo) {
    const parts = combo.toLowerCase().split('+');
    const key = parts.pop();
    return { key: key === 'escape' ? 'esc' : key, ctrl: parts.includes('ctrl'), alt: parts.includes('alt'), shift: parts.includes('shift') };
}

function keyOf(event) {
    const key = event.key.toLowerCase();
    if (key === 'escape') return 'esc';
    if (key === ' ') return 'space';
    return key;
}

function matches(combo, event) {
    const c = parse(combo);
    const ctrl = event.ctrlKey || (isMac && event.metaKey);
    // "?" e "/" dependem do teclado (Shift no ABNT2): compara só o caractere.
    const symbol = c.key.length === 1 && ! /[a-z0-9]/.test(c.key);

    return keyOf(event) === c.key && ctrl === c.ctrl && event.altKey === c.alt && (symbol || event.shiftKey === c.shift);
}

/** Tecla que funciona mesmo com o cursor num campo. */
function worksWhileTyping(combo) {
    const c = parse(combo);
    return c.ctrl || c.alt || c.key === 'esc' || /^f\d{1,2}$/.test(c.key);
}

function usable(el) {
    if (el.disabled || el.closest('[inert], [aria-hidden="true"]')) return false;
    // Opção escondida (radio sr-only dentro do label): vale o label.
    return el.offsetParent !== null || el.getClientRects().length > 0;
}

export function label(combo) {
    return combo.split('+').map((part) => {
        const p = part.toLowerCase();
        if (p === 'ctrl' && isMac) return '⌘';
        return NAMES[p] ?? (p.length === 1 ? p.toUpperCase() : part.toUpperCase());
    }).join(' + ');
}

/** A janela aberta por cima de tudo (a última no documento), se houver. */
function activeModal() {
    const open = [...document.querySelectorAll('[role="alertdialog"], [data-hotkeys-modal], [role="dialog"][aria-modal="true"]')]
        .filter((el) => el.getClientRects().length > 0);

    return open[open.length - 1] ?? null;
}

/** Modal que cuida das próprias teclas: confirmação, esta ajuda e a busca rápida. */
function blocking(modal) {
    return modal && (modal.matches('[role="alertdialog"], [data-hotkeys-modal]') || modal.closest('[data-hotkeys-modal]'));
}

document.addEventListener('keydown', (event) => {
    if (event.defaultPrevented || event.isComposing || event.repeat && ! /^F\d/.test(event.key)) return;

    const typing = event.target.closest?.(TYPING);
    const modal = activeModal();

    if (! typing && event.key === '?' && ! modal) {
        event.preventDefault();
        showHelp();
        return;
    }

    if (blocking(modal)) return;

    for (const el of (modal ?? document).querySelectorAll('[data-hotkey]')) {
        const combo = el.dataset.hotkey.split(/\s+/).find((c) => c && matches(c, event));
        if (! combo || (typing && ! worksWhileTyping(combo)) || ! usable(el)) continue;

        event.preventDefault();

        if (el.matches(TYPING)) {
            el.focus();
            el.select?.();
        } else {
            el.click();
        }
        return;
    }
}, true);

/** Lista os atalhos: os da tela (visíveis agora) e os gerais. */
function collect() {
    const seen = new Set();
    const page = [];

    const add = (keys, text) => {
        const name = (text || '').replace(/\s+/g, ' ').trim();
        const id = keys + name;
        if (! name || seen.has(id)) return;
        seen.add(id);
        page.push([keys, name]);
    };

    document.querySelectorAll('[data-hotkey]').forEach((el) => {
        if (! usable(el) && ! el.matches('input[type="radio"]')) return;
        add(el.dataset.hotkey, el.dataset.hotkeyLabel || el.getAttribute('aria-label') || el.getAttribute('placeholder') || el.textContent);
    });
    document.querySelectorAll('template[data-hotkey-help]').forEach((el) => add(el.dataset.hotkeyHelp, el.content.textContent));

    return page;
}

function row([keys, text]) {
    const li = document.createElement('li');
    li.className = 'flex items-center justify-between gap-4 py-2';
    const span = document.createElement('span');
    span.className = 'text-sm text-stone-700';
    span.textContent = text;
    const kbds = document.createElement('span');
    kbds.className = 'flex shrink-0 flex-wrap justify-end gap-1';
    keys.split(/\s+/).filter(Boolean).forEach((combo, i) => {
        if (i) {
            const or = document.createElement('span');
            or.className = 'self-center text-xs text-stone-400';
            or.textContent = 'ou';
            kbds.appendChild(or);
        }
        const kbd = document.createElement('kbd');
        kbd.className = 'rounded-md border border-stone-300 bg-stone-50 px-1.5 py-0.5 font-sans text-xs font-semibold text-stone-700 shadow-xs';
        kbd.textContent = label(combo);
        kbds.appendChild(kbd);
    });
    li.append(span, kbds);
    return li;
}

export function showHelp() {
    if (document.querySelector('[data-hotkeys-modal]')) return;

    const page = collect();
    const previous = document.activeElement;
    const root = document.createElement('div');
    root.dataset.hotkeysModal = '';
    root.className = 'fixed inset-0 z-[100] flex items-end justify-center sm:items-center sm:p-4';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-label', 'Atalhos de teclado');
    root.innerHTML = `
        <div data-backdrop class="absolute inset-0 bg-stone-950/50 backdrop-blur-sm"></div>
        <div class="relative max-h-[85vh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-white p-6 shadow-2xl sm:rounded-3xl">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-stone-900">Atalhos de teclado</h2>
                <button type="button" data-close class="btn-icon btn-ghost -mr-2" aria-label="Fechar">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                </button>
            </div>
            <section data-page hidden>
                <h3 class="mt-4 text-xs font-semibold tracking-wide text-stone-500 uppercase">Nesta tela</h3>
                <ul data-list class="divide-y divide-stone-100"></ul>
            </section>
            <h3 class="mt-4 text-xs font-semibold tracking-wide text-stone-500 uppercase">Em qualquer tela</h3>
            <ul data-global class="divide-y divide-stone-100"></ul>
        </div>`;

    if (page.length) {
        root.querySelector('[data-page]').hidden = false;
        page.forEach((item) => root.querySelector('[data-list]').appendChild(row(item)));
    }
    GLOBAL.forEach((item) => root.querySelector('[data-global]').appendChild(row(item)));

    const close = () => {
        document.removeEventListener('keydown', onKey, true);
        root.remove();
        previous?.focus?.({ preventScroll: true });
    };
    const onKey = (event) => {
        if (event.key === 'Escape' || event.key === '?') {
            event.preventDefault();
            event.stopImmediatePropagation();
            close();
        }
    };

    root.querySelector('[data-backdrop]').addEventListener('click', close);
    root.querySelector('[data-close]').addEventListener('click', close);
    document.addEventListener('keydown', onKey, true);
    document.addEventListener('livewire:navigating', close, { once: true });

    document.body.appendChild(root);
    root.querySelector('[data-close]').focus();
}

window.showShortcuts = showHelp;
