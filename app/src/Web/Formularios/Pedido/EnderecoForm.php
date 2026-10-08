<?php

declare(strict_types=1);

namespace App\Web\Formularios\Pedido;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Label;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

final class EnderecoForm extends FormModel
{
    #[Label('CEP')]
    #[Required(message: 'Informe o CEP.')]
    #[Regex('/^\d{5}-?\d{3}$/', message: 'Use o formato 00000-000.', skipOnEmpty: true)]
    public string $cep = '';

    #[Label('Cidade')]
    #[Required(message: 'Informe a cidade.')]
    public string $cidade = '';
}
