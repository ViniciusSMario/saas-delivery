<?php

namespace Tests\Fixtures;

use Illuminate\Database\Seeder;
use RuntimeException;

class FailingSeeder extends Seeder
{
    public function run(): void
    {
        throw new RuntimeException('falha simulada no seed');
    }
}
