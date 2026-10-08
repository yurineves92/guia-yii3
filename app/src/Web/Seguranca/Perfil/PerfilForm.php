<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Perfil;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

final class PerfilForm extends FormModel
{
    #[Required(message: 'Informe o nome.')]
    #[Length(max: 100, greaterThanMaxMessage: 'Use no máximo {max} caracteres.')]
    public string $nome = '';
}
