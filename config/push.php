<?php

return [
    /*
    | Notificações no celular (Web Push). "webpush" envia de verdade; "fake" só
    | registra em memória (testes). As chaves VAPID identificam a plataforma para os
    | serviços de push (Google, Apple, Mozilla) e servem para todos os restaurantes:
    | gere uma vez com `php artisan push:vapid` e nunca troque (os celulares já
    | inscritos param de receber).
    */
    'driver' => env('PUSH_DRIVER', 'webpush'),

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:'.env('MAIL_FROM_ADDRESS', 'suporte@example.com')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    // Aviso de "pedido pronto" com mais de 10 minutos na fila do serviço de push não serve mais.
    'ttl' => (int) env('PUSH_TTL', 600),
];
