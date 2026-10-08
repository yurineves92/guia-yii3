<?php

declare(strict_types=1);

namespace App\Formularios\Validacao;

use Yiisoft\Validator\Exception\UnexpectedRuleException;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\RuleHandlerInterface;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\ValidationContext;

final class CpfHandler implements RuleHandlerInterface
{
    public function validate(mixed $value, RuleInterface $rule, ValidationContext $context): Result
    {
        if (!$rule instanceof Cpf) {
            throw new UnexpectedRuleException(Cpf::class, $rule);
        }

        $result = new Result();

        if (!is_string($value) || !$this->cpfValido($value)) {
            $result->addError($rule->message, ['property' => $context->getTranslatedProperty()]);
        }

        return $result;
    }

    private function cpfValido(string $cpf): bool
    {
        $digitos = preg_replace('/\D/', '', $cpf);

        // 11 dígitos, e não todos iguais (111.111.111-11 passa no cálculo, mas não é válido)
        if (strlen($digitos) !== 11 || preg_match('/^(\d)\1{10}$/', $digitos) === 1) {
            return false;
        }

        // Os dois últimos dígitos são verificadores, calculados a partir dos anteriores
        for ($posicao = 9; $posicao < 11; $posicao++) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $digitos[$i] * ($posicao + 1 - $i);
            }
            $verificador = ($soma * 10) % 11 % 10;
            if ((int) $digitos[$posicao] !== $verificador) {
                return false;
            }
        }

        return true;
    }
}
