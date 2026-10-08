# Comandos de console

Nem toda tarefa passa pelo navegador. Importar uma planilha, mandar e-mails pendentes, limpar registros antigos: isso roda no terminal ou num agendador como o cron. No Yii3, esses comandos usam o mesmo container e a mesma configuração da aplicação web, então qualquer serviço que você já tem fica disponível neles.

## Como o console funciona

O script `yii`, na raiz do projeto, cria um `ConsoleApplicationRunner`. Ele monta a configuração e o container do mesmo jeito que o `public/index.php`, só que com os grupos de console (`params-console`, `di-console`). A aplicação de console é o pacote `yiisoft/yii-console`, que por baixo usa o [Symfony Console](https://symfony.com/doc/current/components/console.html).

Isso quer dizer que um comando do Yii3 é um comando do Symfony: uma classe que estende `Symfony\Component\Console\Command\Command`. Argumentos, opções, tabelas e perguntas interativas seguem a documentação do Symfony.

O template já traz um exemplo, o `src/Console/HelloCommand.php`, que imprime `Hello!` quando você roda `./yii hello`.

## Criando um comando

Vamos criar um comando que lista os países da tabela `pais`, criada em [Trabalhando com banco de dados](/primeiros-passos/banco-de-dados). Ele aceita uma busca opcional pelo nome e a opção `--limite`. Crie `src/Topicos/Console/ListarPaisesCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Topicos\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Yii\Console\ExitCode;

#[AsCommand(
    name: 'pais:listar',
    description: 'Lista os países cadastrados',
)]
final class ListarPaisesCommand extends Command
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('busca', InputArgument::OPTIONAL, 'Parte do nome do país')
            ->addOption('limite', 'l', InputOption::VALUE_REQUIRED, 'Quantidade máxima de linhas', '5');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limite = (int) $input->getOption('limite');
        if ($limite < 1) {
            $io->error('O limite precisa ser um número maior que zero.');
            return ExitCode::USAGE;
        }

        $query = $this->db
            ->select(['codigo', 'nome', 'populacao'])
            ->from('pais')
            ->orderBy(['nome' => SORT_ASC])
            ->limit($limite);

        /** @var string|null $busca */
        $busca = $input->getArgument('busca');
        if ($busca !== null) {
            $query->where(['like', 'nome', $busca]);
        }

        /** @var list<array{codigo: string, nome: string, populacao: int|string}> $paises */
        $paises = $query->all();

        if ($paises === []) {
            $io->warning('Nenhum país encontrado.');
            return ExitCode::OK;
        }

        $linhas = [];
        foreach ($paises as $pais) {
            $linhas[] = [
                $pais['codigo'],
                $pais['nome'],
                number_format((int) $pais['populacao'], 0, ',', '.'),
            ];
        }

        $io->table(['Código', 'Nome', 'População'], $linhas);

        return ExitCode::OK;
    }
}
```

O que cada parte faz:

- O atributo `#[AsCommand]` dá o nome e a descrição que aparecem no `./yii list`.
- As dependências chegam pelo construtor, como numa action. Quem cria o comando é o container, então ele recebe a `ConnectionInterface` já configurada. Lembre de chamar `parent::__construct()`.
- O `configure()` declara argumentos e opções. A opção `--limite` tem o atalho `-l` e o valor padrão `'5'`. Valores vindos do terminal são sempre string, por isso o `(int)`.
- O `execute()` faz o trabalho e devolve o código de saída. O `SymfonyStyle` dá métodos prontos para tabela, aviso e erro.
- O comentário `@var` antes do `$query->all()` diz ao Psalm o formato das linhas. Sem ele, a análise estática reclama, como mostra o capítulo de [Qualidade de código](/ferramentas/qualidade).

O template guarda os comandos em `src/Console/`. Aqui o comando fica em `src/Topicos/Console/` só para separar os exemplos do guia. O lugar não importa para o Yii: o que liga o nome à classe é o registro, logo abaixo.

## Registrando em config/console

Os comandos ficam listados em `config/console/commands.php`. O arquivo é um mapa de nome para classe:

```php
<?php

declare(strict_types=1);

use App\Console;
use App\Topicos;

return [
    'hello' => Console\HelloCommand::class,
    'pais:listar|paises' => Topicos\Console\ListarPaisesCommand::class,
    'email:boas-vindas' => Topicos\Console\EnviarBoasVindasCommand::class,
];
```

O `config/console/params.php` do template lê esse arquivo e o entrega ao `yiisoft/yii-console` no parâmetro `commands`. Não é preciso rodar nada depois de editar: o arquivo é lido a cada execução.

Na chave, o `|` separa nomes alternativos. Com `'pais:listar|paises'`, o comando também atende por `./yii paises`. Se a chave começa com `|`, o comando fica escondido da listagem. O próprio `yii-console` registra um comando assim, o `|yii`.

O carregamento é preguiçoso. Para montar a lista, o `CommandLoader` do `yii-console` lê só o atributo `#[AsCommand]`. A classe só é criada pelo container quando o comando é executado, então um comando que depende do banco não abre conexão quando você roda outro.

O segundo comando da lista, o `email:boas-vindas`, aparece no capítulo [Envio de e-mails](/topicos/emails).

## Executando

```bash
./yii list pais
```

```plaintext
Available commands for the "pais" namespace:
  pais:listar  [paises] Lista os países cadastrados
```

Sem argumentos, o comando mostra os cinco primeiros países em ordem alfabética:

```bash
./yii pais:listar
```

```plaintext
 -------- ----------- --------------- 
  Código   Nome        População      
 -------- ----------- --------------- 
  DE       Alemanha    83.000.000     
  AU       Austrália   26.000.000     
  BR       Brasil      212.000.000    
  CA       Canadá      40.000.000     
  CN       China       1.410.000.000  
 -------- ----------- --------------- 
```

Com uma busca e a opção curta:

```bash
./yii paises bra -l 3
```

```plaintext
 -------- -------- ------------- 
  Código   Nome     População    
 -------- -------- ------------- 
  BR       Brasil   212.000.000  
 -------- -------- ------------- 
```

A condição `['like', 'nome', $busca]` do query builder já envolve o valor com `%`, então `bra` encontra "Brasil". O `./yii pais:listar --help` mostra a descrição, o argumento e as opções declaradas no `configure()`.

No Windows, troque `./yii` por `yii.bat`.

## Códigos de saída

O número devolvido pelo `execute()` vira o código de saída do processo. É ele que o cron, o CI ou um script shell usam para saber se deu certo. A classe `Yiisoft\Yii\Console\ExitCode` traz constantes no padrão `sysexits` do BSD: `OK` (0), `UNSPECIFIED_ERROR` (1), `USAGE` (64), `DATAERR` (65), `NOPERM` (77) e outras.

```bash
./yii pais:listar --limite=0
echo $?
```

```plaintext
 [ERROR] O limite precisa ser um número maior que zero.

64
```

Uma exceção não tratada dentro do comando também encerra com código diferente de zero. O `yii-console` registra um listener que escreve o erro no log da aplicação.

## Testando o comando

Há dois caminhos. O primeiro é rodar o `./yii` de verdade e conferir a saída, com a suíte Console do Codeception. Trecho de `tests/Console/ListarPaisesCest.php`:

```php
public function filtraPeloNome(ConsoleTester $I): void
{
    $I->runApp('pais:listar bra');
    $I->seeResultCodeIs(0);
    $I->seeInShellOutput('Brasil');
    $I->dontSeeInShellOutput('Canadá');
}
```

O segundo é criar o comando à mão, com um banco SQLite em memória, e executá-lo com o `CommandTester` do Symfony. Esse caminho aparece em [Testes unitários](/testes/unitarios).

## Comparando com o Yii2

- No Yii2, um comando era um controller de console, com um método `actionXxx()` por subcomando, e rodava como `./yii pais/listar`. No Yii3, cada comando é uma classe do Symfony Console, e o nome costuma usar `:` (`migrate:up`).
- Os argumentos deixam de ser parâmetros do método da action e passam a ser declarados no `configure()`.
- O registro é explícito, em `config/console/commands.php`, em vez do `controllerMap` ou da descoberta pelo namespace.
- O `ExitCode` continua existindo, agora em `Yiisoft\Yii\Console\ExitCode`.
