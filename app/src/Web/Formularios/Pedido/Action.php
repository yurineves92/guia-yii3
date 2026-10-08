<?php

declare(strict_types=1);

namespace App\Web\Formularios\Pedido;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private FormHydrator $formHydrator,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $form = new PedidoForm();

        if ($this->formHydrator->populateFromPost($form, $request)) {
            $form->removerItensVazios();

            if ($this->formHydrator->validate($form)->isValid()) {
                return $this->viewRenderer->render(__DIR__ . '/resumo', ['form' => $form]);
            }
        }

        return $this->viewRenderer->render(__DIR__ . '/formulario', ['form' => $form]);
    }
}
