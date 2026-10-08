<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Login;

use App\Seguranca\UsuarioRepositorio;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
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
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->currentUser->isGuest()) {
            return $this->irParaOPainel();
        }

        $form = new LoginForm();

        if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
            $usuario = $this->usuarios->porEmail($form->email);

            if ($usuario !== null && $this->passwordHasher->validate($form->senha, $usuario->senhaHash)) {
                if ($this->passwordHasher->needsRehash($usuario->senhaHash)) {
                    $this->usuarios->alterarSenha($usuario->id, $this->passwordHasher->hash($form->senha));
                }

                $this->currentUser->login($usuario);

                return $this->irParaOPainel();
            }

            // A mesma mensagem para e-mail inexistente e senha errada
            $form->addError('E-mail ou senha incorretos.', ['senha']);
        }

        $form->senha = '';

        return $this->viewRenderer->render(__DIR__ . '/formulario', ['form' => $form]);
    }

    private function irParaOPainel(): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse(Status::FOUND)
            ->withHeader('Location', $this->urlGenerator->generate('seguranca/painel'));
    }
}
