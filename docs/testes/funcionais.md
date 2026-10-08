# Testes funcionais

Um teste unitário confere uma peça. Um teste funcional confere se as peças estão ligadas: a rota aponta para a action certa, os middlewares deixam a requisição passar, a view recebe os dados, o status HTTP é o esperado. No template do Yii3, esse teste roda a aplicação inteira dentro do próprio processo do teste, sem servidor web.

## Rodando a aplicação dentro do teste

A suíte Functional não usa nenhum módulo do Codeception para HTTP. O template acrescenta ao `FunctionalTester` um método próprio, o `sendRequest()`. Trecho de `tests/Support/FunctionalTester.php`:

```php
public function sendRequest(ServerRequestInterface $request): ResponseInterface
{
    $runner = new HttpApplicationRunner(
        rootPath: dirname(__DIR__, 2),
        environment: Environment::appEnv(),
    );

    $response = $runner->runAndGetResponse($request);

    $body = $response->getBody();
    if ($body->isSeekable()) {
        $body->rewind();
    }

    return $response;
}
```

É o mesmo `HttpApplicationRunner` do `public/index.php`. A diferença é o `runAndGetResponse()`: em vez de enviar a resposta ao navegador, ele a devolve para o teste. A requisição passa por tudo que uma requisição real passa: tratamento de erros, sessão, CSRF, roteador, action e view.

Cada chamada cria um runner novo, com configuração e container novos. Um teste não herda estado do anterior, ao custo de montar a aplicação a cada requisição.

O template traz um exemplo, o `tests/Functional/HomePageCest.php`, que pede `/` e confere o status 200 e um trecho do HTML.

## Testando uma página com parâmetros

A página `/ola`, de [Dizendo "Olá"](/primeiros-passos/dizendo-ola), lê a mensagem da query string. Crie `tests/Functional/OlaCest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use HttpSoft\Message\ServerRequest;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class OlaCest
{
    public function mensagemPadrao(FunctionalTester $tester): void
    {
        $response = $tester->sendRequest(
            new ServerRequest(uri: '/ola'),
        );

        assertSame(200, $response->getStatusCode());
        assertStringContainsString('<h1>Olá</h1>', $response->getBody()->getContents());
    }

    public function mensagemDaQuery(FunctionalTester $tester): void
    {
        $response = $tester->sendRequest(
            new ServerRequest(
                queryParams: ['mensagem' => '<b>Bom dia</b>'],
                uri: '/ola?mensagem=%3Cb%3EBom%20dia%3C%2Fb%3E',
            ),
        );

        assertStringContainsString(
            '<h1>&lt;b&gt;Bom dia&lt;/b&gt;</h1>',
            $response->getBody()->getContents(),
        );
    }
}
```

A requisição é um `ServerRequest` do pacote `httpsoft/http-message`, o mesmo que o template usa para PSR-7. Os argumentos nomeados deixam claro o que está sendo preenchido.

::: warning A query string vai em queryParams
O `ServerRequest` não lê a query da URI. Com `new ServerRequest(uri: '/ola?mensagem=Oi')`, a action recebe `$mensagem` nulo e a página mostra "Olá". Os parâmetros precisam ir em `queryParams`. A URI completa no exemplo é só para manter a requisição coerente.
:::

O segundo teste aproveita para conferir que a view escapa o HTML vindo do usuário.

## Status de erro

Um teste funcional é o lugar certo para conferir os caminhos de erro. Na listagem de [Trabalhando com banco de dados](/primeiros-passos/banco-de-dados), uma página fora do intervalo devolve 404. Crie `tests/Functional/PaisesCest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use HttpSoft\Message\ServerRequest;

use function PHPUnit\Framework\assertSame;

final class PaisesCest
{
    public function paginaForaDoIntervaloDevolve404(FunctionalTester $tester): void
    {
        $response = $tester->sendRequest(
            new ServerRequest(queryParams: ['pagina' => '99'], uri: '/paises?pagina=99'),
        );

        assertSame(404, $response->getStatusCode());
    }
}
```

Este teste usa o banco real do app, `runtime/database.sqlite`, e depende de a migration da tabela `pais` ter sido aplicada. Num projeto de verdade, prefira um banco só para testes, apontado pela configuração do ambiente `test`.

## Enviando formulários

Para um POST, o corpo já interpretado vai em `parsedBody`, no mesmo formato que o PHP colocaria em `$_POST`. Crie `tests/Functional/CadastroCest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use HttpSoft\Message\ServerRequest;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class CadastroCest
{
    public function mostraOFormulario(FunctionalTester $tester): void
    {
        $response = $tester->sendRequest(
            new ServerRequest(uri: '/cadastro'),
        );

        assertSame(200, $response->getStatusCode());
        assertStringContainsString('name="CadastroForm[nome]"', $response->getBody()->getContents());
    }

    public function postSemTokenCsrfERecusado(FunctionalTester $tester): void
    {
        $response = $tester->sendRequest(
            new ServerRequest(
                parsedBody: ['CadastroForm' => ['nome' => 'Ana', 'email' => 'ana@example.com']],
                method: 'POST',
                uri: '/cadastro',
            ),
        );

        assertSame(422, $response->getStatusCode());
    }
}
```

O segundo teste mostra uma consequência de rodar a pilha completa: o `CsrfTokenMiddleware` está lá, e um POST sem o token é recusado com 422. Isso é bom, porque o teste confere que a proteção está ativa.

Para testar o caminho feliz do formulário, o teste precisaria primeiro carregar a página, guardar o cookie de sessão e o token do HTML e mandá-los no POST. É possível fazer isso à mão, mas é exatamente o que um navegador faz, e o [teste web](/testes/web) já cuida disso. A validação em si fica coberta pelo [teste unitário](/testes/unitarios) do formulário.

## Rodando

```bash
vendor/bin/codecept run Functional
```

A saída lista cada método das classes `*Cest`, no formato mostrado em [Visão geral](/testes/visao-geral). Lembre que, sem `APP_ENV` definido, a aplicação dos testes sobe no ambiente `prod`. Para usar o ambiente `test`: `APP_ENV=test vendor/bin/codecept run Functional`.

## Usando o container nos testes

O runner também dá acesso ao container montado, pelo `getContainer()`. Isso permite testar serviços com a configuração real do app, sem passar por HTTP. O capítulo [Servidores de longa duração](/topicos/longa-duracao) usa esse recurso para conferir que um serviço tem o estado limpo entre requisições.

## Comparando com o Yii2

No Yii2, os testes funcionais usavam o módulo `Yii2` do Codeception, com métodos como `$I->amOnPage()` e `$I->see()`, que simulavam o navegador dentro do processo. No template do Yii3, o teste funcional monta a requisição PSR-7 e confere a resposta PSR-7 diretamente. Os métodos de navegador ficam na suíte Web.
