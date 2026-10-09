@php
    use App\Modules\Orders\Enums\OrderStatus;
    use App\Modules\Tables\CommandStatus;

    $tz = \App\Modules\Establishment\Models\Setting::read('timezone', 'America/Sao_Paulo');
    $isOpen = $command->status->isOpen();
    $remaining = $totals['total']->subtract($paid);
    $perPerson = \App\Support\Money::cents((int) ceil($totals['total']->cents / max(1, $command->people)));
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3 print:hidden">
        <div>
            <a href="{{ route('tables.map') }}" class="btn-link" wire:navigate>← Mesas</a>
            <h1 class="page-title mt-1">
                {{ $command->displayName() }}
                <span class="ml-1 text-base font-medium text-stone-500">#{{ $command->number }} · {{ $command->people }} {{ $command->people === 1 ? 'pessoa' : 'pessoas' }}</span>
            </h1>
            <p class="text-sm text-stone-500">{{ $command->status->label() }} · aberta às {{ $command->created_at->timezone($tz)->format('H:i') }}</p>
        </div>
        @if ($isOpen)
            <div class="flex flex-wrap gap-2">
                @if ($command->status === CommandStatus::Open)
                    <button type="button" wire:click="$toggle('adding')" class="btn-primary">{{ $adding ? 'Fechar lançamento' : 'Lançar itens' }}</button>
                    @if ($orders->isNotEmpty())
                        <button type="button" wire:click="requestBill" class="btn-secondary">Pedir conta</button>
                    @endif
                @else
                    <button type="button" wire:click="reopen" class="btn-secondary">Reabrir para lançar</button>
                @endif
            </div>
        @endif
    </div>

    @error('command') <div class="alert-error">{{ $message }}</div> @enderror
    @error('status') <div class="alert-error">{{ $message }}</div> @enderror

    {{-- Lançamento --}}
    @if ($isOpen && $adding && $command->status === CommandStatus::Open)
        <section class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            @include('livewire.tenant.partials.product-picker')
            <div class="card card-body h-fit space-y-3 lg:sticky lg:top-4">
                <h2 class="font-semibold">Para enviar</h2>
                @error('cart') <div class="alert-error">{{ $message }}</div> @enderror
                @include('livewire.tenant.partials.cart-lines', ['empty' => 'Toque nos produtos para lançar.'])
                <input wire:model="sendNotes" type="text" class="input" placeholder="Observação para a cozinha" aria-label="Observação">
                <p class="text-right text-sm font-semibold">{{ $cartSubtotal->format() }}</p>
                <button type="button" wire:click="send" class="btn-primary w-full" @disabled(! $cart) wire:loading.attr="disabled">Enviar para a cozinha</button>
            </div>
        </section>
        @include('livewire.tenant.partials.addon-modal')
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
        {{-- Itens lançados --}}
        <section class="card">
            <h2 class="px-5 pt-5 font-semibold">Consumo</h2>
            @forelse ($orders as $order)
                @php($cancelled = $order->status === OrderStatus::Cancelled)
                <div wire:key="o-{{ $order->id }}" @class(['border-t border-stone-100 px-5 py-3 text-sm', 'opacity-50' => $cancelled])>
                    <div class="flex items-center justify-between gap-2 text-xs text-stone-500">
                        <span>{{ $order->created_at->timezone($tz)->format('H:i') }} · pedido #{{ $order->number }} · {{ $order->status->label() }}{{ $order->createdBy ? ' · '.$order->createdBy->name : '' }}</span>
                        @if ($isOpen && ! $cancelled)
                            @can('orders.cancel')
                                <button type="button" wire:click="$set('cancellingOrderId', {{ $order->id }})" class="text-red-700 hover:underline print:hidden">Cancelar</button>
                            @endcan
                        @endif
                    </div>
                    @foreach ($order->items as $item)
                        <div class="mt-1 flex justify-between gap-2">
                            <span>
                                {{ $item->quantity }}× {{ $item->name }}
                                @if ($item->addons->isNotEmpty())<span class="block pl-5 text-xs text-stone-500">{{ $item->addons->pluck('name')->implode(', ') }}</span>@endif
                                @if ($item->notes)<span class="block pl-5 text-xs text-amber-700">Obs.: {{ $item->notes }}</span>@endif
                            </span>
                            <span @class(['whitespace-nowrap', 'line-through' => $cancelled])>{{ $item->total_cents->format() }}</span>
                        </div>
                    @endforeach
                    @if ($cancelled && $order->cancel_reason)<p class="mt-1 text-xs text-red-700">Cancelado: {{ $order->cancel_reason }}</p>@endif

                    @if ($cancellingOrderId === $order->id)
                        <form wire:submit="cancelOrder" class="mt-2 flex flex-wrap gap-2 print:hidden">
                            <input wire:model="cancelReason" type="text" class="input flex-1" placeholder="Motivo" aria-label="Motivo do cancelamento">
                            <button type="button" wire:click="$set('cancellingOrderId', null)" class="btn-secondary">Voltar</button>
                            <button type="submit" class="btn-danger">Cancelar itens</button>
                        </form>
                        @error('reason') <p class="field-error">{{ $message }}</p> @enderror
                    @endif
                </div>
            @empty
                <p class="px-5 py-6 text-sm text-stone-500">Nada lançado ainda.</p>
            @endforelse
        </section>

        {{-- Conta --}}
        <section class="card card-body h-fit space-y-3">
            <h2 class="font-semibold">Conta</h2>
            <div class="space-y-1 text-sm">
                <div class="flex justify-between"><span>Consumo</span><span>{{ $totals['subtotal']->format() }}</span></div>
                <div class="flex items-center justify-between">
                    @if ($isOpen)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model.live="serviceFee" class="checkbox" @cannot('orders.discount') @disabled($serviceFee) @endcannot>
                            Taxa de serviço ({{ $serviceFeePercent }}%)
                        </label>
                    @else
                        <span>Taxa de serviço</span>
                    @endif
                    <span>{{ $totals['service_fee']->format() }}</span>
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
                <div class="flex justify-between border-t border-stone-100 pt-2 text-lg font-semibold"><span>Total</span><span>{{ $totals['total']->format() }}</span></div>
                @if ($command->people > 1)
                    <p class="text-right text-xs text-stone-500">{{ $command->people }} pessoas · {{ $perPerson->format() }} cada</p>
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
                                    <button type="button" wire:click="removePayment({{ $i }})" class="btn-secondary px-2 text-red-700" aria-label="Remover">×</button>
                                @endif
                            </div>
                        @endforeach
                        <div class="flex justify-between text-xs">
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
                        <button type="button" wire:click="close" wire:confirm="Fechar a conta de {{ $command->displayName() }}?" class="btn-primary w-full" @disabled(! $hasCash || $orders->isEmpty())>Fechar conta</button>
                    </div>
                @endcan
            @else
                <div class="space-y-1 border-t border-stone-100 pt-3 text-sm">
                    <p class="font-semibold">{{ $command->status->label() }} {{ $command->closed_at ? 'às '.$command->closed_at->timezone($tz)->format('H:i') : '' }}</p>
                    @foreach ($receipts as $receipt)
                        <p class="flex justify-between"><span>{{ $receipt->method->name }}</span><span>{{ $receipt->amount_cents->format() }}</span></p>
                    @endforeach
                    @if ($change)<p class="font-medium text-green-700">Troco: {{ $change }}</p>@endif
                    @if ($command->cancel_reason)<p class="text-stone-600">{{ $command->cancel_reason }}</p>@endif
                    <button type="button" onclick="window.print()" class="btn-secondary mt-2 w-full print:hidden">Imprimir conta</button>
                </div>
            @endif
        </section>
    </div>

    {{-- Transferir, juntar, cancelar --}}
    @if ($isOpen)
        <section class="card card-body grid gap-4 text-sm sm:grid-cols-3 print:hidden">
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
                        <button type="submit" class="btn-secondary" @disabled(! $transferTo)>Trocar</button>
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
                    <button type="submit" class="btn-secondary" @disabled(! $mergeWith) wire:confirm="Juntar as contas? A outra comanda será encerrada.">Juntar</button>
                </div>
            </form>
            @if ($orders->where('status', '!=', OrderStatus::Cancelled)->isEmpty())
                <div class="space-y-2">
                    <p class="font-semibold">Comanda aberta por engano?</p>
                    <button type="button" wire:click="cancelCommand" wire:confirm="Cancelar esta comanda vazia?" class="btn-secondary text-red-700">Cancelar comanda</button>
                </div>
            @endif
        </section>
    @endif
</div>
