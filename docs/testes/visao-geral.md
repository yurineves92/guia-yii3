# Visão geral

O template `yiisoft/app` vem com testes automatizados configurados e alguns exemplos prontos. A ferramenta é o [Codeception](https://codeception.com/), que roda sobre o PHPUnit e organiza os testes em suítes. Este capítulo apresenta as suítes, mostra como rodá-las e aponta alguns detalhes do template que confundem na primeira vez. Os capítulos seguintes aprofundam cada tipo de teste.

## Os arquivos

```plaintext
codeception.yml           # configuração geral
tests/
  bootstrap.php           # roda antes de tudo
  Unit.suite.yml
  Functional.suite.yml
  Console.suite.yml
  Web.suite.yml
  Unit/                   # um diretório por suíte
  Functional/
  Console/
  Web/
  Support/                # classes "Tester" de cada suíte
```

O `codeception.yml` define o namespace dos testes (`App\Tests`, mapeado para `tests/` no `autoload-dev` do `composer.json`), liga a ordem aleatória (`shuffle: true`) e configura a cobertura de código. O `tests/bootstrap.php` só chama `App\Environment::prepare()`, que lê as variáveis `APP_*`.

## As suítes do template

| Suíte | O que testa | Como |
|---|---|---|
| Unit | Uma classe isolada | Classes `*Test` que estendem `Codeception\Test\Unit`. A aplicação não sobe. |
| Functional | A aplicação inteira, sem servidor | O teste monta uma `ServerRequest` e a entrega ao `HttpApplicationRunner`, no mesmo processo. |
| Console | Comandos do `./yii` | Executa o `./yii` como processo e confere a saída e o código de saída (módulo `Cli`). |
| Web | O site como um navegador vê | Faz requisições HTTP reais a um servidor rodando, com o módulo `PhpBrowser`. |

Os testes das suítes Functional, Console e Web são escritos como **Cest**: uma classe comum, em que cada método público é um teste e recebe o "Tester" da suíte (`FunctionalTester`, `ConsoleTester`, `WebTester`). Os Testers ficam em `tests/Support/` e é ali que o template acrescenta métodos próprios, como o `sendRequest()` da suíte Functional e o `runApp()` da Console.

Quanto mais embaixo na tabela, mais o teste se parece com o uso real e mais lento ele fica. Uma boa divisão é deixar a maior parte das regras em testes unitários e usar as outras suítes para conferir que as peças estão ligadas.

## composer test

O `composer.json` do template define o script `test`:

```json
"test": "codecept run"
```

Então `composer test` e `vendor/bin/codecept run` fazem o mesmo: rodam todas as suítes. Para rodar uma suíte, uma pasta, um arquivo ou um único método:

```bash
vendor/bin/codecept run Unit
vendor/bin/codecept run Unit Web
vendor/bin/codecept run Functional OlaCest
vendor/bin/codecept run Unit Web/Cadastro/CadastroFormTest:testEmailInvalido
```

A saída de `vendor/bin/codecept run Unit Web`, que roda os testes de `tests/Unit/Web/`:

```plaintext
Codeception PHP Testing Framework v5.3.7 https://stand-with-ukraine.pp.ua
[Seed] 1647521790

App\Tests.Unit Tests (5) -----------------------------------------------------
+ CadastroFormTest: Email invalido | "sem domínio" (0.02s)
+ CadastroFormTest: Dados validos (0.00s)
+ CadastroFormTest: Campos vazios mostram so o erro do required (0.00s)
+ CadastroFormTest: Email invalido | "com espaço" (0.00s)
+ CadastroFormTest: Email invalido | "sem arroba" (0.00s)
------------------------------------------------------------------------------
Time: 00:00.158, Memory: 14.00 MB

OK (5 tests, 6 assertions)
```

O Codeception transforma o nome do método em frase: `testCamposVaziosMostramSoOErroDoRequired` vira "Campos vazios mostram so o erro do required". O teste de e-mail inválido aparece três vezes porque recebe dados de um data provider, como mostra o [próximo capítulo](/testes/unitarios).

Alguns detalhes do template:

- **Ordem aleatória.** Com `shuffle: true`, os testes rodam em ordem diferente a cada vez. É assim que aparecem testes que dependem um do outro sem querer. O número em `[Seed]` permite repetir a mesma ordem: `vendor/bin/codecept run --seed 1647521790`.
- **Só os que falharam.** A extensão `RunFailed` está ligada. Depois de uma rodada com falhas, `vendor/bin/codecept run -g failed` roda só os testes que falharam.
- **A suíte Web precisa de um servidor.** Ela tenta subir o `composer serve` na porta 8080 antes de começar. Se a porta estiver ocupada, os testes vão bater no que estiver nela. O capítulo [Testes web](/testes/web) mostra como usar outra porta.

## Em qual ambiente os testes rodam

O `tests/bootstrap.php` não lê o arquivo `.env`. Então, rodando na sua máquina sem definir nada, `APP_ENV` fica vazio e a aplicação montada nos testes funcionais assume o ambiente `prod`, com `APP_DEBUG` desligado.

Para rodar no ambiente `test` (e carregar `config/environments/test/params.php`), defina a variável na chamada:

```bash
APP_ENV=test vendor/bin/codecept run Functional
```

No Docker, isso já vem resolvido: o `make test` usa o `docker/test/.env`, com `APP_ENV=test`. Veja [Docker](/topicos/docker).

## Criando testes

O Codeception gera o esqueleto de um teste:

```bash
vendor/bin/codecept generate:cest Functional PaginaInicialCest
vendor/bin/codecept generate:test Unit Helpers/Slug
```

O primeiro cria `tests/Functional/PaginaInicialCest.php` com um método `_before()`, que roda antes de cada teste, e um método de exemplo. O segundo cria `tests/Unit/Helpers/SlugTest.php`, uma classe que estende `Codeception\Test\Unit`. Os esqueletos são um ponto de partida: ajuste os nomes e apague o que não usar.

Se você ligar um módulo novo numa suíte (no arquivo `.suite.yml`), rode `vendor/bin/codecept build` para regenerar os métodos do Tester. Na prática, o `codecept run` já faz isso sozinho, a menos que você passe `--no-rebuild`.

## Cobertura de código

O `codeception.yml` já diz o que medir (`src/`, `public/index.php` e `yii`). Para gerar o relatório, o PHP precisa do Xdebug ou do PCOV. Sem nenhum dos dois, o Codeception para com a mensagem `No code coverage driver available`.

```bash
vendor/bin/codecept run --coverage --coverage-html
```

A imagem Docker de desenvolvimento tem o Xdebug, e o `make test-coverage` roda exatamente isso, com o relatório em `tests/_output/coverage/`.

## Próximos capítulos

- [Testes unitários](/testes/unitarios): testando classes sem subir o framework.
- [Testes funcionais](/testes/funcionais): a aplicação inteira dentro do teste.
- [Testes web](/testes/web): requisições reais a um servidor rodando.
- Os testes da suíte Console aparecem em [Comandos de console](/topicos/console).
