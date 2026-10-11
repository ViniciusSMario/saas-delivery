<?php

namespace App\Modules\Printing;

use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Printing\Jobs\SendToNetworkPrinter;
use App\Modules\Printing\Models\PrintJob;
use App\Modules\Printing\Models\PrintStation;
use App\Modules\Tables\Models\Command;
use Illuminate\Support\Collection;

/**
 * Separa os itens de um pedido pelos setores de produção (categoria → setor) e põe
 * um cupom na fila de cada impressora. Setor marcado como "pedido completo" (expedição,
 * caixa) recebe o pedido inteiro, com valores.
 */
class OrderTickets
{
    /** @return Collection<int, PrintJob> */
    public function queue(Order $order, bool $reprint = false): Collection
    {
        $stations = PrintStation::active()->orderBy('id')->get()->keyBy('id');

        if ($stations->isEmpty()) {
            return collect();
        }

        $default = $stations->firstWhere('is_default', true) ?? $stations->first();
        $order->loadMissing(['items.addons', 'items.product.category', 'items.combo.items.product.category']);

        // setor => [ [item, componentes do combo que são deste setor] ]
        $routed = [];

        foreach ($order->items as $item) {
            if ($item->combo) {
                $byStation = $item->combo->items->groupBy(fn ($component) => $this->stationFor($component->product?->category?->print_station_id, $stations, $default));

                foreach ($byStation as $stationId => $components) {
                    $routed[$stationId][] = [$item, $components];
                }

                continue;
            }

            $routed[$this->stationFor($item->product?->category?->print_station_id, $stations, $default)][] = [$item, null];
        }

        $jobs = collect();

        foreach ($stations as $station) {
            if ($station->prints_full_order) {
                $jobs->push($this->store($station, $order, 'receipt', $this->receipt($order, $station, $reprint)));
            } elseif (! empty($routed[$station->id])) {
                $jobs->push($this->store($station, $order, 'production', $this->production($order, $station, $routed[$station->id], $reprint)));
            }
        }

        return $jobs;
    }

    public function test(PrintStation $station): PrintJob
    {
        $ticket = (new Ticket($station->columns))
            ->big('TESTE')
            ->line($station->name, 'center')
            ->line(now()->timezone($this->timezone())->format('d/m/Y H:i'), 'center')
            ->rule()
            ->line('Acentos: ação, pão, café, maçã')
            ->pair('Item', 'R$ 0,00')
            ->rule()
            ->line('Se você está lendo isto, a impressora funciona.', 'center');

        return $this->store($station, null, 'test', $ticket, 'Teste de impressão');
    }

    private function stationFor(?int $stationId, Collection $stations, PrintStation $default): int
    {
        return $stationId && $stations->has($stationId) ? $stationId : $default->id;
    }

    /** @param  list<array{0: OrderItem, 1: Collection|null}>  $entries */
    private function production(Order $order, PrintStation $station, array $entries, bool $reprint): Ticket
    {
        $ticket = $this->header($order, $station, $reprint);

        foreach ($entries as [$item, $components]) {
            $ticket->big("{$item->quantity}x {$item->name}", 'left');

            foreach ($components ?? [] as $component) {
                $ticket->bold('  - '.($component->quantity * $item->quantity).'x '.$component->product?->name);
            }

            foreach ($item->addons as $addon) {
                $ticket->line('  + '.$addon->name);
            }

            if ($item->notes) {
                $ticket->bold('  OBS: '.$item->notes);
            }
        }

        return $this->footer($ticket, $order);
    }

    private function receipt(Order $order, PrintStation $station, bool $reprint): Ticket
    {
        $ticket = $this->header($order, $station, $reprint);

        foreach ($order->items as $item) {
            $ticket->pair("{$item->quantity}x {$item->name}", $item->total_cents->format(), 'bold');

            if ($item->description) {
                $ticket->line('  '.$item->description);
            }

            foreach ($item->addons as $addon) {
                $ticket->line('  + '.$addon->name);
            }

            if ($item->notes) {
                $ticket->line('  OBS: '.$item->notes);
            }
        }

        $ticket->rule()->pair('Subtotal', $order->subtotal_cents->format());

        if (! $order->discount_cents->isZero()) {
            $ticket->pair($order->coupon_code ? 'Cupom '.$order->coupon_code : 'Desconto', '- '.$order->discount_cents->format());
        }

        if ($order->fulfillment === Fulfillment::Delivery) {
            $ticket->pair('Entrega', $order->delivery_fee_cents->format());
        }

        $ticket->pair('TOTAL', $order->total_cents->format(), 'bold');

        $order->loadMissing('payments.method');

        foreach ($order->payments as $payment) {
            $ticket->line('Pagamento: '.$payment->method?->name.' ('.$payment->status->label().')');

            if ($payment->change()) {
                $ticket->bold('Troco para '.$payment->change_for_cents->format().': levar '.$payment->change()->format());
            }
        }

        if ($order->fulfillment === Fulfillment::Delivery) {
            $ticket->rule()->bold('ENTREGA')->line($order->address());

            if ($order->address_reference) {
                $ticket->line('Ref.: '.$order->address_reference);
            }
        }

        return $this->footer($ticket, $order);
    }

    private function header(Order $order, PrintStation $station, bool $reprint): Ticket
    {
        $ticket = new Ticket($station->columns);

        if ($reprint) {
            $ticket->bold('*** REIMPRESSÃO ***', 'center');
        }

        $where = $order->command_id ? Command::with('diningTable')->find($order->command_id)?->displayName() : null;

        $ticket->line(Establishment::current()->trade_name ?: '', 'center')
            ->bold(mb_strtoupper($station->name), 'center')
            ->big($where ? mb_strtoupper($where) : 'PEDIDO #'.$order->number)
            ->line(($where ? 'Pedido #'.$order->number.' · ' : '').$order->fulfillment->label().' · '.$order->created_at->timezone($this->timezone())->format('d/m H:i'), 'center');

        if ($order->customer_name) {
            $ticket->line('Cliente: '.$order->customer_name.($order->customer_phone ? ' · '.$order->customer_phone : ''));
        }

        return $ticket->rule();
    }

    private function footer(Ticket $ticket, Order $order): Ticket
    {
        if ($order->notes) {
            $ticket->rule()->bold('OBS DO PEDIDO: '.$order->notes);
        }

        return $ticket->rule('=');
    }

    private function store(PrintStation $station, ?Order $order, string $kind, Ticket $ticket, ?string $title = null): PrintJob
    {
        $job = PrintJob::create([
            'print_station_id' => $station->id,
            'order_id' => $order?->id,
            'kind' => $kind,
            'title' => $title ?? ($order ? 'Pedido #'.$order->number : $station->name),
            'lines' => $ticket->lines(),
        ]);

        if ($station->isNetwork() && config('printing.network_enabled')) {
            SendToNetworkPrinter::dispatch($job->id);
        }

        return $job;
    }

    private function timezone(): string
    {
        return Setting::read('timezone', 'America/Sao_Paulo');
    }
}
