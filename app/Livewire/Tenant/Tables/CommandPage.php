<?php

namespace App\Livewire\Tenant\Tables;

use App\Livewire\Tenant\Concerns\KeepsDraft;
use App\Livewire\Tenant\Concerns\PicksMenuItems;
use App\Livewire\Tenant\Concerns\Toasts;
use App\Modules\Access\Models\User;
use App\Modules\Cash\CashLedger;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\PaymentMethod;
use App\Modules\Tables\Actions\ManageCommand;
use App\Modules\Tables\Models\Command;
use App\Modules\Tables\Models\DiningTable;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CommandPage extends Component
{
    use KeepsDraft, PicksMenuItems, Toasts;

    #[Locked]
    public int $commandId;

    public bool $adding = false;

    public string $sendNotes = '';

    // Ações da comanda
    public ?int $transferTo = null;

    public ?int $mergeWith = null;

    public ?int $cancellingOrderId = null;

    public string $cancelReason = '';

    // Fechamento
    public bool $serviceFee = true;

    public string $discount = '';

    /** @var list<array{method: string, amount: string}> */
    public array $payments = [];

    public ?string $change = null;

    public function mount(Command $command): void
    {
        $this->commandId = $command->id;
        $this->serviceFee = $command->service_fee_enabled;
        $this->adding = $command->status->isOpen() && $command->orders()->doesntExist();
        $this->payments = [['method' => (string) PaymentMethod::active()->value('code'), 'amount' => '']];

        // Itens escolhidos e ainda não enviados à cozinha voltam se a página recarregar.
        if ($command->status->isOpen()) {
            $this->restoreDraft();
            $this->adding = $this->adding || $this->draftRestored;
        }
    }

    protected function draftKey(): string
    {
        return "comanda-draft:{$this->commandId}";
    }

    protected function draftFields(): array
    {
        return ['cart', 'sendNotes'];
    }

    /** "Começar do zero": descarta os itens recuperados. */
    public function discardDraft(): void
    {
        $this->reset('cart', 'sendNotes');
        $this->forgetDraft();
    }

    private function command(): Command
    {
        return Command::with('diningTable')->findOrFail($this->commandId);
    }

    private function user(): User
    {
        return Auth::guard('web')->user();
    }

    public function send(ManageCommand $manage): void
    {
        $this->authorize('tables.manage');

        if (! $this->cart) {
            return;
        }

        $this->attempt(function () use ($manage) {
            $order = $manage->addItems($this->command(), $this->cartLines(), $this->user(), $this->sendNotes);
            $this->reset('cart', 'sendNotes');
            $this->adding = false;
            $this->toast("Itens enviados para a cozinha (pedido #{$order->number}).");
        });
    }

    public function requestBill(ManageCommand $manage): void
    {
        $this->authorize('tables.manage');
        $this->attempt(fn () => $manage->requestBill($this->command(), $this->user()));
    }

    public function reopen(ManageCommand $manage): void
    {
        $this->authorize('tables.manage');
        $this->attempt(fn () => $manage->reopen($this->command()));
    }

    public function transfer(ManageCommand $manage): void
    {
        $this->authorize('tables.manage');
        $this->attempt(function () use ($manage) {
            $manage->transfer($this->command(), DiningTable::findOrFail($this->transferTo), $this->user());
            $this->transferTo = null;
        });
    }

    public function merge(ManageCommand $manage): void
    {
        $this->authorize('tables.manage');
        $this->attempt(function () use ($manage) {
            $manage->merge($this->command(), Command::findOrFail($this->mergeWith), $this->user());
            $this->mergeWith = null;
        });
    }

    public function cancelOrder(ChangeOrderStatus $change): void
    {
        $this->authorize('orders.cancel');
        $order = Order::where('command_id', $this->commandId)->findOrFail($this->cancellingOrderId);

        $this->attempt(function () use ($change, $order) {
            $change->handle($order, OrderStatus::Cancelled, $this->user(), $this->cancelReason);
            $this->reset('cancellingOrderId', 'cancelReason');
        });
    }

    public function cancelCommand(ManageCommand $manage): void
    {
        $this->authorize('tables.manage');
        $this->attempt(function () use ($manage) {
            $manage->cancel($this->command(), $this->user(), $this->cancelReason ?: 'Comanda aberta por engano');
            $this->redirectRoute('tables.map', navigate: true);
        });
    }

    public function addPayment(): void
    {
        $this->payments[] = ['method' => (string) PaymentMethod::active()->value('code'), 'amount' => ''];
    }

    public function removePayment(int $index): void
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    /** Preenche o valor que falta na última linha de pagamento. */
    public function fillRemaining(ManageCommand $manage): void
    {
        $total = $manage->totals($this->command(), $this->serviceFee, $this->discountValue())['total'];
        $paid = $this->paidValue(except: array_key_last($this->payments));
        $remaining = $total->subtract($paid);
        $this->payments[array_key_last($this->payments)]['amount'] = $remaining->cents > 0 ? $remaining->decimal() : '';
    }

    public function close(ManageCommand $manage): void
    {
        $this->authorize('cash.operate');

        foreach ($this->payments as $i => $p) {
            if (! Money::isValid($p['amount']) || Money::parse($p['amount'])->cents <= 0) {
                $this->addError('payments', 'Informe o valor de cada pagamento.');

                return;
            }
        }

        if ($this->discount !== '' && ! Money::isValid($this->discount)) {
            $this->addError('discount', 'Valor inválido.');

            return;
        }

        $this->attempt(function () use ($manage) {
            $result = $manage->close(
                $this->command(),
                $this->user(),
                array_map(fn ($p) => ['method' => $p['method'], 'amount' => Money::parse($p['amount'])], $this->payments),
                $this->serviceFee,
                $this->discountValue(),
            );

            $this->change = $result['change']->isZero() ? null : $result['change']->format();
            $this->toast('Conta fechada.'.($this->change ? " Troco: {$this->change}." : ''));
        });
    }

    private function discountValue(): Money
    {
        return $this->discount !== '' && Money::isValid($this->discount) ? Money::parse($this->discount) : Money::zero();
    }

    private function paidValue(?int $except = null): Money
    {
        $total = Money::zero();

        foreach ($this->payments as $i => $p) {
            if ($i !== $except && Money::isValid($p['amount'])) {
                $total = $total->add(Money::parse($p['amount']));
            }
        }

        return $total;
    }

    private function attempt(callable $callback): void
    {
        $this->resetErrorBag();

        try {
            $callback();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError(str_starts_with($key, 'lines') ? 'cart' : $key, $messages[0]);
            }
        }
    }

    public function render()
    {
        $manage = app(ManageCommand::class);
        $command = $this->command();
        $totals = $command->status->isOpen()
            ? $manage->totals($command, $this->serviceFee, $this->discountValue())
            : ['subtotal' => $command->subtotal_cents, 'service_fee' => $command->service_fee_cents, 'discount' => $command->discount_cents, 'total' => $command->total_cents];

        return view('livewire.tenant.tables.command-page', $this->pickerData() + [
            'command' => $command,
            'orders' => $command->orders()->with('items.addons', 'createdBy', 'runner')->get(),
            'totals' => $totals,
            'paid' => $this->paidValue(),
            'serviceFeePercent' => Command::serviceFeePercent(),
            'freeTables' => DiningTable::where('is_active', true)->whereDoesntHave('openCommand')->ordered()->get(),
            'otherCommands' => Command::open()->whereKeyNot($command->id)->with('diningTable')->get(),
            'paymentMethods' => PaymentMethod::active()->get(),
            'hasCash' => app(CashLedger::class)->currentFor($this->user()) !== null,
            'receipts' => $command->payments()->with('method')->get(),
            'cartSubtotal' => $this->cartSubtotal(),
        ])->title($command->displayName());
    }
}
