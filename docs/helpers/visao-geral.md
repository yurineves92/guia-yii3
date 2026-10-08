# Visão geral

Escapar HTML, tirar um valor de um array aninhado, gerar um slug, codificar JSON: são tarefas pequenas que aparecem em todo projeto. O Yii3 separa essas utilidades em pacotes independentes, todos já instalados no template: `yiisoft/html`, `yiisoft/arrays`, `yiisoft/strings` e `yiisoft/json`. Eles não dependem do resto do framework e podem ser usados em qualquer projeto PHP.

Os exemplos deste capítulo estão nos testes do app, em `tests/Unit/Helpers/`. O comentário ao lado de cada linha mostra o resultado conferido pelo teste.

## Html

A classe `Yiisoft\Html\Html` gera HTML com escape automático. O método mais importante é o `encode()`, usado em toda view que mostra dado vindo de fora:

```php
use Yiisoft\Html\Html;

Html::encode('<script>alert(1)</script> & "aspas"');
// &lt;script&gt;alert(1)&lt;/script&gt; &amp; "aspas"
```

Os métodos de tag recebem o conteúdo e os atributos. O conteúdo é escapado por padrão:

```php
Html::a('Países', '/paises', ['class' => 'link']);
// <a class="link" href="/paises">Países</a>

Html::a('<b>x</b>', '/x');
// <a href="/x">&lt;b&gt;x&lt;/b&gt;</a>

Html::tag('span', 'Novo', ['class' => ['badge', 'badge-novo'], 'data-id' => 7]);
// <span class="badge badge-novo" data-id="7">Novo</span>

Html::img('/logo.png', 'Logo');
// <img src="/logo.png" alt="Logo">
```

O atributo `class` aceita um array, que vira a lista separada por espaço.

Esses métodos não devolvem string. Devolvem objetos de tag, que você pode continuar configurando com métodos encadeados. A conversão para string acontece no `echo` ou no `<?= ?>` da view:

```php
Html::div(Html::b('mundo'))->class('aviso')->addClass('grande');
// <div class="aviso grande"><b>mundo</b></div>

Html::div('<i>x</i>')->encode(false);
// <div><i>x</i></div>

Html::textInput('nome', 'Ana')->required();
// <input type="text" name="nome" value="Ana" required>

Html::ul()->strings(['Um', 'Dois & três']);
// <ul>
// <li>Um</li>
// <li>Dois &amp; três</li>
// </ul>
```

Repare no primeiro exemplo: o `Html::b('mundo')` é um objeto de tag, e um objeto de tag dentro de outro não é escapado. Já uma string com HTML é. Para desligar o escape de uma string, use `->encode(false)`, e só com conteúdo que você controla.

Os objetos de tag são imutáveis: cada método devolve uma cópia. O mesmo pacote é usado pelos widgets de formulário, como o `Html::form()` visto em [Trabalhando com formulários](/primeiros-passos/formularios).

## Arrays

A classe `Yiisoft\Arrays\ArrayHelper` reúne operações com arrays. Para ler valores aninhados:

```php
use Yiisoft\Arrays\ArrayHelper;

$usuario = ['nome' => 'Ana', 'endereco' => ['cidade' => 'Recife', 'uf' => 'PE']];

ArrayHelper::getValueByPath($usuario, 'endereco.cidade');            // 'Recife'
ArrayHelper::getValue($usuario, ['endereco', 'cidade']);             // 'Recife'
ArrayHelper::getValue($usuario, ['endereco', 'cep'], 'sem CEP');     // 'sem CEP'
ArrayHelper::getValue($usuario, 'endereco.cidade');                  // null
ArrayHelper::filter($usuario, ['nome', 'endereco.uf']);
// ['nome' => 'Ana', 'endereco' => ['uf' => 'PE']]
```

::: warning getValue() não entende o ponto
No Yii2, `ArrayHelper::getValue($array, 'endereco.cidade')` navegava pelo array. No Yii3, o `getValue()` procura uma chave chamada literalmente `endereco.cidade` e devolve `null`. Para caminhos com ponto, use `getValueByPath()`, ou passe a chave como array: `['endereco', 'cidade']`.
:::

Para transformar listas, como as linhas de uma consulta:

```php
$paises = [
    ['codigo' => 'BR', 'nome' => 'Brasil', 'continente' => 'América'],
    ['codigo' => 'PT', 'nome' => 'Portugal', 'continente' => 'Europa'],
    ['codigo' => 'AR', 'nome' => 'Argentina', 'continente' => 'América'],
];

ArrayHelper::map($paises, 'codigo', 'nome');
// ['BR' => 'Brasil', 'PT' => 'Portugal', 'AR' => 'Argentina']

ArrayHelper::map($paises, 'codigo', 'nome', 'continente');
// ['América' => ['BR' => 'Brasil', 'AR' => 'Argentina'], 'Europa' => ['PT' => 'Portugal']]

ArrayHelper::getColumn($paises, 'nome');
// ['Brasil', 'Portugal', 'Argentina']

ArrayHelper::index($paises, 'codigo');
// as mesmas linhas, com as chaves 'BR', 'PT' e 'AR'
```

O `map()` é o caminho mais curto para montar as opções de um `<select>`. Com o terceiro argumento, ele agrupa, o que serve para um `<optgroup>`.

Para juntar configurações, o `merge()` mescla arrays recursivamente, e o valor do último ganha:

```php
ArrayHelper::merge(
    ['db' => ['host' => 'localhost', 'porta' => 5432]],
    ['db' => ['host' => 'db.interno']],
);
// ['db' => ['host' => 'db.interno', 'porta' => 5432]]
```

A diferença para o `array_merge_recursive()` do PHP é que valores com a mesma chave são substituídos, e não transformados em lista.

## Strings

O `yiisoft/strings` tem três classes principais. A `StringHelper` traz funções estáticas que respeitam UTF-8:

```php
use Yiisoft\Strings\StringHelper;

StringHelper::truncateEnd('Programação orientada a objetos', 15);           // 'Programação or…'
StringHelper::truncateWords('Programação orientada a objetos em PHP', 3);   // 'Programação orientada a…'
StringHelper::length('ação');                                               // 4
strlen('ação');                                                             // 6
StringHelper::uppercaseFirstCharacterInEachWord('joão da silva');           // 'João Da Silva'
StringHelper::startsWith('Yii3 framework', 'Yii');                          // true
```

O `strlen()` conta bytes, e "ação" tem 6 bytes em UTF-8. O `StringHelper::length()` conta caracteres. O mesmo cuidado vale para cortar textos: o `truncateEnd()` nunca corta um caractere acentuado pela metade.

A `Inflector` converte entre formatos de nome. Diferente do Yii2, ela é um objeto, e não uma classe estática, porque pode ser configurada (regras de plural, transliteração):

```php
use Yiisoft\Strings\Inflector;

$inflector = new Inflector();

$inflector->toSlug('Programação em PHP: guia prático!');   // 'programacao-em-php-guia-pratico'
$inflector->toCamelCase('data_de_nascimento');             // 'dataDeNascimento'
$inflector->toPascalCase('pedido de compra');              // 'PedidoDeCompra'
$inflector->toSnakeCase('dataDeNascimento');               // 'data_de_nascimento'
$inflector->pascalCaseToId('ListarPaisesCommand');         // 'listar-paises-command'
```

O `toSlug()` usa a extensão `intl` para tirar os acentos. As regras de plural e singular (`toPlural()`, `toSingular()`) são do inglês.

A `NumericHelper` cuida de números em texto. Por exemplo, o `normalize()` troca a vírgula decimal por ponto, útil para valores digitados por usuários brasileiros:

```php
use Yiisoft\Strings\NumericHelper;

NumericHelper::normalize('1,5');   // '1.5'
```

## Json

A classe `Yiisoft\Json\Json` é uma camada fina sobre `json_encode()` e `json_decode()`, com padrões melhores:

```php
use Yiisoft\Json\Json;

Json::encode(['nome' => 'Ana', 'cidade' => 'São Paulo', 'url' => 'a/b']);
// {"nome":"Ana","cidade":"São Paulo","url":"a/b"}

Json::decode('{"nome":"Ana","idade":30}');
// ['nome' => 'Ana', 'idade' => 30]
```

As diferenças em relação às funções do PHP:

- O `encode()` não escapa acentos nem barras. O `json_encode()` puro geraria `"S\u00e3o Paulo"` e `"a\/b"`.
- O `decode()` devolve arrays associativos por padrão, sem precisar do segundo argumento `true`.
- Erros sempre lançam `JsonException`. O `Json::decode('{nome: Ana}')` lança a exceção com a mensagem "Syntax error", em vez de devolver `null` em silêncio.

Para colocar JSON dentro de uma página (num atributo `data-*` ou num `<script>`), use o `Json::htmlEncode()`. Ele escapa `<`, `>`, `&`, aspas e apóstrofos como sequências `\u` do JSON, então o resultado não fecha uma tag `<script>` nem quebra um atributo.

## Rodando os exemplos

```bash
vendor/bin/codecept run Unit Helpers
```

```plaintext
OK (12 tests, 35 assertions)
```

## Comparando com o Yii2

- Os helpers saíram de `yii\helpers\*` para pacotes próprios, com namespaces `Yiisoft\Html`, `Yiisoft\Arrays`, `Yiisoft\Strings` e `Yiisoft\Json`.
- O `Html` do Yii3 devolve objetos de tag, configuráveis por métodos encadeados, em vez de strings.
- O `ArrayHelper::getValue()` não interpreta mais o ponto. Use o `getValueByPath()`.
- O `Inflector` virou um objeto configurável, em vez de uma classe só com métodos estáticos.
- O `Json` mantém o nome e os padrões (sem escapar acentos e barras). Os erros agora são `JsonException`, a exceção nativa do PHP, no lugar da `InvalidArgumentException` do Yii2.
