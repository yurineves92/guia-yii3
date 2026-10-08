<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Senha;

use App\Seguranca\Usuario;
use App\Seguranca\UsuarioRepositorio;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private FormHydrator $formHydrator,
        private CurrentUser $currentUser,
        private UsuarioRepositorio $usuarios,
        private PasswordHasher $passwordHasher,
        private FlashInterface $flash,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        /** @var Usuario $usuario */
        $usuario = $this->currentUser->getIdentity();
        $form = new TrocarSenhaForm($usuario->email);

        if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
            if ($this->passwordHasher->validate($form->senhaAtual, $usuario->senhaHash)) {
                $this->usuarios->alterarSenha($usuario->id, $this->passwordHasher->hash($form->novaSenha));
                $this->flash->set('aviso', 'Senha alterada.');

                return $this->responseFactory
                    ->createResponse(Status::FOUND)
                    ->withHeader('Location', $this->urlGenerator->generate('seguranca/painel'));
            }

            $form->addError('A senha atual está incorreta.', ['senhaAtual']);
        }

        // Senhas nunca voltam preenchidas para o navegador
        $form->senhaAtual = $form->novaSenha = $form->confirmacao = '';

        return $this->viewRenderer->render(__DIR__ . '/formulario', ['form' => $form]);
    }
}
