<x-layouts.public :title="'Finalizar pedido · '.$establishment->trade_name">
    @php
        $menuUrl = \Illuminate\Support\Str::beforeLast($base, '/pedido');
        $ordering = $isOpen && $acceptsOnline && ($acceptsDelivery || $acceptsPickup);
        $defaultFulfillment = old('fulfillment', $acceptsDelivery ? 'delivery' : 'pickup');
        $cashCodes = $paymentMethods->where('accepts_change', true)->pluck('code')->values();
    @endphp

    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-2xl items-center justify-between gap-3 px-4 py-4">
            <a href="{{ $menuUrl }}" class="btn-link">← Cardápio</a>
            <p class="truncate font-semibold">{{ $establishment->trade_name }}</p>
        </div>
    </header>

    <main class="mx-auto max-w-2xl space-y-6 px-4 py-6"
          x-data="{
              fulfillment: @js($defaultFulfillment),
              payment: @js(old('payment_method', $paymentMethods->first()?->code)),
              cashCodes: @js($cashCodes),
              fee: {{ $deliveryFee->cents }},
              minimum: {{ $minOrder->cents }},
              profileKey: 'cliente:{{ tenant()->getKey() }}',
              get fullTotal() { return this.$store.cart.subtotal + (this.fulfillment === 'delivery' ? this.fee : 0) },
              loadProfile() {
                  if (@js((bool) old('name'))) return;
                  try {
                      const p = JSON.parse(localStorage.getItem(this.profileKey)) || {};
                      for (const [name, value] of Object.entries(p)) {
                          const el = this.$root.querySelector(`[name='${name}']`);
                          if (el && ! el.value) el.value = value;
                      }
                  } catch (e) {}
              },
              saveProfile(form) {
                  const p = {};
                  ['name', 'phone', 'address[zip_code]', 'address[street]', 'address[number]', 'address[complement]', 'address[district]', 'address[city]', 'address[reference]']
                      .forEach((n) => { p[n] = form.querySelector(`[name='${n}']`)?.value || ''; });
                  try { localStorage.setItem(this.profileKey, JSON.stringify(p)); } catch (e) {}
              },
          }"
          x-init="loadProfile()">

        <h1 class="page-title">Seu pedido</h1>

        @if ($errors->any())
            <div class="alert-error">
                <p class="font-medium">Não foi possível enviar o pedido:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @unless ($ordering)
            <div class="alert-error">{{ $isOpen ? 'Pedidos online indisponíveis no momento.' : 'Estamos fechados agora. Os pedidos voltam no próximo horário de funcionamento.' }}</div>
        @endunless

        {{-- Itens --}}
        <section class="card">
            <template x-if="$store.cart.count === 0">
                <div class="card-body text-center text-stone-500">
                    Seu carrinho está vazio. <a href="{{ $menuUrl }}" class="btn-link">Ver cardápio</a>
                </div>
            </template>
            <ul class="divide-y divide-stone-100">
                <template x-for="(item, index) in $store.cart.items" :key="item.sig">
                    <li class="flex gap-3 p-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium" x-text="item.name"></p>
                            <p class="text-xs text-stone-500" x-show="item.addons.length" x-text="item.addons.map(a => a.name).join(', ')"></p>
                            <p class="text-xs text-stone-500" x-show="item.notes" x-text="'Obs.: ' + item.notes"></p>
                            <div class="mt-2 flex items-center rounded-lg border border-stone-300 w-fit text-sm">
                                <button type="button" class="px-3 py-1" @click="$store.cart.setQuantity(index, item.quantity - 1)" :aria-label="item.quantity === 1 ? 'Remover' : 'Diminuir'" x-text="item.quantity === 1 ? '🗑' : '−'"></button>
                                <span class="w-8 text-center" x-text="item.quantity"></span>
                                <button type="button" class="px-3 py-1" @click="$store.cart.setQuantity(index, item.quantity + 1)" aria-label="Aumentar">+</button>
                            </div>
                        </div>
                        <p class="font-medium whitespace-nowrap" x-text="money($store.cart.lineTotal(item))"></p>
                    </li>
                </template>
            </ul>
        </section>

        <form method="POST" action="{{ $base }}" class="space-y-6" x-show="$store.cart.count > 0"
              @submit="$refs.cart.value = $store.cart.payload(); saveProfile($el)">
            @csrf
            <input type="hidden" name="cart" x-ref="cart">

            <section class="card card-body space-y-4">
                <h2 class="font-semibold">Seus dados</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name" class="label">Nome</label>
                        <input id="name" name="name" type="text" class="input" value="{{ old('name') }}" autocomplete="name" required maxlength="120">
                    </div>
                    <div>
                        <label for="phone" class="label">Telefone (WhatsApp)</label>
                        <input id="phone" name="phone" type="tel" class="input" value="{{ old('phone') }}" autocomplete="tel" placeholder="(11) 98888-7777" required>
                    </div>
                </div>
            </section>

            <section class="card card-body space-y-4">
                <h2 class="font-semibold">Como você quer receber?</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    @if ($acceptsDelivery)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-3" :class="fulfillment === 'delivery' ? 'border-brand-600 bg-brand-50' : 'border-stone-200'">
                            <input type="radio" name="fulfillment" value="delivery" x-model="fulfillment" class="accent-brand-600">
                            <span><span class="block font-medium">Entrega</span><span class="text-xs text-stone-500">{{ $deliveryFee->isZero() ? 'Grátis' : 'Taxa de '.$deliveryFee->format() }}</span></span>
                        </label>
                    @endif
                    @if ($acceptsPickup)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-3" :class="fulfillment === 'pickup' ? 'border-brand-600 bg-brand-50' : 'border-stone-200'">
                            <input type="radio" name="fulfillment" value="pickup" x-model="fulfillment" class="accent-brand-600">
                            <span><span class="block font-medium">Retirada</span><span class="text-xs text-stone-500">{{ $establishment->address() ?: 'No estabelecimento' }}</span></span>
                        </label>
                    @endif
                </div>

                <div class="grid gap-4 sm:grid-cols-6" x-show="fulfillment === 'delivery'">
                    <div class="sm:col-span-4">
                        <label for="street" class="label">Rua</label>
                        <input id="street" name="address[street]" type="text" class="input" value="{{ old('address.street') }}" autocomplete="address-line1" :required="fulfillment === 'delivery'">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="number" class="label">Número</label>
                        <input id="number" name="address[number]" type="text" class="input" value="{{ old('address.number') }}" :required="fulfillment === 'delivery'">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="complement" class="label">Complemento</label>
                        <input id="complement" name="address[complement]" type="text" class="input" value="{{ old('address.complement') }}" placeholder="Apto, bloco...">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="district" class="label">Bairro</label>
                        <input id="district" name="address[district]" type="text" class="input" value="{{ old('address.district') }}" :required="fulfillment === 'delivery'">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="city" class="label">Cidade</label>
                        <input id="city" name="address[city]" type="text" class="input" value="{{ old('address.city', $establishment->city) }}">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="reference" class="label">Ponto de referência</label>
                        <input id="reference" name="address[reference]" type="text" class="input" value="{{ old('address.reference') }}">
                    </div>
                </div>
            </section>

            <section class="card card-body space-y-4">
                <h2 class="font-semibold">Pagamento na entrega/retirada</h2>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($paymentMethods as $method)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-3" :class="payment === @js($method->code) ? 'border-brand-600 bg-brand-50' : 'border-stone-200'">
                            <input type="radio" name="payment_method" value="{{ $method->code }}" x-model="payment" class="accent-brand-600">
                            <span class="font-medium">{{ $method->name }}</span>
                        </label>
                    @endforeach
                </div>
                <div x-show="cashCodes.includes(payment)">
                    <label for="change_for" class="label">Troco para quanto? (opcional)</label>
                    <input id="change_for" name="change_for" type="text" inputmode="decimal" class="input max-w-48" value="{{ old('change_for') }}" placeholder="Ex.: 100,00">
                </div>
                <div>
                    <label for="notes" class="label">Observações do pedido</label>
                    <textarea id="notes" name="notes" rows="2" maxlength="500" class="input">{{ old('notes') }}</textarea>
                </div>
            </section>

            <section class="card card-body space-y-1 text-sm">
                <div class="flex justify-between"><span>Subtotal</span><span x-text="money($store.cart.subtotal)"></span></div>
                <div class="flex justify-between" x-show="fulfillment === 'delivery'"><span>Taxa de entrega</span><span x-text="fee ? money(fee) : 'Grátis'"></span></div>
                <div class="flex justify-between pt-2 text-base font-semibold"><span>Total</span><span x-text="money(fullTotal)"></span></div>
                <p class="pt-2 text-amber-700" x-show="$store.cart.subtotal < minimum" x-text="'Pedido mínimo: ' + money(minimum) + ' (sem a taxa de entrega).'"></p>
            </section>

            <button type="submit" class="btn-primary w-full py-3 text-base" :disabled="{{ $ordering ? 'false' : 'true' }} || $store.cart.subtotal < minimum">
                Enviar pedido
            </button>
            <p class="text-center text-xs text-stone-500">O valor final é conferido pelo estabelecimento ao receber o pedido.</p>
        </form>
    </main>

    @include('public-menu.partials.cart-script')
</x-layouts.public>
