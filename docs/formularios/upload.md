# Upload de arquivos

Receber um arquivo do usuário tem três etapas: ler o arquivo da requisição, conferir se ele é o que deveria ser e guardá-lo num lugar seguro. Este capítulo monta um formulário de envio de foto em `/formularios/upload` que passa pelas três.

## UploadedFileInterface

No Yii3, a requisição segue a PSR-7, e os arquivos enviados chegam como objetos `Psr\Http\Message\UploadedFileInterface`. Você os obtém com `$request->getUploadedFiles()`, que devolve um array com a mesma estrutura dos nomes dos campos. Um campo chamado `FotoForm[foto]` chega assim:

```php
[
    'FotoForm' => [
        'foto' => UploadedFileInterface,
    ],
]
```

Os métodos do objeto:

- `getError()`: o código de erro do PHP. `UPLOAD_ERR_OK` (0) quando deu certo, `UPLOAD_ERR_NO_FILE` (4) quando nenhum arquivo foi escolhido, `UPLOAD_ERR_INI_SIZE` (1) quando passou do `upload_max_filesize` do `php.ini`.
- `getSize()`: o tamanho em bytes.
- `getClientFilename()` e `getClientMediaType()`: o nome e o tipo informados pelo navegador. Os dois são escolhidos por quem envia e não servem como garantia de nada.
- `getStream()`: o conteúdo, como um stream PSR-7.
- `moveTo($caminho)`: move o arquivo temporário para o destino final. Só pode ser chamado uma vez.

Ao contrário do `$_FILES`, você não lida com `tmp_name` nem com `move_uploaded_file()`.

## O formulário

A propriedade que recebe o arquivo é tipada como `?UploadedFileInterface`. As regras `File` e `Image` do validador sabem trabalhar com esse tipo. Crie `src/Web/Formularios/Upload/FotoForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Formularios\Upload;

use Psr\Http\Message\UploadedFileInterface;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Rule\File;
use Yiisoft\Validator\Rule\Image\Image;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

final class FotoForm extends FormModel
{
    #[Required(message: 'Escreva uma legenda.')]
    #[Length(max: 100, greaterThanMaxMessage: 'Use no máximo {max} caracteres.')]
    public string $legenda = '';

    #[File(
        extensions: ['jpg', 'jpeg', 'png'],
        mimeTypes: ['image/jpeg', 'image/png'],
        maxSize: 1024 * 1024,
        uploadRequiredMessage: 'Escolha uma foto.',
        uploadFailedMessage: 'O envio da foto falhou (código {error}).',
        wrongExtensionMessage: 'Envie um arquivo .jpg ou .png.',
        wrongMimeTypeMessage: 'O conteúdo do arquivo não é JPEG nem PNG.',
        tooBigMessage: 'A foto pode ter no máximo 1 MB.',
    )]
    #[Image(
        maxWidth: 4000,
        maxHeight: 4000,
        tooLargeWidthMessage: 'A foto pode ter no máximo {limit} pixels de largura.',
        tooLargeHeightMessage: 'A foto pode ter no máximo {limit} pixels de altura.',
        skipOnError: true,
    )]
    public ?UploadedFileInterface $foto = null;
}
```

## Validando tipo e tamanho

A regra `File` faz as conferências na ordem em que elas fazem sentido:

1. Se nenhum arquivo foi enviado, o erro é o `uploadRequiredMessage`. Por isso não precisa de `Required`: a própria `File` cobre esse caso, a não ser que você passe `skipOnEmpty: true` para tornar o arquivo opcional.
2. Se o PHP reportou erro no upload, o erro é o `uploadFailedMessage`, com o código em `{error}`.
3. `extensions` confere a extensão do nome do arquivo.
4. `mimeTypes` confere o tipo do conteúdo. O tipo é detectado no servidor, lendo o próprio arquivo com `mime_content_type()`, e não o que o navegador informou. Só com `trustClientMediaType: true` a regra aceitaria o tipo do navegador, e não há motivo para isso num upload público.
5. `maxSize`, `minSize` ou `size` conferem o tamanho em bytes.

Um arquivo `.png` que na verdade é um script PHP passa pela extensão, mas cai no tipo: o conteúdo é `text/x-php`, e não `image/png`.

A regra `Image` vai além: confere se o arquivo é mesmo uma imagem e permite limitar largura, altura e proporção (`width`, `minWidth`, `maxWidth`, `height`, `minHeight`, `maxHeight` e `aspectRatio`). O `skipOnError: true` faz ela só rodar quando a `File` passou, para não juntar mensagens sobre um arquivo que já foi recusado.

O tamanho máximo tem um limite anterior ao validador. Arquivos maiores que o `upload_max_filesize` do `php.ini` (2 MB por padrão) nem chegam ao app: o PHP descarta o conteúdo e o `getError()` devolve `1`, e o formulário mostra "O envio da foto falhou (código 1).". Se a requisição inteira passar do `post_max_size` (8 MB por padrão), o PHP descarta também os outros campos, inclusive o token CSRF, e a resposta é o erro 422 do `CsrfTokenMiddleware`. Ajuste os dois valores no `php.ini` conforme o maior arquivo que você aceita.

## A action

O `populateFromPostAndValidate()` só lê o corpo da requisição (`getParsedBody()`), onde os arquivos não estão. A action junta os dois arrays antes de preencher o formulário. Crie `src/Web/Formularios/Upload/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Formularios\Upload;

use App\Formularios\Upload\ArmazenamentoDeFotos;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private FormHydrator $formHydrator,
        private ArmazenamentoDeFotos $armazenamento,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $form = new FotoForm();

        if ($request->getMethod() === 'POST') {
            $dados = array_replace_recursive(
                (array) $request->getParsedBody(),
                $request->getUploadedFiles(),
            );

            if ($this->formHydrator->populateAndValidate($form, $dados)) {
                $url = $this->armazenamento->salvar($form->foto);

                return $this->viewRenderer->render(__DIR__ . '/enviada', [
                    'legenda' => $form->legenda,
                    'url' => $url,
                ]);
            }
        }

        return $this->viewRenderer->render(__DIR__ . '/formulario', ['form' => $form]);
    }
}
```

O `array_replace_recursive()` coloca o `UploadedFileInterface` em `$dados['FotoForm']['foto']`, ao lado da legenda. A partir daí, o `populateAndValidate()` trata o arquivo como qualquer outro campo.

## Onde e como salvar

Quem decide o destino é uma classe de serviço, fora da camada web. Crie `src/Formularios/Upload/ArmazenamentoDeFotos.php`:

```php
<?php

declare(strict_types=1);

namespace App\Formularios\Upload;

use finfo;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Yiisoft\Aliases\Aliases;

final readonly class ArmazenamentoDeFotos
{
    private const EXTENSOES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function __construct(
        private Aliases $aliases,
    ) {}

    /**
     * Move o arquivo enviado para public/uploads/fotos e devolve a URL pública.
     */
    public function salvar(UploadedFileInterface $arquivo): string
    {
        $caminhoTemporario = (string) $arquivo->getStream()->getMetadata('uri');
        $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($caminhoTemporario);
        $extensao = self::EXTENSOES[$tipo] ?? throw new RuntimeException("Tipo de arquivo não permitido: $tipo");

        $nome = bin2hex(random_bytes(16)) . '.' . $extensao;
        $diretorio = $this->aliases->get('@public/uploads/fotos');

        if (!is_dir($diretorio) && !mkdir($diretorio, 0775, true) && !is_dir($diretorio)) {
            throw new RuntimeException("Não foi possível criar o diretório $diretorio");
        }

        $arquivo->moveTo($diretorio . '/' . $nome);

        return $this->aliases->get('@baseUrl/uploads/fotos/' . $nome);
    }
}
```

As decisões que importam:

- O nome do arquivo é gerado, nunca o que veio do navegador. Isso evita nomes com `../`, sobrescrita de arquivos de outras pessoas e nomes que revelam informação.
- A extensão sai do tipo detectado no conteúdo. Mesmo que alguém consiga enviar um arquivo com nome `foto.php`, ele é salvo como `.jpg` ou `.png` e o servidor web não o executa.
- O diretório é criado se não existir, porque o `moveTo()` exige que ele exista.

Salvar em `public/` faz sentido quando o arquivo é público, como uma foto de perfil: o servidor web entrega direto, sem passar pelo PHP. Para arquivos que só algumas pessoas podem ver (documentos, notas fiscais), salve fora de `public/`, por exemplo em `@runtime/uploads`, e entregue por uma action que confere a permissão antes de devolver o conteúdo. O capítulo [Resposta](/requisicoes/resposta) mostra como devolver um download.

Em servidores com várias máquinas, o disco local não é compartilhado. Nesse caso, a mesma classe de serviço passa a gravar num armazenamento externo (S3 ou similar), e o resto do código não muda.

## As views

O formulário precisa do `enctype="multipart/form-data"`, senão o navegador envia só o nome do arquivo. Crie `src/Web/Formularios/Upload/formulario.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var App\Web\Formularios\Upload\FotoForm $form
 */

$this->setTitle('Enviar foto');
?>

<h1>Enviar foto</h1>

<?= Html::form()
    ->post($urlGenerator->generate('formularios/upload'))
    ->enctypeMultipartFormData()
    ->csrf($csrf)
    ->open() ?>
    <?= Field::text($form, 'legenda') ?>
    <?= Field::file($form, 'foto')->accept('image/jpeg,image/png') ?>
    <?= Field::submitButton('Enviar') ?>
<?= Html::form()->close() ?>
```

O `accept()` faz o seletor de arquivos do navegador mostrar só imagens. É uma conveniência para o usuário, e não uma proteção: a regra `File` continua sendo quem decide.

E a confirmação, em `src/Web/Formularios/Upload/enviada.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var string $legenda
 * @var string $url
 */

$this->setTitle('Foto enviada');
?>

<h1>Foto enviada</h1>

<figure>
    <?= Html::img($url, $legenda)->width(400) ?>
    <figcaption><?= Html::encode($legenda) ?></figcaption>
</figure>
```

A rota, no grupo `/formularios`:

```php
Route::methods([Method::GET, Method::POST], '/upload')
    ->action(Web\Formularios\Upload\Action::class)
    ->name('formularios/upload'),
```

## Testando

Abra http://127.0.0.1:8080/formularios/upload. Para testar os casos de erro, também dá para usar o `curl` com a opção `-F`, que monta um envio `multipart/form-data` (lembre de mandar o cookie de sessão e o campo `_csrf` da página).

- Sem arquivo: "Escolha uma foto.".
- Um arquivo de texto renomeado para `falsa.png`: "O conteúdo do arquivo não é JPEG nem PNG.".
- Um JPEG de 1,5 MB: "A foto pode ter no máximo 1 MB.".
- Um JPEG de 3 MB: "O envio da foto falhou (código 1).", porque passou do `upload_max_filesize`.
- Um PNG com 4100 pixels de largura: "A foto pode ter no máximo 4000 pixels de largura.".
- Um PNG pequeno: aparece "Foto enviada" com a imagem, que fica em `public/uploads/fotos/` com um nome como `75bafc7be7fe8d50557430e627657c1a.png`. Isso acontece mesmo que o envio declare um `Content-Type` falso, porque o tipo é detectado pelo conteúdo.

## Comparando com o Yii2

- O `UploadedFile::getInstance($model, 'foto')` deu lugar ao `$request->getUploadedFiles()`, da PSR-7. O `saveAs()` virou `moveTo()`.
- O `FileValidator` e o `ImageValidator` viraram as regras `File` e `Image`, com parâmetros parecidos (`extensions`, `mimeTypes`, `maxSize`, `maxWidth`...).
