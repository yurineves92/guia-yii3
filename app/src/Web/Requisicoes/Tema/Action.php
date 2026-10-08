<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Tema;

use DateInterval;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Yiisoft\Cookies\Cookie;
use Yiisoft\Cookies\CookieCollection;
use Yiisoft\Cookies\CookieSigner;
use Yiisoft\Input\Http\Attribute\Parameter\Query;

final readonly class Action
{
    private const TEMAS = ['claro', 'escuro'];

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private CookieSigner $signer,
    ) {}

    public function __invoke(ServerRequestInterface $request, #[Query('definir')] ?string $novoTema = null): ResponseInterface
    {
        if ($novoTema !== null && in_array($novoTema, self::TEMAS, true)) {
            $cookie = (new Cookie('tema', $novoTema))->withMaxAge(new DateInterval('P30D'));

            return $this->signer->sign($cookie)->addToResponse($this->texto("Tema definido: $novoTema"));
        }

        return $this->texto('Tema atual: ' . $this->lerTema($request));
    }

    private function lerTema(ServerRequestInterface $request): string
    {
        $cookie = CookieCollection::fromArray($request->getCookieParams())->get('tema');
        if ($cookie === null) {
            return 'claro (padrão)';
        }

        try {
            $tema = $this->signer->validate($cookie)->getValue();
        } catch (RuntimeException) {
            return 'claro (cookie inválido)';
        }

        return in_array($tema, self::TEMAS, true) ? $tema : 'claro (valor desconhecido)';
    }

    private function texto(string $conteudo): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write($conteudo . "\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
