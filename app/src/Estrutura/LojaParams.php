<?php

declare(strict_types=1);

namespace App\Estrutura;

final readonly class LojaParams
{
    public function __construct(
        public string $nome,
        public string $email,
    ) {}
}
