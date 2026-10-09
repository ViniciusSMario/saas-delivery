{{--
    Estado do app do cardápio público (Alpine, que vem junto com o script do Livewire).
    Carrinho e "meus pedidos" ficam no localStorage do navegador, separados por estabelecimento.
    Os preços aqui são só para exibição: o servidor recalcula tudo ao receber o pedido.
--}}
<script>
    document.addEventListener('alpine:init', () => {
        const tenant = '{{ tenant()->getKey() }}';
        const read = (key, fallback) => { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch (e) { return fallback; } };
        const write = (key, value) => { try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) {} };

        window.money = (cents) => 'R$ ' + (cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        window.normalize = (s) => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

        Alpine.store('cart', {
            key: 'cart:' + tenant,
            items: [],
            toast: '',
            init() {
                this.items = read(this.key, []);
            },
            save() {
                write(this.key, this.items);
            },
            add(item) {
                const sig = [item.type, item.id, item.addons.map((a) => a.id).sort().join('.'), item.notes || ''].join('|');
                const found = this.items.find((i) => i.sig === sig);
                if (found) {
                    found.quantity = Math.min(99, found.quantity + item.quantity);
                } else {
                    this.items.push({ ...item, sig });
                }
                this.save();
                this.notify(item.name + ' na sacola');
                navigator.vibrate?.(15);
            },
            notify(message) {
                this.toast = message;
                clearTimeout(this.timer);
                this.timer = setTimeout(() => { this.toast = ''; }, 1800);
            },
            setQuantity(index, quantity) {
                if (quantity <= 0) {
                    this.items.splice(index, 1);
                } else {
                    this.items[index].quantity = Math.min(99, quantity);
                }
                this.save();
            },
            clear() {
                this.items = [];
                this.save();
            },
            lineTotal(item) {
                return (item.unit + item.addons.reduce((sum, a) => sum + a.price, 0)) * item.quantity;
            },
            get count() {
                return this.items.reduce((sum, i) => sum + i.quantity, 0);
            },
            get subtotal() {
                return this.items.reduce((sum, i) => sum + this.lineTotal(i), 0);
            },
            payload() {
                return JSON.stringify(this.items.map((i) => ({
                    type: i.type, id: i.id, quantity: i.quantity, addons: i.addons.map((a) => a.id), notes: i.notes || null,
                })));
            },
        });

        // Pedidos feitos neste aparelho (aba "Pedidos"). A situação vem de {pedido}/status.
        Alpine.store('orders', {
            key: 'pedidos:' + tenant,
            list: [],
            loading: false,
            init() {
                this.list = read(this.key, []);
            },
            get active() {
                return this.list.filter((o) => ! o.final).length;
            },
            remember(order) {
                const previous = this.list.find((o) => o.url === order.url) || {};
                this.list = [{ ...previous, ...order }, ...this.list.filter((o) => o.url !== order.url)].slice(0, 15);
                write(this.key, this.list);
            },
            async refresh() {
                if (this.loading || ! this.list.length) return;
                this.loading = true;
                const updated = await Promise.all(this.list.map(async (order) => {
                    if (order.final) return order;
                    try {
                        const response = await fetch(order.url + '/status', { headers: { Accept: 'application/json' } });
                        if (response.status === 404) return null;
                        return response.ok ? { ...order, ...(await response.json()) } : order;
                    } catch (e) {
                        return order;
                    }
                }));
                this.list = updated.filter(Boolean);
                write(this.key, this.list);
                this.loading = false;
            },
        });

        // Folha (bottom sheet) de detalhe do produto: fotos, adicionais, observação e quantidade.
        Alpine.data('picker', () => ({
            product: null,
            open: false,
            quantity: 1,
            notes: '',
            selected: {},
            photo: 0,
            show(id) {
                const product = (window.menuCatalog || {})[id];
                if (! product) return;
                Object.assign(this, { product, quantity: 1, notes: '', selected: {}, photo: 0, open: true });
                document.body.classList.add('overflow-hidden');
                history.pushState({ sheet: true }, '');
            },
            // Fechar pelo botão volta o histórico; o "voltar" do celular também fecha a folha.
            close() {
                if (history.state?.sheet) history.back(); else this.hide();
            },
            hide() {
                this.open = false;
                document.body.classList.remove('overflow-hidden');
            },
            get canOrder() {
                return window.menuOrdering && this.product?.available;
            },
            findGroup(id) {
                return this.product.groups.find((g) => g.id === id);
            },
            has(groupId, addonId) {
                return (this.selected[groupId] || []).includes(addonId);
            },
            toggle(groupId, addonId) {
                if (! this.canOrder) return;
                const g = this.findGroup(groupId);
                let current = this.selected[groupId] || [];
                if (g.max === 1) {
                    current = current.includes(addonId) && g.min === 0 ? [] : [addonId];
                } else if (current.includes(addonId)) {
                    current = current.filter((id) => id !== addonId);
                } else if (current.length < g.max) {
                    current = [...current, addonId];
                }
                this.selected = { ...this.selected, [groupId]: current };
            },
            full(groupId) {
                const g = this.findGroup(groupId);
                return g.max > 1 && (this.selected[groupId] || []).length >= g.max;
            },
            get missing() {
                return this.product ? this.product.groups.filter((g) => (this.selected[g.id] || []).length < g.min) : [];
            },
            get chosen() {
                return this.product.groups.flatMap((g) => g.addons.filter((a) => this.has(g.id, a.id)));
            },
            get total() {
                return (this.product.price + this.chosen.reduce((s, a) => s + a.price, 0)) * this.quantity;
            },
            add() {
                if (this.missing.length || ! this.canOrder) return;
                Alpine.store('cart').add({
                    type: 'product', id: this.product.id, name: this.product.name, unit: this.product.price,
                    addons: this.chosen.map((a) => ({ id: a.id, name: a.name, price: a.price })),
                    quantity: this.quantity, notes: this.notes.trim(),
                });
                this.close();
            },
        }));
    });
</script>
