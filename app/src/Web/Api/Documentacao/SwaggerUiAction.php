<?php

declare(strict_types=1);

namespace App\Web\Api\Documentacao;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Header;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Página com o Swagger UI, carregado de uma CDN, apontando para a especificação.
 */
final readonly class SwaggerUiAction
{
    private const VERSAO_SWAGGER_UI = '5.17.14';

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $cdn = 'https://unpkg.com/swagger-ui-dist@' . self::VERSAO_SWAGGER_UI;
        $especificacao = json_encode($this->urlGenerator->generate('api/v1/openapi'), JSON_UNESCAPED_SLASHES);

        $html = <<<HTML
            <!doctype html>
            <html lang="pt-BR">
            <head>
                <meta charset="utf-8">
                <title>API de tarefas</title>
                <link rel="stylesheet" href="$cdn/swagger-ui.css">
            </head>
            <body>
                <div id="swagger-ui"></div>
                <script src="$cdn/swagger-ui-bundle.js"></script>
                <script>
                    SwaggerUIBundle({ url: $especificacao, dom_id: '#swagger-ui' });
                </script>
            </body>
            </html>
            HTML;

        $response = $this->responseFactory
            ->createResponse()
            ->withHeader(Header::CONTENT_TYPE, 'text/html; charset=utf-8');
        $response->getBody()->write($html);

        return $response;
    }
}
