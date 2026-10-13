<?php

namespace App\Livewire\Tenant\Onboarding;

use App\Modules\Access\Actions\SaveUser;
use App\Modules\Access\Audit;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\User;
use App\Modules\Delivery\DeliveryFees;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Establishment\Onboarding;
use App\Modules\Menu\Import\MenuTemplates;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Models\PaymentMethod;
use App\Modules\Orders\OrderEta;
use App\Modules\Tables\Models\DiningTable;
use App\Support\Money;
use App\Support\QrCode;
use App\Tenancy\TenantUrl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Assistente do primeiro acesso: uma etapa por vez, cada uma salvando de verdade
 * (dados da loja, horário, entrega e pagamento, cardápio, mesas, equipe, teste e divulgação).
 */
#[Title('Primeiros passos')]
class Wizard extends Component
{
    use WithFileUploads;

    /** Horários prontos: [rótulo, dias abertos (0 = domingo), abre, fecha]. */
    public const HOUR_PRESETS = [
        'all' => ['Todos os dias, 11h às 23h', [0, 1, 2, 3, 4, 5, 6], '11:00', '23:00'],
        'night' => ['Todos os dias, 18h às 23h30', [0, 1, 2, 3, 4, 5, 6], '18:00', '23:30'],
        'lunch' => ['Segunda a sábado, 11h às 15h', [1, 2, 3, 4, 5, 6], '11:00', '15:00'],
        'tue_sun' => ['Terça a domingo, 18h às 23h', [0, 2, 3, 4, 5, 6], '18:00', '23:00'],
    ];

    /** Perfis que o dono convida no assistente. */
    public const INVITE_ROLES = ['gerente' => 'Gerente', 'caixa' => 'Caixa', 'cozinha' => 'Cozinha', 'garcom' => 'Garçom', 'entregador' => 'Entregador'];

    #[Url]
    public string $step = 'store';

    // Loja
    public array $store = [];

    public $logo;

    // Horário
    public array $hours = [];

    // Entrega e pagamento
    public bool $acceptsDelivery = true;

    public bool $acceptsPickup = true;

    public string $deliveryFee = '0,00';

    public string $minOrder = '0,00';

    public int $etaDelivery = OrderEta::DELIVERY_DEFAULT;

    public int $etaPickup = OrderEta::PICKUP_DEFAULT;

    public array $payments = [];

    // Mesas
    public int $tableCount = 10;

    // Equipe
    public string $inviteName = '';

    public string $inviteEmail = '';

    public string $inviteRole = 'caixa';

    public function mount(): void
    {
        $establishment = Establishment::current();
        $this->store = collect(['trade_name', 'phone', 'zip_code', 'street', 'number', 'complement', 'district', 'city', 'state'])
            ->mapWithKeys(fn ($field) => [$field => (string) $establishment->{$field}])->all();
        $this->store['trade_name'] = $this->store['trade_name'] ?: (string) tenant()->name;

        OpeningHour::seedDefaults();

        foreach (OpeningHour::orderBy('weekday')->get() as $hour) {
            $this->hours[$hour->weekday] = [
                'is_closed' => $hour->is_closed,
                'opens_at' => $hour->opens_at ? substr($hour->opens_at, 0, 5) : '11:00',
                'closes_at' => $hour->closes_at ? substr($hour->closes_at, 0, 5) : '23:00',
            ];
        }

        $this->acceptsDelivery = (bool) Setting::read('accepts_delivery', true);
        $this->acceptsPickup = (bool) Setting::read('accepts_pickup', true);
        $this->deliveryFee = $this->money((int) Setting::read('delivery_fee_cents', 0));
        $this->minOrder = $this->money((int) Setting::read('min_order_cents', 0));
        $this->etaDelivery = (int) Setting::read(OrderEta::DELIVERY_SETTING, OrderEta::DELIVERY_DEFAULT);
        $this->etaPickup = (int) Setting::read(OrderEta::PICKUP_SETTING, OrderEta::PICKUP_DEFAULT);
        $this->payments = PaymentMethod::orderBy('position')->get()->mapWithKeys(fn ($m) => [$m->id => $m->is_active])->all();

        if (! array_key_exists($this->step, Onboarding::STEPS)) {
            $this->step = $this->firstPending();
        }
    }

    public function go(string $step): void
    {
        $this->step = array_key_exists($step, Onboarding::STEPS) ? $step : 'store';
        $this->resetValidation();
    }

    // ---------- Loja ----------

    public function saveStore(): void
    {
        $this->authorize('establishment.manage');
        $this->validate([
            'store.trade_name' => ['required', 'string', 'max:120'],
            'store.phone' => ['required', 'string', 'max:20'],
            'store.zip_code' => ['nullable', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            'store.street' => ['required', 'string', 'max:160'],
            'store.number' => ['nullable', 'string', 'max:20'],
            'store.complement' => ['nullable', 'string', 'max:120'],
            'store.district' => ['nullable', 'string', 'max:120'],
            'store.city' => ['required', 'string', 'max:120'],
            'store.state' => ['nullable', 'string', 'size:2'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], attributes: [
            'store.trade_name' => 'nome', 'store.phone' => 'telefone', 'store.zip_code' => 'CEP', 'store.street' => 'rua',
            'store.city' => 'cidade', 'store.state' => 'UF', 'logo' => 'logo',
        ]);

        $establishment = Establishment::current();
        $establishment->fill(array_map(fn ($v) => trim((string) $v) === '' ? null : trim($v), $this->store));
        $establishment->state = $establishment->state ? mb_strtoupper($establishment->state) : null;

        if ($this->logo) {
            $old = $establishment->logo_path;
            $establishment->logo_path = $this->logo->store('establishment', 'tenant');

            if ($old && $old !== $establishment->logo_path) {
                Storage::disk('tenant')->delete($old);
            }
        }

        $isNew = ! $establishment->exists;
        $establishment->save();

        if ($isNew || $establishment->wasChanged()) {
            Audit::record('establishment.updated', $establishment, $isNew ? ['nome' => $establishment->trade_name] : Audit::diff($establishment));
        }

        $this->reset('logo');
        $this->next('store');
    }

    // ---------- Horário ----------

    public function applyPreset(string $preset): void
    {
        [, $days, $opens, $closes] = self::HOUR_PRESETS[$preset] ?? abort(422);

        foreach (array_keys(OpeningHour::WEEKDAYS) as $weekday) {
            $this->hours[$weekday] = ['is_closed' => ! in_array($weekday, $days, true), 'opens_at' => $opens, 'closes_at' => $closes];
        }
    }

    public function saveHours(): void
    {
        $this->authorize('establishment.manage');
        $this->validate([
            'hours.*.is_closed' => ['boolean'],
            'hours.*.opens_at' => ['nullable', 'required_if:hours.*.is_closed,false', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'required_if:hours.*.is_closed,false', 'date_format:H:i'],
        ], attributes: ['hours.*.opens_at' => 'abertura', 'hours.*.closes_at' => 'fechamento']);

        if (collect($this->hours)->every(fn ($h) => $h['is_closed'])) {
            throw ValidationException::withMessages(['hours' => 'Abra pelo menos um dia da semana.']);
        }

        $changes = [];

        foreach ($this->hours as $weekday => $data) {
            $hour = OpeningHour::firstOrNew(['weekday' => $weekday]);
            $hour->fill([
                'is_closed' => (bool) $data['is_closed'],
                'opens_at' => $data['is_closed'] ? null : $data['opens_at'],
                'closes_at' => $data['is_closed'] ? null : $data['closes_at'],
            ]);
            // Confirmar o horário padrão também conta (o checklist olha a data de alteração).
            $hour->updated_at = now()->addSecond();
            $hour->save();
            $changes[$hour->label()] = $hour->is_closed ? 'fechado' : "{$data['opens_at']}–{$data['closes_at']}";
        }

        Audit::record('opening_hours.updated', null, $changes);
        $this->next('hours');
    }

    // ---------- Entrega e pagamento ----------

    public function saveSales(): void
    {
        $this->authorize('settings.manage');
        $this->validate([
            'deliveryFee' => ['required', 'regex:/^\d{1,4}([,.]\d{1,2})?$/'],
            'minOrder' => ['required', 'regex:/^\d{1,4}([,.]\d{1,2})?$/'],
            'etaDelivery' => ['required', 'integer', 'between:0,240'],
            'etaPickup' => ['required', 'integer', 'between:0,240'],
        ], attributes: ['deliveryFee' => 'taxa de entrega', 'minOrder' => 'pedido mínimo', 'etaDelivery' => 'tempo de entrega', 'etaPickup' => 'tempo de retirada']);

        if (! $this->acceptsDelivery && ! $this->acceptsPickup) {
            throw ValidationException::withMessages(['acceptsDelivery' => 'Escolha entrega, retirada ou as duas.']);
        }

        if (! collect($this->payments)->contains(true)) {
            throw ValidationException::withMessages(['payments' => 'Escolha pelo menos uma forma de pagamento.']);
        }

        $values = [
            'accepts_delivery' => $this->acceptsDelivery,
            'accepts_pickup' => $this->acceptsPickup,
            'delivery_fee_cents' => Money::parse($this->deliveryFee)->cents,
            'min_order_cents' => Money::parse($this->minOrder)->cents,
            OrderEta::DELIVERY_SETTING => $this->etaDelivery,
            OrderEta::PICKUP_SETTING => $this->etaPickup,
        ];

        $changes = [];

        foreach ($values as $key => $value) {
            if (Setting::read($key) !== $value) {
                $changes[$key] = ['de' => Setting::read($key), 'para' => $value];
                Setting::write($key, $value);
            }
        }

        foreach (PaymentMethod::all() as $method) {
            $active = (bool) ($this->payments[$method->id] ?? false);

            if ($method->is_active !== $active) {
                $method->update(['is_active' => $active]);
                $changes['pagamento: '.$method->name] = $active ? 'ativado' : 'desativado';
            }
        }

        Audit::record('settings.updated', null, $changes ?: ['primeiros passos' => 'confirmado']);
        $this->next('sales');
    }

    // ---------- Cardápio ----------

    public function useTemplate(string $segment, MenuTemplates $templates): void
    {
        $this->authorize('menu.manage');
        abort_unless(array_key_exists($segment, MenuTemplates::segments()), 422);

        try {
            $templates->apply($segment, $this->user());
        } catch (ValidationException $e) {
            $this->addError('menu', collect($e->errors())->flatten()->first());

            return;
        }

        session()->flash('menu_template', MenuTemplates::segments()[$segment]['name']);
    }

    public function confirmMenu(): void
    {
        if (! Product::where('is_active', true)->exists()) {
            $this->addError('menu', 'O cardápio ainda está vazio: importe uma planilha, use um modelo ou cadastre um produto.');

            return;
        }

        $this->next('menu');
    }

    // ---------- Mesas ----------

    public function createTables(): void
    {
        $this->authorize('establishment.manage');
        $this->validate(['tableCount' => ['required', 'integer', 'between:1,100']], attributes: ['tableCount' => 'quantidade de mesas']);

        $existing = DiningTable::pluck('name')->all();
        $missing = array_diff(array_map('strval', range(1, $this->tableCount)), $existing);

        tenant()->assertWithinLimit('tables', count($existing), 'tableCount', count($missing));

        DB::connection('tenant')->transaction(function () use ($missing) {
            $position = (int) DiningTable::max('position');

            foreach ($missing as $name) {
                DiningTable::create(['name' => $name, 'seats' => 4, 'position' => ++$position]);
            }
        });

        Audit::record('table.created', null, ['mesas' => '1-'.$this->tableCount, 'criadas' => count($missing)]);
        $this->next('tables');
    }

    public function skip(string $step, Onboarding $onboarding): void
    {
        $this->authorize('establishment.manage');
        $onboarding->mark($step, skipped: true);
        $this->step = $this->firstPending($step);
    }

    // ---------- Equipe ----------

    public function invite(SaveUser $saveUser): void
    {
        $this->authorize('users.manage');
        $this->validate([
            'inviteName' => ['required', 'string', 'max:120'],
            'inviteEmail' => ['required', 'email', 'max:160', Rule::unique('tenant.users', 'email')],
            'inviteRole' => ['required', Rule::in(array_keys(self::INVITE_ROLES))],
        ], attributes: ['inviteName' => 'nome', 'inviteEmail' => 'e-mail', 'inviteRole' => 'função']);

        $role = Role::where('slug', $this->inviteRole)->firstOrFail();
        $saveUser->handle(null, ['name' => trim($this->inviteName), 'email' => mb_strtolower(trim($this->inviteEmail))], [$role->id], $this->user());

        session()->flash('invited', $this->inviteEmail);
        $this->reset('inviteName', 'inviteEmail');
    }

    public function confirmTeam(): void
    {
        $this->next('team');
    }

    // ---------- Pedido de teste ----------

    public function startTest(Onboarding $onboarding): void
    {
        $this->authorize('orders.manage');

        try {
            $onboarding->startTestOrder($this->user());
        } catch (ValidationException $e) {
            $this->addError('test', collect($e->errors())->flatten()->first());
        }
    }

    public function finishTest(Onboarding $onboarding): void
    {
        $this->authorize('orders.manage');
        $onboarding->finishTestOrder($this->user());
        $this->step = $this->firstPending('test');
    }

    // ---------- Divulgar e concluir ----------

    public function shared(Onboarding $onboarding): void
    {
        $onboarding->mark('share');
    }

    public function finish(Onboarding $onboarding): void
    {
        $this->authorize('establishment.manage');
        $onboarding->mark('share');
        $onboarding->complete($this->user());
        session()->flash('status', 'Tudo pronto! Seu restaurante está configurado.');
        $this->redirectRoute('dashboard', navigate: true);
    }

    public function later(): void
    {
        $this->redirectRoute('dashboard', navigate: true);
    }

    // ---------- Apoio ----------

    private function next(string $done): void
    {
        app(Onboarding::class)->mark($done);
        $this->resetValidation();
        $this->step = $this->firstPending($done);
    }

    /** Próxima etapa ainda não feita depois de $after (ou a primeira pendente). */
    private function firstPending(?string $after = null): string
    {
        $steps = collect(app(Onboarding::class)->steps());
        $keys = $steps->pluck('key');
        $start = $after ? $keys->search($after) + 1 : 0;
        $pending = $steps->slice($start)->first(fn ($s) => ! $s['done'] && ! $s['skipped'])
            ?? $steps->first(fn ($s) => ! $s['done'] && ! $s['skipped']);

        return $pending['key'] ?? 'share';
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '');
    }

    private function user(): User
    {
        return Auth::guard('web')->user();
    }

    public function render(Onboarding $onboarding, MenuTemplates $templates)
    {
        $menuUrl = TenantUrl::to(tenant(), 'cardapio');

        return view('livewire.tenant.onboarding.wizard', [
            'steps' => $onboarding->steps(),
            'progress' => $onboarding->progress(),
            'weekdays' => OpeningHour::WEEKDAYS,
            'paymentMethods' => PaymentMethod::orderBy('position')->get(),
            'byZone' => DeliveryFees::byZone(),
            'products' => Product::where('is_active', true)->count(),
            'segments' => collect(MenuTemplates::segments())->map(fn ($s, $key) => $s + ['preview' => array_slice($templates->preview($key), 0, 5)]),
            'tables' => DiningTable::count(),
            'team' => User::with('roles')->orderBy('id')->get(),
            'testOrder' => $onboarding->testOrder(),
            'menuUrl' => $menuUrl,
            'menuQr' => $this->step === 'share' ? QrCode::svg($menuUrl, 200) : null,
            'hasLogo' => (bool) Establishment::current()->logo_path,
        ]);
    }
}
