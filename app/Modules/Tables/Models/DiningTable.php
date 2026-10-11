<?php

namespace App\Modules\Tables\Models;

use App\Modules\Tables\CommandStatus;
use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/** Mesa do salão (tabela "tables"). */
class DiningTable extends TenantModel
{
    protected $table = 'tables';

    protected $fillable = ['name', 'seats', 'area', 'is_active', 'position'];

    protected $attributes = ['is_active' => true, 'seats' => 4, 'position' => 0];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'seats' => 'integer', 'position' => 'integer', 'called_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Cada mesa nasce com o QR code dela (pedido pelo celular).
        static::creating(fn (DiningTable $table) => $table->qr_token ??= self::newQrToken());
    }

    public static function newQrToken(): string
    {
        return Str::random(24);
    }

    /** QR novo (ex.: alguém fotografou o antigo e está mandando pedido de fora): o antigo para de funcionar. */
    public function renewQrToken(): void
    {
        $this->forceFill(['qr_token' => self::newQrToken()])->save();
    }

    /** Chamado do cliente pelo celular ainda não atendido (some sozinho depois de 30 min). */
    public function pendingCall(): ?string
    {
        return $this->called_at && $this->called_at->gt(now()->subMinutes(30)) ? $this->call_reason : null;
    }

    public function commands(): HasMany
    {
        return $this->hasMany(Command::class, 'table_id');
    }

    /** Comanda em aberto (no máximo uma, garantido por índice único). */
    public function openCommand(): HasOne
    {
        return $this->hasOne(Command::class, 'table_id')->whereIn('status', CommandStatus::openValues());
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderByRaw("lpad(name, 10, '0')");
    }
}
