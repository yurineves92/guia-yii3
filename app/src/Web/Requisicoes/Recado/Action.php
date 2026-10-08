<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Recado;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
        private FlashInterface $flash,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === Method::POST) {
            $this->flash->set('sucesso', 'Recado salvo.');

            return $this->responseFactory
                ->createResponse(Status::SEE_OTHER)
                ->withHeader(Header::LOCATION, $this->urlGenerator->generate('requisicoes/recado'));
        }

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'mensagem' => $this->flash->get('sucesso'),
        ]);
    }
}
