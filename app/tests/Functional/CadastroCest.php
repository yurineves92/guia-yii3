<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use HttpSoft\Message\ServerRequest;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class CadastroCest
{
    public function mostraOFormulario(FunctionalTester $tester): void
    {
        $response = $tester->sendRequest(
            new ServerRequest(uri: '/cadastro'),
        );

        assertSame(200, $response->getStatusCode());
        assertStringContainsString('name="CadastroForm[nome]"', $response->getBody()->getContents());
    }

    public function postSemTokenCsrfERecusado(FunctionalTester $tester): void
    {
        $response = $tester->sendRequest(
            new ServerRequest(
                parsedBody: ['CadastroForm' => ['nome' => 'Ana', 'email' => 'ana@example.com']],
                method: 'POST',
                uri: '/cadastro',
            ),
        );

        assertSame(422, $response->getStatusCode());
    }
}
