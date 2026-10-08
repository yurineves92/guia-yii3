<?php

declare(strict_types=1);

namespace App\Topicos\Email;

use Yiisoft\Mailer\HtmlToTextBodyConverter;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Mailer\Message;
use Yiisoft\View\View;

final readonly class BoasVindasEmail
{
    public function __construct(
        private MailerInterface $mailer,
        private View $view,
        private string $remetente,
    ) {}

    public function enviar(string $nome, string $email): void
    {
        $html = $this->view->render(__DIR__ . '/boas-vindas.php', ['nome' => $nome]);

        $message = (new Message())
            ->withFrom($this->remetente)
            ->withTo([$email => $nome])
            ->withSubject('Bem-vindo ao Guia Yii3')
            ->withHtmlBody($html)
            ->withTextBody(HtmlToTextBodyConverter::convert($html));

        $this->mailer->send($message);
    }
}
