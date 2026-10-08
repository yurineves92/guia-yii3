<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Contato;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

#[FromBody]
final class ContatoInput extends AbstractInput
{
    #[Required(message: 'Informe o nome.')]
    public string $nome = '';

    #[Required(message: 'Informe o e-mail.')]
    #[Email(message: 'E-mail inválido.', skipOnEmpty: true)]
    public string $email = '';

    #[Length(max: 200, greaterThanMaxMessage: 'A mensagem pode ter no máximo {max} caracteres.')]
    public string $mensagem = '';
}
