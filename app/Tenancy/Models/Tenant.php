<?php

namespace App\Tenancy\Models;

use App\Platform\Models\Plan;
use App\Tenancy\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
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

    protected $connection = 'central';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
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

    public function primaryDomain(): ?string
    {
        return $this->domains()->orderByDesc('is_primary')->value('domain');
    }
}
