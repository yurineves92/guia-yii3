# Qualidade de código

O template `yiisoft/app` já vem com três ferramentas configuradas: o Psalm, que encontra erros de tipo sem executar o código; o Rector, que atualiza o código para versões novas do PHP; e o PHP CS Fixer, que padroniza a formatação. As três ficam em `vendor/bin/` e têm arquivo de configuração na raiz do projeto. Este capítulo mostra o que cada uma faz, usando código do app de exemplo.

## Psalm

O [Psalm](https://psalm.dev/) lê o código e as anotações de tipo (`@var`, `@param`, `@return`) e aponta chamadas que podem falhar: método inexistente, valor que pode ser `null`, array acessado como se fosse objeto.

A configuração está em `psalm.xml`. Os pontos principais:

- `errorLevel="1"`: o nível mais rigoroso, de 1 a 8.
- Analisa `src/`, o `public/index.php` e o `yii`. A pasta `config/` e os testes ficam de fora.
- Dois tipos de aviso são desligados: `MixedAssignment` e `RiskyTruthyFalsyComparison`.
- O cache fica em `runtime/cache/psalm`.

Para analisar o projeto inteiro:

```bash
vendor/bin/psalm
```

Também dá para passar só algumas pastas, o que é útil para conferir o que você acabou de escrever.

### Um erro de verdade

A primeira versão do comando de [Comandos de console](/topicos/console) lia as linhas assim:

```php
$paises = $query->all();

// ...

foreach ($paises as $pais) {
    $linhas[] = [
        $pais['codigo'],
        $pais['nome'],
        number_format((int) $pais['populacao'], 0, ',', '.'),
    ];
}
```

O código funcionava, mas o Psalm reclamou:

```bash
vendor/bin/psalm src/Topicos
```

```plaintext
ERROR: PossiblyInvalidArrayAccess - src/Topicos/Console/ListarPaisesCommand.php:68:17 - Cannot access array value on non-array variable $pais of type object (see https://psalm.dev/109)
                $pais['codigo'],

ERROR: PossiblyInvalidArrayAccess - src/Topicos/Console/ListarPaisesCommand.php:69:17 - Cannot access array value on non-array variable $pais of type object (see https://psalm.dev/109)
                $pais['nome'],

ERROR: PossiblyInvalidArrayAccess - src/Topicos/Console/ListarPaisesCommand.php:70:37 - Cannot access array value on non-array variable $pais of type object (see https://psalm.dev/109)
                number_format((int) $pais['populacao'], 0, ',', '.'),

------------------------------
3 errors found
------------------------------
```

O motivo: pela assinatura do `yiisoft/db`, o `all()` devolve uma lista em que cada linha pode ser um array ou um objeto (dependendo de como a query é configurada). O Psalm não tem como saber que, nessa query, as linhas são arrays. Quem sabe é você, então diga isso a ele com uma anotação:

```php
/** @var list<array{codigo: string, nome: string, populacao: int|string}> $paises */
$paises = $query->all();
```

Agora o Psalm conhece até as chaves de cada linha:

```plaintext
------------------------------

       No errors found!       

------------------------------
```

Uma anotação `@var` é uma afirmação sua, e o Psalm confia nela. Use quando você sabe algo que a assinatura do método não consegue expressar, como aqui.

## Rector

O [Rector](https://getrector.com/) reescreve código automaticamente a partir de regras. O uso mais comum é modernizar: trocar construções antigas pelas equivalentes de versões novas do PHP.

O `rector.php` do template:

```php
<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php82: true)
    ->withRules([
        InlineConstructorDefaultToPropertyRector::class,
    ])
    ->withSkip([
        ClosureToArrowFunctionRector::class,
        ReadOnlyPropertyRector::class,
    ]);
```

O `withPhpSets(php82: true)` liga todas as regras de modernização até o PHP 8.2, a versão mínima do template. Duas regras desse conjunto ficam de fora: a que transforma closures em arrow functions e a que adiciona `readonly` em propriedades por conta própria.

Para ver o efeito, considere uma classe escrita no estilo antigo, `src/Topicos/Qualidade/Saudacao.php`, antes de passar pelas ferramentas:

```php
<?php

declare(strict_types=1);

namespace App\Topicos\Qualidade;

final class Saudacao
{
    private string $nome;

    public function __construct(string $nome)
    {
        $this->nome = $nome;
    }

    public function texto(): string
    {
        if (strpos($this->nome, 'Dr.') !== false) {
            return 'Olá, ' . $this->nome . '. Seja bem-vindo.';
        }

        return 'Oi, ' . $this->nome . '!';
    }
}
```

Rode o Rector com `--dry-run`, que mostra as mudanças sem gravar:

```bash
vendor/bin/rector process src/Topicos/Qualidade --dry-run
```

```plaintext
1 file with changes
===================

1) src/Topicos/Qualidade/Saudacao.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 final class Saudacao
 {
-    private string $nome;
-
-    public function __construct(string $nome)
+    public function __construct(private string $nome)
     {
-        $this->nome = $nome;
     }

     public function texto(): string
     {
-        if (strpos($this->nome, 'Dr.') !== false) {
+        if (str_contains($this->nome, 'Dr.')) {
             return 'Olá, ' . $this->nome . '. Seja bem-vindo.';
         }
    ----------- end diff -----------

Applied rules:
 * ClassPropertyAssignToConstructorPromotionRector
 * StrContainsRector


 [OK] 1 file would have been changed (dry-run) by Rector
```

Duas regras se aplicaram: a promoção da propriedade para o construtor (PHP 8.0) e o `str_contains()` no lugar do `strpos() !== false` (também PHP 8.0). Sem o `--dry-run`, o Rector grava as mudanças. Revise o diff antes de fazer commit: o Rector é seguro na maior parte dos casos, mas é uma reescrita automática.

## PHP CS Fixer

O [PHP CS Fixer](https://cs.symfony.com/) cuida da formatação: espaços, quebras de linha, ordem dos `use`. O `.php-cs-fixer.php` do template aplica o padrão `@PER-CS` (o sucessor do PSR-12) e remove `use` não usados. Ele analisa `config/`, `src/`, `tests/` e o `public/index.php`.

Depois do Rector, o construtor da `Saudacao` ficou com um corpo vazio em três linhas. O CS Fixer aponta isso (na saída abaixo, os caminhos absolutos foram encurtados):

```bash
vendor/bin/php-cs-fixer fix --dry-run --diff src/Topicos/Qualidade
```

```plaintext
   1) src\Topicos\Qualidade\Saudacao.php
      ---------- begin diff ----------
--- C:\...\app\src\Topicos\Qualidade\Saudacao.php
+++ C:\...\app\src\Topicos\Qualidade\Saudacao.php
@@ -6,9 +6,7 @@
 
 final class Saudacao
 {
-    public function __construct(private string $nome)
-    {
-    }
+    public function __construct(private string $nome) {}
 
     public function texto(): string
     {

      ----------- end diff -----------


Found 1 of 1 files that can be fixed in 0.684 seconds, 20.00 MB memory used
```

Sem o `--dry-run`, ele corrige. O arquivo final, que está no app:

```php
<?php

declare(strict_types=1);

namespace App\Topicos\Qualidade;

final class Saudacao
{
    public function __construct(private string $nome) {}

    public function texto(): string
    {
        if (str_contains($this->nome, 'Dr.')) {
            return 'Olá, ' . $this->nome . '. Seja bem-vindo.';
        }

        return 'Oi, ' . $this->nome . '!';
    }
}
```

Sem argumentos, `vendor/bin/php-cs-fixer fix` usa as pastas do arquivo de configuração. Para passar mais de uma pasta na linha de comando, informe o arquivo explicitamente: `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php src tests`.

O CS Fixer avisa quando roda num PHP mais novo que o mínimo do `composer.json` (aqui, PHP 8.4 contra 8.2), porque algumas correções poderiam usar sintaxe que a versão mínima não entende. Para formatação pura, o aviso não costuma ter consequência, mas no CI o ideal é rodar com a versão mínima.

## Dependências do Composer

O template traz uma quarta ferramenta, o `shipmonk/composer-dependency-analyser`, configurada em `composer-dependency-analyser.php`. Ele compara as classes que o código usa com os pacotes do `composer.json` e aponta, por exemplo, "dependências sombra": pacotes que você usa diretamente, mas que só estão instalados porque outro pacote depende deles. Isso costuma acontecer com o `yiisoft/db` e o `yiisoft/validator`, que chegam junto com outros pacotes.

```bash
vendor/bin/composer-dependency-analyser --config=composer-dependency-analyser.php
```

## Na prática

Uma ordem que funciona bem é a usada neste capítulo: Rector primeiro (ele reescreve), CS Fixer depois (ele formata o que o Rector produziu) e Psalm por último (ele confere o resultado). No CI, rode o Rector e o CS Fixer com `--dry-run`, para o build falhar se alguém esqueceu de rodá-los.

Com Docker, o `Makefile` tem atalhos para as quatro: `make rector`, `make cs-fix`, `make psalm` e `make composer-dependency-analyser`. Veja [Docker](/topicos/docker).
