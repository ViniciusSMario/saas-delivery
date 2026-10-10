<x-layouts.public :title="'Finalizar pedido · '.$establishment->trade_name">
    @php
        $menuUrl = \Illuminate\Support\Str::beforeLast($base, '/pedido');
        $ordering = $isOpen && $acceptsOnline && ($acceptsDelivery || $acceptsPickup);
        $defaultFulfillment = old('fulfillment', $acceptsDelivery ? 'delivery' : 'pickup');
        $cashCodes = $paymentMethods->where('accepts_change', true)->pluck('code')->values();
        $option = 'flex cursor-pointer items-center gap-3 rounded-2xl border-2 p-3.5 transition';
    @endphp

    {{-- Barra superior do app --}}
    <header class="sticky top-0 z-20 flex items-center gap-2 border-b border-stone-200/70 bg-white/90 px-2 py-2 backdrop-blur-lg">
        <a href="{{ $menuUrl }}" class="btn-icon btn-ghost rounded-full" aria-label="Voltar ao cardápio">
            <x-icon name="chevron-left" class="size-6" />
        </a>
        <div class="min-w-0">
            <p class="font-bold leading-tight">Sacola</p>
            <p class="truncate text-xs text-stone-500">{{ $establishment->trade_name }}</p>
        </div>
        <button type="button" x-data x-show="$store.cart.count > 0" @click="if (confirm('Esvaziar a sacola?')) $store.cart.clear()" class="btn-ghost btn-sm ml-auto text-red-600 hover:bg-red-50 hover:text-red-700">
            Limpar
        </button>
    </header>

    <main class="space-y-4 px-4 pt-4 pb-40"
          x-data="{
              fulfillment: @js($defaultFulfillment),
              payment: @js(old('payment_method', $paymentMethods->first()?->code)),
              cashCodes: @js($cashCodes),
              baseFee: {{ $deliveryFee->cents }},
              // Taxa por bairro: {bairro: {fee, min}}; vazio = taxa única.
              zones: @js($zones->mapWithKeys(fn ($z) => [$z->name => ['fee' => $z->fee_cents->cents, 'min' => $z->min_order_cents?->cents]])),
              district: @js(old('address.district', '')),
              baseMinimum: {{ $minOrder->cents }},
              sending: false,
              profileKey: 'cliente:{{ tenant()->getKey() }}',
              get byZone() { return Object.keys(this.zones).length > 0 },
              get fee() { return this.byZone ? (this.zones[this.district]?.fee ?? 0) : this.baseFee },
              get minimum() { return (this.fulfillment === 'delivery' && this.zones[this.district]?.min) || this.baseMinimum },
              get fullTotal() { return this.$store.cart.subtotal + (this.fulfillment === 'delivery' ? this.fee : 0) },
              loadProfile() {
                  if (@js((bool) old('name'))) return;
                  try {
                      const p = JSON.parse(localStorage.getItem(this.profileKey)) || {};
                      for (const [name, value] of Object.entries(p)) {
                          if (name === 'address[district]' && this.byZone) {
                              if (this.zones[value]) this.district = value;
                              continue;
                          }
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

        @if ($errors->any())
            <div class="alert-error">
                <x-icon name="alert" class="mt-0.5 size-4" />
                <div>
                    <p class="font-semibold">Não foi possível enviar o pedido:</p>
                    <ul class="mt-1 list-disc pl-5 font-normal">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @unless ($ordering)
            <div class="alert-warning">
                <x-icon name="clock" class="mt-0.5 size-4" />
                <span>{{ $isOpen ? 'Pedidos online indisponíveis no momento.' : 'Estamos fechados agora. Os pedidos voltam no próximo horário de funcionamento.' }}</span>
            </div>
        @endunless

        {{-- Itens --}}
        <section class="card overflow-hidden">
            <template x-if="$store.cart.count === 0">
                <x-ui.empty icon="bag" title="Sua sacola está vazia" text="Escolha seus itens no cardápio e volte aqui para finalizar.">
                    <a href="{{ $menuUrl }}" class="btn-primary">Ver cardápio</a>
                </x-ui.empty>
            </template>
            <ul class="divide-y divide-stone-100">
                <template x-for="(item, index) in $store.cart.items" :key="item.sig">
                    <li class="flex gap-3 p-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold" x-text="item.name"></p>
                            <p class="text-xs text-stone-500" x-show="item.addons.length" x-text="item.addons.map(a => a.name).join(', ')"></p>
                            <p class="text-xs text-stone-500" x-show="item.notes" x-text="'Obs.: ' + item.notes"></p>
                            <p class="mt-1 text-sm font-bold" x-text="money($store.cart.lineTotal(item))"></p>
                        </div>
                        <div class="flex h-9 items-center self-center rounded-xl border border-stone-200 text-brand-600">
                            <button type="button" class="flex size-9 items-center justify-center" @click="$store.cart.setQuantity(index, item.quantity - 1)" :aria-label="item.quantity === 1 ? 'Remover' : 'Diminuir'">
                                <x-icon name="trash" class="size-4" x-show="item.quantity === 1" />
                                <x-icon name="minus" class="size-4" stroke="2.4" x-show="item.quantity > 1" />
                            </button>
                            <span class="w-6 text-center text-sm font-bold text-stone-900" x-text="item.quantity"></span>
                            <button type="button" class="flex size-9 items-center justify-center" @click="$store.cart.setQuantity(index, item.quantity + 1)" aria-label="Aumentar">
                                <x-icon name="plus" class="size-4" stroke="2.4" />
                            </button>
                        </div>
                    </li>
                </template>
            </ul>
            <a href="{{ $menuUrl }}" x-show="$store.cart.count > 0" class="flex items-center justify-center gap-1 border-t border-stone-100 py-3 text-sm font-semibold text-brand-700 hover:bg-brand-50">
                <x-icon name="plus" class="size-4" /> Adicionar mais itens
            </a>
        </section>

        <form method="POST" action="{{ $base }}" class="space-y-4" x-show="$store.cart.count > 0"
              @submit="$refs.cart.value = $store.cart.payload(); saveProfile($el); sending = true">
            @csrf
            <input type="hidden" name="cart" x-ref="cart">

            <section class="card card-body space-y-4">
                <h2 class="section-title flex items-center gap-2"><x-icon name="user" class="size-5 text-brand-600" /> Seus dados</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name" class="label">Nome</label>
                        <input id="name" name="name" type="text" class="input h-11" value="{{ old('name') }}" autocomplete="name" required maxlength="120">
                    </div>
                    <div>
                        <label for="phone" class="label">Telefone (WhatsApp)</label>
                        <input id="phone" name="phone" type="tel" class="input h-11" value="{{ old('phone') }}" autocomplete="tel" placeholder="(11) 98888-7777" required>
                    </div>
                </div>
            </section>

            <section class="card card-body space-y-4">
                <h2 class="section-title flex items-center gap-2"><x-icon name="truck" class="size-5 text-brand-600" /> Como você quer receber?</h2>
                <div class="grid grid-cols-2 gap-3">
                    @if ($acceptsDelivery)
                        <label class="{{ $option }}" :class="fulfillment === 'delivery' ? 'border-brand-600 bg-brand-50' : 'border-stone-200'">
                            <input type="radio" name="fulfillment" value="delivery" x-model="fulfillment" class="sr-only">
                            <x-icon name="truck" class="size-6 text-brand-600" />
                            <span><span class="block font-semibold">Entrega</span><span class="text-xs text-stone-500">{{ $zones->isNotEmpty() ? 'Taxa por bairro' : ($deliveryFee->isZero() ? 'Grátis' : $deliveryFee->format()) }}</span></span>
                        </label>
                    @endif
                    @if ($acceptsPickup)
                        <label class="{{ $option }}" :class="fulfillment === 'pickup' ? 'border-brand-600 bg-brand-50' : 'border-stone-200'">
                            <input type="radio" name="fulfillment" value="pickup" x-model="fulfillment" class="sr-only">
                            <x-icon name="store" class="size-6 text-brand-600" />
                            <span><span class="block font-semibold">Retirada</span><span class="text-xs text-stone-500">No local</span></span>
                        </label>
                    @endif
                </div>
                <p x-show="fulfillment === 'pickup'" class="flex items-start gap-2 rounded-xl bg-stone-50 p-3 text-sm text-stone-600">
                    <x-icon name="pin" class="mt-0.5 size-4 text-stone-400" /> {{ $establishment->address() ?: 'Retire no estabelecimento.' }}
                </p>

                <div class="grid grid-cols-6 gap-4" x-show="fulfillment === 'delivery'">
                    <div class="col-span-4">
                        <label for="street" class="label">Rua</label>
                        <input id="street" name="address[street]" type="text" class="input h-11" value="{{ old('address.street') }}" autocomplete="address-line1" :required="fulfillment === 'delivery'">
                    </div>
                    <div class="col-span-2">
                        <label for="number" class="label">Número</label>
                        <input id="number" name="address[number]" type="text" class="input h-11" value="{{ old('address.number') }}" :required="fulfillment === 'delivery'">
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <label for="complement" class="label">Complemento</label>
                        <input id="complement" name="address[complement]" type="text" class="input h-11" value="{{ old('address.complement') }}" placeholder="Apto, bloco...">
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <label for="district" class="label">Bairro</label>
                        @if ($zones->isNotEmpty())
                            <select id="district" name="address[district]" class="input h-11" x-model="district" :required="fulfillment === 'delivery'">
                                <option value="">Escolha o bairro</option>
                                @foreach ($zones as $zone)
                                    <option value="{{ $zone->name }}">{{ $zone->name }} · {{ $zone->fee_cents->isZero() ? 'grátis' : $zone->fee_cents->format() }}</option>
                                @endforeach
                            </select>
                            <p class="hint">Não achou seu bairro? Ainda não entregamos aí; você pode retirar no local.</p>
                        @else
                            <input id="district" name="address[district]" type="text" class="input h-11" value="{{ old('address.district') }}" :required="fulfillment === 'delivery'">
                        @endif
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <label for="city" class="label">Cidade</label>
                        <input id="city" name="address[city]" type="text" class="input h-11" value="{{ old('address.city', $establishment->city) }}">
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <label for="reference" class="label">Ponto de referência</label>
                        <input id="reference" name="address[reference]" type="text" class="input h-11" value="{{ old('address.reference') }}">
                    </div>
                </div>
            </section>

            <section class="card card-body space-y-4">
                <div>
                    <h2 class="section-title flex items-center gap-2"><x-icon name="wallet" class="size-5 text-brand-600" /> Pagamento</h2>
                    <p class="mt-1 text-xs text-stone-500">Você paga na entrega ou na retirada.</p>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($paymentMethods as $method)
                        <label class="{{ $option }}" :class="payment === @js($method->code) ? 'border-brand-600 bg-brand-50' : 'border-stone-200'">
                            <input type="radio" name="payment_method" value="{{ $method->code }}" x-model="payment" class="sr-only">
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full border-2" :class="payment === @js($method->code) ? 'border-brand-600 bg-brand-600 text-white' : 'border-stone-300'">
                                <span x-show="payment === @js($method->code)"><x-icon name="check" class="size-3" stroke="3.5" /></span>
                            </span>
                            <span class="text-sm font-semibold">{{ $method->name }}</span>
                        </label>
                    @endforeach
                </div>
                <div x-show="cashCodes.includes(payment)">
                    <label for="change_for" class="label">Troco para quanto? (opcional)</label>
                    <input id="change_for" name="change_for" type="text" inputmode="decimal" class="input h-11 max-w-48" value="{{ old('change_for') }}" placeholder="Ex.: 100,00">
                </div>
                <div>
                    <label for="notes" class="label">Observações do pedido</label>
                    <textarea id="notes" name="notes" rows="2" maxlength="500" class="input" placeholder="Ex.: interfone quebrado, ligar ao chegar">{{ old('notes') }}</textarea>
                </div>
            </section>

            <section class="card card-body space-y-2 text-sm">
                <div class="flex justify-between text-stone-600"><span>Subtotal</span><span x-text="money($store.cart.subtotal)"></span></div>
                <div class="flex justify-between text-stone-600" x-show="fulfillment === 'delivery'"><span>Taxa de entrega</span><span x-text="byZone && ! district ? 'escolha o bairro' : (fee ? money(fee) : 'Grátis')"></span></div>
                <div class="flex justify-between border-t border-stone-100 pt-2 text-base font-bold"><span>Total</span><span x-text="money(fullTotal)"></span></div>
                <p class="flex items-center gap-1.5 pt-1 font-medium text-amber-700" x-show="$store.cart.subtotal < minimum">
                    <x-icon name="alert" class="size-4" />
                    <span x-text="'Pedido mínimo: ' + money(minimum) + ' (sem a taxa de entrega).'"></span>
                </p>
            </section>

            <p class="text-center text-xs text-stone-500">O valor final é conferido pelo estabelecimento ao receber o pedido.</p>

            {{-- Barra fixa de envio --}}
            <div class="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-xl border-t border-stone-200/80 bg-white/95 px-4 pt-3 backdrop-blur-lg" style="padding-bottom: max(env(safe-area-inset-bottom), 0.75rem)">
                <button type="submit" class="btn-primary btn-lg w-full justify-between" :disabled="{{ $ordering ? 'false' : 'true' }} || $store.cart.subtotal < minimum || sending">
                    <span x-text="sending ? 'Enviando...' : 'Enviar pedido'">Enviar pedido</span>
                    <span x-text="money(fullTotal)"></span>
                </button>
            </div>
        </form>
    </main>

    @include('public-menu.partials.cart-script')
</x-layouts.public>
