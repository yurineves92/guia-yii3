<?php

declare(strict_types=1);

namespace App\Tests\Unit\Helpers;

use Codeception\Test\Unit;
use Yiisoft\Html\Html;

use function PHPUnit\Framework\assertSame;

final class HtmlTest extends Unit
{
    public function testEncode(): void
    {
        assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt; &amp; "aspas"',
            Html::encode('<script>alert(1)</script> & "aspas"'),
        );
    }

    public function testTags(): void
    {
        assertSame('<a class="link" href="/paises">Países</a>', (string) Html::a('Países', '/paises', ['class' => 'link']));
        assertSame('<a href="/x">&lt;b&gt;x&lt;/b&gt;</a>', (string) Html::a('<b>x</b>', '/x'));
        assertSame(
            '<span class="badge badge-novo" data-id="7">Novo</span>',
            (string) Html::tag('span', 'Novo', ['class' => ['badge', 'badge-novo'], 'data-id' => 7]),
        );
        assertSame('<img src="/logo.png" alt="Logo">', (string) Html::img('/logo.png', 'Logo'));
    }

    public function testObjetosDeTag(): void
    {
        assertSame(
            '<div class="aviso grande"><b>mundo</b></div>',
            (string) Html::div(Html::b('mundo'))->class('aviso')->addClass('grande'),
        );
        assertSame('<div><i>x</i></div>', (string) Html::div('<i>x</i>')->encode(false));
        assertSame(
            '<input type="text" name="nome" value="Ana" required>',
            (string) Html::textInput('nome', 'Ana')->required(),
        );
        assertSame(
            "<ul>\n<li>Um</li>\n<li>Dois &amp; três</li>\n</ul>",
            (string) Html::ul()->strings(['Um', 'Dois & três']),
        );
    }
}
