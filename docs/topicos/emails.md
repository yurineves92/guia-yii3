# Envio de e-mails

Confirmação de cadastro, recuperação de senha, aviso de pedido: quase todo sistema manda e-mail. No Yii3, o envio fica atrás de uma interface, a `MailerInterface`. O seu código monta a mensagem e chama `send()`. Quem decide se ela vai para um servidor SMTP, para um arquivo ou para a memória é a configuração do container.

## Os pacotes

São dois, e o template já traz os dois:

- `yiisoft/mailer` define a `MailerInterface`, a classe `Message` e três implementações que não enviam nada de verdade: `FileMailer` (grava cada mensagem num arquivo), `StubMailer` (guarda as mensagens na memória) e `NullMailer` (descarta).
- `yiisoft/mailer-symfony` traz o `Yiisoft\Mailer\Symfony\Mailer`, que envia usando o `symfony/mailer`. A configuração desse pacote liga a `MailerInterface` a ele.

Ou seja: se você pedir uma `MailerInterface` ao container sem configurar nada, recebe o mailer do Symfony apontando para o SMTP de exemplo do pacote, `smtp.example.com`. Enviar com ele vai falhar até você configurar o servidor.

## Configurando o SMTP

Os dados do servidor ficam nos parâmetros do `yiisoft/mailer-symfony`. Um exemplo para o arquivo de parâmetros:

```php
'yiisoft/mailer-symfony' => [
    'esmtpTransport' => [
        'scheme' => 'smtps',
        'host' => 'smtp.seu-provedor.com',
        'port' => 465,
        'username' => 'contato@seu-dominio.com',
        'password' => (string) getenv('SMTP_PASSWORD'),
    ],
    'messageSettings' => [
        'from' => 'contato@seu-dominio.com',
    ],
],
```

O `messageSettings` define valores padrão aplicados a toda mensagem, como o remetente. Com `'useSendmail' => true`, o pacote usa o `sendmail` do sistema em vez de SMTP. A senha vem de uma variável de ambiente para não ficar no repositório.

Este trecho é configuração: o guia não envia e-mail de verdade, então ele não foi executado contra um servidor.

## Em desenvolvimento: gravando em arquivo

Durante o desenvolvimento, você quer ver o e-mail sem mandá-lo a ninguém. O `FileMailer` serve para isso. O pacote já o configura para gravar em `@runtime/mail` (parâmetro `yiisoft/mailer` → `fileMailer` → `path`).

Para que a aplicação inteira use o `FileMailer`, basta trocar a ligação da interface no container, por exemplo num arquivo de definições carregado só no ambiente de desenvolvimento:

```php
use Yiisoft\Mailer\FileMailer;
use Yiisoft\Mailer\MailerInterface;

return [
    MailerInterface::class => FileMailer::class,
];
```

No app de exemplo, a troca vale só para o serviço deste capítulo, como você vai ver abaixo. Assim os outros exemplos do guia não são afetados.

## Templates de e-mail

O `yiisoft/mailer` não tem sistema de templates próprio. A mensagem recebe o corpo pronto, em HTML e em texto. Para montar esse HTML, use o `yiisoft/view`, o mesmo pacote das views das páginas. A diferença é que aqui você usa a classe `View`, e não a `WebView` com layout.

O template é um arquivo PHP comum. Crie `src/Topicos/Email/boas-vindas.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\View $this
 * @var string $nome
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<body>
    <h1>Olá, <?= Html::encode($nome) ?>!</h1>
    <p>Seu cadastro no Guia Yii3 foi concluído.</p>
    <p>Este é um e-mail automático. Não é preciso responder.</p>
</body>
</html>
```

O `Html::encode()` continua obrigatório: o nome vem do usuário e vai parar dentro de um HTML.

## O serviço que envia

Em vez de montar a mensagem na action ou no comando, crie um serviço para cada tipo de e-mail. Ele fica fácil de reaproveitar e de testar. Crie `src/Topicos/Email/BoasVindasEmail.php`:

```php
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
```

Algumas observações:

- A `Message` é imutável. Cada `with*()` devolve uma cópia, por isso o encadeamento.
- Os endereços aceitam uma string (`'ana@example.com'`) ou um array no formato `[e-mail => nome]`.
- O `HtmlToTextBodyConverter::convert()` gera a versão em texto a partir do HTML, para clientes de e-mail que não mostram HTML.
- O `View::render()` aceita o caminho absoluto do template, como o `WebViewRenderer` das actions.

O remetente é uma string, que o container não tem como adivinhar. Ele vem da configuração, junto com a escolha do mailer. Trecho de `config/common/di/topicos.php`:

```php
use App\Topicos\Email\BoasVindasEmail;
use Yiisoft\Definitions\Reference;
use Yiisoft\Mailer\FileMailer;

return [
    BoasVindasEmail::class => [
        '__construct()' => [
            'mailer' => Reference::to(FileMailer::class),
            'remetente' => 'nao-responda@guia.test',
        ],
    ],
];
```

O `Reference::to(FileMailer::class)` entrega o `FileMailer` no lugar da `MailerInterface` só para este serviço. Em produção, você removeria a linha `'mailer'` e deixaria o container entregar o mailer do Symfony. O [Container de DI](/conceitos/container-di) explica as definições em detalhe.

## Disparando pelo console

Para testar sem criar uma página, o app tem o comando `email:boas-vindas` (registrado como mostrado em [Comandos de console](/topicos/console)). O `execute()` dele só lê os argumentos e chama o serviço:

```php
protected function execute(InputInterface $input, OutputInterface $output): int
{
    /** @var string $email */
    $email = $input->getArgument('email');
    /** @var string $nome */
    $nome = $input->getArgument('nome');

    $this->boasVindas->enviar($nome, $email);

    $output->writeln("E-mail enviado para $email.");

    return ExitCode::OK;
}
```

```bash
./yii email:boas-vindas ana@example.com "Ana Souza"
```

```plaintext
E-mail enviado para ana@example.com.
```

Em `runtime/mail/` aparece um arquivo `.eml` com nome baseado na data e hora. O conteúdo:

```plaintext
Olá, Ana Souza!
Seu cadastro no Guia Yii3 foi concluído.
Este é um e-mail automático. Não é preciso responder.
```

::: warning O FileMailer grava pouco
Na versão 6 do `yiisoft/mailer`, o arquivo contém só os cabeçalhos extras (os definidos com `withHeader()` e afins) e o corpo em texto. Remetente, destinatário, assunto e HTML não aparecem. Ele serve para conferir o texto. Para verificar o resto, use o `StubMailer` num teste, como a seguir.
:::

## Testando o e-mail

O `StubMailer` guarda as mensagens enviadas num array, e o `getMessages()` devolve esse array. Com ele, o teste confere destinatário, assunto e corpo sem tocar em rede nem em disco. Crie `tests/Unit/Topicos/Email/BoasVindasEmailTest.php`:

```php
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
```

O nome com `<` e `>` confere de quebra que o template escapa o HTML e que a versão em texto volta ao original.

```bash
vendor/bin/codecept run Unit Topicos/Email
```

```plaintext
OK (1 test, 5 assertions)
```

## Eventos de envio

Os mailers que estendem `BaseMailer` (o `FileMailer` e o do Symfony, entre outros) disparam dois eventos quando recebem um `EventDispatcherInterface` no construtor: `Yiisoft\Mailer\Event\BeforeSend` e `AfterSend`. Um listener do `BeforeSend` pode marcar `$event->preventSendingMessage = true` para cancelar o envio. O capítulo de [Eventos](/conceitos/eventos) mostra como registrar listeners.

## Comparando com o Yii2

- O `Yii::$app->mailer->compose('view', $params)` renderizava a view junto com a mensagem. No Yii3, você renderiza o template com o `View` e passa o HTML pronto para a `Message`.
- O `useFileTransport = true` do Yii2 corresponde a usar o `FileMailer` no lugar da `MailerInterface`.
- O `yii2-symfonymailer` foi substituído pelo `yiisoft/mailer-symfony`, configurado por parâmetros.
- A mensagem é imutável: `withTo()`, `withSubject()` no lugar de `setTo()`, `setSubject()`.
