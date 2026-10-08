<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Senha;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Label;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\Rule\Callback;
use Yiisoft\Validator\Rule\CompareType;
use Yiisoft\Validator\Rule\Equal;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

final class TrocarSenhaForm extends FormModel
{
    private const SENHAS_COMUNS = [
        '123456789012',
        'senha1234567',
        'qwertyuiopas',
        'abcdefghijkl',
    ];

    #[Label('Senha atual')]
    #[Required(message: 'Informe a senha atual.')]
    public string $senhaAtual = '';

    #[Label('Nova senha')]
    #[Required(message: 'Informe a nova senha.')]
    #[Length(
        min: 12,
        max: 64,
        lessThanMinMessage: 'A senha precisa ter pelo menos {min} caracteres.',
        greaterThanMaxMessage: 'A senha pode ter no máximo {max} caracteres.',
        skipOnEmpty: true,
    )]
    #[Callback(method: 'validarNovaSenha', skipOnEmpty: true, skipOnError: true)]
    public string $novaSenha = '';

    #[Label('Repita a nova senha')]
    #[Equal(targetProperty: 'novaSenha', type: CompareType::STRING, message: 'As senhas não conferem.')]
    public string $confirmacao = '';

    public function __construct(
        private readonly string $email,
    ) {}

    private function validarNovaSenha(string $senha): Result
    {
        $result = new Result();
        $senhaMinuscula = mb_strtolower($senha);
        $usuarioDoEmail = mb_strtolower(explode('@', $this->email)[0]);

        if (in_array($senhaMinuscula, self::SENHAS_COMUNS, true)) {
            $result->addError('Esta senha é comum demais. Escolha outra.');
        } elseif (str_contains($senhaMinuscula, $usuarioDoEmail)) {
            $result->addError('A senha não pode conter o seu e-mail.');
        } elseif ($senha === $this->senhaAtual) {
            $result->addError('A nova senha precisa ser diferente da atual.');
        }

        return $result;
    }
}
