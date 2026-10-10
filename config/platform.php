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

];
