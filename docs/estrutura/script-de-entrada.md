# Script de entrada

Todo app Yii3 começa num arquivo PHP comum: o `public/index.php` para a web e o `./yii` para o console. Esses scripts são curtos. Eles carregam o autoload, descobrem em que ambiente estão e entregam o resto para um runner, que monta a configuração e o container. Entender esse começo ajuda quando algo falha antes mesmo de chegar na sua action, ou quando você precisa mudar o comportamento entre desenvolvimento e produção.

## public/index.php

Este é o arquivo que o servidor web executa em toda requisição. No template, ele é assim:

```php
<?php

declare(strict_types=1);

use App\Environment;
use Psr\Log\LogLevel;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Renderer\HtmlRenderer;
use Yiisoft\Log\Logger;
use Yiisoft\Log\StreamTarget;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;

$root = dirname(__DIR__);

require_once $root . '/src/bootstrap.php';

if (Environment::appC3()) {
    $c3 = $root . '/c3.php';
    if (file_exists($c3)) {
        require_once $c3;
    }
}

/**
 * @psalm-var string $_SERVER['REQUEST_URI']
 */
// PHP built-in server routing.
if (PHP_SAPI === 'cli-server') {
    // Serve static files as is.
    /** @var string $path */
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file(__DIR__ . $path)) {
        return false;
    }

    // Explicitly set for URLs with dot.
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

// Run HTTP application runner
$runner = new HttpApplicationRunner(
    rootPath: $root,
    debug: Environment::appDebug(),
    checkEvents: Environment::appDebug(),
    environment: Environment::appEnv(),
    temporaryErrorHandler: new ErrorHandler(
        new Logger(
            [
                (new StreamTarget())->setLevels([
                    LogLevel::EMERGENCY,
                    LogLevel::ERROR,
                    LogLevel::WARNING,
                ]),
            ],
        ),
        new HtmlRenderer(),
    ),
);
$runner->run();
```

Por partes:

- `src/bootstrap.php` carrega o autoload do Composer e as variáveis de ambiente. Ele é visto mais abaixo.
- O bloco do `c3.php` só roda quando `APP_C3` está ligado. Ele serve para medir cobertura de código nos testes web do Codeception.
- O bloco `cli-server` só vale para o servidor embutido do PHP (o que o `./yii serve` usa). Se a URL aponta para um arquivo que existe em `public/`, como `/favicon.svg`, o PHP entrega o arquivo direto. Em produção, com Nginx ou Apache, quem faz isso é o próprio servidor.
- O `HttpApplicationRunner` recebe a pasta raiz, o modo debug e o nome do ambiente. O `temporaryErrorHandler` é usado só enquanto a configuração e o container são montados. Se você errar uma vírgula num arquivo de configuração, é ele que mostra o erro.

## O ./yii

O script de console é ainda menor:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Environment;
use Yiisoft\Yii\Runner\Console\ConsoleApplicationRunner;

require_once __DIR__ . '/src/bootstrap.php';

// Run console application runner
$runner = new ConsoleApplicationRunner(
    rootPath: __DIR__,
    debug: Environment::appDebug(),
    checkEvents: Environment::appDebug(),
    environment: Environment::appEnv(),
);
$runner->run();
```

No Linux e no macOS, você roda `./yii`. No Windows, o template traz o `yii.bat`, que chama `php yii` com os mesmos argumentos.

## O src/bootstrap.php

Os dois scripts começam pelo mesmo arquivo:

```php
<?php

declare(strict_types=1);

use App\Environment;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load .env for non-Docker/non-container environments.
// Existing process environment variables take precedence (Docker, CI, server config).
// phpdotenv is a dev dependency — not available in production (composer install --no-dev).
if (empty($_ENV['APP_ENV']) && class_exists(\Dotenv\Dotenv::class)) {
    \Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}

Environment::prepare();
```

O `vlucas/phpdotenv` está em `require-dev`, então ele só existe em desenvolvimento. Em produção, com `composer install --no-dev`, o `.env` é ignorado e as variáveis precisam vir do servidor, do container Docker ou do CI.

## HttpApplicationRunner e ConsoleApplicationRunner

Os runners vêm dos pacotes `yiisoft/yii-runner-http` e `yiisoft/yii-runner-console`, e os dois estendem `Yiisoft\Yii\Runner\ApplicationRunner`. Lendo o `run()` do runner HTTP, a sequência é esta:

1. Registra o error handler temporário.
2. Cria o objeto de configuração (`Yiisoft\Config\Config`) a partir do `.merge-plan.php`, usando o ambiente recebido no construtor.
3. Cria o container com as definições do grupo `di-web`.
4. Troca o error handler temporário pelo `ErrorHandler` configurado no container.
5. Executa os callbacks do grupo `bootstrap-web`.
6. Se `checkEvents` for `true`, confere se os listeners de eventos configurados existem.
7. Pega a `Yiisoft\Yii\Http\Application` do container e cria a requisição PSR-7 a partir das variáveis globais (`$_SERVER`, `$_GET`, `$_POST`...).
8. Chama `$application->start()`, `$application->handle($request)` e envia a resposta.
9. No final, mesmo se algo falhou, chama `afterEmit()` e `shutdown()`.

O runner também guarda o momento em que começou no atributo `applicationStartTime` da requisição. Qualquer middleware pode ler esse valor com `$request->getAttribute('applicationStartTime')`.

Os nomes dos grupos de configuração são parâmetros do construtor. O runner HTTP usa `params-web`, `di-web`, `bootstrap-web` e `events-web`. O de console usa as versões `-console`. É isso que faz `config/web/` valer só para a web: o arquivo `config/configuration.php` declara que o grupo `di-web` é o `di` comum mais os arquivos de `config/web/di/`.

O `ConsoleApplicationRunner` faz a mesma montagem, mas em vez de uma requisição ele cria um `ArgvInput` do Symfony Console, executa o comando pedido e encerra o processo com o código de saída do comando.

O runner HTTP também tem o método `runAndGetResponse()`, que processa uma requisição e devolve a resposta em vez de enviá-la. Ele é útil em testes; veja [Testes funcionais](/testes/funcionais).

## Variáveis de ambiente e o .env

A classe `App\Environment`, em `src/Environment.php`, centraliza a leitura das variáveis de ambiente. O template usa quatro:

| Variável | Padrão | Para quê |
|---|---|---|
| `APP_ENV` | `prod` | Ambiente: `dev`, `test` ou `prod` |
| `APP_DEBUG` | `false` | Liga o modo debug e a verificação de eventos |
| `APP_C3` | `false` | Liga a cobertura de código nos testes web |
| `APP_HOST_PATH` | `null` | Caminho do projeto na máquina host, quando roda em Docker |

O `.env` que vem no template liga o modo de desenvolvimento:

```ini
APP_ENV=dev
APP_DEBUG=true
```

Repare que o padrão, sem `.env` e sem variável nenhuma, é `prod` com debug desligado. Esquecer de configurar o servidor de produção não liga o debug por acidente.

O valor de `APP_ENV` é validado. Com `APP_ENV=producao`, a aplicação nem sobe:

```plaintext
RuntimeException: APP_ENV="producao" is invalid. Valid values are "dev", "test", "prod".
```

O nome do ambiente escolhe quais arquivos de `config/environments/` entram na configuração. Com `APP_ENV=dev`, entra o `config/environments/dev/params.php`, por exemplo.

### Vendo o ambiente na prática

Para ver os valores, crie uma action que só imprime o que a `Environment` leu. Em `src/Web/Estrutura/Ambiente/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Ambiente;

use App\Environment;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse()
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');

        $response->getBody()->write(sprintf(
            "APP_ENV: %s\nAPP_DEBUG: %s\n",
            Environment::appEnv(),
            Environment::appDebug() ? 'true' : 'false',
        ));

        return $response;
    }
}
```

E a rota:

```php
Route::get('/estrutura/ambiente')
    ->action(Estrutura\Ambiente\Action::class)
    ->name('estrutura/ambiente'),
```

Com o servidor rodando normalmente (`./yii serve`), `/estrutura/ambiente` mostra o que está no `.env`:

```plaintext
APP_ENV: dev
APP_DEBUG: true
```

Agora suba o servidor passando as variáveis pelo ambiente do processo:

```bash
APP_ENV=prod APP_DEBUG=false ./yii serve
```

```plaintext
APP_ENV: prod
APP_DEBUG: false
```

As variáveis do processo têm prioridade sobre o `.env`, porque o `phpdotenv` é carregado no modo imutável e não sobrescreve o que já existe.

::: warning O .env continua sendo lido
A condição `empty($_ENV['APP_ENV'])` do `bootstrap.php` depende da diretiva `variables_order` do PHP. Com o valor `GPCS`, comum no `php.ini`, o array `$_ENV` fica vazio e o `.env` é carregado mesmo que `APP_ENV` exista no processo. Subindo só com `APP_ENV=prod ./yii serve`, a página mostra `APP_ENV: prod` e `APP_DEBUG: true`: o ambiente veio do processo e o debug veio do `.env`. Em produção isso não acontece, porque o `phpdotenv` nem está instalado.
:::

### Adicionando uma variável

Para uma variável nova, siga o padrão da própria classe: chame um dos métodos privados de leitura dentro de `prepare()` (`setBoolean()`, `setString()`, `setInteger()` ou `setNonEmptyStringOrNull()`, todos já existem na classe) e crie um método estático público que devolve o valor, como `appDebug()`. Ler com `getenv()` direto não é uma boa ideia: o `phpdotenv` no modo imutável grava em `$_ENV` e `$_SERVER`, não no ambiente do processo, então `getenv()` não enxerga o que está no `.env`. O `getRawValue()` da `Environment` já trata os dois casos.

Use variáveis de ambiente para o que muda entre máquinas: ambiente, debug, senhas de banco, chaves de API. Para valores que fazem parte da aplicação, como o nome da loja ou o tamanho da página, use parâmetros, vistos no capítulo [Aplicação](/estrutura/aplicacao).

## Comparando com o Yii2

- O `web/index.php` do Yii2 definia as constantes `YII_DEBUG` e `YII_ENV` e criava `new yii\web\Application($config)`. No Yii3, o ambiente vem de variáveis de ambiente, e quem cria a aplicação é o runner, a partir do container.
- No Yii2, você montava o array de configuração no próprio `index.php`, com `require` e `ArrayHelper::merge()`. No Yii3, o `yiisoft/config` faz essa junção a partir do merge plan.
- O script `yii` continua existindo e tem o mesmo papel, mas agora os comandos são comandos do Symfony Console.
