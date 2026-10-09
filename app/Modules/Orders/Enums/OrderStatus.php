<?php

namespace App\Modules\Orders\Enums;

enum OrderStatus: string
{
    case Received = 'received';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Dispatched = 'dispatched';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Recebido',
            self::Preparing => 'Em preparo',
            self::Ready => 'Pronto',
            self::Dispatched => 'Saiu para entrega',
            self::Completed => 'Concluído',
            self::Cancelled => 'Cancelado',
        };
    }

    /** Texto para o cliente na página de acompanhamento. */
    public function customerLabel(Fulfillment $fulfillment): string
    {
        return match ($this) {
            self::Received => 'Pedido recebido pelo estabelecimento',
            self::Preparing => 'Seu pedido está sendo preparado',
            self::Ready => $fulfillment === Fulfillment::Pickup ? 'Pronto para retirada' : 'Pedido pronto',
            self::Dispatched => 'Saiu para entrega',
            self::Completed => $fulfillment === Fulfillment::Delivery ? 'Entregue' : 'Concluído',
            self::Cancelled => 'Pedido cancelado',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /** Próximo passo "normal" do fluxo (botão principal do painel). */
    public function next(Fulfillment $fulfillment): ?self
    {
        return match ($this) {
            self::Received => self::Preparing,
            self::Preparing => self::Ready,
            self::Ready => $fulfillment === Fulfillment::Delivery ? self::Dispatched : self::Completed,
            self::Dispatched => self::Completed,
            default => null,
        };
    }

    public function canTransitionTo(self $to, Fulfillment $fulfillment): bool
    {
        if ($this->isFinal()) {
            return false;
        }

        return $to === self::Cancelled || $this->next($fulfillment) === $to;
    }

    /** @return list<self> status que aparecem como colunas no painel */
    public static function active(): array
    {
        return [self::Received, self::Preparing, self::Ready, self::Dispatched];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
