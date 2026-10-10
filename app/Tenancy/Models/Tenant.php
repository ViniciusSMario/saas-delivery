<?php

namespace App\Tenancy\Models;

use App\Platform\Models\Invoice;
use App\Platform\Models\Plan;
use App\Tenancy\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property string $schema_name
 * @property TenantStatus $status
 */
class Tenant extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $connection = 'central';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'deletion_scheduled_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'past_due_since' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // O nome do schema é gerado pelo sistema e nunca muda.
        static::creating(function (Tenant $tenant) {
            $tenant->schema_name ??= static::generateSchemaName();
        });

        static::updating(function (Tenant $tenant) {
            if ($tenant->isDirty('schema_name')) {
                throw new LogicException('schema_name é imutável.');
            }
        });
    }

    public static function generateSchemaName(): string
    {
        return 'tenant_'.Str::lower((string) Str::ulid());
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TenantEvent::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }

    public function recordEvent(string $type, array $payload = [], ?string $actor = null): TenantEvent
    {
        return $this->events()->create([
            'type' => $type,
            'payload' => $payload,
            'actor' => $actor ?? (app()->runningInConsole() ? 'console' : null),
        ]);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Link de pagamento da fatura em aberto mais antiga (tela de conta suspensa, aviso no painel). */
    public function openInvoiceUrl(): ?string
    {
        return $this->invoices()->open()->whereNotNull('invoice_url')->orderBy('due_date')->value('invoice_url');
    }

    /** Limite do plano (null = ilimitado; sem plano = cortesia, sem limites). */
    public function planLimit(string $key): ?int
    {
        return $this->plan?->limit($key);
    }

    /**
     * Barra o cadastro que passaria do limite do plano (usuários, mesas, produtos).
     *
     * @throws ValidationException
     */
    public function assertWithinLimit(string $key, int $current, string $field, int $adding = 1): void
    {
        $limit = $this->planLimit($key);

        if ($limit !== null && $current + $adding > $limit) {
            $label = Plan::LIMIT_LABELS[$key][0] ?? $key;

            throw ValidationException::withMessages([
                $field => "Seu plano ({$this->plan->name}) permite até {$limit} {$label}. Para cadastrar mais, troque de plano em Assinatura.",
            ]);
        }
    }

    /** Recurso liberado no plano (sem plano = tudo liberado). */
    public function hasFeature(string $feature): bool
    {
        return $this->plan?->hasFeature($feature) ?? true;
    }

    public function onTrial(): bool
    {
        return $this->subscription_status === 'trialing' && $this->trial_ends_at?->isFuture();
    }

    public function trialDaysLeft(): int
    {
        return $this->trial_ends_at ? max(0, (int) ceil(now()->diffInHours($this->trial_ends_at, false) / 24)) : 0;
    }

    public function primaryDomain(): ?string
    {
        return $this->domains()
            ->where(fn ($q) => $q->where('type', 'subdomain')->orWhereNotNull('verified_at'))
            ->orderByDesc('is_primary')->orderBy('id')->value('domain');
    }
}
