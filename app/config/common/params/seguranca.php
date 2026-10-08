<?php

declare(strict_types=1);

use App\Environment;
use App\Seguranca\Console\CofreCommand;
use App\Seguranca\Console\RbacCommand;

// A chave secreta vem do ambiente. Fora de produção, se ela não existir,
// usamos um valor fixo para o app funcionar sem configuração.
$chaveSecreta = getenv('SEGURANCA_CHAVE') ?: ($_ENV['SEGURANCA_CHAVE'] ?? '');
if ($chaveSecreta === '' && !Environment::isProd()) {
    $chaveSecreta = 'chave-de-desenvolvimento-nao-use-em-producao';
}

return [
    'yiisoft/user' => [
        // Para onde o usuário não logado é mandado
        'authUrl' => '/seguranca/login',
    ],

    'yiisoft/yii-console' => [
        'commands' => [
            'seguranca:rbac' => RbacCommand::class,
            'seguranca:cofre' => CofreCommand::class,
        ],
    ],

    'app/seguranca' => [
        'chaveSecreta' => $chaveSecreta,
    ],
];
