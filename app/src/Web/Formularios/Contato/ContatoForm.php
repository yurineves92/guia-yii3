<?php

declare(strict_types=1);

namespace App\Web\Formularios\Contato;

use Yiisoft\FormModel\Attribute\Hint;
use Yiisoft\FormModel\Attribute\Placeholder;
use Yiisoft\FormModel\Attribute\Safe;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Label;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

final class ContatoForm extends FormModel
{
    public const ASSUNTOS = [
        'duvida' => 'Dúvida',
        'orcamento' => 'Orçamento',
        'suporte' => 'Suporte técnico',
    ];

    #[Label('Seu nome')]
    #[Placeholder('Maria da Silva')]
    #[Required(message: 'Informe o seu nome.')]
    public string $nome = '';

    #[Label('E-mail')]
    #[Hint('Usamos só para responder a esta mensagem.')]
    #[Required(message: 'Informe o seu e-mail.')]
    #[Email(message: 'Este e-mail não parece válido.', skipOnEmpty: true)]
    public string $email = '';

    #[Required(message: 'Escolha um assunto.')]
    #[In(['duvida', 'orcamento', 'suporte'], message: 'Escolha um assunto da lista.', skipOnEmpty: true)]
    public string $assunto = '';

    #[Required(message: 'Escreva a mensagem.')]
    #[Length(min: 10, lessThanMinMessage: 'A mensagem precisa ter pelo menos {min} caracteres.', skipOnEmpty: true)]
    public string $mensagem = '';

    #[Safe]
    public bool $receberNovidades = false;

    public function getPropertyLabels(): array
    {
        return [
            'mensagem' => 'Mensagem',
            'receberNovidades' => 'Quero receber novidades por e-mail',
        ];
    }

    public function getPropertyHints(): array
    {
        return [
            'mensagem' => 'Conte o máximo de detalhes que puder.',
        ];
    }
}
