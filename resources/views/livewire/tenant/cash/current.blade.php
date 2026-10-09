@php($tz = \App\Modules\Establishment\Models\Setting::read('timezone', 'America/Sao_Paulo'))
<div class="space-y-6">
    <x-ui.page-header title="Caixa" :subtitle="$session ? $session->register->name.' · aberto às '.$session->opened_at->timezone($tz)->format('H:i') : 'Abra o caixa para receber pagamentos no balcão e nas mesas.'">
        @can('cash.manage')
            <a href="{{ route('cash.sessions') }}" class="btn-secondary" wire:navigate><x-icon name="list" class="size-4" /> Todos os caixas</a>
        @endcan
        @can('pos.use')
            <a href="{{ route('orders.counter') }}" class="btn-primary" wire:navigate><x-icon name="pos" class="size-4" /> Ir para o PDV</a>
        @endcan
    </x-ui.page-header>

    @if (! $session)
        {{-- Abertura --}}
        <form wire:submit="open" class="card max-w-xl overflow-hidden">
            <div class="flex items-center gap-4 border-b border-stone-100 bg-gradient-to-br from-emerald-50 to-white p-6">
                <span class="flex size-12 items-center justify-center rounded-2xl bg-emerald-600 text-white"><x-icon name="wallet" class="size-6" /></span>
                <div>
                    <h2 class="text-lg font-bold">Abrir caixa</h2>
                    <p class="text-sm text-stone-500">Informe o fundo de troco em dinheiro.</p>
                </div>
            </div>
            <div class="space-y-4 p-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="registerId" class="label">Caixa</label>
                        <select wire:model="registerId" id="registerId" class="input">
                            <option value="">Escolha</option>
                            @foreach ($registers as $register)
                                <option value="{{ $register->id }}" @disabled($register->sessions->isNotEmpty())>
                                    {{ $register->name }}{{ $register->sessions->isNotEmpty() ? ' · aberto por '.$register->sessions->first()->operator->name : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('register') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="opening" class="label">Fundo de troco (R$)</label>
                        <input wire:model="opening" id="opening" type="text" inputmode="decimal" class="input" placeholder="0,00">
                        @error('opening') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <button type="submit" class="btn-success w-full" @disabled(! $registers->contains(fn ($r) => $r->sessions->isEmpty()))>
                    <x-icon name="check" class="size-4" /> Abrir caixa
                </button>
            </div>
        </form>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <section class="card lg:col-span-2">
                <div class="card-header">
                    <h2 class="section-title flex items-center gap-2">
                        <span class="size-2 animate-pulse rounded-full bg-emerald-500"></span> Esperado no caixa
                    </h2>
                    <span class="badge-gray">Fundo {{ $session->opening_cents->format() }}</span>
                </div>
                <dl class="grid gap-3 p-5 sm:grid-cols-3 sm:p-6">
                    @foreach ($expected as $code => $amount)
                        @if (! $amount->isZero() || $code === 'cash')
                            <div @class(['rounded-xl border p-4', 'border-emerald-200 bg-emerald-50' => $code === 'cash', 'border-stone-200 bg-stone-50/70' => $code !== 'cash'])>
                                <dt class="stat-label">{{ $methods[$code]->name }}</dt>
                                <dd class="mt-1 text-xl font-bold tabular-nums">{{ $amount->format() }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
                <div class="flex flex-wrap gap-x-5 gap-y-1 border-t border-stone-100 px-5 py-3 text-xs text-stone-500 sm:px-6">
                    <span>Recebimentos <strong class="text-stone-700">{{ $totals['sale']->format() }}</strong></span>
                    <span>Sangrias <strong class="text-stone-700">{{ $totals['withdrawal']->format() }}</strong></span>
                    <span>Suprimentos <strong class="text-stone-700">{{ $totals['deposit']->format() }}</strong></span>
                    <span>Estornos <strong class="text-stone-700">{{ $totals['refund']->format() }}</strong></span>
                </div>
            </section>

            <form wire:submit="addMovement" class="card card-body space-y-3">
                <h2 class="section-title">Sangria ou suprimento</h2>
                <div class="grid grid-cols-2 gap-1 rounded-xl bg-stone-100 p-1 text-sm">
                    @foreach (['withdrawal' => 'Sangria', 'deposit' => 'Suprimento'] as $value => $label)
                        <label @class(['cursor-pointer rounded-lg px-2 py-2 text-center font-semibold transition', 'bg-white text-brand-700 shadow-sm' => $movementType === $value, 'text-stone-500 hover:text-stone-800' => $movementType !== $value])>
                            <input type="radio" wire:model.live="movementType" value="{{ $value }}" class="sr-only"> {{ $label }}
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-stone-500">{{ $movementType === 'withdrawal' ? 'Retirar dinheiro do caixa.' : 'Reforçar o troco do caixa.' }}</p>
                <input wire:model="movementAmount" type="text" inputmode="decimal" class="input" placeholder="Valor em dinheiro" aria-label="Valor">
                @error('movementAmount') <p class="field-error">{{ $message }}</p> @enderror
                @error('amount') <p class="field-error">{{ $message }}</p> @enderror
                <input wire:model="movementReason" type="text" class="input" placeholder="Motivo (ex.: depósito no banco)" aria-label="Motivo">
                @error('reason') <p class="field-error">{{ $message }}</p> @enderror
                <button type="submit" class="btn-secondary w-full">Registrar</button>
            </form>
        </div>

        {{-- Fechamento --}}
        <section class="card card-body">
            @if (! $closing)
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-stone-100 text-stone-600"><x-icon name="check" class="size-5" /></span>
                        <div>
                            <p class="font-semibold">Fechamento do caixa</p>
                            <p class="text-sm text-stone-500">Você informa quanto contou de cada forma de pagamento, e o sistema mostra a diferença.</p>
                        </div>
                    </div>
                    <button type="button" wire:click="startClosing" class="btn-primary">Fechar caixa</button>
                </div>
            @else
                <form wire:submit="close" class="space-y-4">
                    <h2 class="section-title">Fechar caixa: conferência</h2>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach ($counted as $code => $value)
                            <div wire:key="count-{{ $code }}" class="rounded-xl border border-stone-200 p-4">
                                <label class="label" for="count-{{ $code }}">{{ $methods[$code]->name }} contado</label>
                                <input wire:model="counted.{{ $code }}" id="count-{{ $code }}" type="text" inputmode="decimal" class="input" placeholder="0,00">
                                <p class="hint">Esperado: <strong>{{ ($expected[$code] ?? \App\Support\Money::zero())->format() }}</strong></p>
                                @error("counted.$code") <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                    <textarea wire:model="closingNotes" rows="2" class="input" placeholder="Observações (opcional)"></textarea>
                    @error('cash') <p class="field-error">{{ $message }}</p> @enderror
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('closing', false)" class="btn-secondary">Voltar</button>
                        <button type="submit" class="btn-primary" wire:confirm="Fechar o caixa? Depois de fechado, ele não recebe mais lançamentos.">Confirmar fechamento</button>
                    </div>
                </form>
            @endif
        </section>

        {{-- Movimentos --}}
        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="section-title">Movimentos</h2><span class="text-xs text-stone-500">{{ $movements->count() }} lançamento(s)</span></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Hora</th><th>Tipo</th><th>Forma</th><th>Origem</th><th class="text-right">Valor</th></tr></thead>
                    <tbody>
                        @forelse ($movements as $movement)
                            <tr wire:key="mv-{{ $movement->id }}">
                                <td class="text-stone-500 tabular-nums">{{ $movement->created_at->timezone($tz)->format('H:i') }}</td>
                                <td><span @class(['badge-red' => $movement->type->sign() < 0, 'badge-green' => $movement->type->sign() >= 0])>{{ $movement->type->label() }}</span></td>
                                <td>{{ $movement->method->name }}</td>
                                <td class="text-stone-600">
                                    @if ($movement->order) Pedido #{{ $movement->order->number }}
                                    @elseif ($movement->command) {{ $movement->command->displayName() }} (#{{ $movement->command->number }})
                                    @else {{ $movement->reason }} @endif
                                </td>
                                <td @class(['text-right font-semibold whitespace-nowrap tabular-nums', 'text-red-600' => $movement->type->sign() < 0])>{{ $movement->type->sign() < 0 ? '- ' : '' }}{{ $movement->amount_cents->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-ui.empty icon="cash" title="Nenhum movimento ainda" text="Recebimentos, sangrias e suprimentos aparecem aqui." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
