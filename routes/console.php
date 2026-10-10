<?php

use Illuminate\Support\Facades\Schedule;

// Rotina da plataforma (container "scheduler" roda schedule:work). Horários de Brasília.
Schedule::command('platform:backup-database')->dailyAt('02:30')->timezone('America/Sao_Paulo')->withoutOverlapping();
Schedule::command('tenants:backup --kind=daily')->dailyAt('03:00')->timezone('America/Sao_Paulo')->withoutOverlapping();
Schedule::command('tenants:prune-backups')->dailyAt('04:30')->timezone('America/Sao_Paulo');
Schedule::command('tenants:purge-deleted')->dailyAt('05:00')->timezone('America/Sao_Paulo')->withoutOverlapping();
Schedule::command('billing:check')->dailyAt('06:00')->timezone('America/Sao_Paulo')->withoutOverlapping();
