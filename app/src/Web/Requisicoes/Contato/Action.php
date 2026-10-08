<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Contato;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Status;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(ContatoInput $contato): ResponseInterface
    {
        $resultado = $contato->getValidationResult();

        if (!$resultado->isValid()) {
            $response = $this->responseFactory->createResponse(Status::UNPROCESSABLE_ENTITY);
            foreach ($resultado->getErrorMessagesIndexedByProperty() as $campo => $mensagens) {
                $response->getBody()->write($campo . ': ' . implode(' ', $mensagens) . "\n");
            }

            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("Mensagem de {$contato->nome} <{$contato->email}> recebida.\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
