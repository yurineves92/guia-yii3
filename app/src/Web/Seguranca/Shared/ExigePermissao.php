<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Shared;

use App\Seguranca\Rbac\Permissao;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\User\CurrentUser;

/**
 * Deixa a requisição passar só se o usuário atual tem a permissão.
 *
 * Na rota: ->middleware(ExigePermissao::para(Permissao::VerRelatorios))
 */
final class ExigePermissao implements MiddlewareInterface
{
    private ?Permissao $permissao = null;

    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly Proibido $proibido,
    ) {}

    /**
     * Definição do middleware para usar na rota.
     */
    public static function para(Permissao $permissao): array
    {
        return [
            'class' => self::class,
            'withPermissao()' => [$permissao],
        ];
    }

    public function withPermissao(Permissao $permissao): self
    {
        $new = clone $this;
        $new->permissao = $permissao;
        return $new;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->permissao === null) {
            throw new LogicException('Informe a permissão com ExigePermissao::para().');
        }

        if (!$this->currentUser->can($this->permissao)) {
            return $this->proibido->handle($request);
        }

        return $handler->handle($request);
    }
}
