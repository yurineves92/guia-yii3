# Aliases

Caminhos absolutos escritos no código quebram quando o projeto muda de pasta, de máquina ou entra num container. Aliases resolvem isso: são nomes curtos começando com `@`, como `@runtime`, que representam um caminho de diretório ou uma URL. Este capítulo mostra os aliases do template, como criar os seus e como usá-los nas classes.

## Os aliases do template

O pacote é o `yiisoft/aliases`. O template define os aliases em `config/common/aliases.php`:

```php
<?php

declare(strict_types=1);

return [
    '@root' => dirname(__DIR__, 2),
    '@src' => '@root/src',
    '@assets' => '@root/public/assets',
    '@assetsUrl' => '@baseUrl/assets',
    '@assetsSource' => '@root/assets',
    '@baseUrl' => '/',
    '@public' => '@root/public',
    '@runtime' => '@root/runtime',
    '@vendor' => '@root/vendor',
];
```

- `@root` é a raiz do projeto, calculada a partir do próprio arquivo. Todos os outros caminhos derivam dele.
- `@src`, `@public`, `@runtime` e `@vendor` são as pastas principais.
- `@assets` e `@assetsSource` são onde os assets são publicados e de onde vêm; veja [Assets](/estrutura/assets).
- `@baseUrl` e `@assetsUrl` são URLs, não caminhos. Um alias guarda qualquer string; quem decide se é caminho ou URL é quem usa.

Um alias pode ser definido a partir de outro, como `'@src' => '@root/src'`. A resolução acontece na leitura, então a ordem no arquivo não importa.

Esse arquivo é lido em `config/common/params.php`, na chave `yiisoft/aliases`, e o pacote usa esses params para configurar o serviço `Yiisoft\Aliases\Aliases` no container:

```php
'yiisoft/aliases' => [
    'aliases' => require __DIR__ . '/aliases.php',
],
```

## Criando aliases próprios

O jeito direto é acrescentar uma linha em `config/common/aliases.php`. Como os params são mesclados de forma recursiva, outro arquivo de params também pode acrescentar aliases sem mexer nesse. O app de exemplo faz isso em `config/common/params/conceitos.php`:

```php
'yiisoft/aliases' => [
    'aliases' => [
        '@relatorios' => '@runtime/relatorios',
    ],
],
```

A configuração final junta os aliases do template com `@relatorios`. Veja [Configuração](/conceitos/configuracao) para as regras do merge.

Crie aliases para pastas que a aplicação usa em mais de um lugar: onde ficam os uploads, os relatórios gerados, os arquivos de tradução. Se a pasta mudar, muda uma linha.

## Usando Aliases nas classes

O serviço `Aliases` é pedido no construtor como qualquer dependência. Crie `src/Web/Conceitos/Aliases/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Conceitos\Aliases;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Aliases\Aliases;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private Aliases $aliases,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $linhas = [
            '@root => ' . $this->aliases->get('@root'),
            '@runtime => ' . $this->aliases->get('@runtime'),
            '@relatorios/vendas.csv => ' . $this->aliases->get('@relatorios/vendas.csv'),
            '@assetsUrl => ' . $this->aliases->get('@assetsUrl'),
            'caminho sem alias => ' . $this->aliases->get('runtime/tmp'),
        ];

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write(implode("\n", $linhas) . "\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

Com a rota `/conceitos/aliases`, num projeto em `/var/www/app`:

```plaintext
@root => /var/www/app
@runtime => /var/www/app/runtime
@relatorios/vendas.csv => /var/www/app/runtime/relatorios/vendas.csv
@assetsUrl => /assets
caminho sem alias => runtime/tmp
```

No Windows, a parte do `@root` aparece com barras invertidas e o resto com `/`, como em `C:\projetos\app/runtime`. O PHP aceita a mistura.

O que dá para ver:

- `get()` aceita um alias seguido de um caminho (`@relatorios/vendas.csv`) e troca só a parte do alias.
- `@relatorios` foi resolvido em duas etapas: `@runtime/relatorios`, depois `@root/runtime/relatorios`.
- `@assetsUrl` virou `/assets`, porque `@baseUrl` é `/`.
- Uma string que não começa com `@` volta sem mudança. Por isso funções que recebem caminhos podem aceitar os dois formatos e sempre passar por `get()`.

O `get()` não confere se o arquivo ou a pasta existe. Ele só faz a troca de texto. Se o alias não estiver registrado, ele lança `InvalidArgumentException` com a mensagem `Invalid path alias: @...`.

Outros métodos do serviço:

- `getArray(['@runtime', '@public'])`: resolve uma lista de uma vez.
- `getAll()`: todos os aliases já resolvidos.
- `set('@temporario', '@runtime/tmp')` e `remove('@temporario')`: alteram aliases em tempo de execução. Prefira definir na configuração: um alias criado com `set()` só existe a partir daquele ponto, e a ordem de execução passa a importar.

## Aliases na configuração

As definições do container recebem o serviço `Aliases` do mesmo jeito. O template faz isso na conexão com o banco, em `config/common/di/db.php`:

```php
ConnectionInterface::class => static fn (Aliases $aliases, SchemaCache $schemaCache) => new Connection(
    new Driver('sqlite:' . $aliases->get('@runtime/database.sqlite')),
    $schemaCache,
),
```

Vários pacotes aceitam aliases direto nos params e resolvem por conta própria. O `yiisoft/log-target-file` recebe `'file' => '@runtime/logs/app.log'`, e o renderizador de views recebe `'layout' => '@src/Web/Shared/Layout/Main/layout.php'`. Nas views, o template disponibiliza o serviço como `$aliases`.

## Comparando com o Yii2

- `Yii::getAlias('@runtime')` vira `$aliases->get('@runtime')`, com o serviço injetado. Não existe acesso estático.
- `Yii::setAlias()` vira `$aliases->set()`, mas o normal é declarar os aliases na configuração.
- `@app` e `@webroot` não existem no template. Os equivalentes mais próximos são `@root` (ou `@src`, para o código) e `@public`. `@web` corresponde a `@baseUrl`.
