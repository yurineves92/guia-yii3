<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Login;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Label;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Required;

final class LoginForm extends FormModel
{
    #[Label('E-mail')]
    #[Required(message: 'Informe o e-mail.')]
    #[Email(message: 'Este e-mail não parece válido.', skipOnEmpty: true)]
    public string $email = '';

    #[Required(message: 'Informe a senha.')]
    public string $senha = '';
}
