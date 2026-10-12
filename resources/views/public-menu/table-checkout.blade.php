<x-layouts.public :title="'Pedido da mesa '.$table->name.' · '.$establishment->trade_name">
    {{-- Fechamento do pedido feito pelo QR code da mesa: sem entrega nem pagamento (a conta é da mesa). --}}
    <header class="sticky top-0 z-20 flex items-center gap-2 border-b border-stone-200/70 bg-white/90 px-2 py-2 backdrop-blur-lg">
        <a href="{{ $tableUrl }}" class="btn-icon btn-ghost rounded-full" aria-label="Voltar ao cardápio">
            <x-icon name="chevron-left" class="size-6" />
        </a>
        <div class="min-w-0">
            <p class="font-bold leading-tight">Pedido da mesa {{ $table->name }}</p>
            <p class="truncate text-xs text-stone-500">{{ $establishment->trade_name }}</p>
        </div>
        <button type="button" x-data x-show="$store.cart.count > 0" @click="confirmDialog('Esvaziar o pedido? Os itens escolhidos saem do carrinho.').then((ok) => ok && $store.cart.clear())" class="btn-ghost btn-sm ml-auto text-red-600 hover:bg-red-50 hover:text-red-700">
            Limpar
        </button>
    </header>

    <main class="space-y-4 px-4 pt-4 pb-40" x-data="{ sending: false }">
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

        @unless ($state['ordering'])
            <div class="alert-warning"><x-icon name="info" class="mt-0.5 size-4" /> <span>{{ $state['reason'] }}</span></div>
        @endunless

        <section class="card overflow-hidden">
            <template x-if="$store.cart.count === 0">
                <x-ui.empty icon="utensils" title="Nenhum item escolhido" text="Escolha no cardápio e volte aqui para enviar.">
                    <a href="{{ $tableUrl }}" class="btn-primary">Ver cardápio</a>
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
            <a href="{{ $tableUrl }}" x-show="$store.cart.count > 0" class="flex items-center justify-center gap-1 border-t border-stone-100 py-3 text-sm font-semibold text-brand-700 hover:bg-brand-50">
                <x-icon name="plus" class="size-4" /> Adicionar mais itens
            </a>
        </section>

        <form method="POST" action="{{ $tableUrl }}/pedido" class="space-y-4" x-show="$store.cart.count > 0"
              @submit="$refs.cart.value = $store.cart.payload(); sending = true">
            @csrf
            <input type="hidden" name="cart" x-ref="cart">

            <section class="card card-body space-y-4">
                <div>
                    <label for="name" class="label">Seu nome <span class="font-normal text-stone-400">(opcional)</span></label>
                    <input id="name" name="name" type="text" class="input h-11" value="{{ old('name') }}" autocomplete="given-name" maxlength="40" placeholder="Para o garçom saber de quem é">
                </div>
                <div>
                    <label for="notes" class="label">Observações <span class="font-normal text-stone-400">(opcional)</span></label>
                    <textarea id="notes" name="notes" rows="2" class="input" maxlength="500" placeholder="Ex.: trazer junto com as bebidas">{{ old('notes') }}</textarea>
                </div>
            </section>

            <p class="text-center text-xs text-stone-500">O pedido entra na conta da mesa {{ $table->name }}. O pagamento é no final, com o garçom ou no caixa.</p>

            <div class="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-xl border-t border-stone-200/80 bg-white/95 px-4 pt-3 backdrop-blur-lg" style="padding-bottom: max(env(safe-area-inset-bottom), 0.75rem)">
                <button type="submit" class="btn-primary btn-lg w-full justify-between" :disabled="{{ $state['ordering'] ? 'false' : 'true' }} || sending">
                    <span x-text="sending ? 'Enviando...' : 'Enviar para a cozinha'">Enviar para a cozinha</span>
                    <span x-text="money($store.cart.subtotal)"></span>
                </button>
            </div>
        </form>
    </main>

    <script>
        window.cartScope = @js('mesa-'.$table->id);
    </script>
    @include('public-menu.partials.cart-script')
</x-layouts.public>
