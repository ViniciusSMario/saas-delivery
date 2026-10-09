<?php

namespace App\Modules\Customers\Models;

use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Cliente final. No MVP, identificado por nome e telefone, sem login. */
class Customer extends TenantModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'phone', 'email', 'notes', 'last_order_at'];

    protected function casts(): array
    {
        return ['last_order_at' => 'datetime'];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->latest('last_used_at');
    }

    /** Só dígitos; aceita "(11) 98888-7777", "+55 11 98888-7777"... */
    public static function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (strlen($digits) > 11 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    public static function isValidPhone(?string $phone): bool
    {
        return (bool) preg_match('/^[1-9]{2}9?\d{8}$/', self::normalizePhone($phone));
    }

    public static function formatPhone(?string $phone): string
    {
        $d = self::normalizePhone($phone);

        return match (strlen($d)) {
            11 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7)),
            10 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6)),
            default => (string) $phone,
        };
    }
}
