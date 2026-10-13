<?php

namespace App\Modules\Establishment;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Access\Models\User;
use App\Modules\Access\Permission;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Menu\Models\Product;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PaymentMethod;
use App\Modules\Tables\Models\DiningTable;
use Illuminate\Validation\ValidationException;

/**
 * Primeiros passos do restaurante: assistente do primeiro acesso e checklist no Início.
 * Cada etapa é conferida pelo estado real (tem telefone? tem produto?) ou marcada pelo
 * assistente quando não dá para saber sozinho (horário confirmado, pedido de teste feito).
 */
class Onboarding
{
    public const SETTING = 'onboarding';

    public const STEPS = [
        'store' => ['Sua loja', 'Nome, telefone, endereço e logo.'],
        'hours' => ['Horário', 'Quando o cardápio aceita pedidos.'],
        'sales' => ['Entrega e pagamento', 'Entrega, retirada, taxa e formas de pagamento.'],
        'menu' => ['Cardápio', 'Planilha, modelo pronto ou cadastro manual.'],
        'tables' => ['Mesas', 'Mesas do salão e QR codes (se atende no local).'],
        'team' => ['Equipe', 'Convide caixa, cozinha, garçom e entregador.'],
        'test' => ['Pedido de teste', 'Veja o alarme, o aceite e a impressão funcionando.'],
        'share' => ['Divulgar', 'Link e QR code do cardápio para os clientes.'],
    ];

    /** Etapas que dá para pular (nem todo restaurante tem salão ou equipe). */
    public const OPTIONAL = ['tables', 'team'];

    public const TEST_CUSTOMER = 'Pedido de teste';

    /** @return array{done: array<string, string>, skipped: array<string, string>, dismissed_at: ?string, completed_at: ?string, test_order_id: ?int} */
    public function state(): array
    {
        $saved = Setting::read(self::SETTING);

        return array_replace(['done' => [], 'skipped' => [], 'dismissed_at' => null, 'completed_at' => null, 'test_order_id' => null], is_array($saved) ? $saved : []);
    }

    private function save(array $state): void
    {
        Setting::write(self::SETTING, $state);
    }

    public function mark(string $step, bool $skipped = false): void
    {
        abort_unless(array_key_exists($step, self::STEPS), 422);
        abort_if($skipped && ! in_array($step, self::OPTIONAL, true), 422);

        $state = $this->state();
        $state[$skipped ? 'skipped' : 'done'][$step] = now()->toIso8601String();

        if (! $skipped) {
            unset($state['skipped'][$step]);
        }

        $this->save($state);
    }

    /**
     * Etapas com a situação de cada uma.
     *
     * @return list<array{key: string, title: string, text: string, done: bool, skipped: bool, optional: bool}>
     */
    public function steps(): array
    {
        $state = $this->state();
        $establishment = Establishment::current();

        $real = [
            'store' => filled($establishment->phone) && filled($establishment->street),
            'hours' => OpeningHour::query()->whereColumn('updated_at', '>', 'created_at')->exists(),
            'sales' => AuditLog::where('action', 'settings.updated')->exists(),
            'menu' => Product::where('is_active', true)->exists(),
            'tables' => DiningTable::exists(),
            'team' => User::count() > 1,
            'test' => false,
            'share' => false,
        ];

        return collect(self::STEPS)->map(fn ($info, $key) => [
            'key' => $key,
            'title' => $info[0],
            'text' => $info[1],
            // O cardápio só conta com produto de verdade (marcar não basta).
            'done' => $real[$key] || ($key !== 'menu' && isset($state['done'][$key])),
            'skipped' => ! $real[$key] && isset($state['skipped'][$key]),
            'optional' => in_array($key, self::OPTIONAL, true),
        ])->values()->all();
    }

    /** @return array{done: int, total: int, percent: int} etapa pulada conta como feita */
    public function progress(): array
    {
        $steps = $this->steps();
        $done = collect($steps)->filter(fn ($s) => $s['done'] || $s['skipped'])->count();

        return ['done' => $done, 'total' => count($steps), 'percent' => (int) round($done * 100 / count($steps))];
    }

    public function isComplete(): bool
    {
        $progress = $this->progress();

        return $progress['done'] === $progress['total'];
    }

    /** Checklist no Início: para quem cuida do estabelecimento, até concluir ou esconder. */
    public function visibleFor(User $user): bool
    {
        $state = $this->state();

        return $user->hasPermission(Permission::EstablishmentManage) && ! $state['dismissed_at'] && ! $state['completed_at'];
    }

    /**
     * Abre o assistente sozinho no primeiro acesso: restaurante sem produto nem pedido
     * (quem já usa o sistema nunca é levado para lá; a equipe em modo suporte também não).
     */
    public function shouldStartFor(User $user): bool
    {
        $state = $this->state();

        $support = request()->hasSession() && request()->session()->has('support_access');

        return ! $support && $this->visibleFor($user) && $state['done'] === [] && $state['skipped'] === []
            && ! Product::withTrashed()->exists() && ! Order::exists();
    }

    public function dismiss(?User $user): void
    {
        $state = $this->state();
        $state['dismissed_at'] = now()->toIso8601String();
        $this->save($state);
        Audit::record('onboarding.dismissed', null, [], $user);
    }

    public function complete(?User $user): void
    {
        $state = $this->state();
        $state['completed_at'] = now()->toIso8601String();
        $this->save($state);
        Audit::record('onboarding.completed', null, [], $user);
    }

    // ---------- Pedido de teste ----------

    /** Pedido do cardápio online de mentira: toca o alarme, aparece no painel e sai na impressão. */
    public function startTestOrder(User $user): Order
    {
        if ($order = $this->testOrder()) {
            return $order;
        }

        $product = Product::where('is_active', true)->where('is_available', true)->doesntHave('addonGroups')->orderBy('id')->first()
            ?? Product::where('is_active', true)->where('is_available', true)
                ->whereDoesntHave('addonGroups', fn ($q) => $q->where('min_choices', '>', 0))->orderBy('id')->first();

        if (! $product) {
            throw ValidationException::withMessages(['test' => 'Cadastre pelo menos um produto (sem adicional obrigatório) para fazer o pedido de teste.']);
        }

        $order = app(PlaceOrder::class)->handle([
            'lines' => [['type' => 'product', 'id' => $product->id, 'quantity' => 1]],
            'fulfillment' => 'pickup',
            // A primeira forma de pagamento ativa (o restaurante pode não aceitar dinheiro).
            'payment_method' => PaymentMethod::active()->value('code') ?? 'cash',
            'customer' => ['name' => self::TEST_CUSTOMER],
            'notes' => 'Pedido de teste do primeiro acesso: pode aceitar e avançar à vontade.',
            'test' => true,
        ], OrderSource::Online, $user);

        $state = $this->state();
        $state['test_order_id'] = $order->id;
        $this->save($state);

        return $order;
    }

    public function testOrder(): ?Order
    {
        $id = $this->state()['test_order_id'];

        return $id ? Order::whereKey($id)->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Completed->value])->first() : null;
    }

    /** Encerra o teste: o pedido é cancelado (não entra em vendas nem relatórios) e a etapa fica feita. */
    public function finishTestOrder(User $user): void
    {
        if ($order = $this->testOrder()) {
            app(ChangeOrderStatus::class)->handle($order, OrderStatus::Cancelled, $user, 'Pedido de teste do primeiro acesso');
        }

        $state = $this->state();
        $state['test_order_id'] = null;
        $state['done']['test'] = now()->toIso8601String();
        $this->save($state);
    }
}
