<?php

namespace App\Livewire\Tenant\Customers;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Winback as WinbackService;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Promotions\CouponRules;
use App\Modules\Promotions\CouponType;
use App\Modules\Promotions\Models\Coupon;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Clientes sumidos: quem pedia e parou de pedir, com a mensagem de WhatsApp pronta
 * (e um cupom de volta). Cada contato fica registrado para medir quem voltou.
 */
#[Title('Clientes sumidos')]
class Winback extends Component
{
    use WithPagination;

    /** Desconto do cupom criado na hora: "10" (%), "15", "20" ou "frete" (entrega grátis). */
    public const QUICK_COUPONS = ['10', '15', '20', 'frete'];

    public const QUICK_COUPON_DAYS = 15;

    #[Url]
    public int $days = 30;

    #[Url(as: 'pedidos')]
    public int $minOrders = 2;

    #[Url]
    public string $sort = 'spent';

    #[Url(as: 'todos')]
    public bool $showContacted = false;

    public ?int $couponId = null;

    public string $message = '';

    public string $quickCoupon = '10';

    public bool $editingMessage = false;

    public function mount(WinbackService $winback): void
    {
        $this->message = (string) Setting::read(WinbackService::MESSAGE_SETTING, WinbackService::DEFAULT_MESSAGE);
        $saved = (int) Setting::read(WinbackService::COUPON_SETTING, 0);
        $this->couponId = $winback->coupons()->firstWhere('id', $saved)?->id;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['days', 'minOrders', 'sort', 'showContacted'], true)) {
            $this->days = in_array($this->days, WinbackService::DAYS_OPTIONS, true) ? $this->days : 30;
            $this->minOrders = max(1, min(10, $this->minOrders));
            $this->resetPage();
        }

        // O cupom escolhido fica para a equipe toda (quem abrir a tela depois já encontra).
        if ($property === 'couponId') {
            $this->authorize('customers.manage');
            Setting::write(WinbackService::COUPON_SETTING, $this->couponId ?: null);
        }
    }

    public function saveMessage(): void
    {
        $this->authorize('customers.manage');
        $this->validate(['message' => ['required', 'string', 'min:10', 'max:1000']], attributes: ['message' => 'mensagem']);
        Setting::write(WinbackService::MESSAGE_SETTING, trim($this->message));
        $this->editingMessage = false;
    }

    public function resetMessage(): void
    {
        $this->message = WinbackService::DEFAULT_MESSAGE;
        $this->resetValidation('message');
    }

    /** Cupom de volta criado aqui mesmo: uso único por cliente, válido por 15 dias, não divulgado. */
    public function createCoupon(): void
    {
        $this->authorize('menu.manage');
        abort_unless(in_array($this->quickCoupon, self::QUICK_COUPONS, true), 422);

        $timezone = Setting::read('timezone', 'America/Sao_Paulo');
        $free = $this->quickCoupon === 'frete';
        $base = $free ? 'VOLTEFRETE' : 'VOLTE'.$this->quickCoupon;
        $code = $base;

        for ($i = 2; Coupon::where('code', $code)->exists(); $i++) {
            $code = $base.'-'.$i;
        }

        $coupon = Coupon::create([
            'code' => $code,
            'description' => 'Clientes sumidos',
            'type' => $free ? CouponType::FreeDelivery : CouponType::Percent,
            'percent' => $free ? null : (int) $this->quickCoupon,
            'per_customer_limit' => 1,
            'ends_at' => CarbonImmutable::now($timezone)->addDays(self::QUICK_COUPON_DAYS)->endOfDay()->utc(),
        ]);

        Audit::record('coupon.created', $coupon, ['cupom' => $coupon->code, 'benefício' => $coupon->benefit()]);

        $this->couponId = $coupon->id;
        Setting::write(WinbackService::COUPON_SETTING, $coupon->id);
    }

    /** Clicou no WhatsApp: o link abre no navegador e aqui fica o registro do contato. */
    public function contacted(int $customerId, WinbackService $winback): void
    {
        $this->authorize('customers.manage');
        $customer = Customer::findOrFail($customerId);
        $winback->record($customer, $this->coupon(), $this->user());
    }

    public function optOut(int $customerId, WinbackService $winback): void
    {
        $this->authorize('customers.manage');
        $winback->optOut(Customer::findOrFail($customerId), $this->user());
    }

    private function coupon(): ?Coupon
    {
        return $this->couponId ? app(WinbackService::class)->coupons()->firstWhere('id', $this->couponId) : null;
    }

    private function user(): User
    {
        return Auth::guard('web')->user();
    }

    public function render(WinbackService $winback, CouponRules $rules)
    {
        $customers = $winback->query($this->days, $this->minOrders, hideContacted: ! $this->showContacted)
            ->when($this->sort === 'spent', fn ($q) => $q->orderByDesc('stats.spent_cents'))
            ->when($this->sort === 'orders', fn ($q) => $q->orderByDesc('stats.orders_count'))
            ->when($this->sort === 'oldest', fn ($q) => $q->orderBy('stats.last_at'))
            ->when($this->sort === 'recent', fn ($q) => $q->orderByDesc('stats.last_at'))
            ->orderBy('customers.id')
            ->paginate(20);

        $coupon = $this->coupon();
        $favorites = $winback->favorites($customers->pluck('id')->all());

        $rows = $customers->getCollection()->map(function (Customer $customer) use ($winback, $coupon, $favorites, $rules) {
            $text = $winback->message($this->message, $customer, $coupon, $favorites[$customer->id] ?? null);

            return [
                'customer' => $customer,
                'favorite' => $favorites[$customer->id] ?? null,
                'link' => $text !== null ? $winback->link($customer, $text) : null,
                // Já usou o cupom (limite por cliente): a mensagem ainda sai, mas o cupom não vai valer.
                'couponProblem' => $coupon ? $rules->problem($coupon, Money::cents(PHP_INT_MAX), $customer->phone) : null,
            ];
        });

        $preview = $winback->message($this->message, new Customer(['name' => 'Maria Souza']), $coupon, 'X-Burguer');

        return view('livewire.tenant.customers.winback', [
            'customers' => $customers,
            'rows' => $rows,
            'coupons' => $winback->coupons(),
            'coupon' => $coupon,
            'preview' => $preview,
            'results' => $winback->results(),
            'timezone' => Setting::read('timezone', 'America/Sao_Paulo'),
        ]);
    }
}
