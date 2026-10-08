# Servidores de longa duração

No modelo clássico do PHP, cada requisição começa do zero: o `public/index.php` carrega o autoload, monta a configuração, cria o container, atende a requisição e tudo é descartado no fim. Num servidor de longa duração, a aplicação sobe uma vez e fica na memória atendendo uma requisição atrás da outra. Você economiza o custo de inicialização em cada requisição, mas passa a ter de cuidar do estado que sobra entre elas.

O Yii3 foi desenhado com esse modo em mente. Este capítulo mostra os dois runners disponíveis, para RoadRunner e para FrankenPHP, e o que muda no seu código.

::: warning O que foi verificado aqui
Nenhum dos dois runners está instalado no app de exemplo, e o guia foi escrito num ambiente sem RoadRunner nem FrankenPHP. As configurações abaixo vêm da documentação e do código-fonte dos pacotes, e são passos a fazer no seu projeto, não resultados executados aqui. A parte sobre estado, no fim, foi testada no app.
:::

## Os runners

Quem liga o servidor à aplicação é um runner. O template usa o `yiisoft/yii-runner-http`, no modo clássico. Para longa duração, há dois pacotes:

| Pacote | Servidor | Versão estável no Packagist |
|---|---|---|
| `yiisoft/yii-runner-roadrunner` | RoadRunner | 3.2.0 |
| `yiisoft/yii-runner-frankenphp` | FrankenPHP (modo worker) | 1.0.0 |

Os dois fazem o mesmo trabalho: montam a configuração e o container uma vez e, para cada requisição recebida, chamam a aplicação, enviam a resposta e limpam o estado (mais sobre isso adiante).

## RoadRunner

O [RoadRunner](https://roadrunner.dev/) é um servidor escrito em Go que mantém processos PHP (workers) vivos e repassa as requisições para eles.

Instale o runner e baixe o binário do RoadRunner:

```bash
composer require yiisoft/yii-runner-roadrunner
./vendor/bin/rr get
```

Crie um `worker.php` na raiz do projeto. Ele faz o papel do `public/index.php`:

```php
<?php

declare(strict_types=1);

use App\Environment;
use Yiisoft\Yii\Runner\RoadRunner\RoadRunnerHttpApplicationRunner;

ini_set('display_errors', 'stderr');

require_once __DIR__ . '/src/bootstrap.php';

(new RoadRunnerHttpApplicationRunner(
    rootPath: __DIR__,
    debug: Environment::appDebug(),
    checkEvents: Environment::appDebug(),
    environment: Environment::appEnv(),
))->run();
```

E o `.rr.yaml`, que diz ao RoadRunner como iniciar os workers e onde escutar:

```yaml
version: '3'
server:
  command: "php ./worker.php"

http:
  address: :8082
  pool:
    num_workers: 8
  middleware: ["static"]
  static:
    dir: "./public"
    forbid: [".php", ".htaccess"]

logs:
  mode: production
  level: warn
```

O RoadRunner serve os arquivos de `public/` direto (middleware `static`) e manda o resto para os workers PHP. Para subir:

```bash
./rr serve
```

O pacote também tem integração com o [Temporal](https://temporal.io), para workflows de longa duração, configurada pelos parâmetros `yiisoft/yii-runner-roadrunner`.

## FrankenPHP

O template já usa o FrankenPHP no Docker, mas em modo clássico (veja [Docker](/topicos/docker)). Para passar ao modo worker, instale o runner:

```bash
composer require yiisoft/yii-runner-frankenphp
```

Crie o `worker.php` na raiz. A estrutura é a mesma do `public/index.php` do template, trocando o runner:

```php
<?php

declare(strict_types=1);

use App\Environment;
use Psr\Log\LogLevel;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Renderer\PlainTextRenderer;
use Yiisoft\Log\Logger;
use Yiisoft\Log\StreamTarget;
use Yiisoft\Yii\Runner\FrankenPHP\FrankenPHPApplicationRunner;

$root = __DIR__;

require_once $root . '/src/bootstrap.php';

$runner = new FrankenPHPApplicationRunner(
    rootPath: $root,
    debug: Environment::appDebug(),
    checkEvents: Environment::appDebug(),
    environment: Environment::appEnv(),
    temporaryErrorHandler: new ErrorHandler(
        new Logger([
            (new StreamTarget())->setLevels([LogLevel::EMERGENCY, LogLevel::ERROR, LogLevel::WARNING]),
        ]),
        new PlainTextRenderer(),
    ),
);
$runner->run();
```

Depois, troque o bloco `php_server` dos `Caddyfile`. Em `docker/dev/Caddyfile`:

```plaintext
php_server {
    root /app/public
    worker {
        match *
        file /app/worker.php
        watch /app/**/*.php
    }
}
```

O `watch` reinicia os workers quando um arquivo `.php` muda, para você não precisar reiniciar o container a cada edição. Em `docker/Caddyfile` (produção), use o mesmo bloco sem a linha `watch`. Depois, reconstrua as imagens com `make build`.

Duas observações tiradas do código do runner:

- Se o `worker.php` for executado fora do modo worker (sem a função `frankenphp_handle_request`), ele atende uma única requisição e termina. Ou seja, o mesmo arquivo funciona nos dois modos.
- A variável de ambiente `MAX_REQUESTS` limita quantas requisições cada worker atende antes de sair. O FrankenPHP então inicia um worker novo. É uma rede de segurança contra vazamento de memória.

## Cuidados com estado

No modo clássico, um serviço com estado errado só estraga uma requisição. Num worker, o container é o mesmo para todas as requisições, e os serviços compartilhados também. Uma propriedade preenchida na requisição de um usuário continua lá quando chega a requisição do próximo.

Os problemas típicos são:

- Serviços que guardam dados da requisição em propriedades (usuário atual, idioma, filtros).
- Variáveis `static` e propriedades estáticas usadas como cache.
- Arrays que só crescem, como um registro de consultas executadas, e viram vazamento de memória.

O Yii3 ataca isso de dois jeitos. O primeiro é a imutabilidade: métodos `with*()` devolvem uma cópia em vez de mudar o objeto compartilhado. O `withLocale()` do tradutor, usado em [Internacionalização](/topicos/i18n), é um exemplo. O capítulo [Imutabilidade](/conceitos/imutabilidade) trata disso.

O segundo é o `StateResetter`, do `yiisoft/di`. Os dois runners chamam `StateResetter::reset()` e `gc_collect_cycles()` depois de cada requisição. O `reset()` executa os callbacks `reset` declarados nas definições do container. Vários pacotes do Yii já declaram os seus: o tradutor volta ao idioma padrão, a sessão é fechada, a view limpa os blocos e parâmetros.

### Um serviço com reset

Imagine um serviço que guarda o ID da requisição atual. Crie `src/Topicos/LongaDuracao/ContextoDaRequisicao.php`:

```php
<?php

declare(strict_types=1);

namespace App\Topicos\LongaDuracao;

/**
 * Guarda um dado que só vale durante uma requisição.
 */
final class ContextoDaRequisicao
{
    private ?string $idDaRequisicao = null;

    public function definirId(string $id): void
    {
        $this->idDaRequisicao = $id;
    }

    public function id(): ?string
    {
        return $this->idDaRequisicao;
    }
}
```

Para limpar o estado, declare um `reset` na definição. Trecho de `config/common/di/topicos.php`:

```php
use App\Topicos\LongaDuracao\ContextoDaRequisicao;

return [
    ContextoDaRequisicao::class => [
        'reset' => function () {
            $this->idDaRequisicao = null;
        },
    ],
];
```

O callback roda com `$this` apontando para a instância do serviço e tem acesso às propriedades privadas. Por isso ele consegue zerar `$idDaRequisicao` sem que a classe precise de um método público para isso.

Dá para conferir o comportamento sem RoadRunner nem FrankenPHP: basta montar o container do app e chamar o `StateResetter` como os runners fazem. O teste `tests/Functional/ContextoDaRequisicaoCest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Environment;
use App\Topicos\LongaDuracao\ContextoDaRequisicao;
use Yiisoft\Di\StateResetter;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;

use function dirname;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;

final class ContextoDaRequisicaoCest
{
    public function resetLimpaOEstado(): void
    {
        $runner = new HttpApplicationRunner(
            rootPath: dirname(__DIR__, 2),
            environment: Environment::appEnv(),
        );
        $container = $runner->getContainer();

        $contexto = $container->get(ContextoDaRequisicao::class);
        $contexto->definirId('req-1');

        // O mesmo objeto volta do container: o estado sobrevive entre requisições
        assertSame('req-1', $container->get(ContextoDaRequisicao::class)->id());

        // É isto que os runners de longa duração chamam ao fim de cada requisição
        $container->get(StateResetter::class)->reset();

        assertNull($contexto->id());
    }
}
```

```bash
vendor/bin/codecept run Functional ContextoDaRequisicaoCest
```

```plaintext
OK (1 test, 2 assertions)
```

Sem o `reset` na definição, o teste falha com `Failed asserting that 'req-1' is null.`: é exatamente o vazamento de estado que aconteceria entre duas requisições num worker.

### Outras precauções

- Prefira serviços sem estado. Dado de requisição deve viajar na própria requisição (atributos do `ServerRequestInterface`) ou em objetos criados por requisição, não em propriedades de serviços.
- Conexões com banco ou Redis podem cair durante a vida longa do worker. Teste o comportamento da sua aplicação depois de o banco reiniciar.
- Código novo só entra em vigor quando os workers reiniciam. Em desenvolvimento, use o `watch` do FrankenPHP. Em produção, reinicie os workers a cada deploy.
- Não use `exit()` nem `die()` no meio da aplicação: eles derrubam o worker inteiro.
