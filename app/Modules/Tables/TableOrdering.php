<?php

namespace App\Modules\Tables;

use App\Modules\Establishment\Models\Setting;
use App\Modules\Establishment\OpeningSchedule;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Enums\Fulfillment;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Models\Order;
use App\Modules\Push\StaffAlerts;
use App\Modules\Tables\Actions\ManageCommand;
use App\Modules\Tables\Models\Command;
use App\Modules\Tables\Models\DiningTable;
use App\Tenancy\TenantUrl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Pedido pela mesa: o cliente lê o QR code da mesa, vê o cardápio e manda o pedido para a
 * comanda (cozinha e garçom ficam sabendo). Também chama o garçom e pede a conta.
 */
class TableOrdering
{
    public const SETTING = 'qr_ordering';

    /** off = só ver o cardápio; open = pedir só com a mesa aberta pelo garçom; any = o cliente abre a mesa. */
    public const MODES = ['off', 'open', 'any'];

    public const DEFAULT_MODE = 'open';

    /** Pedidos por mesa a cada 10 minutos (contra brincadeira com a foto do QR). */
    public const MAX_ORDERS = 6;

    /** Um chamado igual seguido só avisa a equipe de novo depois deste tempo. */
    public const CALL_COOLDOWN_SECONDS = 90;

    public const CALL_REASONS = ['waiter' => 'Chamando o garçom', 'bill' => 'Pediu a conta'];

    public function __construct(
        private readonly ManageCommand $commands,
        private readonly StaffAlerts $alerts,
    ) {}

    public static function mode(): string
    {
        $mode = Setting::read(self::SETTING, self::DEFAULT_MODE);

        return in_array($mode, self::MODES, true) ? $mode : self::DEFAULT_MODE;
    }

    public static function url(DiningTable $table): string
    {
        return TenantUrl::to(tenant(), 'cardapio/mesa/'.$table->qr_token);
    }

    public function find(string $token): ?DiningTable
    {
        if (! preg_match('/^[A-Za-z0-9]{16,40}$/', $token)) {
            return null;
        }

        return DiningTable::where('qr_token', $token)->where('is_active', true)->with('openCommand')->first();
    }

    /**
     * Pode pedir agora? Com o motivo para mostrar ao cliente quando não pode.
     *
     * @return array{ordering: bool, reason: ?string, command: ?Command}
     */
    public function state(DiningTable $table): array
    {
        $command = $table->openCommand;
        $mode = self::mode();

        $reason = match (true) {
            $mode === 'off' => 'Os pedidos pelo celular estão desligados. Chame o garçom para pedir.',
            $command?->status === CommandStatus::Closing => 'A conta desta mesa já foi pedida. Para pedir mais, chame o garçom.',
            ! $command && $mode === 'open' => 'Para pedir pelo celular, peça ao garçom para abrir a mesa.',
            ! $command && ! OpeningSchedule::load()->isOpenAt(CarbonImmutable::now(Setting::read('timezone', 'America/Sao_Paulo'))) => 'Estamos fechados agora.',
            default => null,
        };

        return ['ordering' => $reason === null, 'reason' => $reason, 'command' => $command];
    }

    /**
     * Manda os itens para a comanda da mesa (abre a comanda quando o modo permite).
     *
     * @param  list<array>  $lines
     */
    public function place(DiningTable $table, array $lines, ?string $name, ?string $notes): Order
    {
        ['ordering' => $ordering, 'reason' => $reason, 'command' => $command] = $this->state($table);

        if (! $ordering) {
            throw ValidationException::withMessages(['lines' => $reason]);
        }

        $key = 'table-qr:'.tenant()->getKey().'|'.$table->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ORDERS)) {
            throw ValidationException::withMessages(['lines' => 'Muitos pedidos seguidos nesta mesa. Chame o garçom.']);
        }

        $command ??= $this->openCommand($table);
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));

        $order = app(PlaceOrder::class)->handle([
            'lines' => $lines,
            'fulfillment' => Fulfillment::Table->value,
            'command_id' => $command->id,
            'customer' => ['name' => $command->displayName().($name !== '' ? ' · '.mb_substr($name, 0, 40) : '')],
            'notes' => $notes,
            'via_qr' => true,
        ], OrderSource::Table);

        RateLimiter::hit($key, 600);

        return $order;
    }

    /** Chamar o garçom ou pedir a conta: aparece no mapa de mesas e avisa no celular dos garçons. */
    public function call(DiningTable $table, string $reason): void
    {
        abort_unless(array_key_exists($reason, self::CALL_REASONS), 422);

        $repeated = $table->call_reason === $reason && $table->called_at?->gt(now()->subSeconds(self::CALL_COOLDOWN_SECONDS));
        $table->forceFill(['called_at' => now(), 'call_reason' => $reason])->save();

        // Pedir a conta pelo celular: a comanda vai para "conta pedida" (o caixa vê no PDV).
        if ($reason === 'bill' && $table->openCommand?->status === CommandStatus::Open) {
            $this->commands->requestBill($table->openCommand, null);
        }

        if (! $repeated) {
            $this->alerts->tableCall($table, self::CALL_REASONS[$reason]);
        }
    }

    public function answer(DiningTable $table): void
    {
        $table->forceFill(['called_at' => null, 'call_reason' => null])->save();
    }

    private function openCommand(DiningTable $table): Command
    {
        try {
            return $this->commands->open($table, null, 1, null);
        } catch (ValidationException) {
            // Outro celular da mesa abriu ao mesmo tempo: usa a comanda dele.
            return $table->openCommand()->firstOrFail();
        }
    }
}
