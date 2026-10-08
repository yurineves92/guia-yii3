<?php

declare(strict_types=1);

use App\Seguranca\Cripto\Cofre;
use App\Seguranca\Cripto\TokenAssinado;
use App\Seguranca\UsuarioRepositorio;
use Yiisoft\Access\AccessCheckerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Definitions\Reference;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Php\AssignmentsStorage;
use Yiisoft\Rbac\Php\ItemsStorage;

/** @var array $params */

return [
    // Onde o yiisoft/user busca o usuário logado a partir do id guardado na sessão
    IdentityRepositoryInterface::class => UsuarioRepositorio::class,

    // RBAC: papéis e permissões num arquivo PHP, atribuições em outro
    ItemsStorageInterface::class => static fn(Aliases $aliases) => new ItemsStorage(
        $aliases->get('@runtime/rbac/itens.php'),
    ),
    AssignmentsStorageInterface::class => static fn(Aliases $aliases) => new AssignmentsStorage(
        $aliases->get('@runtime/rbac/atribuicoes.php'),
    ),

    // Quem responde "este usuário tem esta permissão?" é o Manager do RBAC
    AccessCheckerInterface::class => Reference::to(ManagerInterface::class),

    // Classes que precisam da chave secreta
    TokenAssinado::class => [
        '__construct()' => ['chaveSecreta' => $params['app/seguranca']['chaveSecreta']],
    ],
    Cofre::class => [
        '__construct()' => ['chaveSecreta' => $params['app/seguranca']['chaveSecreta']],
    ],
];
