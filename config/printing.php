<?php

// Impressão na cozinha.

return [

    // Impressora de rede (ESC/POS na porta 9100): só faz sentido quando o servidor está na
    // rede do restaurante (instalação local). Na nuvem fica desligado; use a estação de
    // impressão no navegador. Ligado, só portas 9100-9109 são aceitas.
    'network_enabled' => (bool) env('PRINTING_NETWORK_ENABLED', false),

    'timeout' => (float) env('PRINTING_TIMEOUT', 3),

];
