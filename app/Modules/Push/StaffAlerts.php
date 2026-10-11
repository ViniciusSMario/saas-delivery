<?php

namespace App\Modules\Push;

use App\Modules\Access\Models\User;
use App\Modules\Access\Permission;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReview;
use App\Modules\Push\Jobs\SendPush;
use App\Modules\Push\Jobs\WarnWaitingOrder;
use App\Modules\Push\Models\PushSubscription;
use App\Modules\Tables\Models\Command;
use Illuminate\Support\Str;

/**
 * Avisos no celular da equipe. Só recebe quem ativou as notificações no aparelho.
 * - Modo app: entrega pronta → entregadores; prato de mesa pronto → garçons;
 *   entrega mandada pela gerência → aquele entregador.
 * - Painel: pedido novo do cardápio online → quem atende pedidos (cozinha, caixa, gerência);
 *   pedido ainda sem aceite depois de X minutos → gerência. Cada um escolhe em Minha conta.
 */
class StaffAlerts
{
    public const NEW_ORDER = 'new_order';

    public const WAITING_ORDER = 'waiting_order';

    public const BAD_REVIEW = 'bad_review';

    /** Nota que gera aviso para a gerência (1 ou 2 estrelas). */
    public const BAD_REVIEW_MAX = 2;

    /** Minutos sem aceite até avisar a gerência (0 = não avisa). */
    public const WAITING_MINUTES_SETTING = 'order_waiting_alert_minutes';

    public const WAITING_MINUTES_DEFAULT = 5;

    public static function receivesAlerts(User $user): bool
    {
        if ($user->usesFieldMode()) {
            return $user->hasPermission(Permission::TablesManage) || $user->hasPermission(Permission::DeliveriesOperate);
        }

        return $user->hasPermission(Permission::OrdersManage);
    }

    /** @return array<string, string> avisos que a pessoa pode ligar/desligar (tipo => texto) */
    public static function choicesFor(User $user): array
    {
        if ($user->usesFieldMode() || ! $user->hasPermission(Permission::OrdersManage)) {
            return [];
        }

        $minutes = self::waitingMinutes();

        return array_filter([
            self::NEW_ORDER => 'Pedido novo do cardápio online',
            self::WAITING_ORDER => $minutes && $user->hasPermission(Permission::OrdersCancel)
                ? "Pedido sem aceite depois de {$minutes} min"
                : null,
            self::BAD_REVIEW => $user->hasPermission(Permission::OrdersCancel) ? 'Avaliação ruim (1 ou 2 estrelas)' : null,
        ]);
    }

    public static function wants(User $user, string $type): bool
    {
        return ($user->alert_preferences[$type] ?? true) !== false;
    }

    public static function waitingMinutes(): int
    {
        return max(0, (int) Setting::read(self::WAITING_MINUTES_SETTING, self::WAITING_MINUTES_DEFAULT));
    }

    /** Pedido do cardápio online: avisa quem atende e agenda a cobrança se ninguém aceitar. */
    public function orderPlaced(Order $order): void
    {
        if ($order->source !== OrderSource::Online || $order->status !== OrderStatus::Received) {
            return;
        }

        $this->send($this->panelUsers(Permission::OrdersManage, self::NEW_ORDER), [
            'title' => "Pedido novo · #{$order->number} · {$order->total_cents->format()}",
            'body' => $this->orderSummary($order),
            'url' => "/pedidos/{$order->id}",
            'tag' => "pedido-{$order->id}",
            'sticky' => true,
        ]);

        if ($minutes = self::waitingMinutes()) {
            WarnWaitingOrder::dispatch($order->id)->delay(now()->addMinutes($minutes));
        }
    }

    /** Ninguém aceitou o pedido online: a gerência fica sabendo (substitui o aviso de pedido novo). */
    public function orderWaiting(Order $order): void
    {
        if ($order->source !== OrderSource::Online || $order->status !== OrderStatus::Received) {
            return;
        }

        // Confere o tempo de novo: a fila pode rodar antes (ou o prazo ter mudado nas configurações).
        $minutes = (int) abs($order->created_at->diffInMinutes(now()));
        $limit = self::waitingMinutes();

        if (! $limit || $minutes < $limit) {
            return;
        }

        $this->send($this->panelUsers(Permission::OrdersCancel, self::WAITING_ORDER), [
            'title' => "Pedido #{$order->number} esperando há {$minutes} min",
            'body' => 'Ninguém aceitou ainda · '.$this->orderSummary($order),
            'url' => "/pedidos/{$order->id}",
            'tag' => "pedido-{$order->id}",
            'sticky' => true,
        ]);
    }

    /** Cliente deu 1 ou 2 estrelas: a gerência fica sabendo na hora, para resolver enquanto dá tempo. */
    public function badReview(OrderReview $review): void
    {
        if ($review->rating > self::BAD_REVIEW_MAX) {
            return;
        }

        $order = $review->order;

        $this->send($this->panelUsers(Permission::OrdersCancel, self::BAD_REVIEW), [
            'title' => "Avaliação ruim · #{$order->number} · {$review->stars()}",
            'body' => Str::limit(collect([$order->customer_name, implode(', ', $review->tagLabels()), $review->comment ? "“{$review->comment}”" : null])->filter()->implode(' · '), 160),
            'url' => "/pedidos/{$order->id}",
            'tag' => "avaliacao-{$review->id}",
        ]);
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
            'body' => $user->usesFieldMode()
                ? 'É assim que você vai saber na hora quando um pedido ficar pronto.'
                : 'É assim que você vai saber na hora quando chegar um pedido novo.',
            'url' => '/',
            'tag' => 'teste',
        ]);
    }

    /** "Entrega · Centro · Rita Moura · 3 itens" */
    private function orderSummary(Order $order): string
    {
        $items = (int) $order->items()->sum('quantity');

        return collect([
            $order->fulfillment->label(),
            $order->fulfillment === Fulfillment::Delivery ? $order->address_district : null,
            $order->customer_name,
            $items === 1 ? '1 item' : "{$items} itens",
        ])->filter()->implode(' · ');
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

    /** @return list<int> usuários do modo app com a permissão */
    private function fieldUsersWith(Permission $permission): array
    {
        return $this->subscribedUsers(fn (User $user) => $user->usesFieldMode() && $user->hasPermission($permission));
    }

    /** @return list<int> usuários do painel completo com a permissão e com o aviso ligado */
    private function panelUsers(Permission $permission, string $type): array
    {
        return $this->subscribedUsers(fn (User $user) => ! $user->usesFieldMode()
            && $user->hasPermission($permission)
            && self::wants($user, $type));
    }

    /** @return list<int> usuários ativos, com pelo menos um aparelho inscrito, que passam no filtro */
    private function subscribedUsers(callable $filter): array
    {
        return User::with('roles')
            ->where('is_active', true)
            ->whereIn('id', PushSubscription::query()->select('user_id'))
            ->get()
            ->filter($filter)
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
