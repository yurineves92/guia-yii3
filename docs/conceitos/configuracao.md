# Configuração

Um app Yii3 é montado a partir de arrays de configuração: definições do container, parâmetros, rotas, listeners de eventos. Parte desses arrays vem dos pacotes instalados em `vendor/`, parte vem da pasta `config/` da aplicação, e parte muda conforme o ambiente. Quem junta tudo é o pacote `yiisoft/config`. Este capítulo explica como ele decide o que carregar e em que ordem, e onde colocar a sua configuração.

## yiisoft/config e o merge plan

O pacote tem duas partes:

- Um plugin do Composer. Sempre que o Composer atualiza o autoload (`install`, `require`, `update`, `dump-autoload`), o plugin procura os pacotes que declaram configuração e grava um plano de merge em `config/.merge-plan.php`.
- Um carregador, a classe `Yiisoft\Config\Config`, que lê o plano em tempo de execução e devolve cada grupo já mesclado.

Um pacote declara a sua configuração no `extra.config-plugin` do próprio `composer.json`. O `yiisoft/session`, por exemplo, traz `config/params.php` (opções da sessão) e `config/di-web.php` (a definição do `SessionInterface`). Por isso, depois de um `composer require`, o serviço já está disponível sem você configurar nada.

A aplicação declara a sua parte em `config/configuration.php`, apontado pelo `extra.config-plugin-file` do `composer.json`. Um trecho do arquivo do template:

```php
return [
    'config-plugin' => [
        'params' => [
            'common/params.php',
            '?common/params/*.php',
        ],
        'params-web' => [
            '$params',
            'web/params.php',
        ],
        'di' => 'common/di/*.php',
        'di-web' => [
            '$di',
            'web/di/*.php',
        ],
        'events' => '?common/events/*.php',
        'events-web' => ['$events'],
        'routes' => [
            'common/routes.php',
            '?common/routes/*.php',
        ],
        // ...
    ],
    'config-plugin-environments' => [
        Environment::DEV => [
            'params' => [
                'environments/dev/params.php',
            ],
        ],
        // ...
    ],
    'config-plugin-options' => [
        'source-directory' => 'config',
    ],
];
```

Cada chave de `config-plugin` é um grupo, e o valor é a lista de arquivos que formam o grupo, relativos a `config/`. Na lista valem três marcadores:

- `*`: curinga. `common/di/*.php` carrega todos os arquivos da pasta, então um arquivo novo em `config/common/di/` passa a valer sem você registrar em lugar nenhum.
- `?`: arquivo opcional. Sem o `?`, um arquivo que não existe causa erro; com ele, um curinga que não encontra nada também é aceito.
- `$`: referência a outro grupo. `params-web` começa com tudo que está em `params` e acrescenta `web/params.php`.

Depois de alterar `config/configuration.php`, reconstrua o plano:

```bash
composer yii-config-rebuild
```

Arquivos novos dentro de uma pasta já coberta por um curinga não precisam disso: o curinga é resolvido em tempo de execução.

### Rotas, params e eventos em vários arquivos

O template original tem só `common/routes.php` no grupo `routes`, só `common/params.php` no grupo `params` e o grupo `events` vazio. O app de exemplo deste guia acrescentou as entradas `'?common/routes/*.php'`, `'?common/params/*.php'` e `'?common/events/*.php'` mostradas acima. Assim, cada seção do guia tem o seu próprio arquivo de rotas (`config/common/routes/requisicoes.php`, `config/common/routes/conceitos.php`...), de params e de eventos, sem mexer nos arquivos das outras. O `?` deixa as pastas começarem vazias.

Num projeto real, a mesma técnica serve para separar a configuração por módulo ou por área do sistema.

## Os grupos

Os grupos que o template define, e quem os usa:

- `params`, `params-web`, `params-console`: parâmetros, ou seja, valores simples como nomes, chaves e opções. Disponíveis como `$params` dentro dos outros arquivos de configuração.
- `di`, `di-web`, `di-console`: definições do container. Veja [Container de DI](/conceitos/container-di).
- `di-providers*`, `di-delegates*`: provedores de serviço e containers delegados, para casos avançados.
- `events`, `events-web`, `events-console`: listeners de eventos. Veja [Eventos](/conceitos/eventos).
- `routes`: as rotas, lidas em `config/common/di/router.php` com `$config->get('routes')`.
- `bootstrap*`: funções executadas logo depois de o container ser criado.

Os grupos com sufixo `-web` são lidos pelo `HttpApplicationRunner` (em `public/index.php`), e os com `-console` pelo `ConsoleApplicationRunner` (no `./yii`). É assim que o container da web tem o `SessionInterface` e o do console não. Os grupos sem sufixo são a base comum, incluída nos outros pela referência `$`.

## Params na prática

Parâmetros servem para tirar valores do código. Crie `config/common/params/conceitos.php`:

```php
<?php

declare(strict_types=1);

return [
    'conceitos' => [
        'saudacao' => 'Bem-vindo ao guia',
        'itensPorPagina' => 10,
        'remetente' => 'loja@guia.test',
    ],

    'yiisoft/aliases' => [
        'aliases' => [
            '@relatorios' => '@runtime/relatorios',
        ],
    ],
];
```

A chave `conceitos` é nossa. A chave `yiisoft/aliases` pertence ao pacote de aliases e é explicada em [Aliases](/conceitos/aliases). Agrupe os seus parâmetros sob uma chave própria, para não colidir com os pacotes.

A classe que usa os valores não lê `$params`. Ela recebe os valores no construtor, como qualquer dependência. Em `src/Conceitos/Saudacao.php`:

```php
<?php

declare(strict_types=1);

namespace App\Conceitos;

final readonly class Saudacao
{
    public function __construct(
        private string $mensagem,
        private int $itensPorPagina,
    ) {}

    public function texto(): string
    {
        return "{$this->mensagem} (mostrando {$this->itensPorPagina} itens por página)";
    }
}
```

E a ligação entre os dois fica numa definição do container, em `config/common/di/conceitos.php`. Um trecho:

```php
/** @var array $params */

return [
    Saudacao::class => [
        '__construct()' => [
            'mensagem' => $params['conceitos']['saudacao'],
            'itensPorPagina' => $params['conceitos']['itensPorPagina'],
        ],
    ],
    // ...
];
```

Dentro dos arquivos de configuração, a variável `$params` já existe, com o grupo de params mesclado. O comentário `@var` só serve para o editor. Uma action que pede `Saudacao` no construtor recebe a instância pronta; com a rota `/conceitos/configuracao`, a resposta é:

```plaintext
Bem-vindo ao guia (mostrando 10 itens por página)
```

Essa separação é proposital: `Saudacao` não sabe que existe um arquivo de configuração, e num teste você cria `new Saudacao('Oi', 5)` sem carregar nada.

Também existe a variável `$config` nos arquivos de configuração, com o próprio objeto `Config`. O template a usa em `config/common/di/router.php` para ler o grupo `routes`.

## Ordem e sobrescrita

A configuração é montada em três camadas, nesta ordem:

1. Pacotes em `vendor/`, que trazem os valores padrão.
2. A aplicação, em `config/`.
3. O ambiente atual, em `config/environments/<ambiente>/`.

Uma camada pode sobrescrever o que veio da anterior. Para os params, o merge é recursivo: o seu arquivo pode mudar só `['yiisoft/session']['session']['options']` sem repetir o resto da configuração do pacote. Os runners do Yii3 ligam esse merge recursivo para os grupos de params e de eventos.

Dentro de uma mesma camada, a mesma chave definida em dois arquivos gera erro. Se dois arquivos de `config/common/di/` definirem `Saudacao::class`, o carregamento falha com `Duplicate key "..." in the following configs`, citando os dois arquivos. A regra protege contra um arquivo sobrescrever o outro sem ninguém perceber. Arrays de params com chaves diferentes, como as duas entradas `yiisoft/aliases` do template e do arquivo acima, são mesclados normalmente.

## Ambientes: dev, prod e test

A seção `config-plugin-environments` acrescenta arquivos a um grupo só num ambiente. No template, cada ambiente tem o seu `environments/<ambiente>/params.php`, e o de `dev` define o parâmetro `traceLink`, usado pela página de erro para abrir arquivos no editor.

O ambiente vem da variável `APP_ENV`, lida pela classe `App\Environment` do template e passada ao runner:

```php
$runner = new HttpApplicationRunner(
    rootPath: $root,
    debug: Environment::appDebug(),
    checkEvents: Environment::appDebug(),
    environment: Environment::appEnv(),
    // ...
);
```

Os valores aceitos são `dev`, `test` e `prod`, e o padrão, quando a variável não existe, é `prod`. Em desenvolvimento, o `.env` do template define `APP_ENV=dev` e `APP_DEBUG=true`.

Para conferir o que cada ambiente enxerga, dá para usar o carregador direto, da raiz do projeto:

```bash
php -r '
require "vendor/autoload.php";
use Yiisoft\Config\Config;
use Yiisoft\Config\ConfigPaths;
use Yiisoft\Config\Modifier\RecursiveMerge;
foreach (["dev", "prod"] as $env) {
    $config = new Config(new ConfigPaths(getcwd(), "config"), $env, [RecursiveMerge::groups("params")]);
    $params = $config->get("params");
    echo $env, ": ", $params["traceLink"] ?? "(não definido)", PHP_EOL;
}'
```

```plaintext
dev: phpstorm://open?url=file://{file}&line={line}
prod: (não definido)
```

O `RecursiveMerge::groups("params")` reproduz o que o runner faz. Sem ele, o carregamento dos params falha com `Duplicate key "yiisoft/yii-debug"`, porque dois pacotes definem essa chave e esperam o merge recursivo.

`APP_ENV` escolhe quais arquivos de configuração entram. `APP_DEBUG` é independente: liga o modo debug do tratamento de erros e a validação das definições do container e dos eventos. Em produção, os dois ficam desligados.

Para ver os grupos e arquivos que um pacote fornece, use `composer yii-config-info yiisoft/session` (sem o nome do pacote, ele mostra os da aplicação). O pacote também tem o `composer yii-config-copy`, que copia os arquivos de configuração de um pacote para a sua pasta `config/`, com um prefixo no nome, como ponto de partida para editar.

## Comparando com o Yii2

- O `config/web.php` com `components` e `params`, montado à mão com `require` e `ArrayHelper::merge()`, vira grupos mesclados automaticamente pelo `yiisoft/config`.
- A configuração dos pacotes vem junto com eles. No Yii2, extensões usavam `bootstrap` ou pediam que você copiasse a configuração.
- `Yii::$app->params['chave']` não existe. Os params só são lidos dentro dos arquivos de configuração e chegam às classes pelo construtor.
- `YII_ENV` e `YII_DEBUG` viram `APP_ENV` e `APP_DEBUG`.
