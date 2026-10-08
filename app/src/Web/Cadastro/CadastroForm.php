<?php

declare(strict_types=1);

namespace App\Web\Cadastro;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Required;

final class CadastroForm extends FormModel
{
    #[Required(message: 'Informe o seu nome.')]
    public string $nome = '';

    #[Required(message: 'Informe o seu e-mail.')]
    #[Email(message: 'Este e-mail não parece válido.', skipOnEmpty: true)]
    public string $email = '';

    public function getPropertyLabels(): array
    {
        return [
            'nome' => 'Nome',
            'email' => 'E-mail',
        ];
    }
}
