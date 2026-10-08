<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Perfil;

use App\Seguranca\Rbac\Permissao;
use App\Seguranca\UsuarioRepositorio;
use App\Web\NotFound\NotFoundHandler;
use App\Web\Seguranca\Shared\Proibido;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Http\Status;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private FormHydrator $formHydrator,
        private CurrentUser $currentUser,
        private UsuarioRepositorio $usuarios,
        private NotFoundHandler $notFoundHandler,
        private Proibido $proibido,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(ServerRequestInterface $request, #[RouteArgument('id')] int $id): ResponseInterface
    {
        $usuario = $this->usuarios->findIdentity((string) $id);
        if ($usuario === null) {
            return $this->notFoundHandler->handle($request);
        }

        // A regra do RBAC recebe o id do perfil para comparar com o usuário logado
        if (!$this->currentUser->can(Permissao::EditarPerfil, ['usuarioId' => $usuario->id])) {
            return $this->proibido->handle($request);
        }

        $form = new PerfilForm();
        $form->nome = $usuario->nome;

        if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
            $this->usuarios->alterarNome($usuario->id, $form->nome);

            return $this->responseFactory
                ->createResponse(Status::FOUND)
                ->withHeader('Location', $this->urlGenerator->generate('seguranca/painel'));
        }

        return $this->viewRenderer->render(__DIR__ . '/formulario', [
            'form' => $form,
            'usuario' => $usuario,
        ]);
    }
}
