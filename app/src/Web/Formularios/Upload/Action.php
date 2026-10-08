<?php

declare(strict_types=1);

namespace App\Web\Formularios\Upload;

use App\Formularios\Upload\ArmazenamentoDeFotos;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private FormHydrator $formHydrator,
        private ArmazenamentoDeFotos $armazenamento,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $form = new FotoForm();

        if ($request->getMethod() === 'POST') {
            $dados = array_replace_recursive(
                (array) $request->getParsedBody(),
                $request->getUploadedFiles(),
            );

            if ($this->formHydrator->populateAndValidate($form, $dados)) {
                $url = $this->armazenamento->salvar($form->foto);

                return $this->viewRenderer->render(__DIR__ . '/enviada', [
                    'legenda' => $form->legenda,
                    'url' => $url,
                ]);
            }
        }

        return $this->viewRenderer->render(__DIR__ . '/formulario', ['form' => $form]);
    }
}
