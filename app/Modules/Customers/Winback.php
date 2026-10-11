<?php

namespace App\Modules\Customers;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerWinback;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Promotions\Models\Coupon;
use App\Tenancy\TenantUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reativar clientes sumidos: quem pedia e parou, o que mais pedia e a mensagem de WhatsApp
 * pronta (com cupom). O envio é pelo WhatsApp do próprio restaurante (link wa.me).
 */
class Winback
{
    public const MESSAGE_SETTING = 'winback_message';

    public const COUPON_SETTING = 'winback_coupon_id';

    /** Opções de "sem pedir há pelo menos". */
    public const DAYS_OPTIONS = [30, 45, 60, 90];

    /** Quem foi chamado há menos que isso sai da lista (não insistir). */
    public const RECONTACT_DAYS = 15;

    /** Janela para contar quem voltou depois de chamado. */
    public const RESULT_DAYS = 30;

    public const DEFAULT_MESSAGE = "Oi, {nome}! Faz tempo que você não pede com a gente e sentimos sua falta! "
        ."Para matar a saudade, use o cupom {cupom} e ganhe {desconto} no próximo pedido. Peça por aqui: {link}\n\n{restaurante}";

    /**
     * Clientes com pelo menos $minOrders pedidos (não cancelados) e nenhum nos últimos $days dias,
     * com telefone válido e sem pedido para não ser chamado.
     */
    public function query(int $days, int $minOrders, bool $hideContacted = true): Builder
    {
        $valid = fn ($q) => $q->where('status', '!=', OrderStatus::Cancelled->value)->whereNotNull('customer_id');

        $stats = DB::connection('tenant')->table('orders')->where($valid)
            ->selectRaw('customer_id, count(*) as orders_count, sum(total_cents) as spent_cents, max(created_at) as last_at')
            ->groupBy('customer_id');

        $contacts = DB::connection('tenant')->table('customer_winbacks')
            ->selectRaw('customer_id, max(created_at) as contacted_at, count(*) as contacts_count')
            ->groupBy('customer_id');

        return Customer::query()
            ->joinSub($stats, 'stats', 'stats.customer_id', '=', 'customers.id')
            ->leftJoinSub($contacts, 'contacts', 'contacts.customer_id', '=', 'customers.id')
            ->select('customers.*', 'stats.orders_count', 'stats.spent_cents', 'stats.last_at', 'contacts.contacted_at', 'contacts.contacts_count')
            ->whereNull('customers.winback_opt_out_at')
            ->whereRaw("customers.phone ~ '^[1-9]{2}9?[0-9]{8}$'")
            ->where('stats.orders_count', '>=', max(1, $minOrders))
            ->where('stats.last_at', '<', now()->subDays($days))
            ->when($hideContacted, fn ($q) => $q->where(fn ($q) => $q
                ->whereNull('contacts.contacted_at')
                ->orWhere('contacts.contacted_at', '<', now()->subDays(self::RECONTACT_DAYS))));
    }

    /**
     * Produto mais pedido de cada cliente (por unidades, pedidos não cancelados).
     *
     * @param  list<int>  $customerIds
     * @return array<int, string>
     */
    public function favorites(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $ranked = DB::connection('tenant')->table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.customer_id', $customerIds)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->groupBy('orders.customer_id', 'order_items.name')
            ->selectRaw('orders.customer_id, order_items.name, row_number() over (partition by orders.customer_id order by sum(order_items.quantity) desc, max(orders.created_at) desc) as position');

        return DB::connection('tenant')->query()->fromSub($ranked, 'ranked')->where('position', 1)
            ->pluck('name', 'customer_id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])
            ->all();
    }

    /** Mensagem pronta para um cliente; null quando falta o cupom que a mensagem cita. */
    public function message(string $template, Customer $customer, ?Coupon $coupon, ?string $favorite = null): ?string
    {
        if (! $coupon && preg_match('/\{(cupom|desconto|validade)\}/', $template)) {
            return null;
        }

        $timezone = Setting::read('timezone', 'America/Sao_Paulo');
        $establishment = Establishment::current();

        return trim(strtr($template, [
            '{nome}' => self::firstName($customer->name),
            '{restaurante}' => $establishment->trade_name ?: tenant()->name,
            '{cupom}' => $coupon?->code ?? '',
            '{desconto}' => $coupon?->benefitInline() ?? '',
            '{validade}' => $coupon?->ends_at ? $coupon->ends_at->timezone($timezone)->format('d/m') : 'por tempo limitado',
            '{produto}' => $favorite ?? 'seu pedido de sempre',
            '{link}' => $this->menuLink($coupon),
        ]));
    }

    /** Link wa.me com a mensagem pronta. */
    public function link(Customer $customer, string $message): string
    {
        return 'https://wa.me/55'.Customer::normalizePhone($customer->phone).'?text='.rawurlencode($message);
    }

    /** Cardápio com o cupom já guardado (o checkout aplica sozinho). */
    public function menuLink(?Coupon $coupon): string
    {
        return TenantUrl::to(tenant(), 'cardapio'.($coupon ? '?cupom='.$coupon->code : ''));
    }

    public function record(Customer $customer, ?Coupon $coupon, ?User $user): CustomerWinback
    {
        return CustomerWinback::create([
            'customer_id' => $customer->id,
            'user_id' => $user?->id,
            'coupon_code' => $coupon?->code,
        ]);
    }

    public function optOut(Customer $customer, ?User $user): void
    {
        $customer->forceFill(['winback_opt_out_at' => now()])->save();
        Audit::record('customer.winback_opt_out', $customer, ['cliente' => $customer->name], $user);
    }

    /**
     * Resultado dos contatos dos últimos RESULT_DAYS dias: quantos foram chamados, quantos
     * voltaram a pedir depois do contato e quanto esses pedidos somaram.
     *
     * @return array{contacted: int, returned: int, revenue_cents: int}
     */
    public function results(): array
    {
        $since = now()->subDays(self::RESULT_DAYS);

        $firstContacts = DB::connection('tenant')->table('customer_winbacks')
            ->where('created_at', '>=', $since)
            ->selectRaw('customer_id, min(created_at) as contacted_at')
            ->groupBy('customer_id');

        $row = DB::connection('tenant')->query()->fromSub($firstContacts, 'c')
            ->leftJoin('orders', fn ($join) => $join->on('orders.customer_id', '=', 'c.customer_id')
                ->whereColumn('orders.created_at', '>=', 'c.contacted_at')
                ->where('orders.status', '!=', OrderStatus::Cancelled->value))
            ->selectRaw('count(distinct c.customer_id) as contacted, count(distinct orders.customer_id) as returned, coalesce(sum(orders.total_cents), 0) as revenue')
            ->first();

        return [
            'contacted' => (int) $row->contacted,
            'returned' => (int) $row->returned,
            'revenue_cents' => (int) $row->revenue,
        ];
    }

    /** Cupons que servem para chamar de volta: ativos, válidos e que não são só de primeiro pedido. */
    public function coupons()
    {
        return Coupon::whereNull('customer_id')->where('is_active', true)->where('first_order_only', false)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderByDesc('id')
            ->get();
    }

    public static function firstName(?string $name): string
    {
        $first = trim(explode(' ', trim((string) $name))[0] ?? '');

        return $first !== '' ? mb_convert_case(mb_strtolower($first), MB_CASE_TITLE) : 'tudo bem';
    }
}
