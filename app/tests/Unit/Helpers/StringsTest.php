<?php

declare(strict_types=1);

namespace App\Tests\Unit\Helpers;

use Codeception\Test\Unit;
use Yiisoft\Strings\Inflector;
use Yiisoft\Strings\NumericHelper;
use Yiisoft\Strings\StringHelper;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

final class StringsTest extends Unit
{
    public function testStringHelper(): void
    {
        assertSame('Programação or…', StringHelper::truncateEnd('Programação orientada a objetos', 15));
        assertSame('Programação orientada a…', StringHelper::truncateWords('Programação orientada a objetos em PHP', 3));
        assertSame(4, StringHelper::length('ação'));
        assertSame(6, strlen('ação'));
        assertSame('João Da Silva', StringHelper::uppercaseFirstCharacterInEachWord('joão da silva'));
        assertTrue(StringHelper::startsWith('Yii3 framework', 'Yii'));
    }

    public function testInflector(): void
    {
        $inflector = new Inflector();

        assertSame('programacao-em-php-guia-pratico', $inflector->toSlug('Programação em PHP: guia prático!'));
        assertSame('dataDeNascimento', $inflector->toCamelCase('data_de_nascimento'));
        assertSame('PedidoDeCompra', $inflector->toPascalCase('pedido de compra'));
        assertSame('data_de_nascimento', $inflector->toSnakeCase('dataDeNascimento'));
        assertSame('listar-paises-command', $inflector->pascalCaseToId('ListarPaisesCommand'));
    }

    public function testNumericHelper(): void
    {
        assertSame('1.5', NumericHelper::normalize('1,5'));
    }
}
