<?php

declare(strict_types=1);

namespace App\Seguranca\Rbac;

use Yiisoft\Rbac\Item;
use Yiisoft\Rbac\RuleContext;
use Yiisoft\Rbac\RuleInterface;

/**
 * Libera a permissão só quando o perfil sendo editado é o do próprio usuário.
 */
final class ProprioPerfilRule implements RuleInterface
{
    public function execute(?string $userId, Item $item, RuleContext $context): bool
    {
        return $userId !== null
            && $userId === (string) $context->getParameterValue('usuarioId');
    }
}
