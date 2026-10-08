# Pacotes

O Yii3 não é um framework instalado de uma vez. Ele é um conjunto de dezenas de pacotes `yiisoft/*` que você instala conforme precisa: roteador, views, banco, cache, validação. Isso traz uma pergunta prática: como um pacote recém-instalado passa a funcionar sem você escrever a configuração dele? Este capítulo mostra como os pacotes trazem a própria configuração, como sobrescrevê-la e como criar um pacote seu no mesmo formato.

## Usando pacotes yiisoft/*

Instalar um pacote é um `composer require` comum. Os capítulos de [Primeiros passos](/primeiros-passos/formularios) fizeram isso com o `yiisoft/form-model`:

```bash
composer require yiisoft/form-model
```

Logo depois, `FormHydrator` e `Field` já funcionam, sem nenhuma linha em `config/`. Isso acontece porque o pacote declara a própria configuração, e o plugin `yiisoft/config` a junta à da aplicação.

Os pacotes são independentes entre si. Cada um tem versão própria, e muitos podem ser usados fora do Yii: o `yiisoft/html`, o `yiisoft/validator` e o `yiisoft/db`, por exemplo, funcionam em qualquer projeto PHP. A lista completa está em [github.com/yiisoft](https://github.com/yiisoft).

Na hora de escolher, prefira pacotes que dependem de interfaces PSR. O template usa `httpsoft/http-message` como implementação da PSR-7, mas qualquer outra serviria, porque o resto do código só conhece as interfaces `Psr\Http\Message\*`. A troca é feita no `config/web/di/psr17.php`.

## Configuração que vem com o pacote

### A seção extra do composer.json

Um pacote declara a sua configuração na seção `extra` do `composer.json`. O do `yiisoft/aliases`, por exemplo:

```json
"extra": {
    "config-plugin-options": {
        "source-directory": "config"
    },
    "config-plugin": {
        "params": "params.php",
        "di": "di.php"
    }
}
```

O `config-plugin` mapeia grupos de configuração para arquivos. O `source-directory` diz que os arquivos estão na pasta `config/` do pacote. O `params.php` define os valores padrão:

```php
<?php

declare(strict_types=1);

return [
    'yiisoft/aliases' => [
        'aliases' => [],
    ],
];
```

E o `di.php` diz ao container como criar o serviço, usando esses parâmetros:

```php
<?php

declare(strict_types=1);

use Yiisoft\Aliases\Aliases;

/* @var array $params */

return [
    Aliases::class => [
        '__construct()' => [$params['yiisoft/aliases']['aliases']],
    ],
];
```

É o mesmo formato dos arquivos de `config/` da aplicação. Um pacote pode declarar qualquer grupo: `params`, `di`, `di-web`, `bootstrap`, `events-web`... O `yiisoft/yii-view-renderer`, por exemplo, declara `params`, `di-web` e `events-web`, porque ele só faz sentido na web.

### O merge plan

Sempre que o Composer instala, atualiza ou remove pacotes, o plugin `yiisoft/config` lê a seção `extra` de cada pacote e grava o resultado em `config/.merge-plan.php`. Um trecho dele:

```php
'di' => [
    'yiisoft/assets' => [
        'config/di.php',
    ],
    // ...
    'yiisoft/aliases' => [
        'config/di.php',
    ],
    // ...
    '/' => [
        'common/di/*.php',
    ],
],
```

A chave `'/'` é a própria aplicação, e ela vem depois dos pacotes. Os caminhos com `*` são guardados como padrão e resolvidos a cada execução, então um arquivo novo em `config/common/di/` passa a valer sem reconstruir o merge plan. Já uma mudança em `config/configuration.php` exige `composer yii-config-rebuild`.

O plugin só roda porque o `composer.json` do template o autoriza em `config.allow-plugins`. E ele só lê pacotes dos tipos `library` e `composer-plugin`.

Para ver o que um pacote declara, use o comando `yii-config-info`:

```bash
composer yii-config-info yiisoft/aliases
```

```plaintext
Yii Config — Package "yiisoft/aliases"
======================================

Source directory: C:\Users\yurin\Documents\code\studies-yii3\guia-yii3\app\vendor/yiisoft/aliases/config

Configuration groups
--------------------

 di
  - di.php

 params
  - params.php
```

Sem o nome do pacote, `composer yii-config-info` mostra os grupos da aplicação e as opções do plugin.

### Sobrescrevendo a configuração de um pacote

A configuração é montada em três camadas, nesta ordem: pacotes, aplicação e ambiente. Uma camada posterior pode sobrescrever as anteriores.

Para parâmetros, a junção é recursiva. O template sobrescreve os aliases e as opções de view em `config/common/params.php`:

```php
'yiisoft/aliases' => [
    'aliases' => require __DIR__ . '/aliases.php',
],
```

Só a chave `aliases` é trocada; se o pacote tivesse outras chaves em `yiisoft/aliases`, elas continuariam lá. Por isso a recomendação de configurar pacotes pelos parâmetros: você muda só o valor que precisa.

Para definições do container, a definição da aplicação substitui a do pacote inteira. Use quando os parâmetros não bastam, por exemplo para trocar a classe que implementa uma interface.

Dentro de uma mesma camada, a mesma chave não pode aparecer duas vezes. Se dois arquivos de `config/common/di/` definirem a mesma classe, a aplicação não sobe e o `yiisoft/config` mostra um erro `Duplicate key` com os dois arquivos.

Para começar a sobrescrever, às vezes é mais fácil copiar os arquivos do pacote. O comando `yii-config-copy` faz isso, acrescentando o nome do pacote como prefixo para evitar conflito:

```bash
composer yii-config-copy yiisoft/view
```

Ele copia os arquivos de configuração do `yiisoft/view` para `config/`, com nomes como `yiisoft-view-params.php`. Você edita a cópia e a inclui num grupo. O funcionamento dos grupos está em [Configuração](/conceitos/configuracao).

## Criando o seu pacote

Código que se repete entre projetos pode virar um pacote no mesmo formato. O exemplo abaixo é um pacote mínimo com um serviço configurável. Ele foi montado e validado com `composer validate` numa pasta separada, mas não está instalado no app de exemplo deste guia, porque isso exigiria alterar o `composer.json` do app.

A estrutura:

```plaintext
saudacao/
  composer.json
  config/
    di.php
    params.php
  src/
    Saudador.php
```

O `composer.json`:

```json
{
    "name": "minha-empresa/saudacao",
    "description": "Gera saudações para aplicações Yii3",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.2"
    },
    "autoload": {
        "psr-4": {
            "MinhaEmpresa\\Saudacao\\": "src"
        }
    },
    "extra": {
        "config-plugin-options": {
            "source-directory": "config"
        },
        "config-plugin": {
            "params": "params.php",
            "di": "di.php"
        }
    }
}
```

O serviço, em `src/Saudador.php`:

```php
<?php

declare(strict_types=1);

namespace MinhaEmpresa\Saudacao;

final readonly class Saudador
{
    public function __construct(
        private string $saudacao = 'Olá',
    ) {}

    public function saudar(string $nome): string
    {
        return "{$this->saudacao}, {$nome}!";
    }
}
```

Os parâmetros padrão, em `config/params.php`, usando o nome do pacote como chave:

```php
<?php

declare(strict_types=1);

return [
    'minha-empresa/saudacao' => [
        'saudacao' => 'Olá',
    ],
];
```

E a definição, em `config/di.php`:

```php
<?php

declare(strict_types=1);

use MinhaEmpresa\Saudacao\Saudador;

/** @var array $params */

return [
    Saudador::class => [
        '__construct()' => [
            'saudacao' => $params['minha-empresa/saudacao']['saudacao'],
        ],
    ],
];
```

O pacote não depende do Yii. A classe `Saudador` funciona em qualquer projeto; a pasta `config/` só é lida quando o projeto usa o `yiisoft/config`. Se o pacote precisar de alguma classe do Yii, declare o pacote correspondente em `require`.

### Usando o pacote num projeto

Enquanto o pacote não está publicado no Packagist, um projeto pode usá-lo a partir de uma pasta local, com um repositório do tipo `path` no `composer.json` do projeto:

```json
"repositories": [
    {
        "type": "path",
        "url": "../saudacao"
    }
]
```

E então:

```bash
composer require minha-empresa/saudacao:@dev
```

Pelo funcionamento do plugin descrito acima, o merge plan passa a incluir o `config/params.php` e o `config/di.php` do pacote. A partir daí, qualquer classe do projeto pode receber `Saudador` no construtor, e o projeto muda a saudação nos seus próprios parâmetros, sem tocar no pacote:

```php
'minha-empresa/saudacao' => [
    'saudacao' => 'Bom dia',
],
```

Algumas recomendações para pacotes:

- Configure pelos parâmetros, com valores padrão razoáveis, e deixe as definições de DI usarem esses parâmetros.
- Coloque em `di-web` ou `di-console` o que só faz sentido num dos dois.
- Mantenha os serviços sem estado entre requisições, para funcionarem em servidores de longa duração. Veja [Servidores de longa duração](/topicos/longa-duracao).
- Escreva testes do pacote sem a aplicação, só com PHPUnit.

## Comparando com o Yii2

- No Yii2, uma extensão era configurada à mão em `config/web.php` (em `components` ou `modules`) ou por uma classe que implementava `BootstrapInterface`. No Yii3, a configuração vem com o pacote e é juntada automaticamente.
- O arquivo `vendor/yiisoft/extensions.php`, gerado pelo `yii2-composer`, cumpria um papel parecido com o do merge plan, mas guardava só aliases e classes de bootstrap.
- O próprio framework deixou de ser um pacote único (`yiisoft/yii2`) e virou vários pacotes pequenos.
