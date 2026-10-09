<?php

namespace App\Modules\Cash;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use App\Modules\Cash\Events\CashSessionClosed;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Orders\Models\OrderPayment;
use App\Modules\Orders\Models\PaymentMethod;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Abertura, movimentos e fechamento do caixa. Todo dinheiro recebido ou retirado
 * passa por aqui e vira uma linha em cash_movements.
 */
class CashLedger
{
    public function currentFor(User $user): ?CashSession
    {
        return CashSession::open()->where('opened_by', $user->id)->first();
    }

    /** Sessão que recebe um pagamento: a do operador ou, se ele não tem, a aberta mais recente. */
    public function sessionForPayment(?User $user): ?CashSession
    {
        return ($user ? $this->currentFor($user) : null)
            ?? CashSession::open()->latest('opened_at')->latest('id')->first();
    }

    public function requireSession(?User $user): CashSession
    {
        return ($user ? $this->currentFor($user) : null)
            ?? throw ValidationException::withMessages(['cash' => 'Abra o caixa antes de receber.']);
    }

    public function open(CashRegister $register, User $user, Money $opening): CashSession
    {
        if ($opening->cents < 0) {
            throw ValidationException::withMessages(['opening' => 'O fundo de troco não pode ser negativo.']);
        }

        try {
            $session = DB::connection('tenant')->transaction(function () use ($register, $user, $opening) {
                $session = CashSession::create([
                    'cash_register_id' => $register->id,
                    'status' => 'open',
                    'opened_by' => $user->id,
                    'opening_cents' => $opening,
                    'opened_at' => now(),
                ]);

                if (! $opening->isZero()) {
                    $this->move($session, MovementType::Opening, $this->cash(), $opening, $user);
                }

                return $session;
            });
        } catch (QueryException $e) {
            // Índices únicos parciais: caixa já aberto, ou operador com outro caixa aberto.
            if ($e->getCode() === '23505') {
                throw ValidationException::withMessages(['register' => 'Este caixa já está aberto, ou você já tem um caixa aberto.']);
            }

            throw $e;
        }

        Audit::record('cash.opened', $session, ['caixa' => $register->name, 'fundo' => $opening->format()], $user);

        return $session;
    }

    public function move(CashSession $session, MovementType $type, PaymentMethod $method, Money $amount, ?User $user, array $refs = [], ?string $reason = null): CashMovement
    {
        if (! $session->isOpen()) {
            throw ValidationException::withMessages(['cash' => 'Este caixa já foi fechado.']);
        }

        if ($amount->cents <= 0) {
            throw ValidationException::withMessages(['amount' => 'Informe um valor maior que zero.']);
        }

        return $session->movements()->create([
            'type' => $type,
            'payment_method_id' => $method->id,
            'amount_cents' => $amount,
            'order_id' => $refs['order_id'] ?? null,
            'command_id' => $refs['command_id'] ?? null,
            'user_id' => $user?->id,
            'reason' => $reason,
        ]);
    }

    /** Sangria (retirada) ou suprimento (reforço) em dinheiro, com motivo. */
    public function adjust(CashSession $session, MovementType $type, Money $amount, User $user, string $reason): CashMovement
    {
        if (! in_array($type, [MovementType::Withdrawal, MovementType::Deposit], true)) {
            throw ValidationException::withMessages(['type' => 'Movimento inválido.']);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Informe o motivo.']);
        }

        return DB::connection('tenant')->transaction(function () use ($session, $type, $amount, $user, $reason) {
            $locked = CashSession::lockForUpdate()->findOrFail($session->id);

            if ($type === MovementType::Withdrawal && $amount->greaterThan($locked->expectedByMethod()[$this->cash()->code])) {
                throw ValidationException::withMessages(['amount' => 'A sangria é maior que o dinheiro em caixa.']);
            }

            $movement = $this->move($locked, $type, $this->cash(), $amount, $user, reason: trim($reason));
            Audit::record('cash.'.$type->value, $locked, ['valor' => $amount->format(), 'motivo' => trim($reason)], $user);

            return $movement;
        });
    }

    /**
     * Fechamento com conferência: o operador informa quanto contou por forma de
     * pagamento; a diferença (sobra ou falta) fica registrada.
     *
     * @param  array<string, Money>  $counted  código => valor contado
     */
    public function close(CashSession $session, User $user, array $counted, ?string $notes = null): CashSession
    {
        $closed = DB::connection('tenant')->transaction(function () use ($session, $user, $counted, $notes) {
            $locked = CashSession::lockForUpdate()->findOrFail($session->id);

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['cash' => 'Este caixa já foi fechado.']);
            }

            $expected = $locked->expectedByMethod();
            $countedCents = [];
            $difference = 0;

            foreach ($expected as $code => $amount) {
                $countedCents[$code] = ($counted[$code] ?? Money::zero())->cents;
                $difference += $countedCents[$code] - $amount->cents;
            }

            $locked->update([
                'status' => 'closed',
                'closed_by' => $user->id,
                'closed_at' => now(),
                'expected' => array_map(fn (Money $m) => $m->cents, $expected),
                'counted' => $countedCents,
                'difference_cents' => Money::cents($difference),
                'notes' => $notes ? trim($notes) : null,
            ]);

            Audit::record('cash.closed', $locked, ['diferenca' => Money::cents($difference)->format()], $user);

            return $locked;
        });

        event(new CashSessionClosed($closed));

        return $closed;
    }

    /**
     * Pagamento de pedido recebido: entra na sessão informada ou na que estiver aberta.
     * Sem caixa aberto (ex.: entrega concluída fora do expediente), fica pago sem lançamento.
     */
    public function recordOrderPayment(OrderPayment $payment, ?User $user, ?CashSession $session = null): void
    {
        $session ??= $this->sessionForPayment($user);

        if (! $session) {
            return;
        }

        $this->move($session, MovementType::Sale, $payment->method, $payment->amount_cents, $user, ['order_id' => $payment->order_id]);
        $payment->update(['cash_session_id' => $session->id]);
    }

    /** Estorno de um pagamento já recebido, se o caixa onde entrou ainda está aberto. */
    public function refundOrderPayment(OrderPayment $payment, ?User $user): void
    {
        $session = $payment->cash_session_id ? CashSession::find($payment->cash_session_id) : null;

        if ($session?->isOpen()) {
            $this->move($session, MovementType::Refund, $payment->method, $payment->amount_cents, $user, ['order_id' => $payment->order_id], 'Pedido cancelado');
        }
    }

    public function cash(): PaymentMethod
    {
        return PaymentMethod::where('code', 'cash')->firstOrFail();
    }
}
