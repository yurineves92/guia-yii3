# Cache de dados

O caso mais comum de cache é guardar o resultado de uma consulta ou de um cálculo, para não repetir o trabalho a cada requisição. O problema que vem junto é saber quando esse resultado ficou velho. Este capítulo usa o `getOrSet()` para guardar um resumo da tabela de países e uma dependência por tag para jogá-lo fora quando for preciso.

Os exemplos usam o cache em arquivo configurado na [Visão geral](/cache/visao-geral) e a tabela `pais` criada em [Trabalhando com banco de dados](/primeiros-passos/banco-de-dados).

## getOrSet()

O padrão "procura no cache; se não achar, calcula e grava" aparece tanto que a classe `Cache` tem um método para ele:

```php
$valor = $cache->getOrSet($chave, $callable, $ttl, $dependencia, $beta);
```

- `$chave`: pode ser string, inteiro ou array. Uma string curta e sem os caracteres `{}()/\@:` é usada como está. Qualquer outra coisa vira um hash MD5 do JSON da chave. Por isso `['produto', 42]` é uma chave válida.
- `$callable`: é chamado só quando o valor não está no cache, expirou ou teve a dependência alterada. O que ele devolve é gravado e retornado.
- `$ttl`: segundos, `DateInterval` ou `null`. Com `null`, vale o TTL padrão configurado no `Cache`.
- `$dependencia`: opcional, explicada mais abaixo.
- `$beta`: controla a proteção contra o efeito manada, também explicada mais abaixo.

## Um serviço com cache

O cálculo fica num serviço, fora da camada web. Crie `src/Cache/ResumoDePaises.php`:

```php
<?php

declare(strict_types=1);

namespace App\Cache;

use DateTimeImmutable;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Cache\Dependency\TagDependency;
use Yiisoft\Cache\Ttl;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class ResumoDePaises
{
    private const TAG = 'paises';

    public function __construct(
        private CacheInterface $cache,
        private ConnectionInterface $db,
    ) {}

    /**
     * @return array{total: int, populacao: int, maiores: list<string>, geradoEm: string}
     */
    public function obter(): array
    {
        return $this->cache->getOrSet(
            ['resumo-de-paises'],
            fn () => $this->calcular(),
            Ttl::minutes(10)->toSeconds(),
            new TagDependency(self::TAG),
        );
    }

    public function invalidar(): void
    {
        TagDependency::invalidate($this->cache, self::TAG);
    }

    private function calcular(): array
    {
        $totais = $this->db->select(['total' => 'COUNT(*)', 'populacao' => 'SUM(populacao)'])
            ->from('pais')
            ->one();

        $maiores = $this->db->select('nome')
            ->from('pais')
            ->orderBy(['populacao' => SORT_DESC])
            ->limit(3)
            ->column();

        return [
            'total' => (int) $totais['total'],
            'populacao' => (int) $totais['populacao'],
            'maiores' => $maiores,
            'geradoEm' => (new DateTimeImmutable())->format('H:i:s'),
        ];
    }
}
```

O `obter()` devolve o resumo guardado por até 10 minutos. O campo `geradoEm` registra a hora em que o cálculo rodou de verdade, para você ver no teste quando o valor veio do cache. O `getOrSet()` aceita TTL em segundos ou `DateInterval`, então convertemos o `Ttl` com `toSeconds()`.

Quem usa o serviço não sabe que existe cache. Se amanhã você trocar o cache por uma tabela de resumo, a action continua igual.

## A página

A action em `src/Web/Cache/Paises/Action.php` só pede o resumo e renderiza:

```php
<?php

declare(strict_types=1);

namespace App\Web\Cache\Paises;

use App\Cache\ResumoDePaises;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ResumoDePaises $resumo,
    ) {}

    public function __invoke(): ResponseInterface
    {
        return $this->viewRenderer->render(__DIR__ . '/template', [
            'resumo' => $this->resumo->obter(),
        ]);
    }
}
```

A view, em `src/Web/Cache/Paises/template.php`, mostra os números e um botão para limpar o cache:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var array{total: int, populacao: int, maiores: list<string>, geradoEm: string} $resumo
 */

$this->setTitle('Resumo dos países');
?>

<h1>Resumo dos países</h1>

<ul>
    <li>Países cadastrados: <?= $resumo['total'] ?></li>
    <li>População somada: <?= number_format($resumo['populacao'], 0, ',', '.') ?></li>
    <li>Mais populosos: <?= Html::encode(implode(', ', $resumo['maiores'])) ?></li>
</ul>

<p>Calculado às <?= Html::encode($resumo['geradoEm']) ?>.</p>

<?= Html::form()->post($urlGenerator->generate('cache/paises/limpar'))->csrf($csrf)->open() ?>
    <?= Html::submitButton('Limpar o cache') ?>
<?= Html::form()->close() ?>
```

O botão envia um POST para `src/Web/Cache/Paises/LimparAction.php`, que invalida o cache e volta para a página:

```php
<?php

declare(strict_types=1);

namespace App\Web\Cache\Paises;

use App\Cache\ResumoDePaises;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;

final readonly class LimparAction
{
    public function __construct(
        private ResumoDePaises $resumo,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $this->resumo->invalidar();

        return $this->responseFactory
            ->createResponse(Status::SEE_OTHER)
            ->withHeader(Header::LOCATION, $this->urlGenerator->generate('cache/paises'));
    }
}
```

As rotas entram no grupo `/cache` do arquivo de rotas:

```php
Route::get('/paises')
    ->action(Cache\Paises\Action::class)
    ->name('paises'),
Route::post('/paises/limpar')
    ->action(Cache\Paises\LimparAction::class)
    ->name('paises/limpar'),
```

## Testando

Abra http://127.0.0.1:8080/cache/paises:

```plaintext
Resumo dos países

- Países cadastrados: 10
- População somada: 3.692.600.000
- Mais populosos: Índia, China, Estados Unidos

Calculado às 01:59:07.
```

Recarregue a página alguns segundos depois: o horário continua `01:59:07`, porque o resumo veio do cache e a consulta não rodou. Clique em "Limpar o cache": a página volta com um horário novo, `01:59:16`, e esse passa a ser o valor guardado pelos próximos 10 minutos.

## Dependências e invalidação

Expirar pelo tempo é simples, mas obriga a escolher entre dados velhos (TTL longo) e pouco ganho (TTL curto). As dependências permitem invalidar um valor quando algo muda, sem esperar o TTL.

Ao gravar um valor, o `Cache` avalia a dependência e guarda o resultado junto com o valor. Ao ler, avalia de novo e compara. Se mudou, o valor é tratado como inexistente e o callable roda outra vez.

O `TagDependency` usado no exemplo funciona assim:

- Ao gravar, ele guarda no próprio cache um marcador para cada tag (um valor gerado com `microtime()`), se ainda não existir.
- O `TagDependency::invalidate($cache, 'paises')` apaga esse marcador.
- Na próxima leitura, o marcador é diferente do que foi gravado junto com o valor, e o valor é descartado.

A vantagem é que uma tag pode marcar muitos valores. Se você guardar o resumo, a lista de países e a página de cada país com a tag `paises`, uma única chamada a `invalidate()` descarta todos. O lugar natural para essa chamada é o código que altera os dados: o serviço que cadastra ou edita um país chama `invalidar()` logo depois de salvar.

As outras dependências do pacote, todas em `Yiisoft\Cache\Dependency`:

- `CallbackDependency(callable)`: invalida quando o resultado do callable muda. Serve, por exemplo, para comparar um `MAX(atualizado_em)` de uma tabela. Cuidado: o callable roda em toda leitura.
- `FileDependency(string $arquivo)`: invalida quando a data de modificação do arquivo muda.
- `ValueDependency(mixed $valor)`: invalida quando o valor passado é diferente do valor gravado. Útil para uma versão: `new ValueDependency($versaoDoCatalogo)`.
- `AnyDependency([...])` e `AllDependencies([...])`: combinam outras dependências. A primeira invalida quando qualquer uma muda; a segunda, só quando todas mudam.

Para apagar um valor específico sem dependência, use `$cache->remove($chave)`, com a mesma chave usada no `getOrSet()`.

## Efeito manada

Quando um valor muito acessado expira, várias requisições podem perceber isso ao mesmo tempo e todas rodam a consulta cara juntas. É o efeito manada (cache stampede).

O `getOrSet()` usa um algoritmo chamado "expiração antecipada probabilística": perto do fim do TTL, uma requisição sorteada passa a receber "não encontrado" e recalcula o valor, enquanto as outras continuam recebendo o valor antigo. O quinto parâmetro, `$beta`, controla quão cedo isso começa. O padrão `1.0` atende à maioria dos casos; valores maiores antecipam o recálculo.

Essa proteção só existe no `getOrSet()`. Se você ler com `psr()->get()` e gravar com `psr()->set()`, o algoritmo fica de fora.

## Cuidados

- O valor é serializado para ir ao driver. Arrays e objetos simples funcionam; closures e conexões abertas, não.
- Se o callable devolver `null`, o `getOrSet()` entende que não há nada no cache e recalcula a cada chamada. Para guardar "não existe", devolva outro valor, como `false` ou um array vazio.
- A chave precisa identificar tudo de que o resultado depende. Se o resumo dependesse do usuário logado, o id do usuário teria de estar na chave: `['resumo-de-paises', $usuarioId]`.

## Comparando com o Yii2

O `getOrSet()` e o `TagDependency` existem no Yii2 com o mesmo nome e a mesma ideia. As diferenças:

- O `DbDependency` do Yii2 não existe. O equivalente é um `CallbackDependency` que faz a consulta.
- Os parâmetros do `getOrSet()` seguem a mesma ordem (chave, callable, duração, dependência), com o `$beta` no final. A proteção contra o efeito manada é nova e vem ligada por padrão.
- O cache é injetado no construtor, em vez de lido de `Yii::$app->cache`.
