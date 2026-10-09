@php
    $tz = \App\Modules\Establishment\Models\Setting::read('timezone', 'America/Sao_Paulo');
    $zero = \App\Support\Money::zero();
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('cash.current') }}" class="inline-flex items-center gap-1 text-sm font-medium text-stone-500 hover:text-stone-800" wire:navigate><x-icon name="chevron-left" class="size-4" /> Caixa</a>
        <button type="button" onclick="window.print()" class="btn-secondary"><x-icon name="printer" class="size-4" /> Imprimir</button>
    </div>

    <section class="card card-body">
        <h1 class="page-title">{{ $session->register->name }} · {{ $session->isOpen() ? 'aberto' : 'fechado' }}</h1>
        <p class="mt-1 text-sm text-stone-600">
            Operador: {{ $session->operator->name }} ·
            {{ $session->opened_at->timezone($tz)->format('d/m/Y H:i') }}
            @if ($session->closed_at) até {{ $session->closed_at->timezone($tz)->format('d/m/Y H:i') }} @endif
            @if ($session->closer && ! $session->closer->is($session->operator)) · fechado por {{ $session->closer->name }} @endif
        </p>
        @if ($session->notes)<p class="mt-2 text-sm">Obs.: {{ $session->notes }}</p>@endif

        <table class="table mt-4">
            <thead><tr><th>Forma de pagamento</th><th class="text-right">Esperado</th><th class="text-right">Contado</th><th class="text-right">Diferença</th></tr></thead>
            <tbody>
                @foreach ($methods as $method)
                    @php
                        $exp = $expected[$method->code] ?? $zero;
                        $cnt = $counted[$method->code] ?? null;
                        $diff = $cnt ? $cnt->subtract($exp) : null;
                    @endphp
                    @continue($exp->isZero() && (! $cnt || $cnt->isZero()))
                    <tr>
                        <td>{{ $method->name }}</td>
                        <td class="text-right">{{ $exp->format() }}</td>
                        <td class="text-right">{{ $cnt?->format() ?? '—' }}</td>
                        <td @class(['text-right font-medium', 'text-red-700' => $diff?->cents < 0, 'text-green-700' => $diff?->cents > 0])>{{ $diff?->format() ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            @if ($session->difference_cents)
                <tfoot><tr><td colspan="3" class="font-semibold">Diferença total (sobra + / falta −)</td><td class="text-right font-semibold">{{ $session->difference_cents->format() }}</td></tr></tfoot>
            @endif
        </table>

        <p class="mt-3 text-sm text-stone-600">
            Fundo {{ $session->opening_cents->format() }} · Recebimentos {{ $totals['sale']->format() }} · Sangrias {{ $totals['withdrawal']->format() }} · Suprimentos {{ $totals['deposit']->format() }} · Estornos {{ $totals['refund']->format() }}
        </p>
    </section>

    <section class="card overflow-x-auto">
        <div class="card-header"><h2 class="section-title">Movimentos ({{ $movements->count() }})</h2></div>
        <table class="table">
            <thead><tr><th>Hora</th><th>Tipo</th><th>Forma</th><th>Origem</th><th>Por</th><th class="text-right">Valor</th></tr></thead>
            <tbody>
                @foreach ($movements as $movement)
                    <tr>
                        <td class="text-stone-500">{{ $movement->created_at->timezone($tz)->format('H:i') }}</td>
                        <td>{{ $movement->type->label() }}</td>
                        <td>{{ $movement->method->name }}</td>
                        <td class="text-stone-600">
                            @if ($movement->order) Pedido #{{ $movement->order->number }}
                            @elseif ($movement->command) {{ $movement->command->displayName() }} (#{{ $movement->command->number }})
                            @else {{ $movement->reason }} @endif
                        </td>
                        <td class="text-stone-500">{{ $movement->user?->name }}</td>
                        <td @class(['text-right whitespace-nowrap', 'text-red-700' => $movement->type->sign() < 0])>{{ $movement->type->sign() < 0 ? '- ' : '' }}{{ $movement->amount_cents->format() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</div>
