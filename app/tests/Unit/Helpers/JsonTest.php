<?php

declare(strict_types=1);

namespace App\Tests\Unit\Helpers;

use Codeception\Test\Unit;
use JsonException;
use Yiisoft\Json\Json;

use function PHPUnit\Framework\assertSame;

final class JsonTest extends Unit
{
    public function testEncodeEDecode(): void
    {
        assertSame(
            '{"nome":"Ana","cidade":"São Paulo","url":"a/b"}',
            Json::encode(['nome' => 'Ana', 'cidade' => 'São Paulo', 'url' => 'a/b']),
        );
        assertSame(['nome' => 'Ana', 'idade' => 30], Json::decode('{"nome":"Ana","idade":30}'));
    }

    public function testHtmlEncode(): void
    {
        assertSame(
            '{"html":"\u003Cscript\u003E\u0022x\u0022\u003C\/script\u003E"}',
            Json::htmlEncode(['html' => '<script>"x"</script>']),
        );
    }

    public function testJsonInvalidoLancaExcecao(): void
    {
        $this->expectException(JsonException::class);

        Json::decode('{nome: Ana}');
    }
}
