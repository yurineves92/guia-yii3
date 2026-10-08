<?php

declare(strict_types=1);

namespace App\Seguranca;

use Yiisoft\Auth\IdentityInterface;

final readonly class Usuario implements IdentityInterface
{
    public function __construct(
        public int $id,
        public string $email,
        public string $nome,
        public string $senhaHash,
    ) {}

    public function getId(): string
    {
        return (string) $this->id;
    }
}
