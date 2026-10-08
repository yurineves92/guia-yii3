# Testes unitários

Um teste unitário confere uma classe sozinha: você cria o objeto, chama um método e compara o resultado. Não há servidor, roteador nem container. Por isso esses testes são rápidos e apontam o problema com precisão. No Yii3, onde as classes recebem as dependências pelo construtor e quase nada é global, testar assim é natural.

## A estrutura de um teste

Os testes unitários do template ficam em `tests/Unit/` e estendem `Codeception\Test\Unit`, que por sua vez estende o `TestCase` do PHPUnit. Tudo que você conhece do PHPUnit funciona: métodos que começam com `test`, as asserções e os métodos executados antes e depois de cada teste (no Codeception, `_before()` e `_after()`).

O template usa as asserções do PHPUnit como funções (`use function PHPUnit\Framework\assertSame;`), e não como `$this->assertSame()`. As duas formas funcionam. Os exemplos deste guia seguem o template.

## Testando domínio sem framework

Comece pelo formulário de cadastro de [Trabalhando com formulários](/primeiros-passos/formularios). O `CadastroForm` declara as regras com atributos. Na aplicação, quem valida é o `FormHydrator`, que vem do container. Mas a validação em si é feita pelo `Validator` do `yiisoft/validator`, e ele pode ser criado com `new`, sem configuração. Crie `tests/Unit/Web/Cadastro/CadastroFormTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Web\Cadastro;

use App\Web\Cadastro\CadastroForm;
use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use Yiisoft\Validator\Validator;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

final class CadastroFormTest extends Unit
{
    public function testDadosValidos(): void
    {
        $form = new CadastroForm();
        $form->nome = 'Ana';
        $form->email = 'ana@example.com';

        $result = (new Validator())->validate($form);

        assertTrue($result->isValid());
    }

    public function testCamposVaziosMostramSoOErroDoRequired(): void
    {
        $result = (new Validator())->validate(new CadastroForm());

        assertFalse($result->isValid());
        assertSame(
            [
                'nome' => ['Informe o seu nome.'],
                'email' => ['Informe o seu e-mail.'],
            ],
            $result->getErrorMessagesIndexedByProperty(),
        );
    }

    #[DataProvider('emailsInvalidos')]
    public function testEmailInvalido(string $email): void
    {
        $form = new CadastroForm();
        $form->nome = 'Ana';
        $form->email = $email;

        $result = (new Validator())->validate($form);

        assertSame(['Este e-mail não parece válido.'], $result->getPropertyErrorMessages('email'));
    }

    public static function emailsInvalidos(): array
    {
        return [
            'sem arroba' => ['nao-e-email'],
            'sem domínio' => ['ana@'],
            'com espaço' => ['ana @example.com'],
        ];
    }
}
```

O que o teste confere:

- Dados corretos passam.
- Com os dois campos vazios, cada um mostra só a mensagem do `Required`. Isso garante que o `skipOnEmpty: true` da regra `Email` continua lá: sem ele, o e-mail vazio teria duas mensagens.
- Três formatos de e-mail inválido mostram a mensagem personalizada.

O `validate()` devolve um `Result`, com métodos para ler os erros de jeitos diferentes: `isValid()`, `getErrorMessagesIndexedByProperty()`, `getPropertyErrorMessages('email')` e outros.

::: warning Data provider no Codeception
O teste de e-mail recebe os dados de um data provider. Repare no atributo: é o `Codeception\Attribute\DataProvider`, e não o `PHPUnit\Framework\Attributes\DataProvider`. Com o atributo do PHPUnit, o Codeception 5 chama o método sem argumentos e o teste quebra com `ArgumentCountError`. A anotação `@dataProvider` em comentário também funciona.
:::

```bash
vendor/bin/codecept run Unit Web
```

```plaintext
+ CadastroFormTest: Email invalido | "sem domínio" (0.02s)
+ CadastroFormTest: Dados validos (0.00s)
+ CadastroFormTest: Campos vazios mostram so o erro do required (0.00s)
+ CadastroFormTest: Email invalido | "com espaço" (0.00s)
+ CadastroFormTest: Email invalido | "sem arroba" (0.00s)

OK (5 tests, 6 assertions)
```

Nada do Yii foi inicializado: nem configuração, nem container. Esse é o ponto de manter a regra de negócio em classes que não dependem do HTTP, como discutido em [Domínio](/estrutura/dominio).

## Trocando dependências

Quando a classe testada depende de um serviço, você cria o serviço no teste e o passa pelo construtor. Para serviços que conversam com o mundo de fora, use uma versão de teste:

- Banco de dados: um SQLite em memória.
- E-mail: o `StubMailer`, que guarda as mensagens num array. Veja [Envio de e-mails](/topicos/emails).
- Data e hora: em vez de chamar `new DateTimeImmutable()` dentro da classe, receba um relógio no construtor e, no teste, passe um que devolve sempre o mesmo instante. A PSR-20 define a interface para isso (`ClockInterface`, no pacote `psr/clock`, que não vem no template).

O comando `pais:listar`, de [Comandos de console](/topicos/console), recebe a `ConnectionInterface` no construtor. No teste, ele recebe uma conexão com um banco em memória, criado e preenchido no `_before()`. Para executar o comando, o Symfony Console oferece o `CommandTester`. Crie `tests/Unit/Topicos/Console/ListarPaisesCommandTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Topicos\Console;

use App\Topicos\Console\ListarPaisesCommand;
use Codeception\Test\Unit;
use Symfony\Component\Console\Tester\CommandTester;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class ListarPaisesCommandTest extends Unit
{
    private ConnectionInterface $db;

    protected function _before(): void
    {
        // Banco SQLite em memória: some quando o teste termina
        $this->db = new Connection(
            new Driver('sqlite::memory:'),
            new SchemaCache(new ArrayCache()),
        );

        $this->db->createCommand(
            'CREATE TABLE pais (codigo CHAR(2), nome VARCHAR(52), populacao INTEGER)',
        )->execute();

        $this->db->createCommand()->insertBatch('pais', [
            ['AR', 'Argentina', 46_000_000],
            ['BR', 'Brasil', 212_000_000],
            ['UY', 'Uruguai', 3_400_000],
        ], ['codigo', 'nome', 'populacao'])->execute();
    }

    public function testRespeitaOLimite(): void
    {
        $tester = new CommandTester(new ListarPaisesCommand($this->db));

        $tester->execute(['--limite' => '2']);

        $tester->assertCommandIsSuccessful();
        $saida = $tester->getDisplay();
        assertStringContainsString('Argentina', $saida);
        assertStringContainsString('212.000.000', $saida);
        assertStringNotContainsString('Uruguai', $saida);
    }

    public function testAvisaQuandoNaoEncontraNada(): void
    {
        $tester = new CommandTester(new ListarPaisesCommand($this->db));

        $codigo = $tester->execute(['busca' => 'Chile']);

        assertSame(0, $codigo);
        assertStringContainsString('Nenhum país encontrado.', $tester->getDisplay());
    }
}
```

Alguns pontos:

- A conexão é montada como no `config/common/di/db.php` do template, só trocando o arquivo pelo DSN `sqlite::memory:`. Cada teste ganha um banco novo, porque o `_before()` roda antes de cada um.
- O teste cria a tabela com só as colunas que o comando usa. O banco do teste não precisa ser igual ao de produção, só compatível com a consulta.
- O `CommandTester` recebe argumentos e opções num array (`'--limite' => '2'`), guarda a saída (`getDisplay()`) e devolve o código de saída.

O teste não depende de `runtime/database.sqlite` nem das migrations. Ele roda em qualquer máquina, na ordem que for.

```bash
vendor/bin/codecept run Unit Topicos/Console
```

```plaintext
OK (2 tests, 6 assertions)
```

## O que vale testar aqui

Testes unitários pagam mais em código com decisões: validação, cálculo, conversão, regras de negócio. Uma classe que só repassa dados de um lado para o outro rende pouco teste unitário e é mais bem coberta por um [teste funcional](/testes/funcionais), que confere a ligação entre as peças.

Se, para testar uma classe, você precisa montar muitas dependências, isso costuma indicar que a classe faz coisas demais. Dividir a classe facilita o teste e o código.
