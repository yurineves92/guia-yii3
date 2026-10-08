# Debug

Quem vem do Yii2 sente falta da barra de debug no rodapé da página, com as consultas SQL, os logs e o tempo de cada requisição. No Yii3, a ferramenta equivalente é o `yiisoft/yii-debug`, junto com uma API e um painel separados. Este capítulo explica em que estado ela está, como foi desenhada e o que usar para depurar enquanto isso.

## O estado do yii-debug

::: warning Ainda em desenvolvimento
Em outubro de 2026, o `yiisoft/yii-debug` não tem versão estável no Packagist, só ramos de desenvolvimento (`dev-master`). O mesmo vale para o `yiisoft/yii-debug-api` e para o gerador de código `yiisoft/yii-gii`. Por isso, nenhum deles está instalado no app de exemplo, e nada desta seção foi executado: a descrição vem dos repositórios no GitHub e dos metadados do Packagist.
:::

Há um obstáculo concreto além da falta de versão. O `dev-master` do `yii-debug` declara compatibilidade com o `symfony/console` nas versões 5.4, 6 e 7, e o template atual usa o `symfony/console` 8. Pelas restrições publicadas, o Composer não conseguiria instalar o `yii-debug` neste app sem mudanças. O guia não tentou a instalação. Antes de usar, confira o repositório: a situação pode ter mudado.

## Como ele foi desenhado

A ferramenta é dividida em três partes:

| Parte | O que faz |
|---|---|
| `yiisoft/yii-debug` | Coleta os dados de cada requisição web ou comando de console e grava em arquivos, por padrão em `@runtime/debug`. |
| `yiisoft/yii-debug-api` | Expõe os dados coletados numa API HTTP, em `/debug`. |
| `yii-dev-panel` | Aplicação JavaScript com o painel e a barra que aparece na página. Fica num repositório próprio no GitHub (`yiisoft/yii-dev-panel`). |

A coleta é feita por **coletores** (collectors). Cada coletor cuida de um assunto: logs, eventos disparados, serviços pedidos ao container, exceções, chamadas HTTP feitas pela aplicação, dados da requisição, middlewares executados, comandos de console.

Para observar um serviço sem mudar o seu código, o `yii-debug` troca o serviço do container por um proxy. O proxy repassa cada chamada ao serviço original e avisa o coletor. Por exemplo, a `LoggerInterface` é embrulhada pelo `LoggerInterfaceProxy`, que entrega cada mensagem de log ao `LogCollector`.

A integração com os outros pacotes já está pronta no lado deles. Vários pacotes instalados no app trazem o próprio coletor e declaram, nos seus parâmetros `yiisoft/yii-debug`, quais serviços devem ser embrulhados:

| Pacote | Coletor |
|---|---|
| `yiisoft/db` | `DatabaseCollector` (consultas SQL e transações) |
| `yiisoft/router` | `RouterCollector` (rota encontrada) |
| `yiisoft/mailer` | `MailerCollector` (e-mails enviados) |
| `yiisoft/validator` | `ValidatorCollector` (validações executadas) |
| `yiisoft/assets` | `AssetCollector` (asset bundles registrados) |
| `yiisoft/auth` | `IdentityCollector` (usuário autenticado) |
| `yiisoft/yii-view-renderer` | `WebViewCollector` (views renderizadas) |

Enquanto o `yii-debug` não está instalado, esses parâmetros simplesmente não são lidos por ninguém.

## Ligando o debug

Segundo o README do pacote, a instalação é feita como dependência de desenvolvimento e o debug é ligado por um parâmetro:

```bash
composer require yiisoft/yii-debug --dev
```

```php
'yiisoft/yii-debug' => [
    'enabled' => true,
],
```

Para ver os dados, o README indica instalar também o `yiisoft/yii-debug-api` e o painel `yii-dev-panel`. A documentação do pacote cita ainda os comandos `debug:reset`, `debug:container` e `debug:events`.

## O painel

O painel é uma aplicação web à parte que conversa com a `yii-debug-api`. Ele lista as requisições e comandos registrados e mostra, para cada um, o que os coletores juntaram. O repositório também traz uma barra (toolbar) que pode ser embutida nas páginas da aplicação, no lugar da barra do Yii2.

Como o painel não está publicado em versão estável e não foi instalado aqui, o guia não mostra telas nem passos de uso. Quando houver versão estável, este capítulo deve ser revisto.

## Gii

O `yiisoft/yii-gii`, o gerador de código, está na mesma situação: só ramos de desenvolvimento no Packagist. Pelo README, ele oferece comandos de console (`gii:controller`, `gii:active-record`) e uma API JSON em `/gii/api`, liberada por padrão só para `127.0.0.1` e `::1`. O `dev-master` também declara compatibilidade com o `symfony/console` só até a versão 7.

## Depurando sem o yii-debug

Enquanto isso, o template já traz o necessário para a maior parte das investigações.

### A página de erro

Com `APP_DEBUG=true` (o padrão do `.env.example`), uma exceção não tratada mostra a página do `yiisoft/error-handler`, com a mensagem, o trecho de código e a pilha de chamadas. Em produção, a mesma exceção mostra uma página genérica. O capítulo [Tratamento de erros](/requisicoes/tratamento-de-erros) entra nos detalhes.

Cada linha da pilha pode virar um link que abre o arquivo no editor. O formato do link fica no parâmetro `traceLink`, definido só no ambiente de desenvolvimento, em `config/environments/dev/params.php`:

```php
return [
    'traceLink' => 'phpstorm://open?url=file://{file}&line={line}',
];
```

O valor do template abre o PhpStorm. Para o VS Code, troque por `'vscode://file/{file}:{line}'`. Se a aplicação roda em Docker, a variável `APP_HOST_PATH` corrige o caminho dos arquivos, como explicado em [Docker](/topicos/docker).

### Inspecionando variáveis

O pacote `yiisoft/var-dumper` está instalado no app (ele chega como dependência de outros pacotes) e registra as funções `d()`, `dd()` e `dump()`. O `d()` imprime os valores, o `dd()` imprime e encerra o script.

```bash
php -r 'require "vendor/autoload.php"; d(["nome" => "Ana", "idade" => 30]);'
```

```plaintext
[
    'nome' => 'Ana'
    'idade' => 30
]
```

Numa página web, a saída sai com destaque de sintaxe. Se você for usar essas funções no seu código, adicione o `yiisoft/var-dumper` ao `require-dev` do `composer.json`, em vez de depender dele chegar por outro pacote. E não esqueça nenhum `dd()` no código que vai para produção.

### Logs e Xdebug

Para acompanhar o que acontece sem parar a execução, escreva no log com a `LoggerInterface` e acompanhe o arquivo em `runtime/`. Veja [Logs](/requisicoes/logs).

Para depurar passo a passo, use o Xdebug. A imagem Docker de desenvolvimento do template já vem com ele instalado. A configuração está em [Docker](/topicos/docker).

## Comparando com o Yii2

- O `yii2-debug` era um módulo único, com barra e páginas renderizadas pelo próprio Yii. No Yii3, a coleta (`yii-debug`), a API (`yii-debug-api`) e a interface (`yii-dev-panel`) são projetos separados.
- No Yii2, os painéis eram classes do módulo de debug. No Yii3, cada pacote traz o seu coletor e declara por configuração os serviços que devem ser observados.
- O Gii do Yii2 tinha interface web própria. O do Yii3, por ora, oferece comandos de console e uma API JSON.
