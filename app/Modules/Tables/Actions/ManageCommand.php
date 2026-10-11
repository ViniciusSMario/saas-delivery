<?php

namespace App\Modules\Tables\Actions;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Permission;
use App\Modules\Cash\CashLedger;
use App\Modules\Cash\MovementType;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PaymentMethod;
use App\Modules\Tables\Models\Command;
use App\Modules\Tables\CommandStatus;
use App\Modules\Tables\Models\DiningTable;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo da comanda: abrir, lançar itens (vão para a cozinha como pedidos),
 * pedir a conta, transferir/juntar mesas e fechar recebendo no caixa.
 * Operações que mexem em saldo travam a comanda (SELECT ... FOR UPDATE).
 */
class ManageCommand
{
    public function __construct(
        private readonly PlaceOrder $placeOrder,
        private readonly CashLedger $ledger,
    ) {
    }

    /** Sem $actor: aberta pelo cliente no pedido pelo QR code da mesa. */
    public function open(?DiningTable $table, ?string $label, int $people, ?User $actor): Command
    {
        $label = trim((string) $label) ?: null;

        if (! $table && ! $label) {
            throw ValidationException::withMessages(['label' => 'Informe a mesa ou um nome/número para a comanda.']);
        }

        if ($table && ! $table->is_active) {
            throw ValidationException::withMessages(['table' => 'Mesa inativa.']);
        }

        try {
            $command = Command::create([
                'table_id' => $table?->id,
                'label' => $label,
                'status' => CommandStatus::Open,
                'people' => max(1, min(99, $people)),
                'service_fee_enabled' => (bool) Setting::read('service_fee_enabled', true),
                'opened_by' => $actor?->id,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                throw ValidationException::withMessages(['table' => "A mesa {$table?->name} já tem uma comanda aberta."]);
            }

            throw $e;
        }

        return $command->refresh();
    }

    /**
     * Lança itens na comanda: vira um pedido de mesa, que aparece no painel da cozinha.
     *
     * @param  list<array{type: string, id: int, quantity: int, addons?: list<int>, notes?: string|null}>  $lines
     */
    public function addItems(Command $command, array $lines, User $actor, ?string $notes = null): Order
    {
        $this->assertOpen($command);

        return $this->placeOrder->handle([
            'lines' => $lines,
            'fulfillment' => Fulfillment::Table->value,
            'command_id' => $command->id,
            'customer' => ['name' => $command->displayName()],
            'notes' => $notes,
        ], OrderSource::Table, $actor);
    }

    public function requestBill(Command $command, ?User $actor): void
    {
        $this->assertOpen($command);
        $command->update(['status' => CommandStatus::Closing]);
    }

    public function reopen(Command $command): void
    {
        if ($command->status !== CommandStatus::Closing) {
            throw ValidationException::withMessages(['command' => 'Só uma conta pedida pode ser reaberta.']);
        }

        $command->update(['status' => CommandStatus::Open]);
    }

    public function transfer(Command $command, DiningTable $to, User $actor): void
    {
        $this->assertOpen($command);

        if (! $to->is_active || $to->openCommand()->exists()) {
            throw ValidationException::withMessages(['table' => "A mesa {$to->name} não está livre."]);
        }

        $from = $command->displayName();
        $command->update(['table_id' => $to->id]);
        Audit::record('command.transferred', $command, ['de' => $from, 'para' => 'Mesa '.$to->name], $actor);
    }

    /** Junta outra comanda nesta: os pedidos dela passam para cá e ela é encerrada. */
    public function merge(Command $target, Command $source, User $actor): void
    {
        if ($target->is($source)) {
            throw ValidationException::withMessages(['command' => 'Escolha outra comanda.']);
        }

        DB::connection('tenant')->transaction(function () use ($target, $source, $actor) {
            [$t, $s] = $this->lockBoth($target, $source);
            $this->assertOpen($t);
            $this->assertOpen($s);

            Order::where('command_id', $s->id)->update(['command_id' => $t->id]);
            $t->update(['people' => min(99, $t->people + $s->people)]);
            $s->update(['status' => CommandStatus::Cancelled, 'cancel_reason' => "Juntada à comanda #{$t->number}", 'closed_at' => now()]);

            Audit::record('command.merged', $t, ['juntou' => $s->displayName()], $actor);
        });
    }

    public function cancel(Command $command, User $actor, string $reason): void
    {
        $this->assertOpen($command);

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Informe o motivo.']);
        }

        if ($command->orders()->where('status', '!=', OrderStatus::Cancelled->value)->exists()) {
            throw ValidationException::withMessages(['command' => 'Cancele os itens lançados antes de cancelar a comanda.']);
        }

        $command->update(['status' => CommandStatus::Cancelled, 'cancel_reason' => trim($reason), 'closed_at' => now()]);
        Audit::record('command.cancelled', $command, ['motivo' => trim($reason)], $actor);
    }

    /**
     * Valores da conta. Tirar a taxa de serviço ou dar desconto exige orders.discount.
     *
     * @return array{subtotal: Money, service_fee: Money, discount: Money, total: Money}
     */
    public function totals(Command $command, ?bool $serviceFee = null, ?Money $discount = null): array
    {
        $subtotal = $command->itemsSubtotal();
        $fee = Command::serviceFeeFor($subtotal, $serviceFee ?? $command->service_fee_enabled);
        $discount ??= Money::zero();

        return [
            'subtotal' => $subtotal,
            'service_fee' => $fee,
            'discount' => $discount,
            'total' => $subtotal->add($fee)->subtract($discount),
        ];
    }

    /**
     * Fecha a conta recebendo no caixa do operador. Aceita várias formas de pagamento;
     * só dinheiro pode passar do total (a diferença é o troco).
     *
     * @param  list<array{method: string, amount: Money}>  $payments
     * @return array{change: Money}
     */
    public function close(Command $command, User $actor, array $payments, bool $serviceFee, ?Money $discount = null): array
    {
        $session = $this->ledger->requireSession($actor);
        $discount ??= Money::zero();

        $completed = [];

        $result = DB::connection('tenant')->transaction(function () use ($command, $actor, $payments, $serviceFee, $discount, $session, &$completed) {
            $locked = Command::lockForUpdate()->findOrFail($command->id);
            $this->assertOpen($locked);

            if ($locked->orders()->where('status', '!=', OrderStatus::Cancelled->value)->doesntExist()) {
                throw ValidationException::withMessages(['payments' => 'A comanda não tem itens. Cancele-a em vez de fechar.']);
            }

            $removingFee = $locked->service_fee_enabled && ! $serviceFee;

            if (($removingFee || ! $discount->isZero()) && ! $actor->hasPermission(Permission::OrdersDiscount)) {
                throw ValidationException::withMessages(['discount' => 'Você não tem permissão para tirar a taxa de serviço ou dar desconto.']);
            }

            $totals = $this->totals($locked, $serviceFee, $discount);

            if ($discount->cents < 0 || $discount->greaterThan($totals['subtotal']->add($totals['service_fee']))) {
                throw ValidationException::withMessages(['discount' => 'Desconto inválido.']);
            }

            [$lines, $change] = $this->splitPayments($payments, $totals['total']);

            foreach ($lines as [$method, $amount]) {
                $this->ledger->move($session, MovementType::Sale, $method, $amount, $actor, ['command_id' => $locked->id]);
            }

            $locked->update([
                'status' => CommandStatus::Closed,
                'service_fee_enabled' => $serviceFee,
                'subtotal_cents' => $totals['subtotal'],
                'service_fee_cents' => $totals['service_fee'],
                'discount_cents' => $totals['discount'],
                'total_cents' => $totals['total'],
                'closed_by' => $actor->id,
                'closed_at' => now(),
            ]);

            // Itens que ainda estavam na cozinha saem do painel.
            foreach ($locked->orders()->whereIn('status', array_map(fn ($s) => $s->value, OrderStatus::active()))->get() as $order) {
                $from = $order->status;
                $order->update(['status' => OrderStatus::Completed, 'completed_at' => now()]);
                $order->history()->create(['from_status' => $from, 'to_status' => OrderStatus::Completed, 'user_id' => $actor->id, 'note' => 'Conta fechada']);
                $completed[] = $order;
            }

            Audit::record('command.closed', $locked, [
                'total' => $totals['total']->format(),
                'taxa_servico' => $totals['service_fee']->format(),
                'desconto' => $totals['discount']->format(),
            ], $actor);

            return ['change' => $change];
        });

        // Depois do commit: quem escuta o fluxo do pedido (ex.: baixa de estoque) é avisado.
        foreach ($completed as $order) {
            event(new OrderStatusChanged($order));
        }

        return $result;
    }

    /**
     * @param  list<array{method: string, amount: Money}>  $payments
     * @return array{0: list<array{0: PaymentMethod, 1: Money}>, 1: Money}
     */
    private function splitPayments(array $payments, Money $total): array
    {
        $methods = PaymentMethod::active()->get()->keyBy('code');
        $lines = [];
        $paid = Money::zero();
        $nonCash = Money::zero();

        foreach ($payments as $payment) {
            $method = $methods->get($payment['method'] ?? '');
            $amount = $payment['amount'] ?? Money::zero();

            if (! $method || $amount->cents <= 0) {
                throw ValidationException::withMessages(['payments' => 'Confira as formas de pagamento e os valores.']);
            }

            $lines[] = [$method, $amount];
            $paid = $paid->add($amount);
            $nonCash = $method->accepts_change ? $nonCash : $nonCash->add($amount);
        }

        if ($total->greaterThan($paid)) {
            throw ValidationException::withMessages(['payments' => 'Faltam '.$total->subtract($paid)->format().' para fechar a conta.']);
        }

        if ($nonCash->greaterThan($total)) {
            throw ValidationException::withMessages(['payments' => 'Só pagamento em dinheiro pode passar do total (troco).']);
        }

        // O troco sai do dinheiro: no caixa entra só o valor da conta.
        $change = $paid->subtract($total);
        $remaining = $change;

        foreach ($lines as $i => [$method, $amount]) {
            if ($remaining->isZero() || ! $method->accepts_change) {
                continue;
            }

            $deduct = $remaining->greaterThan($amount) ? $amount : $remaining;
            $lines[$i][1] = $amount->subtract($deduct);
            $remaining = $remaining->subtract($deduct);
        }

        return [array_values(array_filter($lines, fn ($l) => $l[1]->cents > 0)), $change];
    }

    private function assertOpen(Command $command): void
    {
        if (! $command->status->isOpen()) {
            throw ValidationException::withMessages(['command' => "A comanda #{$command->number} já está {$command->status->label()}."]);
        }
    }

    /** Trava as duas comandas sempre na mesma ordem (evita deadlock). */
    private function lockBoth(Command $a, Command $b): array
    {
        $locked = Command::lockForUpdate()->whereKey([$a->id, $b->id])->orderBy('id')->get()->keyBy('id');

        return [$locked[$a->id], $locked[$b->id]];
    }
}
