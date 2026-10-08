<?php

declare(strict_types=1);

namespace App\Tests\Unit\Helpers;

use Codeception\Test\Unit;
use Yiisoft\Arrays\ArrayHelper;

use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;

final class ArrayHelperTest extends Unit
{
    private const PAISES = [
        ['codigo' => 'BR', 'nome' => 'Brasil', 'continente' => 'América'],
        ['codigo' => 'PT', 'nome' => 'Portugal', 'continente' => 'Europa'],
        ['codigo' => 'AR', 'nome' => 'Argentina', 'continente' => 'América'],
    ];

    public function testLendoValores(): void
    {
        $usuario = ['nome' => 'Ana', 'endereco' => ['cidade' => 'Recife', 'uf' => 'PE']];

        assertSame('Recife', ArrayHelper::getValueByPath($usuario, 'endereco.cidade'));
        assertSame('Recife', ArrayHelper::getValue($usuario, ['endereco', 'cidade']));
        assertSame('sem CEP', ArrayHelper::getValue($usuario, ['endereco', 'cep'], 'sem CEP'));
        // getValue() não interpreta o ponto: procura uma chave chamada "endereco.cidade"
        assertNull(ArrayHelper::getValue($usuario, 'endereco.cidade'));
        assertSame(
            ['nome' => 'Ana', 'endereco' => ['uf' => 'PE']],
            ArrayHelper::filter($usuario, ['nome', 'endereco.uf']),
        );
    }

    public function testTransformandoListas(): void
    {
        assertSame(
            ['BR' => 'Brasil', 'PT' => 'Portugal', 'AR' => 'Argentina'],
            ArrayHelper::map(self::PAISES, 'codigo', 'nome'),
        );
        assertSame(
            ['América' => ['BR' => 'Brasil', 'AR' => 'Argentina'], 'Europa' => ['PT' => 'Portugal']],
            ArrayHelper::map(self::PAISES, 'codigo', 'nome', 'continente'),
        );
        assertSame(['Brasil', 'Portugal', 'Argentina'], ArrayHelper::getColumn(self::PAISES, 'nome'));
        assertSame(['BR', 'PT', 'AR'], array_keys(ArrayHelper::index(self::PAISES, 'codigo')));
    }

    public function testMerge(): void
    {
        assertSame(
            ['db' => ['host' => 'db.interno', 'porta' => 5432]],
            ArrayHelper::merge(
                ['db' => ['host' => 'localhost', 'porta' => 5432]],
                ['db' => ['host' => 'db.interno']],
            ),
        );
    }
}
