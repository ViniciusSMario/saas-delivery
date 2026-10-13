/**
 * Tabela que vira lista de cartões no celular: <table class="table table-cards">.
 *
 * O CSS (app.css) empilha as células de cada linha; aqui cada célula ganha o nome da coluna
 * (data-label, mostrado ao lado do valor) e o papel no cartão (data-card):
 * - title: título do cartão (sem rótulo). Padrão: a primeira coluna.
 * - actions: botões no rodapé. Padrão: a última coluna, se o cabeçalho for vazio ou "Ações".
 * - hide: some no celular. field: linha comum (rótulo + valor).
 * Para mudar o padrão, ponha data-card="..." no <th> da coluna.
 *
 * O Livewire refaz as células a cada atualização e apaga esses atributos: um MutationObserver
 * põe de volta antes de a tela ser desenhada.
 */

function roles(heads) {
    return heads.map((th, i) => {
        if (th.dataset.card) return th.dataset.card;
        if (i === 0) return 'title';
        const text = th.textContent.trim().toLowerCase();
        return i === heads.length - 1 && (text === '' || text === 'ações') ? 'actions' : 'field';
    });
}

function label(table) {
    const heads = [...(table.tHead?.rows[0]?.cells ?? [])];
    if (! heads.length) return;
    const names = heads.map((th) => th.dataset.label ?? th.textContent.trim());
    const kinds = roles(heads);

    for (const body of table.tBodies) {
        for (const row of body.rows) {
            // Linha de "nenhum item" (uma célula com colspan): fica como está.
            if (row.cells.length !== heads.length) continue;

            [...row.cells].forEach((cell, i) => {
                if (cell.dataset.label !== names[i]) cell.dataset.label = names[i];
                if (cell.dataset.card !== kinds[i]) cell.dataset.card = kinds[i];
            });
        }
    }
}

let pending = false;

function labelAll() {
    pending = false;
    document.querySelectorAll('table.table-cards').forEach(label);
}

function schedule() {
    if (pending) return;
    pending = true;
    queueMicrotask(labelAll);
}

new MutationObserver(schedule).observe(document.documentElement, {
    subtree: true,
    childList: true,
    attributes: true,
    attributeFilter: ['data-label', 'data-card'],
});

document.addEventListener('DOMContentLoaded', labelAll);
document.addEventListener('livewire:navigated', labelAll);
