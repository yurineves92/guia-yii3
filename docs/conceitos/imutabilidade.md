# Imutabilidade

Quem chega do Yii2 estranha a quantidade de métodos `with*()` no Yii3 e o fato de que `$response->withHeader(...)` sozinho, sem atribuição, não faz nada. Isso é intencional: boa parte dos objetos do framework é imutável, e os serviços do container são compartilhados. Este capítulo explica as duas ideias, por que elas andam juntas e o que muda quando a aplicação roda num servidor de longa duração.

## Por que tantos with*()

Um objeto imutável não muda depois de criado. Para obter uma versão diferente, você pede uma cópia com a alteração. Por convenção, esses métodos começam com `with` e devolvem o objeto novo, deixando o original como estava.

A requisição e a resposta da [PSR-7](https://www.php-fig.org/psr/psr-7/) funcionam assim, e o erro mais comum é esquecer de guardar o retorno. A action de `src/Web/Conceitos/Imutabilidade/Action.php` mostra o efeito:

```php
<?php

declare(strict_types=1);

namespace App\Web\Conceitos\Imutabilidade;

use App\Conceitos\Imutabilidade\Filtro;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $base = new Filtro();
        $busca = $base->withTermo('yii')->withLimite(5);

        $response = $this->responseFactory->createResponse();
        $response->withHeader('X-Esquecido', 'sim');
        $response = $response->withHeader('X-Guardado', 'sim');

        $response->getBody()->write(
            'Filtro base: ' . $base->descrever() . "\n"
            . 'Filtro da busca: ' . $busca->descrever() . "\n"
            . 'Tem X-Esquecido? ' . ($response->hasHeader('X-Esquecido') ? 'sim' : 'não') . "\n"
            . 'Tem X-Guardado? ' . ($response->hasHeader('X-Guardado') ? 'sim' : 'não') . "\n",
        );

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

Com a rota `/conceitos/imutabilidade`:

```plaintext
Filtro base: termo='', limite=20
Filtro da busca: termo='yii', limite=5
Tem X-Esquecido? não
Tem X-Guardado? sim
```

O `withHeader('X-Esquecido', ...)` criou uma resposta com o header e a descartou, porque o retorno não foi guardado. Não há erro nem aviso; o header simplesmente não aparece. Sempre atribua: `$response = $response->withHeader(...)`, ou encadeie e retorne direto.

O stream do corpo é a exceção: `getBody()->write()` altera o próprio stream, que é compartilhado entre a resposta original e as cópias.

O `Filtro` é uma classe própria no mesmo estilo, em `src/Conceitos/Imutabilidade/Filtro.php`:

```php
<?php

declare(strict_types=1);

namespace App\Conceitos\Imutabilidade;

final class Filtro
{
    private string $termo = '';
    private int $limite = 20;

    public function withTermo(string $termo): self
    {
        $new = clone $this;
        $new->termo = $termo;
        return $new;
    }

    public function withLimite(int $limite): self
    {
        $new = clone $this;
        $new->limite = $limite;
        return $new;
    }

    public function descrever(): string
    {
        return "termo='{$this->termo}', limite={$this->limite}";
    }
}
```

Cada `with*()` clona o objeto, altera a cópia e a devolve. O `$base` continua com os valores iniciais e pode ser reaproveitado como ponto de partida para outros filtros sem risco de um uso contaminar o outro.

O mesmo padrão aparece em todo o framework: `Route` e `Group` no [roteamento](/requisicoes/roteamento), o `Cookie`, o `OffsetPaginator` do `yiisoft/data`, o middleware `Redirect`, os widgets e o `Html`. Na prática, isso dá três garantias:

- Um objeto que você passou para outro código não muda por baixo de você.
- Um objeto configurado uma vez, como um paginador base guardado num serviço, pode ser usado em várias requisições sem carregar sobras da anterior.
- Middlewares não alteram a requisição: eles passam adiante uma cópia (`$request->withAttribute('usuario', $usuario)`), e quem vem antes na pilha continua vendo a original.

## Serviços compartilhados e estado

O [container](/conceitos/container-di) cria cada serviço uma vez e devolve a mesma instância a quem pedir. Até as actions são buscadas no container, então também são instâncias únicas.

Isso é bom para desempenho e é o comportamento esperado para serviços sem estado: um repositório, um gerador de URL, um logger. O problema aparece quando um serviço guarda dados de uma operação nas próprias propriedades. Quem pedir o serviço depois enxerga esses dados.

O `NotificadorEmMemoria` do capítulo anterior é um serviço com estado: ele acumula as mensagens enviadas numa propriedade. A action de `src/Web/Conceitos/Estado/Action.php` mostra o acúmulo e o reset:

```php
<?php

declare(strict_types=1);

namespace App\Web\Conceitos\Estado;

use App\Conceitos\Notificacao\NotificadorEmMemoria;
use App\Conceitos\Notificacao\RelatorioDiario;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Di\StateResetter;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private RelatorioDiario $relatorio,
        private NotificadorEmMemoria $memoria,
        private StateResetter $stateResetter,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $this->relatorio->enviar('gerente@exemplo.com', 12);
        $this->relatorio->enviar('diretoria@exemplo.com', 12);
        $antes = count($this->memoria->enviadas());

        $this->stateResetter->reset();
        $depois = count($this->memoria->enviadas());

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("Mensagens guardadas antes do reset: $antes\nDepois do reset: $depois\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

Com a rota `/conceitos/estado`:

```plaintext
Mensagens guardadas antes do reset: 2
Depois do reset: 0
```

O `RelatorioDiario` e a action receberam a mesma instância do notificador, por isso a action vê as duas mensagens que o relatório enviou. O `StateResetter` chamou a função `reset` que a definição do `NotificadorEmMemoria` declarou:

```php
NotificadorEmMemoria::class => [
    'class' => NotificadorEmMemoria::class,
    'reset' => function (): void {
        $this->enviadas = [];
    },
],
```

Chamar o reset dentro de uma action é só para a demonstração. Quem chama o `StateResetter` de verdade é o laço do servidor de longa duração, entre uma requisição e outra.

Para evitar o problema na origem:

- Declare serviços como `final readonly class` quando possível. Uma propriedade `readonly` não pode ser alterada depois do construtor, então o serviço não tem como acumular estado. Quase todas as classes deste guia seguem esse formato.
- Passe os dados de uma operação como parâmetros de método, não como propriedades do serviço. `$aviso->avisar($cliente, $pedido)` em vez de `$aviso->setCliente($cliente); $aviso->avisar()`.
- Não guarde a requisição, o usuário atual ou o idioma escolhido num serviço comum. Para isso existem serviços próprios, como o `CurrentRoute`, o `CurrentUser` e o `RequestProviderInterface`, que o framework atualiza a cada requisição e que têm reset configurado nos pacotes.
- Se um serviço precisa mesmo de estado, declare o `reset` na definição dele.

O `AvisoDePedido` deste guia guarda um prefixo, alterado por `usarPrefixo()`. Isso só é aceitável porque o método é chamado uma vez, na definição do container, e o valor vale para todas as requisições. Se alguma action chamasse `usarPrefixo()` com um valor por cliente, as requisições seguintes herdariam o prefixo.

## Servidores de longa duração

No modelo tradicional do PHP, com PHP-FPM ou o servidor embutido, cada requisição começa num processo limpo: o container é montado, a requisição é tratada e tudo é descartado. Um serviço com estado sujo não vaza, porque não sobrevive à requisição.

Com RoadRunner, FrankenPHP no modo worker ou Swoole, o processo PHP fica vivo e atende uma requisição depois da outra. O container e os serviços são montados uma vez só, o que elimina o custo de inicialização e é a razão de usar esses servidores. Mas agora tudo que um serviço guarda numa propriedade passa para a requisição seguinte, inclusive de outro usuário.

O Yii3 foi desenhado para isso. As peças deste capítulo são o que torna possível rodar o mesmo código nos dois modelos:

- Requisição e resposta imutáveis: cada requisição tem os seus objetos, e nada do ciclo anterior fica pendurado neles.
- Serviços sem estado: podem ser compartilhados com segurança entre requisições.
- `reset` nas definições: o laço do servidor chama `$container->get(StateResetter::class)->reset()` depois de cada resposta, e os serviços com estado voltam ao início. Os pacotes do Yii3 já declaram o reset dos serviços deles, como a sessão (veja a definição do `SessionInterface` em `vendor/yiisoft/session/config/di-web.php`).

O capítulo [Servidores de longa duração](/topicos/longa-duracao) mostra como rodar o app nesses servidores.

::: warning
Variáveis estáticas e propriedades `static` não passam pelo `StateResetter`. Num servidor de longa duração, um cache em `static` dentro de uma classe vive até o processo morrer. Evite estado estático, ou limpe-o explicitamente.
:::

## Comparando com o Yii2

- No Yii2, `Yii::$app->response->headers->set()` alterava o objeto compartilhado. No Yii3, `withHeader()` devolve outro objeto.
- Componentes do Yii2 eram configurados por propriedades públicas e podiam ser alterados em qualquer ponto. No Yii3, a configuração vai no construtor ou em `with*()`, e o objeto configurado não muda.
- O Yii2 supunha um processo por requisição e tinha muito estado global (`Yii::$app`, componentes com estado). O Yii3 evita estado global para poder rodar em servidores de longa duração.
