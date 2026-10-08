<?php

declare(strict_types=1);

namespace App\Web\Formularios\Pedido;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\Rule\Required;

final class ItemForm extends FormModel
{
    #[Required(message: 'Informe o produto.')]
    public string $produto = '';

    #[Required(message: 'Informe a quantidade.')]
    #[Integer(min: 1, max: 99, lessThanMinMessage: 'Mínimo de {min}.', greaterThanMaxMessage: 'Máximo de {max}.', skipOnEmpty: true)]
    public ?int $quantidade = null;

    public function estaVazio(): bool
    {
        return $this->produto === '' && $this->quantidade === null;
    }
}
