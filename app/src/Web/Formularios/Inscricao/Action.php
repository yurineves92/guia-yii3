<?php

declare(strict_types=1);

namespace App\Web\Formularios\Inscricao;

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
        $form = new InscricaoForm();

        if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
            return $this->viewRenderer->render(__DIR__ . '/confirmada', ['form' => $form]);
        }

        return $this->viewRenderer->render(__DIR__ . '/formulario', ['form' => $form]);
    }
}
