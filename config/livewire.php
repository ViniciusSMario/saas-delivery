<?php

// Só o que muda em relação ao padrão do Livewire (o resto vem de vendor/livewire/livewire/config).
return [
    'temporary_file_upload' => [
        'disk' => null,
        // 20 MB: o .zip de fotos da importação do cardápio (o PHP aceita até 20 MB por arquivo).
        // Cada tela continua validando o próprio limite (foto de produto: 4 MB).
        'rules' => ['required', 'file', 'max:20480'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],
];
