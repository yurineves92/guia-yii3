<?php

declare(strict_types=1);

namespace App\Web\Formularios\Inscricao;

use App\Formularios\Validacao\Cpf;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Label;
use Yiisoft\Validator\Rule\CompareType;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Equal;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Rule\Url;
use Yiisoft\Validator\RulesProviderInterface;
use Yiisoft\Validator\ValidationContext;

final class InscricaoForm extends FormModel implements RulesProviderInterface
{
    public const TIPOS = [
        'estudante' => 'Estudante',
        'profissional' => 'Profissional',
    ];

    #[Required(message: 'Informe o nome.')]
    #[Length(max: 80, greaterThanMaxMessage: 'Use no máximo {max} caracteres.')]
    public string $nome = '';

    #[Label('CPF')]
    #[Required(message: 'Informe o CPF.')]
    #[Cpf(skipOnEmpty: true)]
    public string $cpf = '';

    #[Label('E-mail')]
    #[Required(message: 'Informe o e-mail.')]
    #[Email(message: 'Este e-mail não parece válido.', skipOnEmpty: true)]
    public string $email = '';

    #[Label('Repita o e-mail')]
    #[Equal(targetProperty: 'email', type: CompareType::STRING, message: 'Os e-mails não conferem.')]
    public string $confirmacaoEmail = '';

    #[Required(message: 'Informe a idade.')]
    #[Integer(
        min: 16,
        max: 120,
        lessThanMinMessage: 'A idade mínima é {min} anos.',
        greaterThanMaxMessage: 'Confira a idade informada.',
        skipOnEmpty: true,
    )]
    public ?int $idade = null;

    #[Label('Tipo de ingresso')]
    #[Required(message: 'Escolha o tipo de ingresso.')]
    #[In(['estudante', 'profissional'], message: 'Tipo de ingresso inválido.', skipOnEmpty: true)]
    public string $tipo = '';

    #[Label('Instituição de ensino')]
    public string $instituicao = '';

    #[Label('Site ou portfólio')]
    #[Url(message: 'Informe um endereço começando com http:// ou https://.', skipOnEmpty: true)]
    public string $site = '';

    public function getRules(): array
    {
        return [
            'instituicao' => [
                new Required(
                    message: 'Estudantes precisam informar a instituição.',
                    when: static fn(mixed $value, ValidationContext $context): bool
                        => $context->getDataSet()->getPropertyValue('tipo') === 'estudante',
                ),
            ],
        ];
    }
}
