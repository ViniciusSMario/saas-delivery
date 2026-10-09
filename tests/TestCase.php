<?php

namespace Tests;

use App\Tenancy\Actions\ProvisionTenant;
use App\Tenancy\SchemaManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /** Banco de teste preparado uma vez por execução da suíte. */
    private static bool $prepared = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        if (! self::$prepared) {
            $this->prepareDatabase();
            self::$prepared = true;
        }
    }

    /**
     * public recriado do zero, schemas de tenant de execuções anteriores removidos,
     * Redis de teste limpo e dois tenants de referência: teste1 e teste2.
     */
    private function prepareDatabase(): void
    {
        if (DB::connection('central')->getDatabaseName() !== 'saas_test') {
            throw new RuntimeException('Os testes só rodam no banco saas_test.');
        }

        $schemas = app(SchemaManager::class);

        foreach ($schemas->all() as $schema) {
            $schemas->drop($schema);
        }

        Artisan::call('central:migrate', ['--fresh' => true, '--seed' => true]);

        Redis::connection('default')->flushdb();
        Redis::connection('cache')->flushdb();

        $provision = app(ProvisionTenant::class);
        $provision->handle('teste1', 'Tenant Teste 1');
        $provision->handle('teste2', 'Tenant Teste 2');
    }
}
