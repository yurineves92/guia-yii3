# Assets

CSS, JavaScript e imagens precisam chegar ao navegador, mas os arquivos-fonte nem sempre ficam numa pasta pública. Também é preciso controlar a ordem (o CSS do tema depois do CSS base), evitar incluir o mesmo arquivo duas vezes e trocar a URL quando o arquivo muda, para o cache do navegador não servir a versão antiga. O pacote `yiisoft/assets` resolve isso com asset bundles. Este capítulo mostra como eles funcionam no template e como encaixar um bundler como o Vite.

## AssetBundle e AssetManager

Um asset bundle é uma classe que descreve um conjunto de arquivos. O template tem um, para o layout, em `src/Web/Shared/Layout/Main/MainAsset.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Shared\Layout\Main;

use Yiisoft\Assets\AssetBundle;

final class MainAsset extends AssetBundle
{
    public ?string $basePath = '@assets/main';
    public ?string $baseUrl = '@assetsUrl/main';
    public ?string $sourcePath = '@assetsSource/main';

    public array $css = [
        'site.css',
    ];
}
```

As propriedades principais:

- `sourcePath`: onde estão os arquivos-fonte. Aqui, `@assetsSource/main`, que aponta para `assets/main/` na raiz do projeto, fora de `public/`.
- `basePath` e `baseUrl`: para onde os arquivos são publicados e com que URL. `@assets` é `public/assets` e `@assetsUrl` é `/assets`. Os aliases estão em `config/common/aliases.php`.
- `css` e `js`: os arquivos, relativos ao `sourcePath` (ou ao `basePath`, se não houver `sourcePath`), ou URLs completas.
- `depends`: outros bundles que precisam vir antes deste.
- `cssOptions` e `jsOptions`: atributos extras das tags, como `defer` ou `integrity`.
- `cdn`: indica que todos os arquivos são externos e nada precisa ser publicado.

Quem usa os bundles é o `AssetManager`, um serviço do container. O layout do template começa assim:

```php
$assetManager->register(MainAsset::class);

$this->addCssFiles($assetManager->getCssFiles());
$this->addCssStrings($assetManager->getCssStrings());
$this->addJsFiles($assetManager->getJsFiles());
$this->addJsStrings($assetManager->getJsStrings());
$this->addJsVars($assetManager->getJsVars());
```

O `register()` publica o bundle e as suas dependências e guarda a lista de arquivos. Os `add*()` passam essa lista para a `WebView`, que gera as tags `<link>` e `<script>` nos pontos marcados por `head()` e `endBody()`. O `$assetManager` está disponível em toda view porque é um dos parâmetros comuns da `WebView` (veja [Views](/estrutura/views)).

### Um bundle para uma página

Crie os arquivos-fonte em `assets/estrutura/`. O `estrutura.css` estiliza os widgets do capítulo [Widgets](/estrutura/widgets):

```plaintext
.alerta {
    margin: 12px 0;
    padding: 12px;
    border-radius: 6px;
    cursor: pointer;
}
.alerta-sucesso {
    background: #e3f5e1;
}
.alerta-erro {
    background: #fbe3e3;
}
.cartao {
    padding: 12px 24px;
    border: 1px solid #e6e8ec;
    border-radius: 12px;
    background: #fff;
}
```

E o `estrutura.js` fecha um alerta quando ele é clicado:

```plaintext
document.querySelectorAll('.alerta').forEach((alerta) => {
    alerta.addEventListener('click', () => alerta.remove());
});
```

O bundle, em `src/Web/Estrutura/Assets/EstruturaAsset.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Assets;

use App\Web\Shared\Layout\Main\MainAsset;
use Yiisoft\Assets\AssetBundle;

final class EstruturaAsset extends AssetBundle
{
    public ?string $basePath = '@assets/estrutura';
    public ?string $baseUrl = '@assetsUrl/estrutura';
    public ?string $sourcePath = '@assetsSource/estrutura';

    public array $css = [
        'estrutura.css',
    ];

    public array $js = [
        'estrutura.js',
    ];

    public array $depends = [
        MainAsset::class,
    ];
}
```

Um segundo bundle, com o Alpine.js vindo de uma CDN, em `src/Web/Estrutura/Assets/AlpineAsset.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Assets;

use Yiisoft\Assets\AssetBundle;

final class AlpineAsset extends AssetBundle
{
    public bool $cdn = true;

    public array $js = [
        ['https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js', 'defer' => true],
    ];
}
```

Um item de `$js` pode ser um array: o primeiro elemento é o arquivo e os demais são atributos da tag.

A view da página registra os dois bundles, em `src/Web/Estrutura/Assets/template.php`:

```php
<?php

declare(strict_types=1);

use App\Web\Estrutura\Assets\AlpineAsset;
use App\Web\Estrutura\Assets\EstruturaAsset;
use App\Web\Estrutura\Widget\Alerta;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Assets\AssetManager $assetManager
 */

$assetManager->register(EstruturaAsset::class);
$assetManager->register(AlpineAsset::class);

$this->setTitle('Assets');
?>

<h1>Assets</h1>

<?= Alerta::widget()->tipo('sucesso')->mensagem('Clique para fechar este alerta.') ?>

<div class="cartao" x-data="{ aberto: false }">
    <button type="button" @click="aberto = !aberto">Mostrar detalhes</button>
    <p x-show="aberto">Este trecho usa o Alpine.js, carregado de uma CDN.</p>
</div>
```

A action (`src/Web/Estrutura/Assets/Action.php`) só renderiza essa view, e a rota é:

```php
Route::get('/estrutura/assets')
    ->action(Estrutura\Assets\Action::class)
    ->name('estrutura/assets'),
```

Registrar o bundle na view funciona porque a view é renderizada antes do layout. Quando o layout chama `$assetManager->getCssFiles()`, o `EstruturaAsset` já está na lista. As tags geradas em `/estrutura/assets`:

```plaintext
<link rel="stylesheet" href="/assets/main/8978fe33/site.css">
<link rel="stylesheet" href="/assets/estrutura/c055a00/estrutura.css"></head>
...
<script src="/assets/estrutura/c055a00/estrutura.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js" defer></script></body>
```

O `site.css` vem antes do `estrutura.css` por causa do `depends`. O `MainAsset` é registrado duas vezes (pela view, como dependência, e pelo layout), mas aparece uma vez só. No navegador, o botão "Mostrar detalhes" exibe o parágrafo e um clique no alerta o remove.

## Publicação dos arquivos

Quando um bundle tem `sourcePath`, o `AssetManager` entrega o bundle ao `AssetPublisher`, que copia a pasta inteira para `basePath`, dentro de uma subpasta com um hash:

```plaintext
public/assets/
  estrutura/
    c055a00/
      estrutura.css
      estrutura.js
  main/
    8978fe33/
      site.css
```

O `basePath` e o `baseUrl` do bundle são trocados pelos da pasta publicada, e é daí que saem as URLs do HTML. A cópia só acontece se a pasta de destino ainda não existe.

O hash é um CRC32 calculado a partir do caminho da pasta de origem, da data da última modificação e da quantidade de arquivos. Mudou um arquivo, muda o hash. Depois de editar o `estrutura.css`, a página passou a apontar para outra pasta:

```plaintext
/assets/estrutura/1eb0f5ee/estrutura.css
```

Isso resolve o cache do navegador sem nenhuma configuração: a URL nova é um arquivo que ele nunca viu. O efeito colateral é que as pastas antigas ficam em `public/assets/`. Elas podem ser apagadas a qualquer momento, porque são recriadas quando necessário. O template já traz um `.gitignore` em `public/assets/` que ignora tudo lá dentro.

### Opções

As opções do pacote ficam no parâmetro `yiisoft/assets`. Os valores padrão, do `vendor/yiisoft/assets/config/params.php`:

```php
'yiisoft/assets' => [
    'assetConverter' => [
        'commands' => [
            'scss' => ['css', '@npm/.bin/sass {options} {from} {to}'],
        ],
        'forceConvert' => false,
    ],
    'assetLoader' => [
        'appendTimestamp' => false,
        'assetMap' => [],
        'basePath' => null,
        'baseUrl' => null,
    ],
    'assetPublisher' => [
        'forceCopy' => false,
        'linkAssets' => false,
    ],
    'assetManager' => [
        'allowedBundleNames' => [],
        'customizedBundles' => [],
        'register' => [],
        'publisher' => AssetPublisherInterface::class,
    ],
],
```

As que mais aparecem no dia a dia:

- `assetPublisher.linkAssets`: cria um link simbólico em vez de copiar. Útil em desenvolvimento, porque a pasta publicada reflete o original na hora. Depende de o sistema permitir symlinks.
- `assetPublisher.forceCopy`: copia sempre, mesmo que a pasta exista. Deixa cada requisição mais lenta; use só para investigar problemas.
- `assetLoader.appendTimestamp`: acrescenta `?v=<timestamp>` às URLs.
- `assetManager.customizedBundles`: muda a configuração de um bundle sem editar a classe. Por exemplo, trocar os arquivos locais de um bundle de pacote por uma CDN em produção.
- `assetConverter.commands`: converte arquivos como `.scss` em `.css` na publicação, chamando um programa externo.

Para mudar uma opção, sobrescreva a chave nos seus parâmetros, como em [Aplicação](/estrutura/aplicacao).

## Integração com Vite ou outro bundler

Projetos com muito JavaScript costumam usar um bundler, como Vite, webpack ou esbuild, que compila TypeScript, junta módulos e gera arquivos com hash no nome. Entre os pacotes instalados no app de exemplo não há integração oficial com o Vite, então o encaixe é feito à mão. O app de exemplo não usa bundler; o que segue descreve os caminhos possíveis a partir do código do `yiisoft/assets`.

**O bundler gera os arquivos dentro de `public/`.** Configure a saída do bundler para uma pasta como `public/build/`. Como os arquivos já são públicos, o bundle não precisa de `sourcePath`, só de `basePath` e `baseUrl`. Este bundle foi conferido com um `app.js` colocado à mão em `public/build/`, no lugar do arquivo que o bundler geraria:

```php
final class AppAsset extends AssetBundle
{
    public ?string $basePath = '@public/build';
    public ?string $baseUrl = '@baseUrl/build';

    public array $js = [
        ['app.js', 'type' => 'module'],
    ];
}
```

Registrado numa view, ele gera `<script src="/build/app.js" type="module"></script>`. Sem `sourcePath`, nada é copiado: o `AssetManager` só gera as tags. O `AssetLoader` confere se o arquivo existe em `basePath`; sem o `app.js`, a página falha com `Asset files not found`, o que ajuda a perceber um build esquecido. Se o bundler coloca um hash no nome (`app-3f2a1b.js`), o bundle precisa descobrir o nome real lendo o manifesto que o bundler gera (no Vite, o `manifest.json` habilitado com a opção `build.manifest`). Uma forma de fazer isso é montar o bundle por `customizedBundles` a partir do manifesto, na configuração.

**O bundler lê os bundles do Yii.** O caminho inverso também existe: o `AssetManager::export()` grava a lista de arquivos dos bundles registrados para outra ferramenta consumir. O pacote traz dois exportadores, `JsonAssetExporter` e `WebpackAssetExporter`, em `Yiisoft\Assets\Exporter`.

Em desenvolvimento, o Vite também pode servir os arquivos pelo próprio servidor dele, com recarga automática. Nesse caso, o layout aponta as tags para o endereço do servidor do Vite, e o `AssetManager` só é usado para os arquivos que não passam pelo bundler.

## Comparando com o Yii2

- A ideia é a mesma do Yii2: `AssetBundle` com `sourcePath`, `css`, `js` e `depends`, publicado numa pasta com hash.
- No Yii2, você registrava com `AppAsset::register($this)`. No Yii3, chama `$assetManager->register(AppAsset::class)` e o layout passa os arquivos para a `WebView`.
- O `assetManager->bundles` do Yii2 virou o parâmetro `customizedBundles`.
- O Yii2 usava o `asset-packagist` para instalar pacotes npm pelo Composer. No Yii3, os arquivos de frontend ficam por sua conta (npm, CDN ou bundler).
