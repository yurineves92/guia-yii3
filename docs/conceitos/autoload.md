# Autoload

O Yii3 não tem um autoloader próprio. Quem carrega as classes, tanto as do framework quanto as suas, é o Composer, seguindo a [PSR-4](https://www.php-fig.org/psr/psr-4/). Este capítulo explica a regra que liga um nome de classe a um arquivo, como o template a configura e o que muda em produção.

## PSR-4 com Composer

A PSR-4 associa um prefixo de namespace a uma pasta. A partir daí, o resto do nome da classe vira o caminho do arquivo: cada `\` vira um separador de pasta, e o último trecho vira o nome do arquivo com `.php`.

O mapeamento fica no `composer.json`, na seção `autoload`. No template:

```json
"autoload": {
    "psr-4": {
        "App\\": "src"
    }
},
"autoload-dev": {
    "psr-4": {
        "App\\Tests\\": "tests"
    }
}
```

No JSON, a barra invertida é escrita dobrada, então `"App\\"` é o prefixo `App\`. A regra diz: toda classe que começa com `App\` está dentro de `src/`.

O Composer gera o autoloader em `vendor/autoload.php`, e o template o carrega logo no começo, em `src/bootstrap.php`, que é incluído pelo `public/index.php` e pelo `./yii`. Depois disso, cada classe é carregada na primeira vez que o código a usa, seja num `new`, numa chamada estática ou quando o container a resolve. O `use` no topo do arquivo não carrega nada: ele só cria um apelido para o nome.

## Namespace App\ e a pasta src/

Alguns exemplos com as classes deste guia:

```plaintext
App\Conceitos\Saudacao                    -> src/Conceitos/Saudacao.php
App\Conceitos\Notificacao\AvisoDePedido   -> src/Conceitos/Notificacao/AvisoDePedido.php
App\Web\Conceitos\Container\Action        -> src/Web/Conceitos/Container/Action.php
App\Tests\Functional\HomePageCest         -> tests/Functional/HomePageCest.php
```

Para conferir qual arquivo o autoloader usa para uma classe, rode da raiz do projeto:

```bash
php -r 'require "vendor/autoload.php"; echo (new ReflectionClass(App\Conceitos\Saudacao::class))->getFileName(), PHP_EOL;'
```

O resultado é o caminho completo de `src/Conceitos/Saudacao.php`. Uma classe que não segue a regra simplesmente não é encontrada: `class_exists('App\Conceitos\NaoExiste')` devolve `false`, e o uso dela termina em `Class "..." not found`.

As regras práticas que saem disso:

- Uma classe por arquivo, com o nome do arquivo igual ao nome da classe.
- O namespace acompanha as pastas. Uma classe em `src/Conceitos/Eventos/` tem o namespace `App\Conceitos\Eventos`.
- Maiúsculas e minúsculas precisam bater. No Windows e no macOS o sistema de arquivos costuma não diferenciar, então `src/conceitos/Saudacao.php` funciona na sua máquina e falha num servidor Linux. Se algo funciona localmente e dá `Class not found` em produção, confira primeiro a caixa dos nomes.

### Classes novas não precisam de comando

Com PSR-4, o autoloader calcula o caminho na hora em que a classe é pedida. Criar um arquivo novo em `src/` não exige rodar nada. Todos os exemplos deste guia foram criados assim.

Você só precisa do `composer dump-autoload` quando muda a seção `autoload` do `composer.json`, por exemplo para acrescentar outro prefixo:

```json
"psr-4": {
    "App\\": "src",
    "Modulos\\": "modules"
}
```

### autoload-dev

O `autoload-dev` só vale em instalações de desenvolvimento. Com `composer install --no-dev`, o `App\Tests\` não é registrado, e as classes de teste não existem em produção. O mesmo vale para os pacotes de `require-dev`: o `src/bootstrap.php` do template confere com `class_exists()` se o `vlucas/phpdotenv` está disponível antes de ler o `.env`, porque ele é dependência de desenvolvimento.

## Em produção

Em desenvolvimento, o autoloader PSR-4 procura o arquivo no disco a cada classe nova. Em produção, dá para trocar essa busca por um mapa pronto de classe para arquivo:

```bash
composer install --no-dev --optimize-autoloader
```

O `--optimize-autoloader` (ou `-o`) gera um classmap com todas as classes encontradas. Com `--classmap-authoritative` (ou `-a`), o autoloader passa a confiar só no mapa e nem consulta o disco para classes que não estão nele. É mais rápido, mas uma classe criada depois do comando não é encontrada até você rodar o Composer de novo. Use só no deploy, nunca no ambiente de desenvolvimento.

O capítulo [Desempenho](/topicos/desempenho) trata disso junto com o OPcache.

## Comparando com o Yii2

- O Yii2 tinha um autoloader próprio, o `Yii::autoload()`, baseado em aliases: `app\models\User` era procurado em `@app/models/User.php`. No Yii3 só existe o autoloader do Composer.
- O namespace raiz `app\` (minúsculo) do template básico do Yii2 vira `App\`, mapeado para `src/`.
- `Yii::$classMap` vira o classmap gerado pelo Composer com `-o`.
