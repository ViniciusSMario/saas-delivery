<x-layouts.site title="Fatura de teste">
    <section class="mx-auto max-w-md px-4 py-16 sm:px-6">
        <div class="alert-warning mb-6">
            <x-icon name="alert" class="mt-0.5 size-4" />
            <span>Ambiente de teste (BILLING_DRIVER=fake): nenhum dinheiro é cobrado. Com o Asaas, este link abre a fatura real com Pix, boleto e cartão.</span>
        </div>

        @if (session('status'))
            <div class="alert-success mb-6"><x-icon name="check" class="mt-0.5 size-4" /> {{ session('status') }}</div>
        @endif

        <div class="card overflow-hidden">
            <div class="border-b border-stone-100 bg-stone-50 p-6 text-center">
                <p class="text-sm text-stone-500">{{ $invoice->tenant->name }}</p>
                <p class="mt-1 text-3xl font-extrabold tracking-tight">{{ $invoice->amount()->format() }}</p>
                <p class="mt-1 text-sm text-stone-500">{{ $invoice->description }} · vence {{ $invoice->due_date->format('d/m/Y') }}</p>
                <span class="{{ $invoice->statusBadge() }} mt-3">{{ $invoice->statusLabel() }}</span>
            </div>
            @if (in_array($invoice->status, ['pending', 'overdue'], true))
                <div class="space-y-2 p-6">
                    <form method="POST" action="{{ \Illuminate\Support\Facades\URL::signedRoute('billing.fake.pay', ['charge' => $invoice->gateway_id]) }}">
                        @csrf
                        <button type="submit" class="btn-success btn-lg w-full"><x-icon name="check" class="size-5" /> Simular pagamento</button>
                    </form>
                    @if ($invoice->status === 'pending')
                        <form method="POST" action="{{ \Illuminate\Support\Facades\URL::signedRoute('billing.fake.pay', ['charge' => $invoice->gateway_id]) }}">
                            @csrf
                            <input type="hidden" name="status" value="overdue">
                            <button type="submit" class="btn-secondary w-full">Simular vencimento</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </section>
</x-layouts.site>
