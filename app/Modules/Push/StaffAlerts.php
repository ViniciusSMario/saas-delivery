<?php

namespace App\Modules\Push;

use App\Modules\Access\Models\User;
use App\Modules\Access\Permission;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Push\Jobs\SendPush;
use App\Modules\Push\Models\PushSubscription;
use App\Modules\Tables\Models\Command;
use Illuminate\Support\Str;

/**
 * Avisos no celular do garçom e do entregador (quem usa o "modo app"):
 * entrega pronta → entregadores; prato de mesa pronto → garçons; entrega mandada
 * pela gerência → aquele entregador. Só recebe quem ativou as notificações no celular.
 */
class StaffAlerts
{
    /** Quem tem avisos para receber hoje (as notificações da gerência e da cozinha vêm depois). */
    public static function receivesAlerts(User $user): bool
    {
        return $user->usesFieldMode()
            && ($user->hasPermission(Permission::TablesManage) || $user->hasPermission(Permission::DeliveriesOperate));
    }

    public function orderReady(Order $order): void
    {
        if ($order->status !== OrderStatus::Ready) {
            return;
        }

        if ($order->fulfillment === Fulfillment::Delivery) {
            $this->send($this->fieldUsersWith(Permission::DeliveriesOperate), [
                'title' => "Entrega pronta · #{$order->number}",
                'body' => $this->deliverySummary($order),
                'url' => '/entregas',
                'tag' => "entrega-{$order->id}",
            ]);
        }

        if ($order->fulfillment === Fulfillment::Table && $order->command_id) {
            $place = Command::with('diningTable')->find($order->command_id)?->displayName() ?? 'Mesa';

            $this->send($this->fieldUsersWith(Permission::TablesManage), [
                'title' => "{$place} · pedido pronto",
                'body' => Str::limit($order->items()->get()->map(fn ($item) => "{$item->quantity}× {$item->name}")->implode(', '), 120),
                'url' => '/mesas/prontos',
                'tag' => "mesa-pedido-{$order->id}",
            ]);
        }
    }

    public function courierAssigned(Order $order, User $courier): void
    {
        $this->send([$courier->id], [
            'title' => "Entrega para você · #{$order->number}",
            'body' => $this->deliverySummary($order),
            'url' => '/entregas',
            'tag' => "entrega-{$order->id}",
        ]);
    }

    public function test(User $user): void
    {
        $this->send([$user->id], [
            'title' => 'Notificações ativadas',
            'body' => 'É assim que você vai saber na hora quando um pedido ficar pronto.',
            'url' => '/',
            'tag' => 'teste',
        ]);
    }

    /** "Jardim América · Ana Souza · cobrar R$ 54,00 (Dinheiro)" */
    private function deliverySummary(Order $order): string
    {
        $payment = $order->payments()->with('method')->first();
        $charge = $payment && $payment->status === PaymentStatus::Pending
            ? 'cobrar '.$order->total_cents->format().($payment->method ? " ({$payment->method->name})" : '')
            : 'pago';

        return collect([$order->address_district, $order->customer_name, $charge])->filter()->implode(' · ');
    }

    /** @return list<int> usuários do modo app com a permissão e com pelo menos um celular inscrito */
    private function fieldUsersWith(Permission $permission): array
    {
        return User::with('roles')
            ->where('is_active', true)
            ->whereIn('id', PushSubscription::query()->select('user_id'))
            ->get()
            ->filter(fn (User $user) => $user->usesFieldMode() && $user->hasPermission($permission))
            ->pluck('id')
            ->values()
            ->all();
    }

    /** @param  list<int>  $userIds */
    private function send(array $userIds, array $payload): void
    {
        if ($userIds) {
            SendPush::dispatch($userIds, $payload);
        }
    }
}
