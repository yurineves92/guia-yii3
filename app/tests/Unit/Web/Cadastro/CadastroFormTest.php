<?php

declare(strict_types=1);

namespace App\Tests\Unit\Web\Cadastro;

use App\Web\Cadastro\CadastroForm;
use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use Yiisoft\Validator\Validator;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

final class CadastroFormTest extends Unit
{
    public function testDadosValidos(): void
    {
        $form = new CadastroForm();
        $form->nome = 'Ana';
        $form->email = 'ana@example.com';

        $result = (new Validator())->validate($form);

        assertTrue($result->isValid());
    }

    public function testCamposVaziosMostramSoOErroDoRequired(): void
    {
        $result = (new Validator())->validate(new CadastroForm());

        assertFalse($result->isValid());
        assertSame(
            [
                'nome' => ['Informe o seu nome.'],
                'email' => ['Informe o seu e-mail.'],
            ],
            $result->getErrorMessagesIndexedByProperty(),
        );
    }

    #[DataProvider('emailsInvalidos')]
    public function testEmailInvalido(string $email): void
    {
        $form = new CadastroForm();
        $form->nome = 'Ana';
        $form->email = $email;

        $result = (new Validator())->validate($form);

        assertSame(['Este e-mail não parece válido.'], $result->getPropertyErrorMessages('email'));
    }

    public static function emailsInvalidos(): array
    {
        return [
            'sem arroba' => ['nao-e-email'],
            'sem domínio' => ['ana@'],
            'com espaço' => ['ana @example.com'],
        ];
    }
}
