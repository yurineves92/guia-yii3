# Eventos

Eventos servem para uma parte do código avisar que algo aconteceu sem saber quem vai reagir. A confirmação de um pedido não precisa conhecer o e-mail de aviso, o log ou a atualização de estoque: ela dispara um evento, e cada interessado registra um listener. O Yii3 usa a interface padrão [PSR-14](https://www.php-fig.org/psr/psr-14/) para isso. Este capítulo mostra como criar e disparar eventos, como registrar listeners na configuração e quais eventos a própria aplicação dispara.

## PSR-14 com yiisoft/event-dispatcher

A PSR-14 define três papéis:

- O evento: um objeto qualquer. Não precisa estender classe nem implementar interface.
- O dispatcher (`Psr\EventDispatcher\EventDispatcherInterface`): recebe o evento e chama os listeners.
- O provider de listeners: diz quais listeners valem para cada evento.

A implementação é o `yiisoft/event-dispatcher`, e o `yiisoft/yii-event` liga tudo à [configuração](/conceitos/configuracao): ele monta a lista de listeners a partir dos grupos `events-web` e `events-console`. O template já traz os dois pacotes.

O listener é escolhido pelo tipo do evento. Não existe nome de evento em string: a classe do objeto é o nome. Um listener declarado para uma interface recebe todos os eventos que a implementam.

## Criando e disparando um evento

O evento, em `src/Conceitos/Eventos/PedidoConfirmado.php`. É um objeto simples com os dados que os listeners vão precisar:

```php
<?php

declare(strict_types=1);

namespace App\Conceitos\Eventos;

final readonly class PedidoConfirmado
{
    public function __construct(
        public int $pedido,
        public string $cliente,
        public float $total,
    ) {}
}
```

O serviço que dispara, em `src/Conceitos/Eventos/ConfirmacaoDePedido.php`:

```php
<?php

declare(strict_types=1);

namespace App\Conceitos\Eventos;

use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class ConfirmacaoDePedido
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function confirmar(int $pedido, string $cliente, float $total): void
    {
        // Aqui entraria a regra de negócio: validar estoque, gravar no banco...

        $this->eventDispatcher->dispatch(new PedidoConfirmado($pedido, $cliente, $total));
    }
}
```

O `dispatch()` chama os listeners um depois do outro, na mesma requisição, e devolve o próprio evento. Não há fila nem execução em segundo plano: se um listener demora, a requisição demora; se lança exceção, ela sobe até quem chamou o `dispatch()`.

Um listener pode ser uma classe com `__invoke()`. Crie `src/Conceitos/Eventos/RegistrarPedidoNoLog.php`:

```php
<?php

declare(strict_types=1);

namespace App\Conceitos\Eventos;

use Psr\Log\LoggerInterface;

final readonly class RegistrarPedidoNoLog
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(PedidoConfirmado $evento): void
    {
        $this->logger->info('Pedido {pedido} confirmado. Total: {total}.', [
            'pedido' => $evento->pedido,
            'total' => $evento->total,
            'category' => 'conceitos',
        ]);
    }
}
```

## Registrando listeners na configuração

Os listeners ficam num array cuja chave é a classe do evento e cujo valor é a lista de listeners. No app de exemplo, o grupo `events` lê os arquivos de `config/common/events/` (o capítulo [Configuração](/conceitos/configuracao) mostra como isso foi ligado; no template original, o grupo `events` começa vazio). Crie `config/common/events/conceitos.php`:

```php
<?php

declare(strict_types=1);

use App\Conceitos\Eventos\PedidoConfirmado;
use App\Conceitos\Eventos\RegistrarPedidoNoLog;
use App\Conceitos\Notificacao\AvisoDePedido;
use Psr\Log\LoggerInterface;
use Yiisoft\Yii\Http\Event\BeforeRequest;

return [
    PedidoConfirmado::class => [
        RegistrarPedidoNoLog::class,
        static function (PedidoConfirmado $evento, AvisoDePedido $aviso): void {
            $aviso->avisar($evento->cliente, $evento->pedido);
        },
    ],

    BeforeRequest::class => [
        static function (BeforeRequest $evento, LoggerInterface $logger): void {
            $caminho = $evento->getRequest()->getUri()->getPath();
            if (str_starts_with($caminho, '/conceitos/')) {
                $logger->debug('Requisição para {caminho}', ['caminho' => $caminho, 'category' => 'conceitos']);
            }
        },
    ],
];
```

`PedidoConfirmado` tem dois listeners, em dois formatos:

- `RegistrarPedidoNoLog::class`: o nome de uma classe com `__invoke()`. O container cria o objeto, com as dependências do construtor.
- Uma closure. O primeiro parâmetro é o evento; os demais são resolvidos pelo container. Aqui ela reaproveita o `AvisoDePedido` do capítulo [Container de DI](/conceitos/container-di).

Também são aceitos `[Classe::class, 'metodo']` (o container cria a instância e chama o método) e o nome de qualquer definição do container que seja invocável. As dependências só são resolvidas quando o evento acontece: um listener de um evento que nunca é disparado não custa nada.

A action que usa o serviço, em `src/Web/Conceitos/Pedido/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Conceitos\Pedido;

use App\Conceitos\Eventos\ConfirmacaoDePedido;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\HydratorAttribute\RouteArgument;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private ConfirmacaoDePedido $confirmacao,
    ) {}

    public function __invoke(#[RouteArgument] int $id): ResponseInterface
    {
        $this->confirmacao->confirmar($id, 'ana@exemplo.com', 149.90);

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("Pedido $id confirmado.\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

Com a rota `Route::get('/pedido/{id:\d+}')` no grupo `/conceitos`, acesse `/conceitos/pedido/7`. A resposta é `Pedido 7 confirmado.`, e o `runtime/logs/app.log` ganha três linhas da categoria `conceitos`:

```plaintext
[debug][conceitos] Requisição para /conceitos/pedido/7
[info][conceitos] Pedido 7 confirmado. Total: 149.9.
[info][conceitos] [pedidos] Para ana@exemplo.com: [Guia Yii3] Seu pedido 7 foi recebido. Responda para loja@guia.test.
```

A primeira veio do listener de `BeforeRequest`, as outras duas dos listeners de `PedidoConfirmado`, na ordem em que foram declarados. A `ConfirmacaoDePedido` não sabe de nenhum deles.

Com `APP_DEBUG=true`, o runner confere a configuração de eventos na inicialização: um listener que aponta para uma classe inexistente ou que não é invocável dá erro logo na primeira requisição.

### Parando a propagação

Se o evento implementar `Psr\EventDispatcher\StoppableEventInterface`, o dispatcher pergunta `isPropagationStopped()` antes de cada listener e para quando a resposta for `true`. Um listener pode, por exemplo, marcar o evento como tratado para que os seguintes não façam nada.

## Eventos da aplicação

O framework também dispara eventos, e você registra listeners para eles do mesmo jeito. Os da aplicação web, do pacote `yiisoft/yii-http`, ficam em `Yiisoft\Yii\Http\Event`:

- `ApplicationStartup`: a aplicação vai começar a tratar a requisição.
- `BeforeRequest`: antes de passar a requisição para a pilha de middlewares. Tem `getRequest()`.
- `AfterRequest`: a pilha devolveu a resposta. Tem `getResponse()`.
- `AfterEmit`: a resposta já foi enviada ao cliente. Bom lugar para trabalho que não deve atrasar a resposta.
- `ApplicationShutdown`: fim do ciclo.

Outros pacotes disparam os seus. O `ErrorCatcher` dispara `Yiisoft\ErrorHandler\Event\ApplicationError` quando pega uma exceção, e o dispatcher de middlewares dispara `BeforeMiddleware` e `AfterMiddleware` em volta de cada middleware. No console, os eventos vêm do Symfony Console, como o `ConsoleTerminateEvent`.

Vários pacotes já usam esses eventos na configuração deles. O `yiisoft/log` registra um listener de `AfterEmit` que grava as mensagens acumuladas do log, e o `yiisoft/request-provider` usa o `BeforeMiddleware` para manter atualizada a requisição atual, que é de onde atributos como o `#[Query]` leem os dados.

Os eventos não podem trocar a requisição nem a resposta: `BeforeRequest` e `AfterRequest` só dão acesso de leitura. Para alterar o que entra ou sai, use um [middleware](/estrutura/middleware).

### Grupos por tipo de aplicação

O grupo `events` vale para a web e para o console, porque `events-web` e `events-console` começam com `$events`. O listener de `BeforeRequest` acima está nesse grupo comum; no console ele só nunca é chamado, porque o evento não acontece lá. Se um listener depende de algo que só existe na web, como a sessão, coloque-o num arquivo que entre só em `events-web`.

## Comparando com o Yii2

- No Yii2, eventos eram strings (`ActiveRecord::EVENT_AFTER_INSERT`) disparadas com `$this->trigger()` por objetos que estendiam `Component`. No Yii3, o evento é um objeto de uma classe sua, disparado pelo `EventDispatcherInterface`.
- `$objeto->on(...)` e `Event::on(Classe::class, ...)` viram a configuração de eventos. Não há listeners presos a uma instância.
- `Application::EVENT_BEFORE_REQUEST` e `EVENT_AFTER_REQUEST` viram as classes `BeforeRequest` e `AfterRequest`.
- `$event->handled = true` vira um evento que implementa `StoppableEventInterface`.
