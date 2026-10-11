<?php

namespace App\Livewire\Tenant\Promotions;

use App\Modules\Access\Audit;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Promotions\CouponType;
use App\Modules\Promotions\Models\Coupon;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Cupons de desconto do cardápio online: cadastro e resultado (usos, desconto dado, vendas). */
#[Title('Cupons de desconto')]
class Coupons extends Component
{
    public ?int $editingId = null;

    public string $code = '';

    public string $description = '';

    public string $type = 'percent';

    public string $percent = '10';

    public string $amount = '';

    public string $maxDiscount = '';

    public string $minOrder = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public string $usageLimit = '';

    public string $perCustomerLimit = '1';

    public bool $firstOrderOnly = false;

    public bool $isPublic = false;

    public bool $showAfterOrder = false;

    public bool $isActive = true;

    public bool $showForm = false;

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('tenant.coupons', 'code')->ignore($this->editingId)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:160'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'percent' => ['exclude_unless:type,percent', 'required', 'integer', 'between:1,100'],
            'amount' => ['exclude_unless:type,fixed', 'required', 'regex:/^\d{1,5}([,.]\d{1,2})?$/'],
            'maxDiscount' => ['exclude_unless:type,percent', 'nullable', 'regex:/^\d{1,5}([,.]\d{1,2})?$/'],
            'minOrder' => ['nullable', 'regex:/^\d{1,5}([,.]\d{1,2})?$/'],
            'startsAt' => ['nullable', 'date_format:Y-m-d'],
            'endsAt' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:startsAt'],
            'usageLimit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'perCustomerLimit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'code' => 'código', 'description' => 'descrição', 'percent' => 'porcentagem', 'amount' => 'valor',
            'maxDiscount' => 'desconto máximo', 'minOrder' => 'pedido mínimo', 'startsAt' => 'início', 'endsAt' => 'fim',
            'usageLimit' => 'limite de usos', 'perCustomerLimit' => 'usos por cliente',
        ];
    }

    protected function messages(): array
    {
        return ['code.regex' => 'Use só letras, números e hífen, sem espaços (ex.: PRIMEIRA10).'];
    }

    public function create(): void
    {
        $this->cancel();
        $this->code = $this->suggestCode();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $coupon = Coupon::findOrFail($id);
        $timezone = $this->timezone();
        $money = fn (?Money $m) => $m ? number_format($m->cents / 100, 2, ',', '') : '';

        $this->fill([
            'editingId' => $coupon->id,
            'code' => $coupon->code,
            'description' => (string) $coupon->description,
            'type' => $coupon->type->value,
            'percent' => (string) ($coupon->percent ?? '10'),
            'amount' => $money($coupon->amount_cents),
            'maxDiscount' => $money($coupon->max_discount_cents),
            'minOrder' => $coupon->min_order_cents?->isZero() ? '' : $money($coupon->min_order_cents),
            'startsAt' => $coupon->starts_at?->timezone($timezone)->toDateString() ?? '',
            'endsAt' => $coupon->ends_at?->timezone($timezone)->toDateString() ?? '',
            'usageLimit' => (string) ($coupon->usage_limit ?? ''),
            'perCustomerLimit' => (string) ($coupon->per_customer_limit ?? ''),
            'firstOrderOnly' => $coupon->first_order_only,
            'isPublic' => $coupon->is_public,
            'showAfterOrder' => $coupon->show_after_order,
            'isActive' => $coupon->is_active,
            'showForm' => true,
        ]);
    }

    public function cancel(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorize('menu.manage');
        $this->code = Coupon::normalize($this->code);
        $this->validate();

        $timezone = $this->timezone();
        $coupon = $this->editingId ? Coupon::findOrFail($this->editingId) : new Coupon;
        $type = CouponType::from($this->type);

        $coupon->fill([
            'code' => $this->code,
            'description' => trim($this->description) ?: null,
            'type' => $type,
            'percent' => $type === CouponType::Percent ? (int) $this->percent : null,
            'amount_cents' => $type === CouponType::Fixed ? Money::parse($this->amount) : null,
            'max_discount_cents' => $type === CouponType::Percent && $this->maxDiscount !== '' ? Money::parse($this->maxDiscount) : null,
            'min_order_cents' => $this->minOrder !== '' ? Money::parse($this->minOrder) : Money::zero(),
            // Datas no fuso do restaurante: vale do começo do primeiro dia ao fim do último.
            'starts_at' => $this->startsAt !== '' ? CarbonImmutable::parse($this->startsAt, $timezone)->startOfDay()->utc() : null,
            'ends_at' => $this->endsAt !== '' ? CarbonImmutable::parse($this->endsAt, $timezone)->endOfDay()->utc() : null,
            'usage_limit' => $this->usageLimit !== '' ? (int) $this->usageLimit : null,
            'per_customer_limit' => $this->perCustomerLimit !== '' ? (int) $this->perCustomerLimit : null,
            'first_order_only' => $this->firstOrderOnly,
            'is_public' => $this->isPublic,
            'show_after_order' => $this->showAfterOrder,
            'is_active' => $this->isActive,
        ])->save();

        Audit::record($this->editingId ? 'coupon.updated' : 'coupon.created', $coupon, ['cupom' => $coupon->code, 'benefício' => $coupon->benefit()]);
        session()->flash('status', "Cupom {$coupon->code} salvo.");
        $this->cancel();
    }

    public function toggle(int $id): void
    {
        $this->authorize('menu.manage');
        $coupon = Coupon::findOrFail($id);
        $coupon->update(['is_active' => ! $coupon->is_active]);
        Audit::record('coupon.updated', $coupon, ['cupom' => $coupon->code, 'situação' => $coupon->is_active ? 'ativado' : 'pausado']);
    }

    /** Excluir libera o código; os pedidos que usaram o cupom continuam mostrando o código. */
    public function delete(int $id): void
    {
        $this->authorize('menu.manage');
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();
        Audit::record('coupon.deleted', $coupon, ['cupom' => $coupon->code]);

        if ($this->editingId === $id) {
            $this->cancel();
        }
    }

    public function generateCode(): void
    {
        $this->code = $this->suggestCode();
    }

    private function suggestCode(): string
    {
        do {
            $code = 'CUPOM'.Str::upper(Str::random(5));
        } while (Coupon::where('code', $code)->exists());

        return $code;
    }

    private function timezone(): string
    {
        return Setting::read('timezone', 'America/Sao_Paulo');
    }

    public function render()
    {
        // Resultado de cada cupom: usos, desconto dado e vendas, só de pedidos não cancelados.
        $stats = DB::connection('tenant')->table('coupon_redemptions')
            ->join('orders', 'orders.id', '=', 'coupon_redemptions.order_id')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('coupon_redemptions.coupon_id, count(*) as uses, sum(coupon_redemptions.discount_cents) as discount, sum(orders.total_cents) as sales, count(distinct coupon_redemptions.customer_phone) as customers')
            ->groupBy('coupon_redemptions.coupon_id')
            ->get()
            ->keyBy('coupon_id');

        return view('livewire.tenant.promotions.coupons', [
            // Prêmios da fidelidade (cupons pessoais) ficam na tela de fidelidade.
            'coupons' => Coupon::whereNull('customer_id')->orderByDesc('is_active')->orderByDesc('id')->get(),
            'stats' => $stats,
            'timezone' => $this->timezone(),
        ]);
    }
}
