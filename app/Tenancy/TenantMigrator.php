<?php

namespace App\Tenancy;

use App\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Roda o migrator/seeder nativo do Laravel na conexão "tenant", um tenant por vez.
 */
class TenantMigrator
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    /** @return string saída do comando migrate */
    public function migrate(Tenant $tenant, bool $pretend = false): string
    {
        return $this->locked($tenant, function () use ($tenant, $pretend) {
            $output = $this->artisan('migrate', [
                '--path' => $this->path(),
                '--force' => true,
                '--pretend' => $pretend,
            ]);

            if (! $pretend) {
                $tenant->update(['schema_version' => $this->latestRan()]);
            }

            return $output;
        });
    }

    public function rollback(Tenant $tenant, int $step = 1): string
    {
        return $this->locked($tenant, function () use ($tenant, $step) {
            $output = $this->artisan('migrate:rollback', [
                '--path' => $this->path(),
                '--step' => $step,
                '--force' => true,
            ]);

            $tenant->update(['schema_version' => $this->latestRan()]);

            return $output;
        });
    }

    public function seed(Tenant $tenant, ?string $class = null): string
    {
        return $this->context->run($tenant, fn () => $this->artisan('db:seed', [
            '--class' => $class ?? config('tenancy.seeder'),
            '--force' => true,
        ]));
    }

    /** @return list<string> migrations do disco ainda não aplicadas no tenant */
    public function pending(Tenant $tenant): array
    {
        $files = collect(glob(base_path($this->path()).'/*.php'))
            ->map(fn (string $file) => pathinfo($file, PATHINFO_FILENAME));

        $ran = $this->context->run($tenant, function () {
            $db = DB::connection('tenant');

            return $db->getSchemaBuilder()->hasTable('migrations')
                ? $db->table('migrations')->pluck('migration')
                : collect();
        });

        return $files->diff($ran)->sort()->values()->all();
    }

    /**
     * Lock de sessão por schema: dois deploys simultâneos não migram o mesmo tenant.
     */
    private function locked(Tenant $tenant, Closure $callback): string
    {
        return $this->context->run($tenant, function () use ($callback) {
            $db = DB::connection('tenant');
            $db->select('select pg_advisory_lock(hashtext(current_schema()))');

            try {
                return $callback();
            } finally {
                $db->select('select pg_advisory_unlock(hashtext(current_schema()))');
            }
        });
    }

    private function artisan(string $command, array $parameters): string
    {
        $exitCode = Artisan::call($command, ['--database' => 'tenant'] + $parameters);
        $output = Artisan::output();

        if ($exitCode !== 0) {
            throw new RuntimeException("[{$command}] terminou com código {$exitCode}: ".trim($output));
        }

        return $output;
    }

    private function latestRan(): ?string
    {
        return DB::connection('tenant')->table('migrations')->max('migration');
    }

    private function path(): string
    {
        return config('tenancy.migrations_path');
    }
}
