<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Tests\Support\ConsoleTester;

final class ListarPaisesCest
{
    public function filtraPeloNome(ConsoleTester $I): void
    {
        $I->runApp('pais:listar bra');
        $I->seeResultCodeIs(0);
        $I->seeInShellOutput('Brasil');
        $I->dontSeeInShellOutput('Canadá');
    }

    public function recusaLimiteInvalido(ConsoleTester $I): void
    {
        // Com false, runShellCommand() não falha o teste quando o código de saída é diferente de zero
        $I->runShellCommand(
            escapeshellarg(PHP_BINARY) . ' ' . dirname(__DIR__, 2) . '/yii pais:listar --limite=0',
            false,
        );
        $I->seeResultCodeIs(64);
        $I->seeInShellOutput('O limite precisa ser um número maior que zero.');
    }
}
