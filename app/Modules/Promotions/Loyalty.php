<?php

namespace App\Modules\Promotions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Promotions\Models\Coupon;
use App\Modules\Promotions\Models\LoyaltyReward;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cartão fidelidade: a cada N pedidos concluídos (cardápio online ou balcão, com telefone),
 * o cliente ganha um cupom pessoal. Conta só pedidos a partir do dia em que o programa foi ligado.
 */
class Loyalty
{
    public const SETTING = 'loyalty';

    public const DEFAULTS = [
        'enabled' => false,
        'goal' => 10,
        'min_cents' => 0,
        'reward_type' => 'fixed',
        'reward_percent' => 10,
        'reward_cents' => 2000,
        'valid_days' => 30,
        'started_at' => null,
    ];

    private const CODE_CHARS = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** @return array{enabled: bool, goal: int, min_cents: int, reward_type: string, reward_percent: int, reward_cents: int, valid_days: int, started_at: ?string} */
    public function config(): array
    {
        $saved = Setting::read(self::SETTING);

        return array_replace(self::DEFAULTS, is_array($saved) ? array_intersect_key($saved, self::DEFAULTS) : []);
    }

    public function enabled(): bool
    {
        return (bool) $this->config()['enabled'];
    }

    public function save(array $config): void
    {
        $current = $this->config();
        $config = array_replace($current, array_intersect_key($config, self::DEFAULTS));

        // Ligou pela primeira vez: conta a partir de agora (pedidos antigos não entram).
        if ($config['enabled'] && ! $current['started_at']) {
            $config['started_at'] = now()->toIso8601String();
        }

        Setting::write(self::SETTING, $config);
    }

    /** Cupom de exemplo com o prêmio configurado (para textos: "R$ 20,00 de desconto"). */
    public function rewardCoupon(?array $config = null): Coupon
    {
        $config ??= $this->config();

        return new Coupon([
            'type' => CouponType::from($config['reward_type']),
            'percent' => $config['reward_type'] === 'percent' ? (int) $config['reward_percent'] : null,
            'amount_cents' => $config['reward_type'] === 'fixed' ? Money::cents((int) $config['reward_cents']) : null,
        ]);
    }

    /** "R$ 20,00 de desconto", "10% de desconto", "entrega grátis". */
    public function rewardLabel(?array $config = null): string
    {
        return $this->rewardCoupon($config)->benefitInline();
    }

    /** "A cada 10 pedidos, ganhe R$ 20,00 de desconto" (+ " · pedidos a partir de R$ 30,00"). */
    public function headline(?array $config = null): string
    {
        $config ??= $this->config();
        $min = (int) $config['min_cents'];

        return "A cada {$config['goal']} pedidos, ganhe {$this->rewardLabel($config)}"
            .($min > 0 ? ' · vale pedido a partir de '.Money::cents($min)->format() : '');
    }

    /** Pedidos que contam para o cartão do cliente. */
    public function qualifying(int $customerId): Builder
    {
        $config = $this->config();

        return Order::query()
            ->where('customer_id', $customerId)
            ->where('status', OrderStatus::Completed->value)
            ->whereIn('source', [OrderSource::Online->value, OrderSource::Counter->value])
            ->when($config['started_at'], fn ($q, $start) => $q->where('created_at', '>=', CarbonImmutable::parse($start)))
            ->where('subtotal_cents', '>=', (int) $config['min_cents']);
    }

    /** Pedidos acumulados desde o último prêmio. */
    public function stamps(int $customerId): int
    {
        return max(0, $this->qualifying($customerId)->count() - (int) LoyaltyReward::where('customer_id', $customerId)->sum('stamps'));
    }

    /**
     * Situação do cliente: pedidos no cartão, meta e prêmios ainda não usados.
     *
     * @return array{stamps: int, goal: int, missing: int, rewards: Collection<int, Coupon>}|null
     */
    public function status(?Customer $customer): ?array
    {
        if (! $customer || ! $this->enabled()) {
            return null;
        }

        $goal = max(1, (int) $this->config()['goal']);
        $stamps = min($goal, $this->stamps($customer->id));

        return [
            'stamps' => $stamps,
            'goal' => $goal,
            'missing' => $goal - $stamps,
            'rewards' => $this->openRewards($customer),
        ];
    }

    public function statusForPhone(?string $phone): ?array
    {
        $phone = Customer::normalizePhone($phone);

        return $phone === '' ? null : $this->status(Customer::where('phone', $phone)->first());
    }

    /** @return Collection<int, Coupon> cupons de prêmio do cliente que ainda valem */
    public function openRewards(Customer $customer): Collection
    {
        return Coupon::where('customer_id', $customer->id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->whereDoesntHave('redemptions', fn ($q) => $q->whereHas('order', fn ($q) => $q->where('status', '!=', OrderStatus::Cancelled->value)))
            ->orderBy('ends_at')
            ->get();
    }

    /**
     * Pedido concluído: se completou a meta, gera o cupom pessoal. A linha do cliente fica
     * travada, então dois pedidos concluídos ao mesmo tempo não geram dois prêmios.
     */
    public function award(Order $order): ?LoyaltyReward
    {
        if (! $order->customer_id || $order->status !== OrderStatus::Completed || ! $this->enabled()) {
            return null;
        }

        if (! $this->qualifying($order->customer_id)->whereKey($order->id)->exists()) {
            return null;
        }

        return DB::connection('tenant')->transaction(function () use ($order) {
            $customer = Customer::query()->lockForUpdate()->find($order->customer_id);
            $config = $this->config();
            $goal = max(1, (int) $config['goal']);

            if (! $customer || $this->stamps($customer->id) < $goal) {
                return null;
            }

            $timezone = Setting::read('timezone', 'America/Sao_Paulo');
            $coupon = $this->rewardCoupon($config)->fill([
                'code' => $this->newCode(),
                'description' => 'Fidelidade · '.$customer->name,
                'usage_limit' => 1,
                'per_customer_limit' => 1,
                'ends_at' => CarbonImmutable::now($timezone)->addDays(max(1, (int) $config['valid_days']))->endOfDay()->utc(),
            ]);
            $coupon->customer_id = $customer->id;
            $coupon->save();

            return LoyaltyReward::create([
                'customer_id' => $customer->id,
                'coupon_id' => $coupon->id,
                'order_id' => $order->id,
                'stamps' => $goal,
            ]);
        });
    }

    /**
     * Números do programa desde que foi ligado.
     *
     * @return array{members: int, rewards: int, used: int, used_sales_cents: int}
     */
    public function stats(): array
    {
        $config = $this->config();
        $start = $config['started_at'] ? CarbonImmutable::parse($config['started_at']) : null;

        $members = $start ? Order::query()
            ->where('status', OrderStatus::Completed->value)
            ->whereIn('source', [OrderSource::Online->value, OrderSource::Counter->value])
            ->whereNotNull('customer_id')
            ->where('created_at', '>=', $start)
            ->distinct()->count('customer_id') : 0;

        $used = DB::connection('tenant')->table('loyalty_rewards')
            ->join('coupon_redemptions', 'coupon_redemptions.coupon_id', '=', 'loyalty_rewards.coupon_id')
            ->join('orders', 'orders.id', '=', 'coupon_redemptions.order_id')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('count(*) as used, coalesce(sum(orders.total_cents), 0) as sales')
            ->first();

        return [
            'members' => $members,
            'rewards' => LoyaltyReward::count(),
            'used' => (int) $used->used,
            'used_sales_cents' => (int) $used->sales,
        ];
    }

    private function newCode(): string
    {
        do {
            $code = 'FIEL-'.collect(range(1, 5))->map(fn () => self::CODE_CHARS[random_int(0, strlen(self::CODE_CHARS) - 1)])->implode('');
        } while (Coupon::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
