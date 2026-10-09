@php($tz = \App\Modules\Establishment\Models\Setting::read('timezone', 'America/Sao_Paulo'))
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Caixa</h1>
        <div class="flex gap-2">
            @can('cash.manage')
                <a href="{{ route('cash.sessions') }}" class="btn-secondary" wire:navigate>Todos os caixas</a>
            @endcan
            @can('pos.use')
                <a href="{{ route('orders.counter') }}" class="btn-primary" wire:navigate>Ir para o PDV</a>
            @endcan
        </div>
    </div>

    @if (! $session)
        {{-- Abertura --}}
        <form wire:submit="open" class="card card-body max-w-xl space-y-4">
            <h2 class="font-semibold">Abrir caixa</h2>
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
                    <input wire:model="opening" id="opening" type="text" inputmode="decimal" class="input">
                    @error('opening') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <button type="submit" class="btn-primary" @disabled(! $registers->contains(fn ($r) => $r->sessions->isEmpty()))>Abrir caixa</button>
        </form>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <section class="card card-body lg:col-span-2">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="font-semibold">{{ $session->register->name }} · aberto às {{ $session->opened_at->timezone($tz)->format('H:i') }}</h2>
                    <span class="text-sm text-stone-500">Fundo: {{ $session->opening_cents->format() }}</span>
                </div>
                <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                    @foreach ($expected as $code => $amount)
                        @if (! $amount->isZero() || $code === 'cash')
                            <div class="rounded-lg bg-stone-50 p-3">
                                <dt class="text-xs text-stone-500">{{ $methods[$code]->name }}</dt>
                                <dd class="text-lg font-semibold">{{ $amount->format() }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
                <p class="mt-3 text-xs text-stone-500">
                    Recebimentos {{ $totals['sale']->format() }} · Sangrias {{ $totals['withdrawal']->format() }} · Suprimentos {{ $totals['deposit']->format() }} · Estornos {{ $totals['refund']->format() }}
                </p>
            </section>

            <form wire:submit="addMovement" class="card card-body space-y-3">
                <h2 class="font-semibold">Sangria ou suprimento</h2>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    @foreach (['withdrawal' => 'Sangria (retirar)', 'deposit' => 'Suprimento (reforçar)'] as $value => $label)
                        <label @class(['cursor-pointer rounded-lg border px-2 py-1.5 text-center', 'border-brand-600 bg-brand-50 text-brand-700' => $movementType === $value, 'border-stone-300' => $movementType !== $value])>
                            <input type="radio" wire:model.live="movementType" value="{{ $value }}" class="sr-only"> {{ $label }}
                        </label>
                    @endforeach
                </div>
                <input wire:model="movementAmount" type="text" inputmode="decimal" class="input" placeholder="Valor em dinheiro" aria-label="Valor">
                @error('movementAmount') <p class="field-error">{{ $message }}</p> @enderror
                @error('amount') <p class="field-error">{{ $message }}</p> @enderror
                <input wire:model="movementReason" type="text" class="input" placeholder="Motivo (ex.: depósito no banco)" aria-label="Motivo">
                @error('reason') <p class="field-error">{{ $message }}</p> @enderror
                <button type="submit" class="btn-secondary">Registrar</button>
            </form>
        </div>

        {{-- Fechamento --}}
        <section class="card card-body">
            @if (! $closing)
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-stone-600">No fechamento você informa quanto contou de cada forma de pagamento, e o sistema mostra a diferença.</p>
                    <button type="button" wire:click="startClosing" class="btn-primary">Fechar caixa</button>
                </div>
            @else
                <form wire:submit="close" class="space-y-4">
                    <h2 class="font-semibold">Fechar caixa: conferência</h2>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach ($counted as $code => $value)
                            <div wire:key="count-{{ $code }}">
                                <label class="label" for="count-{{ $code }}">{{ $methods[$code]->name }} contado</label>
                                <input wire:model="counted.{{ $code }}" id="count-{{ $code }}" type="text" inputmode="decimal" class="input" placeholder="0,00">
                                <p class="hint">Esperado: {{ ($expected[$code] ?? \App\Support\Money::zero())->format() }}</p>
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
        <section class="card overflow-x-auto">
            <h2 class="px-5 pt-5 font-semibold sm:px-6">Movimentos</h2>
            <table class="table mt-3">
                <thead class="bg-stone-50"><tr><th>Hora</th><th>Tipo</th><th>Forma</th><th>Origem</th><th class="text-right">Valor</th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($movements as $movement)
                        <tr wire:key="mv-{{ $movement->id }}">
                            <td class="text-stone-500">{{ $movement->created_at->timezone($tz)->format('H:i') }}</td>
                            <td>{{ $movement->type->label() }}</td>
                            <td>{{ $movement->method->name }}</td>
                            <td class="text-stone-600">
                                @if ($movement->order) Pedido #{{ $movement->order->number }}
                                @elseif ($movement->command) {{ $movement->command->displayName() }} (#{{ $movement->command->number }})
                                @else {{ $movement->reason }} @endif
                            </td>
                            <td @class(['text-right font-medium whitespace-nowrap', 'text-red-700' => $movement->type->sign() < 0])>{{ $movement->type->sign() < 0 ? '- ' : '' }}{{ $movement->amount_cents->format() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-stone-500">Nenhum movimento ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endif
</div>
