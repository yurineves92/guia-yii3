<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use HttpSoft\Message\ServerRequest;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class OlaCest
{
    public function mensagemPadrao(FunctionalTester $tester): void
    {
        $response = $tester->sendRequest(
            new ServerRequest(uri: '/ola'),
        );

        assertSame(200, $response->getStatusCode());
        assertStringContainsString('<h1>Olá</h1>', $response->getBody()->getContents());
    }

    public function mensagemDaQuery(FunctionalTester $tester): void
    {
        $response = $tester->sendRequest(
            new ServerRequest(
                queryParams: ['mensagem' => '<b>Bom dia</b>'],
                uri: '/ola?mensagem=%3Cb%3EBom%20dia%3C%2Fb%3E',
            ),
        );

        assertStringContainsString(
            '<h1>&lt;b&gt;Bom dia&lt;/b&gt;</h1>',
            $response->getBody()->getContents(),
        );
    }
}
