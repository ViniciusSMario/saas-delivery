/**
 * Modal de confirmação no lugar do confirm() do navegador.
 *
 * - window.confirmDialog('Excluir este cupom? O código fica livre.') → Promise<boolean>
 *   (o texto até o primeiro "?" vira o título; o resto, a explicação). Também aceita
 *   { title, text, confirm, cancel, tone: 'danger' | 'default' }.
 * - wire:confirm do Livewire passa a usar este modal sem mudar as telas. Opcionais no
 *   elemento: data-confirm-button="Excluir" e data-confirm-tone="danger" | "default".
 */

const DANGER_WORDS = /^(excluir|apagar|remover|cancelar|tirar|encerrar|desativar|esvaziar|limpar|gerar um qr novo|suspender|bloquear|descartar|estornar|desfazer)/i;

const ICONS = {
    danger: '<path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" />',
    default: '<path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01" />',
};

/** "Excluir o cupom X? O código fica livre." → título, texto e botão ("Excluir"). */
function parse(message) {
    const text = String(message ?? '').trim() || 'Tem certeza?';
    const cut = text.indexOf('?');
    const title = cut >= 0 ? text.slice(0, cut + 1) : text;
    const rest = cut >= 0 ? text.slice(cut + 1).trim() : '';
    const verb = title.split(/\s+/)[0].replace(/[^\p{L}]/gu, '');
    const isVerb = /(ar|er|ir)$/i.test(verb) && verb.length > 3;

    return {
        title,
        text: rest,
        confirm: isVerb ? verb.charAt(0).toUpperCase() + verb.slice(1).toLowerCase() : 'Confirmar',
        tone: DANGER_WORDS.test(title) ? 'danger' : 'default',
    };
}

let open = null;

export function confirmDialog(options) {
    const o = typeof options === 'string' || options == null ? parse(options) : { ...parse(options.title), ...options };
    // "Cancelar a assinatura?": o botão de desistir não pode se chamar "Cancelar" também.
    if (! o.cancel && (o.confirm || '').toLowerCase() === 'cancelar') o.cancel = 'Voltar';
    const tone = o.tone === 'danger' ? 'danger' : 'default';

    // Um modal por vez: um segundo pedido cancela o anterior.
    open?.(false);

    return new Promise((resolve) => {
        const previous = document.activeElement;
        const root = document.createElement('div');
        root.className = 'fixed inset-0 z-[100] flex items-end justify-center p-0 sm:items-center sm:p-4';
        root.setAttribute('role', 'alertdialog');
        root.setAttribute('aria-modal', 'true');
        root.innerHTML = `
            <div data-backdrop class="absolute inset-0 bg-stone-950/50 opacity-0 backdrop-blur-sm transition-opacity duration-200"></div>
            <div data-panel class="relative w-full max-w-sm translate-y-6 rounded-t-3xl bg-white p-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] text-center opacity-0 shadow-2xl transition duration-200 sm:translate-y-0 sm:scale-95 sm:rounded-3xl">
                <span class="mx-auto flex size-14 items-center justify-center rounded-full ${tone === 'danger' ? 'bg-red-50 text-red-600' : 'bg-brand-50 text-brand-600'}">
                    <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[tone]}</svg>
                </span>
                <h2 data-title class="mt-4 text-lg leading-snug font-bold text-stone-900"></h2>
                <p data-text class="mt-2 text-sm text-stone-600"></p>
                <div class="mt-6 grid grid-cols-2 gap-2">
                    <button type="button" data-cancel class="btn-secondary h-11 justify-center"></button>
                    <button type="button" data-confirm class="${tone === 'danger' ? 'btn-danger' : 'btn-primary'} h-11 justify-center"></button>
                </div>
            </div>`;

        // Textos sempre como texto (nunca HTML).
        root.querySelector('[data-title]').textContent = o.title;
        root.querySelector('[data-text]').textContent = o.text || '';
        root.querySelector('[data-text]').hidden = ! o.text;
        root.querySelector('[data-cancel]').textContent = o.cancel || 'Cancelar';
        root.querySelector('[data-confirm]').textContent = o.confirm || 'Confirmar';
        root.setAttribute('aria-label', o.title);

        const panel = root.querySelector('[data-panel]');
        const backdrop = root.querySelector('[data-backdrop]');

        const close = (result) => {
            if (open !== close) return;
            open = null;
            document.removeEventListener('keydown', onKey, true);
            backdrop.classList.add('opacity-0');
            panel.classList.add('opacity-0', 'translate-y-6', 'sm:scale-95');
            setTimeout(() => root.remove(), 180);
            previous?.focus?.({ preventScroll: true });
            resolve(result);
        };

        const onKey = (e) => {
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(false); }
            if (e.key === 'Tab') {
                // Foco preso nos dois botões.
                const buttons = [...root.querySelectorAll('button')];
                const i = buttons.indexOf(document.activeElement);
                e.preventDefault();
                buttons[(i + (e.shiftKey ? -1 : 1) + buttons.length) % buttons.length].focus();
            }
        };

        root.querySelector('[data-cancel]').addEventListener('click', () => close(false));
        root.querySelector('[data-confirm]').addEventListener('click', () => close(true));
        backdrop.addEventListener('click', () => close(false));
        document.addEventListener('keydown', onKey, true);

        open = close;
        document.body.appendChild(root);
        requestAnimationFrame(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'translate-y-6', 'sm:scale-95');
            // Ação perigosa: o foco começa no "Cancelar".
            root.querySelector(tone === 'danger' ? '[data-cancel]' : '[data-confirm]').focus({ preventScroll: true });
        });
    });
}

window.confirmDialog = confirmDialog;

/**
 * Copia um texto: área de transferência quando o navegador deixa (HTTPS e permissão) e,
 * se não, o jeito antigo (campo escondido + copiar). Resolve true/false, sem erro solto.
 */
window.copyText = async (text) => {
    try {
        await navigator.clipboard.writeText(text);

        return true;
    } catch (e) {
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.cssText = 'position:fixed;opacity:0;top:0;left:0';
        document.body.appendChild(area);
        area.select();
        const ok = document.execCommand?.('copy') ?? false;
        area.remove();

        return ok;
    }
};

/*
 * wire:confirm: o Livewire guarda em el.__livewire_confirm uma função que chama confirm().
 * Aqui a atribuição é interceptada e a função passa a abrir o modal (o "prompt" continua nativo).
 */
Object.defineProperty(HTMLElement.prototype, '__livewire_confirm', {
    configurable: true,
    get() {
        return this.__appConfirm;
    },
    set(original) {
        const el = this;

        el.__appConfirm = (action, instead) => {
            const attr = [...el.attributes].find((a) => a.name.startsWith('wire:confirm'));

            if (! attr || attr.name.includes('.prompt')) {
                return original(action, instead);
            }

            const options = parse(attr.value.replaceAll('\\n', '\n'));
            if (el.dataset.confirmButton) options.confirm = el.dataset.confirmButton;
            if (el.dataset.confirmTone) options.tone = el.dataset.confirmTone;

            confirmDialog(options).then((ok) => (ok ? action() : instead()));
        };
    },
});
