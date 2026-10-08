<?php

declare(strict_types=1);

return [
    'requisicoes' => [
        // Em produção, defina APP_COOKIE_KEY no ambiente com um valor aleatório e longo.
        'cookieKey' => $_ENV['APP_COOKIE_KEY'] ?? 'chave-de-desenvolvimento-troque-em-producao',
    ],
];
