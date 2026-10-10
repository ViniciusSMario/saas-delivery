<?php

namespace App\Modules\Delivery;

use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Establishment\Models\Setting;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Taxa de entrega: valor único (padrão) ou por bairro. Por bairro, só entrega
 * nos bairros cadastrados; o nome é comparado sem acento, caixa ou espaços extras.
 */
class DeliveryFees
{
    public const MODE_FIXED = 'fixed';

    public const MODE_ZONES = 'zones';

    public static function mode(): string
    {
        return Setting::read('delivery_fee_mode', self::MODE_FIXED) === self::MODE_ZONES ? self::MODE_ZONES : self::MODE_FIXED;
    }

    public static function byZone(): bool
    {
        return self::mode() === self::MODE_ZONES;
    }

    /**
     * @return array{fee: Money, zone: DeliveryZone|null}
     *
     * @throws ValidationException bairro fora da área de entrega
     */
    public function resolve(?string $district): array
    {
        if (! self::byZone()) {
            return ['fee' => Money::cents((int) Setting::read('delivery_fee_cents', 0)), 'zone' => null];
        }

        $zone = $this->find($district);

        if (! $zone) {
            throw ValidationException::withMessages([
                'address.district' => 'Ainda não entregamos no bairro "'.trim((string) $district).'". Escolha um dos bairros atendidos.',
            ]);
        }

        return ['fee' => $zone->fee_cents, 'zone' => $zone];
    }

    /** Para telas: taxa do bairro, ou null quando o bairro não é atendido. */
    public function quote(?string $district): ?Money
    {
        if (! self::byZone()) {
            return Money::cents((int) Setting::read('delivery_fee_cents', 0));
        }

        return $this->find($district)?->fee_cents;
    }

    /** Menor taxa (para "entrega a partir de"). */
    public function cheapest(): Money
    {
        if (! self::byZone()) {
            return Money::cents((int) Setting::read('delivery_fee_cents', 0));
        }

        return Money::cents((int) DeliveryZone::active()->min('fee_cents'));
    }

    /** @return Collection<int, DeliveryZone> */
    public function zones(): Collection
    {
        return DeliveryZone::active()->ordered()->get();
    }

    public function find(?string $district): ?DeliveryZone
    {
        $key = self::normalize($district);

        return $key === '' ? null : $this->zones()->first(fn (DeliveryZone $zone) => self::normalize($zone->name) === $key);
    }

    public static function normalize(?string $name): string
    {
        return Str::of((string) $name)->ascii()->lower()->squish()->toString();
    }
}
