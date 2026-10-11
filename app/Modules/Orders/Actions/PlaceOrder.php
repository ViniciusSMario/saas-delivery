<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Access\Models\User;
use App\Modules\Access\Permission;
use App\Modules\Cash\CashLedger;
use App\Modules\Customers\Models\Customer;
use App\Modules\Delivery\DeliveryFees;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Establishment\OpeningSchedule;
use App\Modules\Menu\Models\Combo;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PaymentMethod;
use App\Modules\Orders\RushMode;
use App\Modules\Promotions\CouponRules;
use App\Modules\Promotions\Models\Coupon;
use App\Modules\Promotions\Models\CouponRedemption;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cria um pedido (online ou de balcão). Tudo que vem do cliente é conferido aqui,
 * no servidor: preços, disponibilidade, regras dos adicionais, horário, pedido mínimo.
 * O pedido guarda um retrato de nomes e preços do momento da venda.
 */
class PlaceOrder
{
    public const MAX_LINES = 50;

    public const MAX_QUANTITY = 99;

    public function __construct(
        private readonly CashLedger $ledger,
        private readonly DeliveryFees $fees,
        private readonly CouponRules $coupons,
    ) {
    }

    /**
     * @param  array{
     *     lines: list<array{type: string, id: int, quantity: int, addons?: list<int>, notes?: string|null}>,
     *     fulfillment: string,
     *     customer?: array{name?: string|null, phone?: string|null},
     *     address?: array{zip_code?: string|null, street?: string|null, number?: string|null, complement?: string|null, district?: string|null, city?: string|null, reference?: string|null},
     *     payment_method: string,
     *     change_for?: Money|null,
     *     discount?: Money|null,
     *     coupon?: string|null,
     *     notes?: string|null,
     * }  $input
     */
    public function handle(array $input, OrderSource $source, ?User $actor = null): Order
    {
        $online = $source === OrderSource::Online;
        $table = $source === OrderSource::Table;
        // Pedido do cliente pelo QR code da mesa: confere o cardápio como no pedido online.
        $viaQr = $table && ! empty($input['via_qr']);
        $fulfillment = Fulfillment::tryFrom($input['fulfillment'] ?? '')
            ?? $this->fail('fulfillment', 'Escolha entrega ou retirada.');

        // Mesa só por comanda; comanda só com pedido de mesa.
        if ($table !== ($fulfillment === Fulfillment::Table) || $table !== ! empty($input['command_id'])) {
            $this->fail('fulfillment', 'Tipo de pedido inválido.');
        }

        if ($online) {
            $this->assertAcceptingOnlineOrders($fulfillment);
        }

        $customer = $this->customerData($input['customer'] ?? [], required: $online || $fulfillment === Fulfillment::Delivery);
        $address = $fulfillment === Fulfillment::Delivery ? $this->addressData($input['address'] ?? []) : null;
        $lines = $this->priceLines($input['lines'] ?? [], $online || $viaQr);

        $subtotal = array_reduce($lines, fn (Money $sum, array $line) => $sum->add($line['total']), Money::zero());
        $discount = $this->discount($input['discount'] ?? null, $subtotal, $online, $actor);
        // Taxa única ou por bairro (bairro fora da área de entrega é recusado).
        ['fee' => $deliveryFee, 'zone' => $zone] = $fulfillment === Fulfillment::Delivery
            ? $this->fees->resolve($address['district'])
            : ['fee' => Money::zero(), 'zone' => null];

        if ($zone) {
            $address['district'] = $zone->name; // grafia do cadastro
        }

        // Cupom (cardápio online ou PDV): no lugar do desconto manual, nunca junto.
        $coupon = null;

        if (Coupon::normalize($input['coupon'] ?? null) !== '') {
            if ($table) {
                $this->fail('coupon', 'Cupom não vale em pedido de mesa.');
            }

            if (! $discount->isZero()) {
                $this->fail('coupon', 'Use o cupom ou o desconto manual, não os dois.');
            }

            $coupon = Coupon::findByCode($input['coupon']) ?? $this->fail('coupon', 'Cupom não encontrado. Confira o código.');
            $this->assertCoupon($coupon, $subtotal, $customer['phone'], $fulfillment);
            $discount = $coupon->discountFor($subtotal, $deliveryFee);
        }

        $total = $subtotal->subtract($discount)->add($deliveryFee);

        if ($online) {
            $minimum = $zone?->min_order_cents ?? Money::cents((int) Setting::read('min_order_cents', 0));

            if ($minimum->greaterThan($subtotal)) {
                $this->fail('lines', "O pedido mínimo é de {$minimum->format()} (sem a taxa de entrega).");
            }
        }

        // Pedido de mesa não tem pagamento próprio: a conta é da comanda.
        [$method, $changeFor] = $table ? [null, null] : $this->payment($input['payment_method'] ?? '', $input['change_for'] ?? null, $total);

        // Balcão: pago na hora, então precisa do caixa aberto do operador.
        $paidNow = $fulfillment === Fulfillment::Counter;
        $cashSession = $paidNow ? $this->ledger->requireSession($actor) : null;

        $order = DB::connection('tenant')->transaction(function () use (
            $viaQr, $source, $fulfillment, $customer, $address, $lines, $subtotal, $discount, $deliveryFee, $zone, $total, $method, $changeFor, $input, $actor, $paidNow, $cashSession, $coupon
        ) {
            // Cupom travado e conferido de novo: dois pedidos juntos não passam do limite de usos.
            if ($coupon) {
                $locked = Coupon::query()->lockForUpdate()->find($coupon->id) ?? $this->fail('coupon', 'Cupom não encontrado. Confira o código.');
                $this->assertCoupon($locked, $subtotal, $customer['phone'], $fulfillment);
            }

            $customerModel = $customer['phone'] ? $this->rememberCustomer($customer, $address) : null;

            $order = Order::create([
                'public_token' => Str::random(32),
                'source' => $source,
                'fulfillment' => $fulfillment,
                'status' => OrderStatus::Received,
                'customer_id' => $customerModel?->id,
                'command_id' => $input['command_id'] ?? null,
                'customer_name' => $customer['name'],
                'customer_phone' => $customer['phone'],
                'address_zip_code' => $address['zip_code'] ?? null,
                'address_street' => $address['street'] ?? null,
                'address_number' => $address['number'] ?? null,
                'address_complement' => $address['complement'] ?? null,
                'address_district' => $address['district'] ?? null,
                'address_city' => $address['city'] ?? null,
                'address_reference' => $address['reference'] ?? null,
                'notes' => $this->clean($input['notes'] ?? null, 500),
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discount,
                'delivery_fee_cents' => $deliveryFee,
                'delivery_zone_id' => $zone?->id,
                'total_cents' => $total,
                'coupon_code' => $coupon?->code,
                'via_qr' => $viaQr,
                'eta_extra_minutes' => in_array($fulfillment, [Fulfillment::Delivery, Fulfillment::Pickup], true) ? RushMode::extraMinutes() : 0,
                'created_by' => $actor?->id,
            ]);

            if ($coupon) {
                CouponRedemption::create([
                    'coupon_id' => $coupon->id,
                    'order_id' => $order->id,
                    'customer_phone' => $customer['phone'],
                    'discount_cents' => $discount,
                ]);
            }

            foreach ($lines as $line) {
                $item = $order->items()->create([
                    'product_id' => $line['product_id'],
                    'combo_id' => $line['combo_id'],
                    'name' => $line['name'],
                    'description' => $line['description'],
                    'unit_price_cents' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'addons_cents' => $line['addons_price'],
                    'total_cents' => $line['total'],
                    'notes' => $line['notes'],
                ]);

                $item->addons()->createMany($line['addons']);
            }

            $order->history()->create(['from_status' => null, 'to_status' => OrderStatus::Received, 'user_id' => $actor?->id]);

            // Balcão: pago na hora (entra no caixa). Entrega/retirada: recebido na conclusão.
            if ($method) {
                $payment = $order->payments()->create([
                    'payment_method_id' => $method->id,
                    'amount_cents' => $total,
                    'change_for_cents' => $changeFor,
                    'status' => $paidNow ? PaymentStatus::Paid : PaymentStatus::Pending,
                    'paid_at' => $paidNow ? now() : null,
                ]);

                if ($cashSession && ! $total->isZero()) {
                    $this->ledger->recordOrderPayment($payment, $actor, $cashSession);
                }
            }

            return $order->refresh();
        });

        event(new OrderPlaced($order));

        return $order;
    }

    private function assertAcceptingOnlineOrders(Fulfillment $fulfillment): void
    {
        if (! Setting::read('accepts_online_orders', true)) {
            $this->fail('lines', 'O estabelecimento não está recebendo pedidos online no momento.');
        }

        if ($paused = RushMode::pausedUntil()) {
            $at = $paused->timezone(Setting::read('timezone', 'America/Sao_Paulo'))->format('H:i');
            $this->fail('lines', "Estamos com muitos pedidos e pausamos os pedidos online até as {$at}. Tente de novo em alguns minutos.");
        }

        $now = CarbonImmutable::now(Setting::read('timezone', 'America/Sao_Paulo'));

        if (! OpeningSchedule::load()->isOpenAt($now)) {
            $this->fail('lines', 'O estabelecimento está fechado agora. Confira os horários no cardápio.');
        }

        $allowed = match ($fulfillment) {
            Fulfillment::Delivery => Setting::read('accepts_delivery', true),
            Fulfillment::Pickup => Setting::read('accepts_pickup', true),
            Fulfillment::Counter => false,
        };

        if (! $allowed) {
            $this->fail('fulfillment', 'Esta opção de entrega não está disponível.');
        }
    }

    /** @return array{name: string|null, phone: string|null} */
    private function customerData(array $data, bool $required): array
    {
        $name = $this->clean($data['name'] ?? null, 120);
        $phone = ($data['phone'] ?? '') !== '' ? Customer::normalizePhone($data['phone']) : null;

        if ($required && ! $name) {
            $this->fail('customer.name', 'Informe o nome.');
        }

        if ($required && ! $phone) {
            $this->fail('customer.phone', 'Informe o telefone (WhatsApp) com DDD.');
        }

        if ($phone && ! Customer::isValidPhone($phone)) {
            $this->fail('customer.phone', 'Telefone inválido. Use DDD + número, ex.: (11) 98888-7777.');
        }

        return ['name' => $name, 'phone' => $phone];
    }

    private function addressData(array $data): array
    {
        $address = [];

        foreach (['zip_code' => 9, 'street' => 160, 'number' => 20, 'complement' => 120, 'district' => 120, 'city' => 120, 'reference' => 160] as $field => $max) {
            $address[$field] = $this->clean($data[$field] ?? null, $max);
        }

        foreach (['street' => 'a rua', 'number' => 'o número', 'district' => 'o bairro'] as $field => $label) {
            if (! $address[$field]) {
                $this->fail("address.{$field}", "Informe {$label} para a entrega.");
            }
        }

        return $address;
    }

    /**
     * Confere cada linha contra o cardápio atual e calcula os valores.
     *
     * @return list<array<string, mixed>>
     */
    private function priceLines(array $lines, bool $online): array
    {
        if ($lines === []) {
            $this->fail('lines', 'O pedido está vazio.');
        }

        if (count($lines) > self::MAX_LINES) {
            $this->fail('lines', 'Pedido com itens demais.');
        }

        $productIds = collect($lines)->where('type', 'product')->pluck('id')->map(fn ($id) => (int) $id)->unique();
        $comboIds = collect($lines)->where('type', 'combo')->pluck('id')->map(fn ($id) => (int) $id)->unique();

        $products = Product::query()
            ->with(['category', 'addonGroups' => fn ($q) => $q->where('is_active', true), 'addonGroups.addons' => fn ($q) => $q->where('is_active', true)])
            ->whereKey($productIds)
            ->get()
            ->keyBy('id');
        $combos = Combo::query()->with('items.product')->whereKey($comboIds)->get()->keyBy('id');

        $priced = [];

        foreach (array_values($lines) as $index => $line) {
            $key = "lines.{$index}";
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
                $this->fail($key, 'Quantidade inválida.');
            }

            $priced[] = match ($line['type'] ?? null) {
                'product' => $this->priceProduct($products->get((int) $line['id']), $line, $quantity, $key, $online),
                'combo' => $this->priceCombo($combos->get((int) $line['id']), $line, $quantity, $key),
                default => $this->fail($key, 'Item inválido.'),
            };
        }

        return $priced;
    }

    private function priceProduct(?Product $product, array $line, int $quantity, string $key, bool $online): array
    {
        if (! $product || ! $product->is_active || ($online && ! $product->category?->is_active)) {
            $this->fail($key, 'Um item do pedido não está mais no cardápio.');
        }

        if (! $product->is_available) {
            $this->fail($key, "{$product->name} está esgotado no momento.");
        }

        $selected = collect($line['addons'] ?? [])->map(fn ($id) => (int) $id);

        if ($selected->duplicates()->isNotEmpty()) {
            $this->fail($key, 'Adicional repetido.');
        }

        $addons = [];
        $addonsPrice = Money::zero();
        $matched = 0;

        foreach ($product->addonGroups as $group) {
            $chosen = $group->addons->whereIn('id', $selected->all());
            $matched += $chosen->count();

            if ($chosen->count() < $group->min_choices || $chosen->count() > $group->max_choices) {
                $this->fail($key, "{$product->name}: em \"{$group->name}\", {$this->ruleText($group->min_choices, $group->max_choices)}.");
            }

            foreach ($chosen as $addon) {
                $addons[] = [
                    'addon_id' => $addon->id,
                    'group_name' => $group->name,
                    'name' => $addon->name,
                    'unit_price_cents' => $addon->price_cents,
                ];
                $addonsPrice = $addonsPrice->add($addon->price_cents);
            }
        }

        // Adicional que não pertence a este produto (ou está inativo).
        if ($matched !== $selected->count()) {
            $this->fail($key, "{$product->name}: um dos adicionais escolhidos não está mais disponível.");
        }

        return [
            'product_id' => $product->id,
            'combo_id' => null,
            'name' => $product->name,
            'description' => null,
            'unit_price' => $product->price_cents,
            'addons_price' => $addonsPrice,
            'quantity' => $quantity,
            'total' => $product->price_cents->add($addonsPrice)->multiply($quantity),
            'notes' => $this->clean($line['notes'] ?? null, 255),
            'addons' => $addons,
        ];
    }

    private function priceCombo(?Combo $combo, array $line, int $quantity, string $key): array
    {
        if (! $combo || ! $combo->is_active || ! $combo->isAvailable()) {
            $this->fail($key, 'Um combo do pedido não está mais disponível.');
        }

        return [
            'product_id' => null,
            'combo_id' => $combo->id,
            'name' => $combo->name,
            'description' => $combo->items->map(fn ($i) => $i->quantity.'× '.$i->product->name)->implode(', '),
            'unit_price' => $combo->price_cents,
            'addons_price' => Money::zero(),
            'quantity' => $quantity,
            'total' => $combo->price_cents->multiply($quantity),
            'notes' => $this->clean($line['notes'] ?? null, 255),
            'addons' => [],
        ];
    }

    private function assertCoupon(Coupon $coupon, Money $subtotal, ?string $phone, Fulfillment $fulfillment): void
    {
        if ($problem = $this->coupons->problem($coupon, $subtotal, $phone, $fulfillment, requirePhone: true)) {
            $this->fail('coupon', $problem);
        }
    }

    private function discount(?Money $discount, Money $subtotal, bool $online, ?User $actor): Money
    {
        if (! $discount || $discount->isZero()) {
            return Money::zero();
        }

        if ($online || ! $actor?->hasPermission(Permission::OrdersDiscount)) {
            $this->fail('discount', 'Você não tem permissão para dar desconto.');
        }

        if ($discount->cents < 0 || $discount->greaterThan($subtotal)) {
            $this->fail('discount', 'O desconto não pode ser maior que o subtotal.');
        }

        return $discount;
    }

    /** @return array{0: PaymentMethod, 1: Money|null} */
    private function payment(string $code, ?Money $changeFor, Money $total): array
    {
        $method = PaymentMethod::active()->where('code', $code)->first()
            ?? $this->fail('payment_method', 'Escolha a forma de pagamento.');

        if (! $changeFor || $changeFor->isZero()) {
            return [$method, null];
        }

        if (! $method->accepts_change) {
            return [$method, null];
        }

        if ($total->greaterThan($changeFor)) {
            $this->fail('change_for', "O troco precisa ser para um valor de pelo menos {$total->format()}.");
        }

        return [$method, $changeFor];
    }

    private function rememberCustomer(array $customer, ?array $address): Customer
    {
        $model = Customer::withTrashed()->firstOrNew(['phone' => $customer['phone']]);
        $model->fill(['name' => $customer['name'] ?? $model->name ?? 'Cliente', 'last_order_at' => now()]);
        $model->deleted_at = null;
        $model->save();

        if ($address) {
            $saved = $model->addresses()
                ->whereRaw('lower(street) = lower(?)', [$address['street']])
                ->where('number', $address['number'])
                ->first();

            ($saved ?? $model->addresses()->make())->fill($address + ['last_used_at' => now()])->save();
        }

        return $model;
    }

    private function ruleText(int $min, int $max): string
    {
        return match (true) {
            $min === $max => "escolha {$max}",
            $min === 0 => "escolha até {$max}",
            default => "escolha de {$min} a {$max}",
        };
    }

    private function clean(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
