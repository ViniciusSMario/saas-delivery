<?php

namespace App\Http\Controllers\Central;

use App\Platform\Billing\Charge;
use App\Platform\Billing\FakeGateway;
use App\Platform\Billing\Subscriptions;
use App\Platform\Billing\WebhookEvent;
use App\Platform\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/** Webhook do gateway de cobrança e a fatura de teste do gateway "fake". */
class BillingController
{
    public function webhook(Request $request, string $gateway, Subscriptions $subscriptions): JsonResponse
    {
        abort_unless($gateway === $subscriptions->gateway()->name(), 404);

        try {
            $event = $subscriptions->gateway()->parseWebhook($request);
        } catch (Throwable $e) {
            Log::warning('Webhook de cobrança recusado', ['gateway' => $gateway, 'erro' => $e->getMessage()]);

            return response()->json(['ok' => false], 401);
        }

        if ($event) {
            $subscriptions->handle($event);
        }

        return response()->json(['ok' => true]);
    }

    /** Fatura do gateway de teste (link assinado; só existe com BILLING_DRIVER=fake). */
    public function fakeInvoice(string $charge, Subscriptions $subscriptions): View
    {
        abort_unless($subscriptions->gateway() instanceof FakeGateway, 404);

        return view('central.fake-invoice', ['invoice' => Invoice::where('gateway_id', $charge)->with('tenant')->firstOrFail()]);
    }

    public function fakePay(Request $request, string $charge, Subscriptions $subscriptions): RedirectResponse
    {
        abort_unless($subscriptions->gateway() instanceof FakeGateway, 404);
        $invoice = Invoice::where('gateway_id', $charge)->with('tenant')->firstOrFail();
        $status = $request->input('status') === 'overdue' ? 'overdue' : 'paid';

        // Mesmo caminho de um webhook de verdade.
        $subscriptions->handle(new WebhookEvent(
            id: 'fake_evt_'.Str::lower(Str::random(16)),
            type: WebhookEvent::CHARGE_UPDATED,
            charge: new Charge($invoice->gateway_id, (int) $invoice->amount_cents, CarbonImmutable::parse($invoice->due_date), $status),
        ));

        return back()->with('status', $status === 'paid' ? 'Pagamento simulado: fatura paga.' : 'Vencimento simulado.');
    }
}
