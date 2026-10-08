<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Erro;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Yiisoft\ErrorHandler\Exception\UserException;
use Yiisoft\Input\Http\Attribute\Parameter\Query;

final readonly class Action
{
    public function __invoke(#[Query] string $tipo = ''): ResponseInterface
    {
        throw match ($tipo) {
            'usuario' => new UserException('O cálculo de frete está indisponível. Tente de novo em alguns minutos.'),
            'configuracao' => new ChaveDeApiAusenteException(),
            default => new RuntimeException('Falha ao conectar em frete.exemplo.com:443.'),
        };
    }
}
