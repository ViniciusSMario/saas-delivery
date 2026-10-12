/**
 * Máscaras de digitação: <input data-mask="phone|cpf|cnpj|doc|cep">.
 * Um ouvinte só, no documento e na fase de captura: formata antes do Livewire/Alpine lerem
 * o valor, então funciona com wire:model, x-model e formulário comum. O servidor continua
 * aceitando com ou sem pontuação (e guarda só os dígitos onde precisa).
 *
 * CEP com busca no ViaCEP: <input data-mask="cep" data-cep> dentro de um contêiner
 * [data-cep-scope] (ou do formulário) com os campos [data-cep-field="street|district|city|state|number"]
 * e, opcional, um [data-cep-status] para "Buscando..." / "CEP não encontrado".
 */

const digits = (value) => String(value ?? '').replace(/\D/g, '');

/** Aplica o molde ("(##) #####-####") aos dígitos. */
function pattern(value, mold) {
    let out = '';
    let i = 0;

    for (const char of mold) {
        if (i >= value.length) break;
        out += char === '#' ? value[i++] : char;
    }

    return out;
}

export const masks = {
    phone: (v) => {
        const d = digits(v).replace(/^55(?=\d{10,11}$)/, '').slice(0, 11);
        if (d.length <= 2) return d.length ? `(${d}` : '';
        return pattern(d, d.length > 10 ? '(##) #####-####' : '(##) ####-####');
    },
    cpf: (v) => pattern(digits(v).slice(0, 11), '###.###.###-##'),
    cnpj: (v) => pattern(digits(v).slice(0, 14), '##.###.###/####-##'),
    doc: (v) => (digits(v).length > 11 ? masks.cnpj(v) : masks.cpf(v)),
    cep: (v) => pattern(digits(v).slice(0, 8), '#####-###'),
};

const MAX = { phone: 15, cpf: 14, cnpj: 18, doc: 18, cep: 9 };

function format(el, keepCaret = true) {
    const mask = masks[el.dataset.mask];
    if (! mask) return;

    const old = el.value;
    const next = mask(old);
    if (next === old) return;

    // Mantém o cursor depois do mesmo número de dígitos (editar no meio não pula para o fim).
    const caret = keepCaret && document.activeElement === el ? el.selectionStart : null;
    const before = caret === null ? 0 : digits(old.slice(0, caret)).length;
    el.value = next;

    if (caret !== null) {
        let pos = 0;
        for (let seen = 0; pos < next.length && seen < before; pos++) {
            if (/\d/.test(next[pos])) seen++;
        }
        el.setSelectionRange(pos, pos);
    }
}

function prepare(el) {
    if (! el.dataset.maskReady) {
        el.dataset.maskReady = '1';
        el.setAttribute('inputmode', 'numeric');
        if (MAX[el.dataset.mask]) el.setAttribute('maxlength', MAX[el.dataset.mask]);
    }
    format(el, false);
}

export function formatAll(root = document) {
    root.querySelectorAll('input[data-mask]').forEach(prepare);
}

document.addEventListener('input', (e) => {
    const el = e.target;
    if (! (el instanceof HTMLInputElement) || ! el.dataset.mask) return;

    format(el);

    if (el.dataset.mask === 'cep' && el.hasAttribute('data-cep') && digits(el.value).length === 8 && el.dataset.cepLast !== digits(el.value)) {
        lookupCep(el);
    }
}, true);

// Valores que vieram do servidor (ou do rascunho) já aparecem formatados.
document.addEventListener('DOMContentLoaded', () => formatAll());
document.addEventListener('livewire:navigated', () => formatAll());
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ succeed }) => succeed(() => queueMicrotask(() => formatAll())));
});

// ---------- CEP (ViaCEP) ----------

const plain = (s) => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

/** Preenche o campo e avisa Livewire/Alpine (input, change e blur cobrem wire:model, .live, .blur e x-model). */
function fill(el, value) {
    if (! el || value == null || value === '') return false;

    if (el instanceof HTMLSelectElement) {
        // Bairro em lista (taxa por bairro): escolhe a opção de mesmo nome, sem acento e maiúsculas.
        const wanted = plain(value);
        const option = [...el.options].find((o) => o.value && (plain(o.value) === wanted || plain(o.textContent).startsWith(wanted)));
        if (! option) return false;
        el.value = option.value;
    } else {
        el.value = value;
    }

    ['input', 'change', 'blur'].forEach((type) => el.dispatchEvent(new Event(type, { bubbles: true })));

    return true;
}

export async function lookupCep(input) {
    const cep = digits(input.value);
    const scope = input.closest('[data-cep-scope]') || input.form || document;
    const field = (name) => scope.querySelector(`[data-cep-field="${name}"]`);
    const status = scope.querySelector('[data-cep-status]');
    const say = (text, tone = 'muted') => {
        if (! status) return;
        status.textContent = text;
        status.hidden = ! text;
        status.dataset.tone = tone;
    };

    input.dataset.cepLast = cep;
    say('Buscando o endereço...');

    try {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 6000);
        const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`, { signal: controller.signal });
        clearTimeout(timer);
        const data = await response.json();

        if (! response.ok || data.erro) {
            say('CEP não encontrado. Confira o número ou preencha o endereço.', 'error');
            return;
        }

        // Digitou outro CEP enquanto buscava: ignora a resposta antiga.
        if (digits(input.value) !== cep) return;

        fill(field('street'), data.logradouro);
        fill(field('city'), data.localidade);
        fill(field('state'), data.uf);
        const district = field('district');
        const matched = fill(district, data.bairro);

        if (district instanceof HTMLSelectElement && data.bairro && ! matched) {
            say(`O bairro ${data.bairro} não está na lista de entrega.`, 'error');
        } else {
            say('');
        }

        // Rua achada: o próximo é o número.
        if (data.logradouro) field('number')?.focus();
        input.dispatchEvent(new CustomEvent('cep-found', { bubbles: true, detail: data }));
    } catch (e) {
        say('Não foi possível buscar o CEP agora. Preencha o endereço.', 'error');
    }
}

window.lookupCep = lookupCep;
