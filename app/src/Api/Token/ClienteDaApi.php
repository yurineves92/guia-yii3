<?php

declare(strict_types=1);

namespace App\Api\Token;

use Yiisoft\Auth\IdentityInterface;

/**
 * O cliente identificado pelo token enviado na requisição.
 */
final readonly class ClienteDaApi implements IdentityInterface
{
    public function __construct(
        public int $id,
        public string $nome,
    ) {}

    public function getId(): string
    {
        return (string) $this->id;
    }
}
