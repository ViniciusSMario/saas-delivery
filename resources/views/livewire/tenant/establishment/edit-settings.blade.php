<form wire:submit="save" class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Configurações</h1>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> Salvar</button>
    </div>

    <section class="card card-body space-y-4">
        <h2 class="section-title">Cardápio online</h2>

        <div>
            <label for="welcome_message" class="label">Mensagem de boas-vindas</label>
            <input wire:model="welcome_message" id="welcome_message" type="text" maxlength="160" class="input">
            <p class="hint">Aparece no topo do cardápio público.</p>
            @error('welcome_message') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" wire:model="accepts_online_orders" class="checkbox"> Receber pedidos online pelo cardápio</label>
        <p class="hint -mt-2">Desligado, o cardápio continua visível, mas sem carrinho. Os pedidos também só entram no horário de funcionamento.</p>

        <div>
            <label class="flex flex-wrap items-center gap-2 text-sm">
                Avisar a gerência no celular quando um pedido ficar
                <input wire:model="order_waiting_alert_minutes" type="number" min="0" max="60" class="input w-20" aria-label="Minutos sem aceite">
                minutos sem ninguém aceitar
            </label>
            <p class="hint">0 = não avisar. O painel toca um alarme desde a chegada do pedido; este aviso vai para quem pode cancelar pedidos e ativou as notificações.</p>
            @error('order_waiting_alert_minutes') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap gap-6">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="accepts_delivery" class="checkbox"> Aceita entrega</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="accepts_pickup" class="checkbox"> Aceita retirada no local</label>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="min_order" class="label">Pedido mínimo (R$)</label>
                <input wire:model="min_order" id="min_order" type="text" inputmode="decimal" class="input">
                @error('min_order') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="delivery_fee" class="label">Taxa de entrega padrão (R$)</label>
                <input wire:model="delivery_fee" id="delivery_fee" type="text" inputmode="decimal" class="input">
                @error('delivery_fee') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="card card-body space-y-3">
        <h2 class="section-title">Mesas</h2>
        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="service_fee_enabled" class="checkbox"> Cobrar taxa de serviço nas comandas</label>
            <label class="flex items-center gap-2 text-sm">
                <input wire:model="service_fee_percent" type="number" min="0" max="30" class="input w-20"> %
            </label>
        </div>
        <p class="hint">Quem tem permissão de desconto pode tirar a taxa no fechamento da conta.</p>
        @error('service_fee_percent') <p class="field-error">{{ $message }}</p> @enderror
    </section>

    <section class="card card-body space-y-2">
        <h2 class="section-title">Estoque</h2>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="stock_auto_sold_out" class="checkbox"> Marcar o produto como esgotado quando um insumo da ficha técnica zerar</label>
        <p class="hint">Desligado, a venda continua mesmo com estoque negativo, e o insumo fica em alerta.</p>
    </section>

    <section class="card card-body">
        <h2 class="section-title">Formas de pagamento aceitas</h2>
        <p class="hint">Pagamento na entrega, na retirada ou no balcão.</p>
        <div class="mt-3 flex flex-wrap gap-6">
            @foreach ($methods as $method)
                <label class="flex items-center gap-2 text-sm" wire:key="pm-{{ $method->id }}"><input type="checkbox" wire:model="paymentMethods.{{ $method->id }}" class="checkbox"> {{ $method->name }}</label>
            @endforeach
        </div>
        @error('paymentMethods') <p class="field-error">{{ $message }}</p> @enderror
    </section>

    <section class="card card-body">
        <h2 class="section-title">Regional</h2>
        <div class="mt-4 max-w-md">
            <label for="timezone" class="label">Fuso horário</label>
            <select wire:model="timezone" id="timezone" class="input">
                @foreach ($timezones as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('timezone') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </section>
</form>
