<?php

declare(strict_types=1);

namespace App\Tests\Unit\Topicos\Email;

use App\Topicos\Email\BoasVindasEmail;
use Codeception\Test\Unit;
use Yiisoft\Mailer\StubMailer;
use Yiisoft\View\View;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class BoasVindasEmailTest extends Unit
{
    public function testMontaAMensagem(): void
    {
        $mailer = new StubMailer();
        $boasVindas = new BoasVindasEmail($mailer, new View(), 'nao-responda@guia.test');

        $boasVindas->enviar('Ana <Souza>', 'ana@example.com');

        $mensagens = $mailer->getMessages();
        assertCount(1, $mensagens);
        assertSame(['ana@example.com' => 'Ana <Souza>'], $mensagens[0]->getTo());
        assertSame('Bem-vindo ao Guia Yii3', $mensagens[0]->getSubject());
        assertStringContainsString('<h1>Olá, Ana &lt;Souza&gt;!</h1>', (string) $mensagens[0]->getHtmlBody());
        assertStringContainsString('Olá, Ana <Souza>!', (string) $mensagens[0]->getTextBody());
    }
}
