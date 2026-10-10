<?php

// Operação da plataforma: backups, exclusão de estabelecimentos, planos e cobrança.

return [

    'backups' => [
        // Quantos backups diários manter e por quantas semanas guardar o de domingo.
        'keep_daily' => (int) env('BACKUPS_KEEP_DAILY', 7),
        'keep_weekly' => (int) env('BACKUPS_KEEP_WEEKLY', 4),
        // Manuais, "antes da restauração" e o final da exclusão.
        'keep_other_days' => (int) env('BACKUPS_KEEP_OTHER_DAYS', 30),
        'timeout' => (int) env('BACKUPS_TIMEOUT', 900),
    ],

    // Dias entre "agendar exclusão" e a remoção definitiva dos dados.
    'deletion_grace_days' => (int) env('TENANT_DELETION_GRACE_DAYS', 30),

    'billing' => [
        // fake (desenvolvimento/testes, nada sai da máquina) ou asaas.
        'driver' => env('BILLING_DRIVER', 'fake'),
        // Dias com fatura vencida antes de suspender o acesso.
        'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),
        // Aviso de fim do teste grátis, N dias antes.
        'trial_reminder_days' => 3,
    ],

    // Segredo compartilhado com o Caddy (/caddy/ask): só ele consulta quais domínios são nossos.
    'caddy_ask_token' => env('CADDY_ASK_TOKEN', ''),

    // Cadastro self-service: tentativas por IP por hora.
    'signup_per_hour' => (int) env('SIGNUP_PER_HOUR', 5),

];
