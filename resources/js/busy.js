/**
 * Botão ocupado enquanto o servidor responde, para o painel inteiro e sem mudar as telas.
 *
 * - Livewire: o botão clicado (wire:click) ou o de enviar do formulário (wire:submit) fica
 *   travado já no clique até a resposta; um segundo clique é ignorado (sem pedido ou
 *   lançamento repetido). O Livewire junta cliques seguidos num envio só, por isso a trava
 *   não pode esperar o envio sair. Depois de ~150 ms aparece a rodinha no lugar do texto.
 *   Com wire:confirm, a trava começa quando a pessoa confirma.
 * - Formulário comum (POST): o botão de enviar trava até a página seguinte.
 * - Erro de validação fora da tela: rola até o primeiro campo com erro.
 * - data-busy="off" no botão desliga tudo isso para ele.
 */

const SHOW_AFTER = 150;
const CLICK_WINDOW = 30_000; // o wire:confirm pode deixar o modal aberto por um tempo
const NO_REQUEST = 1500; // clique que não virou envio (só Alpine, por exemplo): solta

const recent = new WeakMap(); // componente → { el, method, at, release? }

function methodOf(expression) {
    return String(expression || '').trim().match(/^\$?[\w.]+/)?.[0] ?? '';
}

function wireAttr(el, name) {
    return el.getAttributeNames().find((attr) => attr === `wire:${name}` || attr.startsWith(`wire:${name}.`));
}

function markBusy(el) {
    if (! el || el.dataset.busy === 'off' || el.hasAttribute('data-busy')) return () => {};

    el.style.setProperty('--busy-color', getComputedStyle(el).color);
    el.setAttribute('data-busy', '');
    el.setAttribute('aria-busy', 'true');
    const timer = setTimeout(() => el.setAttribute('data-busy-visible', ''), SHOW_AFTER);

    return () => {
        clearTimeout(timer);
        el.removeAttribute('data-busy');
        el.removeAttribute('data-busy-visible');
        el.removeAttribute('aria-busy');
        el.style.removeProperty('--busy-color');
    };
}

// Fase de captura: roda antes do Livewire e consegue barrar o clique repetido.
document.addEventListener('click', (event) => {
    const el = event.target.closest?.('button, a, [role="button"]');
    if (! el) return;

    if (el.hasAttribute('data-busy')) {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
    }

    const attr = wireAttr(el, 'click');
    const component = attr && el.closest('[wire\\:id]');
    if (! component) return;

    const entry = { el, method: methodOf(el.getAttribute(attr)), at: Date.now() };

    if (! wireAttr(el, 'confirm')) {
        const release = markBusy(el);
        const timer = setTimeout(() => {
            release();
            if (recent.get(component) === entry) recent.delete(component);
        }, NO_REQUEST);
        entry.release = () => {
            clearTimeout(timer);
            release();
        };
    }

    recent.get(component)?.release?.();
    recent.set(component, entry);
}, true);

document.addEventListener('submit', (event) => {
    const form = event.target;
    const submitter = event.submitter ?? form.querySelector('button[type="submit"], button:not([type])');

    if (submitter?.hasAttribute('data-busy')) {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
    }

    const attr = wireAttr(form, 'submit');

    if (attr) {
        const component = form.closest('[wire\\:id]');
        if (component) recent.set(component, { el: submitter, method: methodOf(form.getAttribute(attr)), at: Date.now() });
        return;
    }

    // Formulário comum: trava até sair da página (download ou nova aba: solta sozinho).
    if ((form.method || '').toLowerCase() !== 'post' || form.target === '_blank' || event.defaultPrevented) return;
    const release = markBusy(submitter);
    setTimeout(release, 8000);
}, true);

// Voltar pelo navegador com a página em cache: nada fica travado.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-busy]').forEach((el) => {
        el.removeAttribute('data-busy');
        el.removeAttribute('data-busy-visible');
        el.removeAttribute('aria-busy');
    });
});

function isVisible(el) {
    const rect = el.getBoundingClientRect();
    return rect.top >= 0 && rect.bottom <= window.innerHeight;
}

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ component, commit, respond }) => {
        const methods = (commit.calls ?? []).map((call) => call.method);
        const root = component.el;
        const click = recent.get(root);
        let release = () => {};

        if (click?.release) {
            // Já travado no clique: solta quando esta resposta chegar.
            recent.delete(root);
            release = click.release;
        } else if (click && Date.now() - click.at < CLICK_WINDOW && methods.includes(click.method)) {
            recent.delete(root);
            release = markBusy(click.el);
        }

        // Só ações (não a digitação com wire:model.live) rolam até o erro novo.
        const before = methods.length ? new Set(root.querySelectorAll('.field-error')) : null;

        respond(() => {
            release();

            if (! before) return;
            requestAnimationFrame(() => {
                const error = [...root.querySelectorAll('.field-error')].find((el) => ! before.has(el) && el.offsetParent !== null);
                if (! error || isVisible(error)) return;

                error.scrollIntoView({ behavior: 'smooth', block: 'center' });
                const field = error.parentElement?.querySelector('input:not([type="hidden"]), select, textarea');
                field?.focus({ preventScroll: true });
            });
        });
    });
});
