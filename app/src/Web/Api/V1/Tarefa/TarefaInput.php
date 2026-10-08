<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Tarefa;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

#[FromBody]
final class TarefaInput extends AbstractInput
{
    #[Required(message: 'Informe o título.')]
    #[Length(
        min: 3,
        max: 200,
        lessThanMinMessage: 'O título deve ter pelo menos {min} caracteres.',
        greaterThanMaxMessage: 'O título deve ter no máximo {max} caracteres.',
        skipOnEmpty: true,
    )]
    public string $titulo = '';

    public bool $concluida = false;
}
