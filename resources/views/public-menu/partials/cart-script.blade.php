{{--
    Carrinho do cardápio público (Alpine, que vem junto com o script do Livewire).
    Fica no localStorage do navegador, separado por estabelecimento. Os preços aqui são
    só para exibição: o servidor recalcula tudo ao receber o pedido.
--}}
<script>
    document.addEventListener('alpine:init', () => {
        const key = 'cart:{{ tenant()->getKey() }}';

        window.money = (cents) => 'R$ ' + (cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        Alpine.store('cart', {
            items: [],
            toast: '',
            init() {
                try { this.items = JSON.parse(localStorage.getItem(key)) || []; } catch (e) { this.items = []; }
            },
            save() {
                try { localStorage.setItem(key, JSON.stringify(this.items)); } catch (e) {}
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
                this.toast = item.name + ' adicionado';
                setTimeout(() => { this.toast = ''; }, 1800);
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

        // Escolha de adicionais de um produto (dentro do dialog de detalhe).
        Alpine.data('picker', (product) => ({
            product,
            quantity: 1,
            notes: '',
            selected: {},
            group(id) {
                return this.product.groups.find((g) => g.id === id);
            },
            has(groupId, addonId) {
                return (this.selected[groupId] || []).includes(addonId);
            },
            toggle(groupId, addonId) {
                const g = this.group(groupId);
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
                const g = this.group(groupId);
                return g.max > 1 && (this.selected[groupId] || []).length >= g.max;
            },
            get missing() {
                return this.product.groups.filter((g) => (this.selected[g.id] || []).length < g.min);
            },
            get chosen() {
                return this.product.groups.flatMap((g) => g.addons.filter((a) => this.has(g.id, a.id)));
            },
            get total() {
                return (this.product.price + this.chosen.reduce((s, a) => s + a.price, 0)) * this.quantity;
            },
            add() {
                if (this.missing.length) return;
                Alpine.store('cart').add({
                    type: 'product', id: this.product.id, name: this.product.name, unit: this.product.price,
                    addons: this.chosen.map((a) => ({ id: a.id, name: a.name, price: a.price })),
                    quantity: this.quantity, notes: this.notes.trim(),
                });
                this.quantity = 1;
                this.notes = '';
                this.selected = {};
                this.$root.closest('dialog')?.close();
            },
        }));
    });
</script>
