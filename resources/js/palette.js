/**
 * Busca rápida do painel (Ctrl+K ou o botão "Buscar" do menu): telas e ações filtradas aqui
 * mesmo, e pedidos, clientes, produtos e insumos vindos do servidor (QuickSearchController).
 * Markup em resources/views/partials/command-palette.blade.php.
 */

/** "Configurações" e "configuracoes" são a mesma coisa para a busca. */
export function normalize(text) {
    return String(text ?? '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim();
}

/** Nota da tela para o termo: começa com > palavra começa com > contém > palavra-chave. */
function score(item, term) {
    const label = normalize(item.label);
    if (label.startsWith(term)) return 4;
    if (label.split(/\s+/).some((word) => word.startsWith(term))) return 3;
    if (label.includes(term)) return 2;
    if (normalize(`${item.keywords} ${item.section}`).split(/\s+/).some((word) => word.startsWith(term))) return 1;
    return 0;
}

const isMac = /Mac|iPhone|iPad/.test(navigator.platform);

document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || (isMac && event.metaKey)) && ! event.altKey && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        window.dispatchEvent(new CustomEvent('open-palette'));
    }
});

document.addEventListener('alpine:init', () => {
    window.Alpine.data('commandPalette', () => ({
        open: false,
        query: '',
        active: 0,
        loading: false,
        remote: [],
        config: { pages: [], actions: [], icons: {}, url: '' },
        timer: null,
        controller: null,
        previous: null,

        init() {
            try {
                this.config = JSON.parse(this.$refs.data.textContent);
            } catch {
                // sem dados: a busca fica só com o que vier do servidor
            }
        },

        get items() {
            const term = normalize(this.query);
            let local;

            if (! term) {
                local = [...this.config.actions, ...this.config.pages];
            } else {
                local = [...this.config.actions, ...this.config.pages]
                    .map((item) => ({ item, score: score(item, term) }))
                    .filter((entry) => entry.score > 0)
                    .sort((a, b) => b.score - a.score)
                    .slice(0, 8)
                    .map((entry) => ({ ...entry.item, section: entry.item.section === 'Ações' ? 'Ações' : 'Telas' }));
            }

            const list = [...local, ...this.remote.map((r) => ({ ...r, section: r.group, label: r.title }))];
            return list.map((item, index) => ({ ...item, index }));
        },

        /** Itens agrupados pela seção, na ordem em que aparecem. */
        get groups() {
            const groups = [];
            for (const item of this.items) {
                let group = groups[groups.length - 1];
                if (! group || group.name !== item.section) {
                    group = { name: item.section, items: [] };
                    groups.push(group);
                }
                group.items.push(item);
            }
            return groups;
        },

        icon(name) {
            return this.config.icons[name] ?? this.config.icons.info ?? '';
        },

        show() {
            if (this.open) return;
            this.previous = document.activeElement;
            this.open = true;
            this.query = '';
            this.remote = [];
            this.active = 0;
            this.$nextTick(() => this.$refs.input.focus());
        },

        hide() {
            if (! this.open) return;
            this.open = false;
            this.controller?.abort();
            clearTimeout(this.timer);
            this.previous?.focus?.({ preventScroll: true });
        },

        search() {
            this.active = 0;
            clearTimeout(this.timer);
            this.controller?.abort();

            const term = this.query.trim();
            if (term.length < 2 && ! /^#?\d+$/.test(term)) {
                this.remote = [];
                this.loading = false;
                return;
            }

            this.loading = true;
            this.timer = setTimeout(async () => {
                this.controller = new AbortController();
                try {
                    const response = await fetch(`${this.config.url}?q=${encodeURIComponent(term)}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        signal: this.controller.signal,
                    });
                    if (! response.ok) throw new Error(String(response.status));
                    const data = await response.json();
                    // Resposta de uma busca que já mudou: descarta.
                    if (term === this.query.trim()) this.remote = data.results ?? [];
                } catch (error) {
                    if (error.name !== 'AbortError') this.remote = [];
                } finally {
                    if (term === this.query.trim()) this.loading = false;
                }
            }, 180);
        },

        move(step) {
            const total = this.items.length;
            if (! total) return;
            this.active = (this.active + step + total) % total;
            this.$nextTick(() => this.$refs.list.querySelector(`[data-index='${this.active}']`)?.scrollIntoView({ block: 'nearest' }));
        },

        go(item) {
            if (! item) return;
            this.hide();
            if (item.external) {
                window.open(item.url, '_blank', 'noopener');
            } else if (window.Livewire?.navigate) {
                window.Livewire.navigate(item.url);
            } else {
                window.location.href = item.url;
            }
        },
    }));
});
