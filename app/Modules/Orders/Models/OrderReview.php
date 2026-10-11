<?php

namespace App\Modules\Orders\Models;

use App\Modules\Access\Models\User;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Avaliação do cliente (1 a 5 estrelas, motivos e comentário) de um pedido concluído. */
class OrderReview extends TenantModel
{
    public const UPDATED_AT = null;

    /** Dias depois da conclusão em que o cliente ainda pode avaliar. */
    public const WINDOW_DAYS = 7;

    /** Motivos que o cliente marca: o que deu errado (até 3 estrelas) e o que gostou (4 e 5). */
    public const NEGATIVE_TAGS = [
        'late' => 'Demorou',
        'cold' => 'Chegou frio',
        'missing' => 'Faltou item',
        'wrong' => 'Veio errado',
        'taste' => 'Sabor',
        'package' => 'Embalagem',
        'courier' => 'Entregador',
    ];

    public const POSITIVE_TAGS = [
        'fast' => 'Chegou rápido',
        'tasty' => 'Muito gostoso',
        'hot' => 'Chegou quentinho',
        'package_ok' => 'Bem embalado',
        'courier_ok' => 'Entregador atencioso',
    ];

    protected $fillable = ['order_id', 'rating', 'tags', 'comment', 'courier_id'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'tags' => 'array'];
    }

    public static function tagLabel(string $tag): string
    {
        return self::NEGATIVE_TAGS[$tag] ?? self::POSITIVE_TAGS[$tag] ?? $tag;
    }

    /** @return list<string> */
    public function tagLabels(): array
    {
        return array_map(fn ($tag) => self::tagLabel($tag), $this->tags ?? []);
    }

    public function stars(): string
    {
        return str_repeat('★', $this->rating).str_repeat('☆', 5 - $this->rating);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }
}
