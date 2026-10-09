<?php

namespace App\Tenancy\Console;

use Database\Seeders\Central\CentralDatabaseSeeder;
use Illuminate\Console\Command;

class CentralMigrate extends Command
{
    protected $signature = 'central:migrate {--fresh : apaga as tabelas do public e recria} {--seed : roda o seeder central}';

    protected $description = 'Roda as migrations do schema public (plataforma)';

    public function handle(): int
    {
        $exitCode = $this->call($this->option('fresh') ? 'migrate:fresh' : 'migrate', [
            '--database' => 'central',
            '--path' => 'database/migrations/central',
            '--force' => true,
        ]);

        if ($exitCode === self::SUCCESS && $this->option('seed')) {
            $exitCode = $this->call('db:seed', [
                '--database' => 'central',
                '--class' => CentralDatabaseSeeder::class,
                '--force' => true,
            ]);
        }

        return $exitCode;
    }
}
