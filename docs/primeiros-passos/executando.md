# Executando a aplicação

Com o projeto criado e o `./yii serve` rodando, vale entender o que o template montou antes de escrever código.

## As pastas

```plaintext
meu-app/
  assets/              # CSS e JS de origem, publicados em public/assets
  config/              # toda a configuração
    common/            # vale para web e console
      di/              # definições do container (todo arquivo aqui é carregado)
      params.php
      routes.php
    console/           # só para o console
    web/               # só para a web
    environments/      # ajustes por ambiente: dev, prod e test
    configuration.php  # o mapa que diz como os arquivos acima se juntam
  docker/              # Docker para desenvolvimento e produção
  public/              # raiz do servidor web
    index.php          # script de entrada da web
  runtime/             # logs, cache e arquivos gerados
  src/                 # seu código, namespace App\
  tests/               # testes com Codeception
  yii                  # script de entrada do console (yii.bat no Windows)
```

Só a pasta `public/` deve ficar exposta no servidor web. O resto, inclusive `config/` e `runtime/`, fica fora do alcance do navegador.

## O código que já vem pronto

Dentro de `src/`, o template traz poucas classes:

```plaintext
src/
  Console/HelloCommand.php        # o comando ./yii hello
  Web/
    HomePage/
      Action.php                  # a página inicial
      template.php                # a view dela
    NotFound/
      NotFoundHandler.php         # a página 404
      template.php
    Shared/Layout/Main/
      layout.php                  # o layout com cabeçalho e rodapé
      MainAsset.php               # o CSS do layout
  Environment.php                 # lê APP_ENV e APP_DEBUG
```

Repare que a view mora ao lado da action que a usa. O template organiza o código por funcionalidade (`HomePage`, `NotFound`), e não por tipo de arquivo (`controllers/`, `views/`). Os exemplos deste guia seguem a mesma ideia.

## O caminho de uma requisição

Quando você abre http://127.0.0.1:8080, acontece isto:

1. O servidor entrega a requisição para `public/index.php`.
2. O `index.php` cria um `HttpApplicationRunner`, que monta a configuração e o container de DI.
3. A requisição passa por uma pilha de middlewares definida em `config/web/di/application.php`: tratamento de erros, sessão, CSRF e, por último, o roteador.
4. O roteador procura a rota em `config/common/routes.php`. A rota `/` aponta para `App\Web\HomePage\Action`.
5. O container cria a action, entregando o que ela pede no construtor (aqui, o `WebViewRenderer`).
6. A action renderiza a view dentro do layout e devolve uma resposta.
7. A resposta volta pelos middlewares e é enviada ao navegador.

Se nenhuma rota combinar com a URL, quem responde é o `NotFoundHandler`, com uma página 404.

A página inicial inteira é esta classe:

```php
final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
    ) {}

    public function __invoke(): ResponseInterface
    {
        return $this->viewRenderer->render(__DIR__ . '/template');
    }
}
```

E a rota que leva até ela:

```php
Route::get('/')
    ->action(Web\HomePage\Action::class)
    ->name('home'),
```

## O console

O script `yii` é a entrada do console e usa a mesma configuração e o mesmo container da web. Experimente:

```bash
./yii hello
```

Ele imprime `Hello!`. O código está em `src/Console/HelloCommand.php`, e o comando é registrado em `config/console/commands.php`. Pacotes também podem adicionar comandos: depois de instalar o `yiisoft/db-migration`, por exemplo, aparecem os `migrate:*`.

No próximo capítulo, criamos a primeira página.
