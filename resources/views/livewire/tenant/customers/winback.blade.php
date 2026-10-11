@php
    use App\Modules\Customers\Models\Customer;
    use App\Modules\Customers\Winback;
    use App\Support\Money;
@endphp
<div class="space-y-6">
    <x-ui.page-header title="Clientes sumidos" subtitle="Quem pedia e parou de pedir. Mande uma mensagem pelo WhatsApp com um cupom para trazer de volta.">
        <a href="{{ route('customers.index') }}" class="btn-secondary" wire:navigate><x-icon name="users" class="size-4" /> Todos os clientes</a>
    </x-ui.page-header>

    {{-- Resultado dos contatos --}}
    <div class="grid grid-cols-3 gap-2 sm:gap-3">
        <div class="card card-body p-3 sm:p-5">
            <p class="text-[11px] leading-tight font-semibold text-stone-500 uppercase sm:text-xs">Chamados em {{ Winback::RESULT_DAYS }} dias</p>
            <p class="mt-1 text-lg font-extrabold tabular-nums sm:text-2xl">{{ $results['contacted'] }}</p>
        </div>
        <div class="card card-body p-3 sm:p-5">
            <p class="text-[11px] leading-tight font-semibold text-stone-500 uppercase sm:text-xs">Voltaram a pedir</p>
            <p class="mt-1 text-lg font-extrabold tabular-nums sm:text-2xl">
                {{ $results['returned'] }}
                @if ($results['contacted'])
                    <span class="text-sm font-semibold text-emerald-700 sm:text-base">{{ round($results['returned'] * 100 / $results['contacted']) }}%</span>
                @endif
            </p>
        </div>
        <div class="card card-body p-3 sm:p-5">
            <p class="text-[11px] leading-tight font-semibold text-stone-500 uppercase sm:text-xs">Vendas de quem voltou</p>
            <p class="mt-1 text-lg font-extrabold tabular-nums sm:text-2xl">{{ Money::cents($results['revenue_cents'])->format() }}</p>
        </div>
    </div>

    {{-- Cupom e mensagem --}}
    <section class="card">
        <div class="card-body grid gap-5 lg:grid-cols-2">
            <div class="space-y-3">
                <div>
                    <label for="winback-coupon" class="label">Cupom para oferecer</label>
                    <select id="winback-coupon" wire:model.live="couponId" class="input">
                        <option value="">Sem cupom</option>
                        @foreach ($coupons as $option)
                            <option value="{{ $option->id }}">{{ $option->code }} · {{ $option->benefit() }}{{ $option->ends_at ? ' · até '.$option->ends_at->timezone($timezone)->format('d/m') : '' }}</option>
                        @endforeach
                    </select>
                    @if ($coupon && $coupon->per_customer_limit === null)
                        <p class="hint">Este cupom não tem limite por cliente: a mesma pessoa pode usar mais de uma vez.</p>
                    @endif
                </div>

                @can('menu.manage')
                    <div class="flex flex-wrap items-end gap-2 rounded-xl bg-stone-50 p-3">
                        <div class="min-w-0 flex-1">
                            <label for="quick-coupon" class="label">Ou crie um cupom de volta agora</label>
                            <select id="quick-coupon" wire:model="quickCoupon" class="input">
                                <option value="10">10% de desconto</option>
                                <option value="15">15% de desconto</option>
                                <option value="20">20% de desconto</option>
                                <option value="frete">Entrega grátis</option>
                            </select>
                        </div>
                        <button type="button" wire:click="createCoupon" wire:loading.attr="disabled" class="btn-secondary">
                            <x-icon name="ticket" class="size-4" /> Criar cupom
                        </button>
                        <p class="hint w-full">Uso único por cliente, válido por {{ \App\Livewire\Tenant\Customers\Winback::QUICK_COUPON_DAYS }} dias e fora da vitrine do cardápio.</p>
                    </div>
                @endcan
            </div>

            <div>
                <div class="flex items-center justify-between gap-2">
                    <span class="label mb-0">Mensagem</span>
                    @unless ($editingMessage)
                        <button type="button" wire:click="$set('editingMessage', true)" class="btn-link inline-flex items-center gap-1 text-sm"><x-icon name="edit" class="size-4" /> Editar</button>
                    @endunless
                </div>

                @if ($editingMessage)
                    <textarea wire:model.live.debounce.500ms="message" rows="5" class="input mt-1" aria-label="Mensagem"></textarea>
                    @error('message') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <p class="hint">Use {nome}, {restaurante}, {cupom}, {desconto}, {validade}, {produto} (o que a pessoa mais pedia) e {link} (cardápio com o cupom já guardado).</p>
                    <div class="mt-2 flex gap-2">
                        <button type="button" wire:click="saveMessage" class="btn-primary btn-sm">Salvar mensagem</button>
                        <button type="button" wire:click="resetMessage" class="btn-ghost btn-sm">Voltar ao texto padrão</button>
                    </div>
                @endif

                @if ($preview !== null)
                    <div class="mt-2 rounded-2xl rounded-tl-sm bg-emerald-50 px-4 py-3 text-sm whitespace-pre-line text-stone-800 ring-1 ring-emerald-100">{{ $preview }}</div>
                    <p class="hint">Exemplo para uma cliente chamada Maria que pedia X-Burguer.</p>
                @else
                    <div class="alert-warning mt-2"><x-icon name="alert" class="mt-0.5 size-4" /> A mensagem cita o cupom: escolha um cupom ou tire {cupom}, {desconto} e {validade} do texto.</div>
                @endif
            </div>
        </div>
    </section>

    {{-- Filtros --}}
    <div class="flex flex-wrap items-center gap-3">
        <label class="flex items-center gap-2 text-sm">
            <span class="text-stone-600">Sem pedir há</span>
            <select wire:model.live="days" class="input w-auto" aria-label="Sem pedir há pelo menos">
                @foreach (Winback::DAYS_OPTIONS as $option)
                    <option value="{{ $option }}">{{ $option }} dias ou mais</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-2 text-sm">
            <span class="text-stone-600">com</span>
            <select wire:model.live="minOrders" class="input w-auto" aria-label="Pedidos feitos">
                <option value="1">1 pedido ou mais</option>
                <option value="2">2 pedidos ou mais</option>
                <option value="3">3 pedidos ou mais</option>
                <option value="5">5 pedidos ou mais</option>
            </select>
        </label>
        <select wire:model.live="sort" class="input w-auto" aria-label="Ordenar">
            <option value="spent">Mais gastou</option>
            <option value="orders">Mais pedidos</option>
            <option value="oldest">Sumido há mais tempo</option>
            <option value="recent">Sumiu há menos tempo</option>
        </select>
        <label class="flex items-center gap-2 text-sm text-stone-600">
            <input type="checkbox" wire:model.live="showContacted" class="rounded border-stone-300">
            Mostrar quem já foi chamado nos últimos {{ Winback::RECONTACT_DAYS }} dias
        </label>
    </div>

    {{-- Lista --}}
    <div class="card divide-y divide-stone-100">
        @forelse ($rows as $row)
            @php
                $customer = $row['customer'];
                $away = (int) abs(\Carbon\CarbonImmutable::parse($customer->last_at)->diffInDays(now()));
                $spent = Money::cents((int) $customer->spent_cents);
                $ticket = Money::cents(intdiv((int) $customer->spent_cents, max(1, (int) $customer->orders_count)));
            @endphp
            <div wire:key="wb-{{ $customer->id }}" class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center">
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-baseline gap-x-2">
                        <a href="{{ route('customers.show', $customer) }}" class="font-semibold text-stone-900 hover:underline" wire:navigate>{{ $customer->name }}</a>
                        <span class="text-xs text-stone-500">{{ Customer::formatPhone($customer->phone) }}</span>
                        @if ($customer->contacted_at)
                            <span class="badge-gray">Chamado {{ \Carbon\CarbonImmutable::parse($customer->contacted_at)->timezone($timezone)->format('d/m') }}{{ $customer->contacts_count > 1 ? ' · '.$customer->contacts_count.' vezes' : '' }}</span>
                        @endif
                    </p>
                    <p class="mt-0.5 text-sm text-stone-600">
                        <span class="font-semibold text-red-700">Sem pedir há {{ $away }} dias</span>
                        · {{ $customer->orders_count }} {{ (int) $customer->orders_count === 1 ? 'pedido' : 'pedidos' }} · {{ $spent->format() }} no total · ticket {{ $ticket->format() }}
                    </p>
                    @if ($row['favorite'])
                        <p class="mt-0.5 text-xs text-stone-500">Mais pedia: <span class="font-medium text-stone-700">{{ $row['favorite'] }}</span></p>
                    @endif
                    @if ($row['couponProblem'])
                        <p class="mt-1 text-xs font-medium text-amber-700">Cupom {{ $coupon->code }}: {{ str_replace('Você já usou', 'já usou', $row['couponProblem']) }}</p>
                    @endif
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button type="button" wire:click="optOut({{ $customer->id }})" wire:confirm="Tirar {{ $customer->name }} da lista? A pessoa não aparece mais aqui para ser chamada."
                            class="btn-ghost btn-sm text-stone-500" title="Não chamar mais">
                        <x-icon name="user-off" class="size-4" /> <span class="sm:hidden lg:inline">Não chamar</span>
                    </button>
                    @if ($row['link'])
                        <a href="{{ $row['link'] }}" target="_blank" rel="noopener" wire:click="contacted({{ $customer->id }})" class="btn-success btn-sm">
                            <x-icon name="message" class="size-4" /> Chamar no WhatsApp
                        </a>
                    @else
                        <span class="btn-secondary btn-sm pointer-events-none opacity-50" title="Escolha um cupom ou ajuste a mensagem"><x-icon name="message" class="size-4" /> Chamar no WhatsApp</span>
                    @endif
                </div>
            </div>
        @empty
            <x-ui.empty icon="users" title="Ninguém sumido por aqui"
                        text="Nenhum cliente com {{ $minOrders }} {{ $minOrders === 1 ? 'pedido' : 'pedidos' }} ou mais está há {{ $days }} dias sem pedir{{ $showContacted ? '' : ' (fora quem já foi chamado)' }}." />
        @endforelse
    </div>
    {{ $customers->links() }}
</div>
