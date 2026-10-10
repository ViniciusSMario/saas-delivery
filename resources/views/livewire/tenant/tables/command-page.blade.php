@php
    use App\Modules\Orders\Enums\OrderStatus;
    use App\Modules\Tables\CommandStatus;

    $tz = \App\Modules\Establishment\Models\Setting::read('timezone', 'America/Sao_Paulo');
    $isOpen = $command->status->isOpen();
    $launching = $isOpen && $adding && $command->status === CommandStatus::Open;
    $remaining = $totals['total']->subtract($paid);
    $perPerson = \App\Support\Money::cents((int) ceil($totals['total']->cents / max(1, $command->people)));
    $cartCount = array_sum(array_column($cart, 'quantity'));
    // No modo app (garçom) não há topo do layout: o cabeçalho da comanda fica grudado em cima.
    $field = auth()->user()->usesFieldMode();
@endphp

<div @class(['space-y-4 sm:space-y-6', 'pb-28 sm:pb-0' => $isOpen && ! $launching, 'pb-28 xl:pb-0' => $launching]) x-data="{ cartOpen: false }">
    {{-- Cabeçalho --}}
    <header @class(['print:hidden', 'sticky top-0 z-20 -mx-4 border-b border-stone-200 bg-white/95 px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-3 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0' => $field])>
        <div class="flex items-center gap-3">
            <a href="{{ route('tables.map') }}" wire:navigate class="flex size-11 shrink-0 items-center justify-center rounded-2xl border border-stone-200 bg-white text-stone-700 active:bg-stone-100" aria-label="Voltar para as mesas">
                <x-icon name="chevron-left" class="size-6" />
            </a>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-xl font-bold tracking-tight sm:text-2xl">{{ $command->displayName() }}</h1>
                {{-- Situação na linha de baixo (quebra se faltar espaço): o nome da mesa nunca some em celular estreito. --}}
                <p class="flex flex-wrap items-center gap-x-1 gap-y-0.5 text-xs text-stone-500 sm:text-sm">
                    <span @class(['badge mr-1', 'badge-amber' => $command->status === CommandStatus::Closing, 'badge-brand' => $command->status === CommandStatus::Open, 'badge-gray' => ! $isOpen])>{{ $command->status->label() }}</span>
                    <span class="inline-flex items-center gap-1 whitespace-nowrap">
                        #{{ $command->number }}
                        <span aria-hidden="true">·</span> <x-icon name="users" class="size-3.5" /> <span title="pessoas">{{ $command->people }}</span>
                        <span aria-hidden="true">·</span> <x-icon name="clock" class="size-3.5" /> <span title="aberta às">{{ $command->created_at->timezone($tz)->format('H:i') }}</span>
                    </span>
                </p>
            </div>
            <div class="text-right">
                <p class="text-[11px] font-semibold tracking-wide text-stone-500 uppercase">Total</p>
                <p class="text-lg font-extrabold tabular-nums sm:text-2xl">{{ $totals['total']->format() }}</p>
            </div>
            @if ($isOpen)
                <div class="hidden items-center gap-2 sm:flex">
                    @if ($command->status === CommandStatus::Open)
                        @if ($orders->isNotEmpty())
                            <button type="button" wire:click="requestBill" class="btn-secondary"><x-icon name="file" class="size-4" /> Pedir conta</button>
                        @endif
                        <button type="button" wire:click="$toggle('adding')" class="btn-primary">
                            <x-icon :name="$adding ? 'x' : 'plus'" class="size-4" /> {{ $adding ? 'Fechar lançamento' : 'Lançar itens' }}
                        </button>
                    @else
                        <button type="button" wire:click="reopen" class="btn-secondary"><x-icon name="refresh" class="size-4" /> Reabrir para lançar</button>
                    @endif
                </div>
            @endif
        </div>
    </header>

    @error('command') <div class="alert-error">{{ $message }}</div> @enderror
    @error('status') <div class="alert-error">{{ $message }}</div> @enderror

    @if ($command->status === CommandStatus::Closing)
        <div class="alert-warning print:hidden"><x-icon name="file" class="mt-0.5 size-4" /> Conta pedida. Para lançar mais itens, reabra a comanda.</div>
    @endif

    {{-- Lançamento --}}
    @if ($launching)
        <section class="grid gap-6 xl:grid-cols-[1fr_22rem] xl:rounded-2xl xl:border-2 xl:border-dashed xl:border-brand-200 xl:bg-brand-50/30 xl:p-4">
            @include('livewire.tenant.partials.product-picker')

            {{-- Computador: carrinho ao lado --}}
            <div class="card card-body hidden h-fit space-y-3 xl:sticky xl:top-6 xl:block">
                <h2 class="section-title flex items-center gap-2"><x-icon name="flame" class="size-5 text-brand-600" /> Para enviar</h2>
                @error('cart') <div class="alert-error">{{ $message }}</div> @enderror
                @include('livewire.tenant.partials.cart-lines', ['empty' => 'Toque nos produtos para lançar.'])
                <input wire:model="sendNotes" type="text" class="input" placeholder="Observação para a cozinha" aria-label="Observação">
                <p class="flex justify-between text-sm font-semibold"><span class="text-stone-500">Subtotal</span><span class="tabular-nums">{{ $cartSubtotal->format() }}</span></p>
                <button type="button" wire:click="send" class="btn-primary w-full" @disabled(! $cart) wire:loading.attr="disabled"><x-icon name="flame" class="size-4" /> Enviar para a cozinha</button>
            </div>
        </section>

        {{-- Celular: barra de envio e folha com os itens --}}
        <div class="print:hidden fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-white px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-8px_24px_-12px_rgba(0,0,0,0.15)] xl:hidden">
            @error('cart') <p class="field-error mt-0 mb-2">{{ $message }}</p> @enderror
            @if ($cart)
                <div class="grid grid-cols-[1fr_auto] gap-2">
                    <button type="button" @click="cartOpen = true" class="flex h-14 min-w-0 items-center gap-2.5 rounded-2xl border border-stone-200 px-3 text-left active:bg-stone-50">
                        <span class="flex h-8 min-w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 px-1.5 text-sm font-bold text-white">{{ $cartCount }}</span>
                        <span class="min-w-0">
                            <span class="block truncate text-xs text-stone-500">Ver pedido</span>
                            <span class="block truncate font-bold whitespace-nowrap tabular-nums">{{ $cartSubtotal->format() }}</span>
                        </span>
                        <x-icon name="chevron-down" class="ml-auto size-5 shrink-0 rotate-180 text-stone-400 max-[359px]:hidden" />
                    </button>
                    <button type="button" wire:click="send" wire:loading.attr="disabled" class="btn-primary h-14 rounded-2xl px-4 text-base font-bold"><x-icon name="flame" class="size-5" /> Enviar</button>
                </div>
            @else
                <div class="flex items-center gap-3">
                    <p class="flex-1 text-sm text-stone-500">Toque nos produtos para lançar.</p>
                    @if ($orders->isNotEmpty())
                        <button type="button" wire:click="$set('adding', false)" class="btn-secondary h-12 rounded-2xl">Fechar</button>
                    @endif
                </div>
            @endif
        </div>

        <div x-show="cartOpen" x-cloak class="print:hidden fixed inset-0 z-40 flex items-end bg-stone-950/50 backdrop-blur-sm xl:hidden" @click.self="cartOpen = false" @keydown.escape.window="cartOpen = false">
            <div x-show="cartOpen" x-transition:enter="transition duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 class="relative flex max-h-[85vh] w-full flex-col rounded-t-3xl bg-white pb-[max(1rem,env(safe-area-inset-bottom))] shadow-2xl">
                <div class="flex items-center justify-between px-5 pt-6 pb-2">
                    <div>
                        <div class="absolute top-2 left-1/2 h-1.5 w-10 -translate-x-1/2 rounded-full bg-stone-200"></div>
                        <h2 class="text-lg font-bold">Para enviar à cozinha</h2>
                    </div>
                    <button type="button" @click="cartOpen = false" class="btn-icon btn-ghost rounded-full" aria-label="Fechar"><x-icon name="x" class="size-6" /></button>
                </div>
                <div class="flex-1 overflow-y-auto px-5">
                    @include('livewire.tenant.partials.cart-lines', ['empty' => 'Nada escolhido ainda.'])
                </div>
                <div class="space-y-3 border-t border-stone-100 px-5 pt-3">
                    <input wire:model="sendNotes" type="text" class="input h-12" placeholder="Observação para a cozinha (opcional)" aria-label="Observação">
                    <button type="button" wire:click="send" @click="cartOpen = false" class="btn-primary btn-xl" @disabled(! $cart) wire:loading.attr="disabled">
                        <x-icon name="flame" class="size-5" /> Enviar · {{ $cartSubtotal->format() }}
                    </button>
                </div>
            </div>
        </div>
        @include('livewire.tenant.partials.addon-modal')
    @endif

    <div @class(['grid gap-4 sm:gap-6 lg:grid-cols-[1fr_24rem]', 'hidden xl:grid' => $launching])>
        {{-- Itens lançados --}}
        <section class="card">
            <div class="card-header"><h2 class="section-title">Consumo</h2></div>
            @forelse ($orders as $order)
                @php($cancelled = $order->status === OrderStatus::Cancelled)
                <div wire:key="o-{{ $order->id }}" @class(['border-b border-stone-100 px-5 py-3 last:border-b-0', 'opacity-50' => $cancelled])>
                    <div class="flex items-center justify-between gap-2 text-xs text-stone-500">
                        <span class="flex items-center gap-1.5">
                            {{ $order->created_at->timezone($tz)->format('H:i') }} · #{{ $order->number }}
                            <span @class(['badge', 'badge-green' => $order->status === OrderStatus::Ready, 'badge-amber' => in_array($order->status, [OrderStatus::Received, OrderStatus::Preparing], true), 'badge-gray' => $order->status === OrderStatus::Completed, 'badge-red' => $cancelled])>
                                {{ $order->status === OrderStatus::Completed ? 'Servido' : $order->status->label() }}
                            </span>
                        </span>
                        @if ($isOpen && ! $cancelled)
                            @can('orders.cancel')
                                <button type="button" wire:click="$set('cancellingOrderId', {{ $order->id }})" class="px-1 py-1 font-medium text-red-700 print:hidden">Cancelar</button>
                            @endcan
                        @endif
                    </div>
                    @foreach ($order->items as $item)
                        <div class="mt-1.5 flex justify-between gap-2 text-sm">
                            <span>
                                <strong>{{ $item->quantity }}×</strong> {{ $item->name }}
                                @if ($item->addons->isNotEmpty())<span class="block pl-5 text-xs text-stone-500">{{ $item->addons->pluck('name')->implode(', ') }}</span>@endif
                                @if ($item->notes)<span class="block pl-5 text-xs text-amber-700">Obs.: {{ $item->notes }}</span>@endif
                            </span>
                            <span @class(['whitespace-nowrap tabular-nums', 'line-through' => $cancelled])>{{ $item->total_cents->format() }}</span>
                        </div>
                    @endforeach
                    @if ($cancelled && $order->cancel_reason)<p class="mt-1 text-xs text-red-700">Cancelado: {{ $order->cancel_reason }}</p>@endif

                    @if ($cancellingOrderId === $order->id)
                        <form wire:submit="cancelOrder" class="mt-2 space-y-2 print:hidden">
                            <input wire:model="cancelReason" type="text" class="input" placeholder="Motivo do cancelamento" aria-label="Motivo do cancelamento">
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" wire:click="$set('cancellingOrderId', null)" class="btn-secondary">Voltar</button>
                                <button type="submit" class="btn-danger">Cancelar itens</button>
                            </div>
                        </form>
                        @error('reason') <p class="field-error">{{ $message }}</p> @enderror
                    @endif
                </div>
            @empty
                <x-ui.empty icon="utensils" title="Nada lançado ainda" text="Toque em “Lançar itens” para enviar os pedidos desta mesa para a cozinha." />
            @endforelse
        </section>

        {{-- Conta --}}
        <section class="card card-body h-fit space-y-3">
            <h2 class="section-title flex items-center gap-2"><x-icon name="cash" class="size-5 text-brand-600" /> Conta</h2>
            <div class="space-y-1.5 text-sm">
                <div class="flex justify-between"><span>Consumo</span><span class="tabular-nums">{{ $totals['subtotal']->format() }}</span></div>
                <div class="flex items-center justify-between">
                    @if ($isOpen)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model.live="serviceFee" class="checkbox size-5" @cannot('orders.discount') @disabled($serviceFee) @endcannot>
                            Taxa de serviço ({{ $serviceFeePercent }}%)
                        </label>
                    @else
                        <span>Taxa de serviço</span>
                    @endif
                    <span class="tabular-nums">{{ $totals['service_fee']->format() }}</span>
                </div>
                @if ($isOpen)
                    @can('orders.discount')
                        <div class="flex items-center justify-between gap-2">
                            <span>Desconto</span>
                            <input wire:model.live.debounce.400ms="discount" type="text" inputmode="decimal" class="input w-28 py-1 text-right" placeholder="0,00" aria-label="Desconto">
                        </div>
                    @endcan
                @elseif (! $totals['discount']->isZero())
                    <div class="flex justify-between text-green-700"><span>Desconto</span><span>- {{ $totals['discount']->format() }}</span></div>
                @endif
                @error('discount') <p class="field-error">{{ $message }}</p> @enderror
                <div class="flex items-baseline justify-between border-t border-stone-100 pt-2"><span class="font-semibold">Total</span><span class="text-2xl font-extrabold tracking-tight tabular-nums">{{ $totals['total']->format() }}</span></div>
                @if ($command->people > 1)
                    <p class="text-right text-sm text-stone-500">{{ $command->people }} pessoas · <strong class="text-stone-700">{{ $perPerson->format() }}</strong> cada</p>
                @endif
            </div>

            @if ($isOpen)
                @can('cash.operate')
                    <div class="space-y-2 border-t border-stone-100 pt-3 print:hidden">
                        <p class="text-sm font-semibold">Receber</p>
                        @unless ($hasCash)
                            <div class="alert-error">Abra o seu caixa para receber. <a href="{{ route('cash.current') }}" class="font-medium underline" wire:navigate>Abrir caixa</a></div>
                        @endunless
                        @foreach ($payments as $i => $payment)
                            <div class="flex gap-2" wire:key="pay-{{ $i }}">
                                <select wire:model.live="payments.{{ $i }}.method" class="input" aria-label="Forma de pagamento">
                                    @foreach ($paymentMethods as $method)
                                        <option value="{{ $method->code }}">{{ $method->name }}</option>
                                    @endforeach
                                </select>
                                <input wire:model.live.debounce.400ms="payments.{{ $i }}.amount" type="text" inputmode="decimal" class="input w-28 text-right" placeholder="0,00" aria-label="Valor">
                                @if (count($payments) > 1)
                                    <button type="button" wire:click="removePayment({{ $i }})" class="btn-secondary px-3 text-red-700" aria-label="Remover">×</button>
                                @endif
                            </div>
                        @endforeach
                        <div class="flex flex-wrap justify-between gap-2 text-xs">
                            <span class="space-x-3">
                                <button type="button" wire:click="addPayment" class="btn-link text-xs">+ Dividir pagamento</button>
                                <button type="button" wire:click="fillRemaining" class="btn-link text-xs">Completar o restante</button>
                            </span>
                            @if ($remaining->cents > 0)
                                <span class="text-amber-700">Faltam {{ $remaining->format() }}</span>
                            @elseif ($remaining->cents < 0)
                                <span class="text-green-700">Troco {{ \App\Support\Money::cents(-$remaining->cents)->format() }}</span>
                            @endif
                        </div>
                        @error('payments') <p class="field-error">{{ $message }}</p> @enderror
                        @error('cash') <p class="field-error">{{ $message }}</p> @enderror
                        <button type="button" wire:click="close" wire:confirm="Fechar a conta de {{ $command->displayName() }}?" class="btn-success btn-lg w-full" @disabled(! $hasCash || $orders->isEmpty())><x-icon name="check" class="size-5" /> Fechar conta</button>
                    </div>
                @else
                    @if ($command->status === CommandStatus::Closing)
                        <p class="border-t border-stone-100 pt-3 text-sm text-stone-500">O caixa fecha a conta e recebe o pagamento.</p>
                    @endif
                @endcan
            @else
                <div class="space-y-1 border-t border-stone-100 pt-3 text-sm">
                    <p class="font-semibold">{{ $command->status->label() }} {{ $command->closed_at ? 'às '.$command->closed_at->timezone($tz)->format('H:i') : '' }}</p>
                    @foreach ($receipts as $receipt)
                        <p class="flex justify-between"><span>{{ $receipt->method->name }}</span><span>{{ $receipt->amount_cents->format() }}</span></p>
                    @endforeach
                    @if ($change)<p class="font-medium text-green-700">Troco: {{ $change }}</p>@endif
                    @if ($command->cancel_reason)<p class="text-stone-600">{{ $command->cancel_reason }}</p>@endif
                    <button type="button" onclick="window.print()" class="btn-secondary mt-2 w-full print:hidden"><x-icon name="printer" class="size-4" /> Imprimir conta</button>
                </div>
            @endif
        </section>
    </div>

    {{-- Trocar de mesa, juntar, cancelar --}}
    @if ($isOpen && ! $launching)
        <details class="card group print:hidden" @if ($errors->has('table')) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 font-semibold">
                Mais opções
                <x-icon name="chevron-down" class="size-5 text-stone-400 transition group-open:rotate-180" />
            </summary>
            <div class="grid gap-5 border-t border-stone-100 px-5 py-4 text-sm sm:grid-cols-3">
                @if ($command->table_id)
                    <form wire:submit="transfer" class="space-y-2">
                        <p class="font-semibold">Trocar de mesa</p>
                        <div class="flex gap-2">
                            <select wire:model="transferTo" class="input" aria-label="Mesa de destino">
                                <option value="">Mesa livre</option>
                                @foreach ($freeTables as $table)
                                    <option value="{{ $table->id }}">Mesa {{ $table->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn-secondary">Trocar</button>
                        </div>
                        @error('table') <p class="field-error">{{ $message }}</p> @enderror
                    </form>
                @endif
                <form wire:submit="merge" class="space-y-2">
                    <p class="font-semibold">Juntar outra comanda aqui</p>
                    <div class="flex gap-2">
                        <select wire:model="mergeWith" class="input" aria-label="Comanda a juntar">
                            <option value="">Escolha</option>
                            @foreach ($otherCommands as $other)
                                <option value="{{ $other->id }}">{{ $other->displayName() }} (#{{ $other->number }})</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-secondary" wire:confirm="Juntar as contas? A outra comanda será encerrada.">Juntar</button>
                    </div>
                </form>
                @if ($orders->where('status', '!=', OrderStatus::Cancelled)->isEmpty())
                    <div class="space-y-2">
                        <p class="font-semibold">Comanda aberta por engano?</p>
                        <button type="button" wire:click="cancelCommand" wire:confirm="Cancelar esta comanda vazia?" class="btn-secondary text-red-700">Cancelar comanda</button>
                    </div>
                @endif
            </div>
        </details>
    @endif

    {{-- Celular: ações principais embaixo, ao alcance do polegar --}}
    @if ($isOpen && ! $launching)
        <div class="print:hidden fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-white px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-8px_24px_-12px_rgba(0,0,0,0.15)] sm:hidden">
            @if ($command->status === CommandStatus::Open)
                {{-- "Pedir conta" compacto (ícone em cima) para caber ao lado em celular de 320 px. --}}
                <div @class(['grid gap-2', 'grid-cols-[5.5rem_1fr]' => $orders->isNotEmpty()])>
                    @if ($orders->isNotEmpty())
                        <button type="button" wire:click="requestBill" wire:confirm="Pedir a conta de {{ $command->displayName() }}?" class="btn-secondary h-14 flex-col gap-0.5 rounded-2xl px-1 text-xs"><x-icon name="file" class="size-5" /> Pedir conta</button>
                    @endif
                    <button type="button" wire:click="$set('adding', true)" class="btn-primary btn-xl"><x-icon name="plus" class="size-6" stroke="2.4" /> Lançar itens</button>
                </div>
            @else
                <button type="button" wire:click="reopen" class="btn-secondary btn-xl"><x-icon name="refresh" class="size-5" /> Reabrir para lançar</button>
            @endif
        </div>
    @endif
</div>
