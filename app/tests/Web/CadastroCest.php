<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Support\WebTester;

final class CadastroCest
{
    public function enviaDadosValidos(WebTester $I): void
    {
        $I->amOnPage('/cadastro');
        $I->fillField('Nome', 'Ana');
        $I->fillField('E-mail', 'ana@example.com');
        $I->click('Enviar');

        $I->seeResponseCodeIs(200);
        $I->see('Recebemos o seu cadastro', 'h1');
        $I->see('ana@example.com');
    }

    public function mostraOsErrosDeValidacao(WebTester $I): void
    {
        $I->amOnPage('/cadastro');
        $I->fillField('E-mail', 'nao-e-email');
        $I->click('Enviar');

        $I->see('Informe o seu nome.');
        $I->see('Este e-mail não parece válido.');
        $I->seeInField('E-mail', 'nao-e-email');
    }
}
