# Logs

Quando algo dá errado em produção, o log é o que sobra para entender o que aconteceu. O Yii3 usa a interface padrão [PSR-3](https://www.php-fig.org/psr/psr-3/), `Psr\Log\LoggerInterface`, e a implementação do pacote `yiisoft/log`. Este capítulo mostra como gravar mensagens, para onde elas vão e como separá-las por categoria.

## LoggerInterface

Qualquer classe pode pedir o logger no construtor. A interface tem um método por nível de severidade, do mais grave ao mais leve: `emergency()`, `alert()`, `critical()`, `error()`, `warning()`, `notice()`, `info()` e `debug()`. Cada um recebe a mensagem e um array de contexto.

Crie `src/Web/Requisicoes/Pagamento/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Pagamento;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Query;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(#[Query] int $pedido = 0, #[Query] float $valor = 0.0): ResponseInterface
    {
        $this->logger->debug('Iniciando pagamento do pedido {pedido}.', ['pedido' => $pedido]);

        if ($valor <= 0) {
            $this->logger->warning('Pedido {pedido} com valor inválido: {valor}.', [
                'pedido' => $pedido,
                'valor' => $valor,
                'category' => 'pagamentos',
            ]);

            return $this->texto('Valor inválido.');
        }

        $this->logger->info('Pedido {pedido} pago: R$ {valor}.', [
            'pedido' => $pedido,
            'valor' => number_format($valor, 2, ',', '.'),
            'category' => 'pagamentos',
        ]);

        return $this->texto('Pagamento registrado.');
    }

    private function texto(string $conteudo): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write($conteudo . "\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

Com a rota `Route::get('/pagamento')`, faça duas chamadas:

```bash
curl "http://127.0.0.1:8080/requisicoes/pagamento?pedido=1001&valor=149.9"
curl "http://127.0.0.1:8080/requisicoes/pagamento?pedido=1002&valor=0"
```

O resultado no log aparece mais abaixo, depois de configurar os destinos.

## Contexto e placeholders

O segundo parâmetro de cada método é o contexto: um array com dados que acompanham a mensagem. Chaves do contexto entre chaves na mensagem, como `{pedido}`, são substituídas pelo valor. Isso é melhor que concatenar, porque a mensagem fica fixa e os dados ficam separados, o que facilita buscar e agrupar.

O `yiisoft/log` também aceita caminhos com ponto (`{usuario.nome}` para `['usuario' => ['nome' => ...]]`), e converte arrays e objetos em texto.

Algumas chaves do contexto têm uso especial:

- `category`: a categoria da mensagem, usada para filtrar. Sem ela, a categoria é `application`.
- `exception`: a PSR-3 recomenda essa chave para passar o objeto de uma exceção. O `ErrorHandler` do Yii3 usa a chave `throwable` ao gravar cada erro não tratado, e o target escreve a exceção com a pilha de chamadas; veja [Tratamento de erros](/requisicoes/tratamento-de-erros).

Além do que você passa, o logger acrescenta `time`, `memory` e `trace` ao contexto.

Não coloque senhas, tokens, números de cartão ou dados pessoais no contexto. O log costuma ter menos proteção que o banco; o capítulo [Boas práticas](/seguranca/boas-praticas) volta a esse assunto.

## Targets

O `Logger` não grava nada sozinho: ele junta as mensagens em memória e as entrega para os targets, os destinos. O `yiisoft/log` traz dois:

- `StreamTarget`: escreve num stream do PHP. O padrão é `php://stdout`.
- `PsrTarget`: repassa as mensagens para outro logger PSR-3.

Outros destinos vêm em pacotes separados:

- `yiisoft/log-target-file`: arquivo, com rotação. Instalado no template.
- `yiisoft/log-target-email`: envia as mensagens por e-mail. Não vem no template; instale com `composer require yiisoft/log-target-email` se precisar.
- `yiisoft/log-target-db` e `yiisoft/log-target-syslog`: banco de dados e syslog.

O template configura o logger em `config/common/di/logger.php`, inicialmente só com o `StreamTarget`. Com o servidor embutido do PHP, `php://stdout` é o terminal onde o `./yii serve` está rodando, e é lá que as mensagens aparecem. Em Docker, a saída padrão vai para o `docker logs`.

Para gravar também em arquivo, acrescente o `FileTarget`. O app de exemplo deixou o arquivo assim:

```php
<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Definitions\ReferencesArray;
use Yiisoft\Log\Logger;
use Yiisoft\Log\StreamTarget;
use Yiisoft\Log\Target\File\FileTarget;

/** @var array $params */

return [
    LoggerInterface::class => [
        'class' => Logger::class,
        '__construct()' => [
            'targets' => ReferencesArray::from([
                StreamTarget::class,
                FileTarget::class,
                'log.pagamentos',
            ]),
        ],
    ],

    'log.pagamentos' => static fn(Aliases $aliases) => (new FileTarget($aliases->get('@runtime/logs/pagamentos.log')))
        ->setCategories(['pagamentos']),
];
```

- O `ReferencesArray::from()` transforma a lista de nomes em referências ao container. Cada target é buscado pelo nome.
- O `FileTarget::class` já vem configurado pelo próprio pacote `log-target-file`, a partir dos params dele. O arquivo padrão é `@runtime/logs/app.log`.
- O `'log.pagamentos'` é uma definição com nome próprio: um segundo `FileTarget`, que grava em outro arquivo e só aceita a categoria `pagamentos`.

Depois das duas chamadas do exemplo, `runtime/logs/pagamentos.log` tem:

```plaintext
2026-10-08 05:01:52.691300 [info][pagamentos] Pedido 1001 pago: R$ 149,90.

Message context:

trace:
    
time: 1791435712.691328
memory: 1296280
category: 'pagamentos'
pedido: 1001
valor: '149,90'

2026-10-08 05:01:52.760600 [warning][pagamentos] Pedido 1002 com valor inválido: 0.

Message context:

trace:
    
time: 1791435712.760626
memory: 1294760
category: 'pagamentos'
pedido: 1002
valor: 0.0
```

Cada linha começa com data e hora, nível e categoria entre colchetes. O contexto vem logo abaixo. As mensagens de `debug` da categoria `application` não estão nesse arquivo, mas estão em `runtime/logs/app.log`, junto com as de pagamento, porque o target padrão aceita todas as categorias.

### Ajustando os targets

Todo target estende `Yiisoft\Log\Target` e tem os mesmos ajustes:

- `setLevels([LogLevel::ERROR, LogLevel::WARNING])`: só esses níveis. Sem chamar, aceita todos.
- `setCategories(['pagamentos', 'App\Pagamento\*'])`: só essas categorias. O `*` no fim casa com qualquer sufixo.
- `setExcept(['Yiisoft\Db\*'])`: todas as categorias menos essas.
- `setExportInterval(100)`: quantas mensagens acumular antes de gravar.
- `setFormat()` e `setPrefix()`: funções que mudam o texto de cada mensagem.

O `FileTarget` padrão lê esses valores dos params do pacote, na chave `yiisoft/log-target-file`. Por padrão ele aceita os níveis `emergency`, `error`, `warning`, `info` e `debug`, e o `FileRotator` cria um arquivo novo quando o atual passa de 10 MB, mantendo 5. Para mudar, sobrescreva a chave no seu arquivo de params.

As mensagens são gravadas em lote. O pacote `yiisoft/log` registra um listener do evento `AfterEmit` que esvazia o logger depois que a resposta é enviada, e o equivalente no console. Por isso as mensagens de uma requisição aparecem no arquivo no fim dela, e não linha a linha.

::: tip
O nível certo ajuda a filtrar depois. Use `error` para falhas que precisam de atenção, `warning` para situações estranhas que o código contornou, `info` para eventos de negócio (pedido pago, usuário cadastrado) e `debug` para detalhes que só interessam ao depurar. Em produção, é comum deixar `debug` fora dos targets.
:::

## Categorias por classe

Uma convenção útil é usar o nome da classe como categoria: `['category' => self::class]`. Assim, `setCategories(['App\Pagamento\*'])` pega todas as mensagens de um módulo, e `setExcept(['App\Relatorio\*'])` silencia uma parte barulhenta sem mexer no código dela.

## Comparando com o Yii2

- `Yii::info($mensagem, $categoria)` vira `$logger->info($mensagem, ['category' => $categoria])`, com o logger injetado.
- Os targets do componente `log` (`FileTarget`, `EmailTarget`, `DbTarget`) continuam existindo, como pacotes separados e configurados no container.
- `categories`, `except`, `levels` e `exportInterval` têm os mesmos nomes e o mesmo papel, agora como métodos `set*()`.
- A interface é a PSR-3, então qualquer biblioteca que aceite um `LoggerInterface` (Monolog incluso) funciona com o logger do Yii3, e vice-versa.
