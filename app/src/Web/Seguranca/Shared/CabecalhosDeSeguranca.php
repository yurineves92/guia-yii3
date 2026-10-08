<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Acrescenta headers de segurança em todas as respostas que passam por ele.
 */
final class CabecalhosDeSeguranca implements MiddlewareInterface
{
    private const HEADERS = [
        // Não deixa o navegador "adivinhar" o tipo do conteúdo
        'X-Content-Type-Options' => 'nosniff',
        // Não deixa outros sites abrirem as páginas dentro de um <iframe>
        'X-Frame-Options' => 'DENY',
        // Ao seguir um link para outro site, envia só a origem (protocolo e domínio) como Referer
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        // De onde a página pode carregar scripts, estilos, imagens...
        'Content-Security-Policy' => "default-src 'self'; style-src 'self' 'unsafe-inline'; "
            . "frame-ancestors 'none'; form-action 'self'; base-uri 'self'",
        // Recursos do navegador que a página não usa
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        foreach (self::HEADERS as $nome => $valor) {
            if (!$response->hasHeader($nome)) {
                $response = $response->withHeader($nome, $valor);
            }
        }

        return $response;
    }
}
