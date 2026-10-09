<?php

namespace App\Tenancy\Console;

use App\Tenancy\Enums\TenantStatus;
use App\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\LazyCollection;
use Throwable;

/**
 * Base dos comandos tenants:* que percorrem tenants. Falha em um não para os outros.
 */
abstract class TenantCommand extends Command
{
    /** @return LazyCollection<int, Tenant> */
    protected function tenants(): LazyCollection
    {
        $query = Tenant::query()->orderBy('created_at');

        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        } else {
            $query->whereIn('status', TenantStatus::migratable());
        }

        return $query->cursor();
    }

    /**
     * @param  Closure(Tenant): string  $callback
     */
    protected function eachTenant(string $event, Closure $callback): int
    {
        $failed = [];
        $count = 0;

        foreach ($this->tenants() as $tenant) {
            $count++;

            try {
                $output = trim($callback($tenant));
                $this->components->info("{$tenant->slug} ({$tenant->schema_name})");

                if ($output !== '' && $this->output->isVerbose()) {
                    $this->line($output);
                }

                $tenant->recordEvent($event);
            } catch (Throwable $e) {
                $failed[] = $tenant->slug;
                $this->components->error("{$tenant->slug}: {$e->getMessage()}");
                $tenant->recordEvent("{$event}_failed", ['error' => $e->getMessage()]);
            }
        }

        if ($count === 0) {
            $this->components->warn('Nenhum tenant encontrado.');

            return $this->option('tenant') ? self::FAILURE : self::SUCCESS;
        }

        if ($failed !== []) {
            $this->components->error('Falharam: '.implode(', ', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
