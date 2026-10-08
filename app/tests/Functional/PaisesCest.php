<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use HttpSoft\Message\ServerRequest;

use function PHPUnit\Framework\assertSame;

final class PaisesCest
{
    public function paginaForaDoIntervaloDevolve404(FunctionalTester $tester): void
    {
        $response = $tester->sendRequest(
            new ServerRequest(queryParams: ['pagina' => '99'], uri: '/paises?pagina=99'),
        );

        assertSame(404, $response->getStatusCode());
    }
}
