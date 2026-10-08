# Requisição

Toda action precisa de dados que vêm do cliente: parâmetros da URL, campos de formulário, JSON, headers. No Yii3 esses dados chegam num objeto PSR-7, e você pode ler direto dele ou deixar que atributos e DTOs façam a extração. Este capítulo mostra as duas formas.

## ServerRequestInterface

A requisição é um objeto que implementa `Psr\Http\Message\ServerRequestInterface`, da [PSR-7](https://www.php-fig.org/psr/psr-7/). O Yii3 não tem uma classe `Request` própria: o template usa a implementação do pacote `httpsoft/http-message`, mas o seu código só depende da interface.

Para receber a requisição, declare um parâmetro com esse tipo no método da action. O dispatcher de middlewares entrega o objeto. Crie `src/Web/Requisicoes/Inspecao/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Inspecao;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $linhas = [
            'Método: ' . $request->getMethod(),
            'Caminho: ' . $request->getUri()->getPath(),
            'Query: ' . json_encode($request->getQueryParams(), JSON_UNESCAPED_UNICODE),
            'Content-Type: ' . $request->getHeaderLine('Content-Type'),
            'User-Agent: ' . $request->getHeaderLine('User-Agent'),
            'Corpo: ' . json_encode($request->getParsedBody(), JSON_UNESCAPED_UNICODE),
        ];

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write(implode("\n", $linhas) . "\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

E a rota, aceitando GET e POST:

```php
use Yiisoft\Http\Method;
use Yiisoft\Request\Body\RequestBodyParser;

Route::methods([Method::GET, Method::POST], '/inspecao')
    ->middleware(RequestBodyParser::class)
    ->action(Requisicoes\Inspecao\Action::class)
    ->name('inspecao'),
```

O `RequestBodyParser` é explicado mais abaixo. Os métodos mais usados da requisição:

- `getMethod()`: o verbo, como `GET` ou `POST`.
- `getUri()`: um `UriInterface`, com `getPath()`, `getQuery()`, `getHost()` e outros.
- `getQueryParams()`: a query string como array, o equivalente a `$_GET`.
- `getParsedBody()`: o corpo já interpretado, o equivalente a `$_POST`.
- `getHeaderLine('Nome')`: o valor do header como string, vazia se ele não existir. O `getHeader('Nome')` devolve os valores como array.
- `getCookieParams()`: os cookies, o equivalente a `$_COOKIE`.
- `getUploadedFiles()`: os arquivos enviados, como objetos `UploadedFileInterface`.
- `getAttribute('nome')`: valores que middlewares penduraram na requisição.
- `getServerParams()`: o equivalente a `$_SERVER`.

Nomes de header não diferenciam maiúsculas de minúsculas: `getHeaderLine('content-type')` funciona igual.

Uma requisição GET com query string:

```bash
curl "http://127.0.0.1:8080/requisicoes/inspecao?ordem=nome&pagina=2"
```

```plaintext
Método: GET
Caminho: /requisicoes/inspecao
Query: {"ordem":"nome","pagina":"2"}
Content-Type: 
User-Agent: curl/8.21.0
Corpo: null
```

Os valores da query string são sempre strings. Converter e validar é responsabilidade sua, ou dos atributos que aparecem adiante.

## POST e o token CSRF

O template coloca o `CsrfTokenMiddleware` na pilha global, então todo POST, PUT, PATCH ou DELETE sem um token CSRF válido é recusado com `422`. Num formulário HTML, o `->csrf($csrf)` do `Html::form()` resolve isso, como no capítulo [Trabalhando com formulários](/primeiros-passos/formularios).

Para testar com o curl, é preciso guardar o cookie de sessão e mandar o token no header `X-CSRF-Token`. O layout do template publica o token numa tag `<meta name="csrf">`:

```bash
TOKEN=$(curl -s -c cookies.txt http://127.0.0.1:8080/ | sed -n 's/.*name="csrf" content="\([^"]*\)".*/\1/p')

curl -b cookies.txt -H "X-CSRF-Token: $TOKEN" -d "nome=Ana&idade=30" \
    http://127.0.0.1:8080/requisicoes/inspecao
```

```plaintext
Método: POST
Caminho: /requisicoes/inspecao
Query: []
Content-Type: application/x-www-form-urlencoded
User-Agent: curl/8.21.0
Corpo: {"nome":"Ana","idade":"30"}
```

Os exemplos de POST deste capítulo usam esse `$TOKEN` e esse `cookies.txt`. Uma API que recebe chamadas de outros sistemas normalmente não usa CSRF; isso é tratado na seção [APIs REST](/api/inicio-rapido).

## Corpo em JSON

O `getParsedBody()` só vem preenchido sozinho para formulários (`application/x-www-form-urlencoded` e `multipart/form-data`). Para JSON, o corpo fica cru e `getParsedBody()` devolve `null`.

O middleware `RequestBodyParser`, do pacote `yiisoft/request-body-parser`, resolve isso: ele lê o `Content-Type`, decodifica o JSON e coloca o resultado em `getParsedBody()`. Por isso ele está na rota de `/inspecao`:

```bash
curl -b cookies.txt -H "X-CSRF-Token: $TOKEN" -H "Content-Type: application/json" \
    -d '{"nome":"Ana","tags":["php","yii"]}' \
    http://127.0.0.1:8080/requisicoes/inspecao
```

```plaintext
Método: POST
Caminho: /requisicoes/inspecao
Query: []
Content-Type: application/json
User-Agent: curl/8.21.0
Corpo: {"nome":"Ana","tags":["php","yii"]}
```

Sem o middleware na rota, a mesma chamada mostra `Corpo: null`. Com JSON malformado, o próprio middleware responde `400 Bad Request` e a action nem é chamada.

Aqui o middleware foi colocado só numa rota. Se a aplicação inteira recebe JSON, ele pode ir para um grupo ou para a pilha global; veja [Middleware](/estrutura/middleware).

## Atributos de entrada

Ler `getQueryParams()['pagina']` e converter na mão fica repetitivo. O pacote `yiisoft/input-http` traz atributos que preenchem parâmetros da action a partir da requisição. Você já viu o `#[Query]` no capítulo [Dizendo "Olá"](/primeiros-passos/dizendo-ola). Crie `src/Web/Requisicoes/Busca/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Busca;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Query;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(
        #[Query('q')] string $termo = '',
        #[Query] int $pagina = 1,
        #[Query('tag')] array $tags = [],
    ): ResponseInterface {
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write(sprintf(
            "Termo: %s\nPágina: %d\nTags: %s\n",
            $termo,
            $pagina,
            implode(', ', $tags),
        ));

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

```bash
curl "http://127.0.0.1:8080/requisicoes/busca?q=yii3&pagina=2&tag[]=php&tag[]=psr"
```

```plaintext
Termo: yii3
Página: 2
Tags: php, psr
```

- Sem argumento, o atributo usa o nome do parâmetro do método (`#[Query] int $pagina` lê `pagina`).
- O valor é convertido para o tipo declarado. Se o parâmetro não vier, vale o padrão do método.
- A conversão não é validação. Com `?pagina=abc`, o `$pagina` chega como `0`. Confira os limites antes de usar o valor.

Os atributos disponíveis:

- `#[Query]`: query string.
- `#[Body]`: corpo interpretado, o mesmo de `getParsedBody()`.
- `#[Request]`: atributos da requisição, os de `getAttribute()`.
- `#[UploadedFiles]`: arquivos enviados.
- `#[RouteArgument]`: parâmetros da rota. Este vem do `yiisoft/router`, os outros ficam em `Yiisoft\Input\Http\Attribute\Parameter`.

Os quatro do `input-http` também aceitam um caminho em array, para dados aninhados: `#[Body(['endereco', 'cidade'])]`.

Quem faz esses atributos funcionarem é o `ParametersResolverInterface`, configurado no template em `config/web/di/application.php` com o `HydratorAttributeParametersResolver` e o `RequestInputParametersResolver`.

## DTOs de entrada com o hydrator

Quando a action recebe vários campos, é melhor juntá-los numa classe. Uma classe que estende `AbstractInput` é preenchida pelo hydrator e validada antes de chegar na action. Crie `src/Web/Requisicoes/Contato/ContatoInput.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Contato;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

#[FromBody]
final class ContatoInput extends AbstractInput
{
    #[Required(message: 'Informe o nome.')]
    public string $nome = '';

    #[Required(message: 'Informe o e-mail.')]
    #[Email(message: 'E-mail inválido.', skipOnEmpty: true)]
    public string $email = '';

    #[Length(max: 200, greaterThanMaxMessage: 'A mensagem pode ter no máximo {max} caracteres.')]
    public string $mensagem = '';
}
```

O `#[FromBody]` na classe diz que as propriedades vêm do corpo da requisição. Existe também o `#[FromQuery]`, para a query string. As regras são as mesmas do `yiisoft/validator`, vistas em [Validação](/formularios/validacao).

A action só declara o DTO como parâmetro. Crie `src/Web/Requisicoes/Contato/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Contato;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Status;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(ContatoInput $contato): ResponseInterface
    {
        $resultado = $contato->getValidationResult();

        if (!$resultado->isValid()) {
            $response = $this->responseFactory->createResponse(Status::UNPROCESSABLE_ENTITY);
            foreach ($resultado->getErrorMessagesIndexedByProperty() as $campo => $mensagens) {
                $response->getBody()->write($campo . ': ' . implode(' ', $mensagens) . "\n");
            }

            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("Mensagem de {$contato->nome} <{$contato->email}> recebida.\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

A rota:

```php
Route::post('/contato')
    ->middleware(RequestBodyParser::class)
    ->action(Requisicoes\Contato\Action::class)
    ->name('contato'),
```

Testando com dados inválidos e depois válidos:

```bash
curl -b cookies.txt -H "X-CSRF-Token: $TOKEN" -H "Content-Type: application/json" \
    -d '{"nome":"","email":"ana@"}' http://127.0.0.1:8080/requisicoes/contato

curl -b cookies.txt -H "X-CSRF-Token: $TOKEN" -H "Content-Type: application/json" \
    -d '{"nome":"Ana","email":"ana@exemplo.com","mensagem":"Oi"}' http://127.0.0.1:8080/requisicoes/contato
```

```plaintext
nome: Informe o nome.
email: E-mail inválido.

Mensagem de Ana <ana@exemplo.com> recebida.
```

A primeira resposta vem com status `422`, a segunda com `200`.

Por padrão, um DTO inválido ainda chega na action, e você decide o que fazer com o `getValidationResult()`. O pacote tem o parâmetro `throwInputValidationException` (em `$params['yiisoft/input-http']['requestInputParametersResolver']`) que, ligado, faz o resolver lançar `InputValidationException` em vez de chamar a action. Isso combina com uma camada de tratamento de erros que converta a exceção numa resposta, assunto do capítulo [Tratamento de erros](/requisicoes/tratamento-de-erros).

Para formulários HTML com widgets de campo, o caminho continua sendo o `FormModel` e o `FormHydrator`, de [Criando formulários](/formularios/criando-formularios). O DTO com `AbstractInput` é mais adequado para entradas sem view, como JSON. Upload de arquivos tem capítulo próprio: [Upload de arquivos](/formularios/upload).

## Comparando com o Yii2

- O `Yii::$app->request` global vira o `ServerRequestInterface` recebido como parâmetro. Não existe acesso estático à requisição.
- `$request->get('pagina')` e `$request->post('nome')` viram `getQueryParams()` e `getParsedBody()`, ou os atributos `#[Query]` e `#[Body]`.
- No Yii2, o `JsonParser` era configurado em `request.parsers`. No Yii3, o equivalente é o middleware `RequestBodyParser`.
- O objeto de requisição é imutável. Para "alterar" a requisição, um middleware cria uma cópia com `withAttribute()` e passa adiante. O capítulo [Imutabilidade](/conceitos/imutabilidade) explica o porquê.
