@php
    use App\Modules\Orders\Enums\Fulfillment;
    use App\Modules\Orders\Enums\OrderStatus;

    $steps = $order->fulfillment === Fulfillment::Delivery
        ? [OrderStatus::Received, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Dispatched, OrderStatus::Completed]
        : [OrderStatus::Received, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed];
    // Hora em que cada etapa aconteceu (a primeira vez).
    $reachedAt = $order->history->groupBy(fn ($h) => $h->to_status->value)->map(fn ($list) => $list->first()->created_at->timezone($timezone));
    $payment = $order->payments->first();
    $cancelled = $order->status === OrderStatus::Cancelled;
    $final = $order->status->isFinal();
    $stepIcons = ['received' => 'check', 'preparing' => 'flame', 'ready' => 'bag', 'dispatched' => 'truck', 'completed' => 'home'];
    $stepHints = [
        'received' => 'O restaurante recebeu seu pedido',
        'preparing' => 'Aceito e na cozinha',
        'ready' => $order->fulfillment === Fulfillment::Pickup ? 'Pode vir buscar' : 'Embalado, esperando o entregador',
        'dispatched' => $courierName ? "A caminho com {$courierName}" : 'A caminho',
        'completed' => $order->fulfillment === Fulfillment::Delivery ? 'Bom apetite!' : 'Obrigado pela preferência',
    ];
    $orderUrl = rtrim(request()->url(), '/');
    $placedAt = $order->created_at->timezone($timezone);
    $digits = preg_replace('/\D/', '', (string) $establishment->phone);
    $whatsapp = strlen($digits) >= 10
        ? 'https://wa.me/'.(strlen($digits) <= 11 ? '55' : '').$digits.'?text='.rawurlencode("Olá! Sobre o meu pedido #{$order->number}")
        : null;
@endphp
<x-layouts.public :title="'Pedido #'.$order->number.' · '.$establishment->trade_name">
    <x-slot:head>
        <meta name="push-key" content="{{ config('push.vapid.public_key') }}">
        @vite('resources/js/order-push.js')
    </x-slot:head>

    <header class="sticky top-0 z-20 flex items-center gap-2 border-b border-stone-200/70 bg-white/90 px-2 py-2 backdrop-blur-lg">
        <a href="{{ $menuUrl }}#pedidos" class="btn-icon btn-ghost rounded-full" aria-label="Voltar aos pedidos">
            <x-icon name="chevron-left" class="size-6" />
        </a>
        <div class="min-w-0">
            <p class="font-bold leading-tight">Pedido #{{ $order->number }}</p>
            <p class="truncate text-xs text-stone-500">{{ $establishment->trade_name }}</p>
        </div>
    </header>

    <main class="space-y-4 px-4 pt-4 pb-28"
          x-data="orderTracking({ url: @js($orderUrl), statusUrl: @js($orderUrl.'/status'), token: @js($order->public_token), orderId: {{ $order->id }}, status: @js($order->status->value), final: @js($final) })"
          x-init="$store.orders.remember({
              url: @js($orderUrl), number: {{ $order->number }}, status: @js($order->status->value), final: @js($final),
              label: @js($order->status->customerLabel($order->fulfillment)), total: @js($order->total_cents->format()),
              placed_at: @js($placedAt->format('d/m \à\s H:i')),
          })">
        @if (request()->boolean('novo'))
            <div class="alert-success">
                <x-icon name="check" class="mt-0.5 size-4" />
                <span>Pedido enviado! Ele fica salvo na aba <strong>Pedidos</strong> deste aparelho.</span>
            </div>
        @endif

        {{-- Situação --}}
        <section class="card overflow-hidden">
            <div @class([
                'px-5 py-6 text-center text-white',
                'bg-gradient-to-br from-red-500 to-red-700' => $cancelled,
                'bg-gradient-to-br from-emerald-500 to-emerald-700' => $order->status === OrderStatus::Completed,
                'bg-gradient-to-br from-brand-500 to-brand-700' => ! $final,
            ])>
                <span class="relative mx-auto flex size-14 items-center justify-center rounded-full bg-white/20">
                    @unless ($final)<span class="absolute inset-0 animate-ping rounded-full bg-white/20 [animation-duration:2.5s]"></span>@endunless
                    <x-icon :name="$cancelled ? 'x' : ($stepIcons[$order->status->value] ?? 'check')" class="size-7" stroke="2.2" />
                </span>
                <p class="mt-3 text-sm font-medium text-white/80">Pedido #{{ $order->number }}</p>
                <p class="mt-1 text-xl font-extrabold tracking-tight">{{ $order->status->customerLabel($order->fulfillment) }}</p>
                @if ($order->status === OrderStatus::Dispatched && $courierName)
                    <p class="mt-1 text-sm text-white/90">Com {{ $courierName }}</p>
                @endif
                @if ($cancelled && $order->cancel_reason)
                    <p class="mt-1 text-sm text-white/90">Motivo: {{ $order->cancel_reason }}</p>
                @endif
                {{-- Retirada já pronta: não precisa mais de previsão. --}}
                @if ($eta && ! ($order->fulfillment === Fulfillment::Pickup && $order->status === OrderStatus::Ready))
                    <p class="mx-auto mt-4 w-fit rounded-2xl bg-white/15 px-4 py-2">
                        <span class="block text-xs font-medium text-white/80">{{ $order->fulfillment === Fulfillment::Delivery ? 'Previsão de entrega' : 'Previsão para retirar' }}</span>
                        <span class="block text-2xl font-extrabold tabular-nums">{{ $eta->timezone($timezone)->format('H:i') }}</span>
                    </p>
                @endif
                @unless ($final)
                    <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium">
                        <span class="size-1.5 animate-pulse rounded-full bg-white"></span> Atualiza sozinho
                    </p>
                @endunless
            </div>

            @unless ($cancelled)
                <ol class="space-y-0 p-5">
                    @foreach ($steps as $step)
                        @php($at = $reachedAt->get($step->value))
                        @php($done = (bool) $at)
                        @php($current = $order->status === $step)
                        <li class="relative flex gap-3 pb-5 last:pb-0">
                            @unless ($loop->last)
                                @php($nextDone = $reachedAt->has($steps[$loop->index + 1]->value))
                                <span @class(['absolute top-8 left-[15px] h-[calc(100%-2rem)] w-0.5', 'bg-brand-500' => $nextDone, 'bg-stone-200' => ! $nextDone])></span>
                            @endunless
                            <span @class([
                                'relative flex size-8 shrink-0 items-center justify-center rounded-full',
                                'bg-brand-600 text-white' => $done,
                                'ring-4 ring-brand-100' => $current,
                                'bg-stone-100 text-stone-400' => ! $done,
                            ])>
                                <x-icon :name="$stepIcons[$step->value]" class="size-4" stroke="2.2" />
                            </span>
                            <span class="min-w-0 flex-1 pt-1">
                                <span class="flex items-baseline justify-between gap-2">
                                    <span @class(['text-sm', 'font-bold text-stone-900' => $current, 'font-medium text-stone-700' => $done && ! $current, 'text-stone-400' => ! $done])>{{ $step->label() }}</span>
                                    @if ($at)<span class="text-xs tabular-nums text-stone-500">{{ $at->format('H:i') }}</span>@endif
                                </span>
                                @if ($current || $done)
                                    <span @class(['block text-xs', 'text-stone-600' => $current, 'text-stone-400' => ! $current])>{{ $stepHints[$step->value] }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endunless
        </section>

        {{-- Avisos no celular do cliente --}}
        @unless ($final)
            <section class="card p-4" x-cloak>
                <template x-if="subscribed">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><x-icon name="bell" class="size-5" /></span>
                        <p class="min-w-0 flex-1 text-sm"><span class="block font-semibold">Avisos ativados</span><span class="text-stone-500">Você recebe uma notificação a cada etapa.</span></p>
                        <button type="button" @click="disable()" :disabled="busy" class="btn-ghost btn-sm text-stone-500">Desligar</button>
                    </div>
                </template>
                <template x-if="! subscribed && pushSupported && permission !== 'denied'">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="bell" class="size-5" /></span>
                        <p class="min-w-0 flex-1 text-sm"><span class="block font-semibold">Quer ser avisado?</span><span class="text-stone-500">Mandamos uma notificação quando o pedido for aceito, sair e chegar.</span></p>
                        <button type="button" @click="enable()" :disabled="busy" class="btn-primary btn-sm shrink-0">Avisar</button>
                    </div>
                </template>
                <template x-if="! subscribed && ! pushSupported && needsInstall">
                    <p class="flex items-start gap-2 text-sm text-stone-600"><x-icon name="info" class="mt-0.5 size-4 shrink-0 text-brand-600" /> No iPhone, para receber avisos: toque em Compartilhar e em <strong>Adicionar à Tela de Início</strong>, abra o cardápio por lá e volte a este pedido.</p>
                </template>
                <template x-if="! subscribed && ! pushSupported && ! needsInstall">
                    <p class="flex items-start gap-2 text-sm text-stone-600"><x-icon name="info" class="mt-0.5 size-4 shrink-0 text-brand-600" /> Deixe esta página aberta: ela se atualiza sozinha a cada etapa.</p>
                </template>
                <template x-if="! subscribed && pushSupported && permission === 'denied'">
                    <p class="flex items-start gap-2 text-sm text-stone-600"><x-icon name="info" class="mt-0.5 size-4 shrink-0 text-brand-600" /> As notificações estão bloqueadas neste navegador. A página continua se atualizando sozinha.</p>
                </template>
                <p x-show="error" x-text="error" class="field-error mt-2"></p>
            </section>
        @endunless

        {{-- Itens --}}
        <section class="card">
            <div class="card-header"><h2 class="section-title">Itens</h2><span class="text-xs text-stone-500">{{ $placedAt->format('d/m/Y \à\s H:i') }}</span></div>
            <ul class="divide-y divide-stone-100">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-3 px-5 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold"><span class="text-brand-600">{{ $item->quantity }}×</span> {{ $item->name }}</p>
                            @if ($item->description)<p class="text-xs text-stone-500">{{ $item->description }}</p>@endif
                            @if ($item->addons->isNotEmpty())<p class="text-xs text-stone-500">{{ $item->addons->pluck('name')->implode(', ') }}</p>@endif
                            @if ($item->notes)<p class="text-xs text-stone-500">Obs.: {{ $item->notes }}</p>@endif
                        </div>
                        <p class="font-medium whitespace-nowrap">{{ $item->total_cents->format() }}</p>
                    </li>
                @endforeach
            </ul>
            <div class="space-y-1.5 border-t border-stone-100 px-5 py-4 text-sm">
                <div class="flex justify-between text-stone-600"><span>Subtotal</span><span>{{ $order->subtotal_cents->format() }}</span></div>
                @unless ($order->discount_cents->isZero())
                    <div class="flex justify-between text-stone-600"><span>Desconto</span><span>- {{ $order->discount_cents->format() }}</span></div>
                @endunless
                @if ($order->fulfillment === Fulfillment::Delivery)
                    <div class="flex justify-between text-stone-600"><span>Entrega</span><span>{{ $order->delivery_fee_cents->isZero() ? 'Grátis' : $order->delivery_fee_cents->format() }}</span></div>
                @endif
                <div class="flex justify-between pt-1 text-base font-bold"><span>Total</span><span>{{ $order->total_cents->format() }}</span></div>
            </div>
        </section>

        {{-- Entrega e pagamento --}}
        <section class="card divide-y divide-stone-100 text-sm">
            <div class="flex gap-3 p-5">
                <x-icon :name="$order->fulfillment === Fulfillment::Delivery ? 'truck' : 'store'" class="mt-0.5 size-5 text-brand-600" />
                <div>
                    <p class="font-semibold">{{ $order->fulfillment->label() }}</p>
                    @if ($order->fulfillment === Fulfillment::Delivery)
                        <p class="text-stone-600">{{ $order->address() }}</p>
                        @if ($order->address_reference)<p class="text-xs text-stone-500">Ref.: {{ $order->address_reference }}</p>@endif
                    @else
                        <p class="text-stone-600">{{ $establishment->address() ?: 'No estabelecimento' }}</p>
                    @endif
                </div>
            </div>
            <div class="flex gap-3 p-5">
                <x-icon name="wallet" class="mt-0.5 size-5 text-brand-600" />
                <div>
                    <p class="font-semibold">Pagamento</p>
                    <p class="text-stone-600">{{ $payment?->method->name }} · {{ $payment?->status->label() }}</p>
                    @if ($payment?->change())
                        <p class="text-xs text-stone-500">Troco para {{ $payment->change_for_cents->format() }} ({{ $payment->change()->format() }} de troco)</p>
                    @endif
                </div>
            </div>
            @if ($establishment->phone)
                <div class="flex flex-wrap items-center gap-3 p-5">
                    <x-icon name="phone" class="size-5 text-brand-600" />
                    <p class="min-w-0 flex-1">Dúvidas? {{ $establishment->phone }}</p>
                    <div class="flex gap-2">
                        @if ($whatsapp)
                            <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn-success btn-sm">WhatsApp</a>
                        @endif
                        <a href="tel:{{ $digits }}" class="btn-secondary btn-sm">Ligar</a>
                    </div>
                </div>
            @endif
        </section>
    </main>

    @include('public-menu.partials.bottom-nav', ['menuUrl' => $menuUrl, 'current' => 'pedidos'])

    @if (request()->boolean('novo'))
        <script>
            try { localStorage.removeItem('cart:{{ tenant()->getKey() }}'); } catch (e) {}
        </script>
    @endif
    @include('public-menu.partials.cart-script')
</x-layouts.public>
