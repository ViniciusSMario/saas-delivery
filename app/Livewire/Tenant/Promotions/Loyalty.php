<?php

namespace App\Livewire\Tenant\Promotions;

use App\Modules\Access\Audit;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Promotions\Loyalty as LoyaltyProgram;
use App\Modules\Promotions\Models\LoyaltyReward;
use App\Support\Money;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Cartão fidelidade: regra (a cada N pedidos, um prêmio), resultado e prêmios gerados. */
#[Title('Cartão fidelidade')]
class Loyalty extends Component
{
    public bool $enabled = false;

    public string $goal = '10';

    public string $minOrder = '';

    public string $rewardType = 'fixed';

    public string $rewardPercent = '10';

    public string $rewardAmount = '20';

    public string $validDays = '30';

    public bool $saved = false;

    public function mount(LoyaltyProgram $loyalty): void
    {
        $config = $loyalty->config();
        $this->enabled = $config['enabled'];
        $this->goal = (string) $config['goal'];
        $this->minOrder = $config['min_cents'] ? $this->money($config['min_cents']) : '';
        $this->rewardType = $config['reward_type'];
        $this->rewardPercent = (string) $config['reward_percent'];
        $this->rewardAmount = $this->money($config['reward_cents']);
        $this->validDays = (string) $config['valid_days'];
    }

    public function updated(): void
    {
        $this->saved = false;
    }

    protected function rules(): array
    {
        return [
            'goal' => ['required', 'integer', 'between:2,50'],
            'minOrder' => ['nullable', 'regex:/^\d{1,5}([,.]\d{1,2})?$/'],
            'rewardType' => ['required', 'in:fixed,percent,free_delivery'],
            'rewardPercent' => ['exclude_unless:rewardType,percent', 'required', 'integer', 'between:1,100'],
            'rewardAmount' => ['exclude_unless:rewardType,fixed', 'required', 'regex:/^\d{1,5}([,.]\d{1,2})?$/'],
            'validDays' => ['required', 'integer', 'between:1,365'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'goal' => 'pedidos para ganhar', 'minOrder' => 'valor mínimo do pedido', 'rewardPercent' => 'porcentagem',
            'rewardAmount' => 'valor do desconto', 'validDays' => 'validade do prêmio',
        ];
    }

    public function save(LoyaltyProgram $loyalty): void
    {
        $this->authorize('menu.manage');
        $this->validate();

        $before = $loyalty->config();
        $config = $this->config();
        $loyalty->save($config);

        Audit::record('loyalty.updated', changes: [
            'situação' => $config['enabled'] ? 'ligado' : 'desligado',
            'regra' => $loyalty->headline($loyalty->config()),
        ] + ($before['enabled'] !== $config['enabled'] ? ['antes' => $before['enabled'] ? 'ligado' : 'desligado'] : []));

        $this->saved = true;
    }

    /** Configuração do formulário (sem validar: serve também à prévia). */
    private function config(): array
    {
        $parse = fn (string $value) => $value !== '' && Money::isValid($value) ? Money::parse($value)->cents : 0;

        return [
            'enabled' => $this->enabled,
            'goal' => max(2, min(50, (int) $this->goal)),
            'min_cents' => $parse($this->minOrder),
            'reward_type' => in_array($this->rewardType, ['fixed', 'percent', 'free_delivery'], true) ? $this->rewardType : 'fixed',
            'reward_percent' => max(1, min(100, (int) $this->rewardPercent)),
            'reward_cents' => max(1, $parse($this->rewardAmount)),
            'valid_days' => max(1, min(365, (int) $this->validDays)),
        ];
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '');
    }

    public function render(LoyaltyProgram $loyalty)
    {
        $preview = $this->config();

        return view('livewire.tenant.promotions.loyalty', [
            'headline' => $loyalty->headline($preview),
            'goalPreview' => $preview['goal'],
            'startedAt' => $loyalty->config()['started_at'],
            'stats' => $loyalty->stats(),
            'rewards' => LoyaltyReward::with(['customer', 'coupon.redemptions.order'])->latest('created_at')->latest('id')->limit(20)->get(),
            'cancelled' => OrderStatus::Cancelled,
            'timezone' => Setting::read('timezone', 'America/Sao_Paulo'),
        ]);
    }
}
