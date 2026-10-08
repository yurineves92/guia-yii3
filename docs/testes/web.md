# Testes web

O teste web é o que mais se parece com um usuário de verdade: ele faz requisições HTTP a um servidor rodando, segue links, preenche formulários e lê o HTML devolvido. Ele confere coisas que os outros testes não alcançam, como cookies, sessão e o servidor web em si. Em troca, é o mais lento e precisa de um servidor no ar.

## Codeception com o servidor rodando

A configuração está em `tests/Web.suite.yml`:

```yaml
actor: WebTester
extensions:
  enabled:
    - Codeception\Extension\RunProcess:
        0: composer serve
        sleep: 3
modules:
  enabled:
    - PhpBrowser:
        url: http://127.0.0.1:8080
```

São duas peças:

- O módulo `PhpBrowser` faz as requisições com o Guzzle e interpreta o HTML. Ele guarda cookies entre as requisições, como um navegador, mas não executa JavaScript.
- A extensão `RunProcess` sobe o `composer serve` (o `./yii serve` na porta 8080) antes da suíte, espera 3 segundos e derruba o processo no fim.

O template traz dois testes: `HomePageCest`, que abre `/` e procura o texto "Hello!", e `NotFoundHandlerCest`, que confere a página 404 e o link "Go Back Home".

## Testando um formulário

O formulário de cadastro, de [Trabalhando com formulários](/primeiros-passos/formularios), não pôde ser enviado com sucesso no [teste funcional](/testes/funcionais), por causa do token CSRF. Aqui o problema não existe: o `PhpBrowser` abre a página, recebe o cookie de sessão, e o formulário já traz o token num campo escondido. Ao clicar em "Enviar", tudo vai junto. Crie `tests/Web/CadastroCest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Support\WebTester;

final class CadastroCest
{
    public function enviaDadosValidos(WebTester $I): void
    {
        $I->amOnPage('/cadastro');
        $I->fillField('Nome', 'Ana');
        $I->fillField('E-mail', 'ana@example.com');
        $I->click('Enviar');

        $I->seeResponseCodeIs(200);
        $I->see('Recebemos o seu cadastro', 'h1');
        $I->see('ana@example.com');
    }

    public function mostraOsErrosDeValidacao(WebTester $I): void
    {
        $I->amOnPage('/cadastro');
        $I->fillField('E-mail', 'nao-e-email');
        $I->click('Enviar');

        $I->see('Informe o seu nome.');
        $I->see('Este e-mail não parece válido.');
        $I->seeInField('E-mail', 'nao-e-email');
    }
}
```

O teste fala a língua da página:

- `fillField('Nome', ...)` encontra o campo pelo texto do `<label>`. Também aceita o `name` do campo ou um seletor CSS.
- `click('Enviar')` encontra o botão pelo texto e envia o formulário que o contém.
- `see('Recebemos o seu cadastro', 'h1')` procura o texto dentro de um `<h1>`.
- `seeInField()` confere o valor de um campo. Aqui, que o e-mail digitado voltou preenchido depois do erro.

Como o teste depende dos textos da página, ele quebra se alguém mudar um rótulo. Às vezes é isso que você quer. Quando não for, prefira seletores mais estáveis, como `name` ou `id`.

## Rodando em outra porta

A suíte espera o app em `http://127.0.0.1:8080`. Se essa porta já está ocupada por outro programa, acontecem duas coisas: o `composer serve` da extensão não sobe (o `./yii serve` recusa com `http://127.0.0.1:8080 is taken by another process.`) e os testes fazem as requisições para o programa que está na porta, falhando de jeitos confusos.

A saída é subir o app em outra porta e dizer ao Codeception onde ele está, sem mexer no arquivo da suíte. Num terminal:

```bash
./yii serve --port=8106
```

Em outro:

```bash
vendor/bin/codecept run Web -o "modules: config: PhpBrowser: url: http://127.0.0.1:8106"
```

A opção `-o` (ou `--override`) sobrescreve a configuração só nesta execução. A chave `modules: config: PhpBrowser` tem prioridade sobre a configuração escrita junto do `enabled` no `Web.suite.yml`.

```plaintext
App\Tests.Web Tests (5) -------------------------------------------------------

  [RunProcess] Starting composer serve
+ CadastroCest: Mostra os erros de validacao (1.56s)
+ CadastroCest: Envia dados validos (0.06s)
+ NotFoundHandlerCest: See 404 page. (0.23s)
+ NotFoundHandlerCest: Check "Go Back Home" link. (0.08s)
+ HomePageCest: Home page works. (0.02s)
-------------------------------------------------------------------------------
  [RunProcess] Stopping composer serve

OK (5 tests, 14 assertions)
```

A extensão continua tentando subir o `composer serve`, que encerra sozinho porque a 8080 está ocupada. Os testes usam o servidor da porta 8106.

A mesma opção funciona para a rodada completa (`vendor/bin/codecept run -o "..."`). Trocar o comando da extensão pela linha de comando não dá certo: o Codeception junta a lista do arquivo com a da opção, em vez de substituir, e acaba tentando rodar os dois comandos.

## Cobertura nos testes web

Nos testes web, o código roda no processo do servidor, não no processo do teste. Para medir a cobertura, o template usa o `codeception/c3`: com `APP_C3=true`, o `public/index.php` carrega o `c3.php`, que coleta a cobertura de cada requisição e entrega ao Codeception. O ambiente de testes do Docker já liga essa variável, e o `make test-coverage` junta tudo num relatório. Veja [Docker](/topicos/docker).

## Quando o JavaScript importa

O `PhpBrowser` não executa JavaScript. Se uma tela depende dele (um campo que aparece ao marcar uma opção, uma busca que carrega por AJAX), o teste precisa de um navegador de verdade. O Codeception tem o módulo `WebDriver` (pacote `codeception/module-webdriver`, que não vem no template), que controla um Chrome ou Firefox via Selenium ou ChromeDriver. A estrutura dos testes continua a mesma: `amOnPage()`, `fillField()`, `click()`, `see()`.

## Comparando com o Yii2

Os templates do Yii2 tinham uma suíte de aceitação (`acceptance`) que podia usar o mesmo `PhpBrowser`, com os mesmos métodos, e você subia o servidor antes de rodá-la. No Yii3, a suíte se chama Web e o template tenta subir o servidor sozinho, com a extensão `RunProcess`.
