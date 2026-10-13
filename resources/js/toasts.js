/**
 * Avisos rápidos no canto da tela ("Produto salvo", "Erro ao imprimir").
 *
 * - window.toast('Cupom salvo.') ou window.toast('Não deu certo.', { tone: 'error' }).
 *   Tons: success (padrão), error, warning, info. Opcional: { action: { label, run } }.
 * - Livewire: $this->toast('...') (App\Livewire\Concerns\Toasts) dispara o evento "toast";
 *   com $undo, o botão "Desfazer" chama aquele método do componente.
 * - Mensagem guardada na sessão antes de um redirecionamento (session('status') / 'error')
 *   chega pelo partial toasts-flash e aparece na tela seguinte.
 */

const TONES = {
    success: { icon: 'M20 6 9 17l-5-5', color: 'text-emerald-400', duration: 4000 },
    error: { icon: 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z', color: 'text-red-400', duration: 7000 },
    warning: { icon: 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z', color: 'text-amber-400', duration: 6000 },
    info: { icon: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 11v5M12 8h.01', color: 'text-sky-400', duration: 4500 },
};

const MAX_VISIBLE = 4;

let stack = null;

/** A troca de página do wire:navigate refaz o body: o contêiner volta quando precisar. */
function container() {
    if (stack && document.body.contains(stack)) return stack;

    stack = document.createElement('div');
    // Celular: no alto (embaixo ficam o carrinho e o alarme de pedido). Computador: embaixo à
    // esquerda do conteúdo, sem cobrir os botões do topo nem o alarme (embaixo à direita).
    stack.className = 'pointer-events-none fixed inset-x-3 top-3 z-[95] flex flex-col items-center gap-2 sm:inset-x-auto sm:top-auto sm:bottom-5 sm:left-5 sm:items-start print:hidden';
    // Painel com a barra lateral fixa (computador): começa depois dela.
    if (document.querySelector('[data-sidebar]')) stack.classList.add('lg:left-[17.25rem]');
    stack.setAttribute('aria-live', 'polite');
    stack.setAttribute('role', 'status');
    document.body.appendChild(stack);

    return stack;
}

export function toast(message, options = {}) {
    const text = String(message ?? '').trim();
    if (! text) return;

    const tone = TONES[options.tone] ? options.tone : 'success';
    const config = TONES[tone];
    const action = options.action && options.action.label && typeof options.action.run === 'function' ? options.action : null;
    const duration = options.duration ?? (action ? Math.max(config.duration, 6000) : config.duration);

    const root = container();

    // A mesma mensagem duas vezes seguidas (clique repetido): só renova a que já está na tela.
    const same = [...root.children].find((el) => el.dataset.text === text && el.dataset.tone === tone);
    if (same) {
        same._restart?.();
        return;
    }

    while (root.children.length >= MAX_VISIBLE) root.firstElementChild._close?.(true);

    const el = document.createElement('div');
    el.dataset.text = text;
    el.dataset.tone = tone;
    el.className = 'pointer-events-auto flex w-full max-w-sm -translate-y-2 items-start gap-3 rounded-2xl bg-stone-900 py-3 pr-2 pl-4 text-sm text-white opacity-0 shadow-float transition duration-200 sm:w-80 sm:translate-y-2 lg:w-96';
    if (tone === 'error') el.setAttribute('role', 'alert');
    el.innerHTML = `
        <svg class="mt-0.5 size-5 shrink-0 ${config.color}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${config.icon}" /></svg>
        <p data-message class="min-w-0 flex-1 py-px leading-snug font-medium"></p>
        <button type="button" data-action class="-my-1 shrink-0 rounded-lg px-2.5 py-1 text-sm font-bold text-brand-300 hover:bg-white/10" hidden></button>
        <button type="button" data-close class="-my-1 shrink-0 rounded-lg p-1 text-stone-400 hover:bg-white/10 hover:text-white" aria-label="Fechar aviso">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
        </button>`;
    el.querySelector('[data-message]').textContent = text;

    let timer = null;
    const close = (instant = false) => {
        clearTimeout(timer);
        el._close = null;
        if (instant) return el.remove();
        el.classList.add('opacity-0');
        setTimeout(() => el.remove(), 200);
    };
    const start = () => {
        clearTimeout(timer);
        if (duration > 0) timer = setTimeout(() => close(), duration);
    };
    el._close = close;
    el._restart = start;

    if (action) {
        const button = el.querySelector('[data-action]');
        button.hidden = false;
        button.textContent = action.label;
        button.addEventListener('click', () => {
            close();
            action.run();
        });
    }
    el.querySelector('[data-close]').addEventListener('click', () => close());

    // Parado enquanto o mouse está em cima (dá tempo de ler ou clicar em Desfazer).
    el.addEventListener('mouseenter', () => clearTimeout(timer));
    el.addEventListener('mouseleave', start);

    root.appendChild(el);
    requestAnimationFrame(() => el.classList.remove('opacity-0', '-translate-y-2', 'sm:translate-y-2'));
    start();
}

window.toast = toast;

// Evento do Livewire ($this->dispatch('toast', ...)) ou de qualquer script.
window.addEventListener('toast', (event) => {
    const { message, tone, undo } = event.detail ?? {};
    const action = undo?.component && undo?.method
        ? { label: undo.label || 'Desfazer', run: () => window.Livewire?.find(undo.component)?.call(undo.method, ...(undo.params ?? [])) }
        : null;

    toast(message, { tone, action });
});

/** Mensagens que vieram do servidor junto com a página (ver partials/toasts-flash). */
function showFlashed() {
    document.querySelectorAll('[data-flash-toast]').forEach((el) => {
        el.remove();
        toast(el.textContent, { tone: el.dataset.flashToast });
    });
}

document.addEventListener('DOMContentLoaded', showFlashed);
document.addEventListener('livewire:navigated', showFlashed);
