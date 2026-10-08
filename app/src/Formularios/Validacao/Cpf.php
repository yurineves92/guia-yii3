<?php

declare(strict_types=1);

namespace App\Formularios\Validacao;

use Attribute;
use Yiisoft\Validator\Rule\Trait\SkipOnEmptyTrait;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\SkipOnEmptyInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Cpf implements RuleInterface, SkipOnEmptyInterface
{
    use SkipOnEmptyTrait;

    public function __construct(
        public readonly string $message = 'O CPF informado não é válido.',
        bool|callable|null $skipOnEmpty = null,
    ) {
        $this->skipOnEmpty = $skipOnEmpty;
    }

    public function getHandler(): string
    {
        return CpfHandler::class;
    }
}
