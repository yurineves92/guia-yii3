<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Painel;

use App\Seguranca\Usuario;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private CurrentUser $currentUser,
        private ManagerInterface $rbac,
        private FlashInterface $flash,
    ) {}

    public function __invoke(): ResponseInterface
    {
        /** @var Usuario $usuario */
        $usuario = $this->currentUser->getIdentity();

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'usuario' => $usuario,
            'papeis' => array_keys($this->rbac->getRolesByUserId($usuario->id)),
            'currentUser' => $this->currentUser,
            'aviso' => $this->flash->get('aviso'),
        ]);
    }
}
