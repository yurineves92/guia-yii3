# Criptografia

Além de senhas, um app precisa de outras operações criptográficas: gerar tokens que ninguém consegue adivinhar, garantir que um dado enviado ao usuário volte sem alteração e guardar dados sensíveis de um jeito que só o app consiga ler. O pacote `yiisoft/security` tem uma classe para cada caso: `Random`, `Mac` e `Crypt`. Este capítulo mostra as três, com dois exemplos no app: um link de convite assinado e um "cofre" que cifra textos.

| Preciso de... | Use | O dado fica... |
|---|---|---|
| Um valor imprevisível (token, código, nome de arquivo) | `Random::string()` ou `random_bytes()` | Aleatório |
| Saber se um dado foi alterado | `Mac` | Legível, mas não alterável |
| Esconder um dado e ler de volta depois | `Crypt` | Ilegível sem a chave |
| Guardar uma senha | `PasswordHasher` ([Senhas](/seguranca/senhas)) | Impossível de ler de volta |

## Gerando tokens aleatórios

Tokens de convite, de confirmação de e-mail, de redefinição de senha e de API precisam ser imprevisíveis. Use sempre uma fonte de aleatoriedade criptográfica:

```php
use Yiisoft\Security\Random;

Random::string(32);        // VfIvX8owyi3TK-zokm8ZA9t0DEbDngkL
bin2hex(random_bytes(16)); // 089ab63e0c2a952cfd46ef1cac3ca1da
random_int(100000, 999999); // 847379, para um código numérico
```

O `Random::string()` devolve só letras, números, `-` e `_`, então o valor pode ir direto numa URL. Por baixo, ele usa o `random_bytes()`. Nunca use `rand()`, `mt_rand()`, `uniqid()` ou `md5(time())` para isso: os valores dessas funções podem ser previstos.

Um token que dá acesso a algo deve ser tratado como uma senha. Guarde no banco só o hash dele, e não o valor:

```php
$token = Random::string(32);                // vai para o usuário (e-mail, link)
$hashDoToken = hash('sha256', $token);      // vai para o banco
```

Quando o token voltar, calcule o `hash('sha256', ...)` dele e procure pelo hash. Assim, quem tiver acesso ao banco não consegue usar os tokens. Aqui um hash rápido como o SHA-256 basta, porque o token já é aleatório e longo, ao contrário de uma senha escolhida por uma pessoa. Se precisar comparar dois valores secretos em PHP, use `hash_equals()`, que leva o mesmo tempo independentemente de onde está a diferença.

## A chave secreta

Assinar e cifrar exigem uma chave que só o servidor conhece. No app de exemplo, ela vem da variável de ambiente `SEGURANCA_CHAVE` e fica nos parâmetros, em `config/common/params/seguranca.php`:

```php
<?php

declare(strict_types=1);

use App\Environment;
use App\Seguranca\Console\CofreCommand;
use App\Seguranca\Console\RbacCommand;

// A chave secreta vem do ambiente. Fora de produção, se ela não existir,
// usamos um valor fixo para o app funcionar sem configuração.
$chaveSecreta = getenv('SEGURANCA_CHAVE') ?: ($_ENV['SEGURANCA_CHAVE'] ?? '');
if ($chaveSecreta === '' && !Environment::isProd()) {
    $chaveSecreta = 'chave-de-desenvolvimento-nao-use-em-producao';
}

return [
    'yiisoft/user' => [
        // Para onde o usuário não logado é mandado
        'authUrl' => '/seguranca/login',
    ],

    'yiisoft/yii-console' => [
        'commands' => [
            'seguranca:rbac' => RbacCommand::class,
            'seguranca:cofre' => CofreCommand::class,
        ],
    ],

    'app/seguranca' => [
        'chaveSecreta' => $chaveSecreta,
    ],
];
```

Em produção, sem a variável, a chave fica vazia, e as classes que dependem dela lançam uma exceção ao serem criadas. É melhor o app parar do que funcionar com uma chave conhecida. Para gerar uma chave:

```bash
php -r "require 'vendor/autoload.php'; echo Yiisoft\Security\Random::string(64), PHP_EOL;"
```

A chave não vai para o repositório: ela fica na configuração do servidor ou no `.env` local, que o template já deixa fora do Git.

As classes recebem a chave pelo container, em `config/common/di/seguranca.php` (trecho):

```php
/** @var array $params */

return [
    // Classes que precisam da chave secreta
    TokenAssinado::class => [
        '__construct()' => ['chaveSecreta' => $params['app/seguranca']['chaveSecreta']],
    ],
    Cofre::class => [
        '__construct()' => ['chaveSecreta' => $params['app/seguranca']['chaveSecreta']],
    ],
];
```

## Assinando dados com Mac

O `Mac` calcula um HMAC: um código que depende do dado e da chave. Quem não tem a chave não consegue calcular o código certo para um dado alterado. Os métodos:

- `sign($dado, $chave)` devolve o código seguido do dado.
- `getMessage($assinado, $chave)` confere o código e devolve o dado original, ou lança `DataIsTamperedException` se algo foi alterado.

Com isso dá para criar links que carregam informação sem precisar de uma tabela no banco: o link de convite abaixo diz quem convidou e até quando vale, e o app confia nesses dados porque estão assinados. Crie `src/Seguranca/Cripto/TokenAssinado.php`:

```php
<?php

declare(strict_types=1);

namespace App\Seguranca\Cripto;

use InvalidArgumentException;
use SensitiveParameter;
use Yiisoft\Security\DataIsTamperedException;
use Yiisoft\Security\Mac;
use Yiisoft\Strings\StringHelper;

/**
 * Gera tokens com dados e prazo de validade, assinados com HMAC.
 * Quem recebe o token consegue ler os dados, mas não consegue alterá-los.
 */
final readonly class TokenAssinado
{
    private string $chave;
    private Mac $mac;

    public function __construct(#[SensitiveParameter] string $chaveSecreta)
    {
        if ($chaveSecreta === '') {
            throw new InvalidArgumentException('A chave secreta não foi configurada.');
        }

        // Uma subchave só para esta finalidade, derivada da chave principal
        $this->chave = hash_hkdf('sha256', $chaveSecreta, 32, 'token-assinado');
        $this->mac = new Mac();
    }

    public function gerar(array $dados, int $validadeEmSegundos): string
    {
        $dados['expira'] = time() + $validadeEmSegundos;
        $assinado = $this->mac->sign(json_encode($dados, JSON_THROW_ON_ERROR), $this->chave);

        return StringHelper::base64UrlEncode($assinado);
    }

    /**
     * Devolve os dados, ou null se o token foi alterado ou expirou.
     */
    public function ler(string $token): ?array
    {
        try {
            $json = $this->mac->getMessage(StringHelper::base64UrlDecode($token), $this->chave);
        } catch (DataIsTamperedException) {
            return null;
        }

        $dados = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $dados['expira'] >= time() ? $dados : null;
    }
}
```

Alguns detalhes:

- O prazo de validade vai dentro dos dados assinados. Assim, ninguém consegue estender o prazo sem invalidar a assinatura.
- O `hash_hkdf()` deriva da chave principal uma subchave só para esta classe. Usar a mesma chave para finalidades diferentes faria um token de um tipo valer como outro.
- O `#[SensitiveParameter]` esconde o valor da chave dos stack traces, como mostra o capítulo [Boas práticas](/seguranca/boas-praticas).

A action que gera o convite fica no grupo que exige login. Crie `src/Web/Seguranca/Convite/GerarAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Convite;

use App\Seguranca\Cripto\TokenAssinado;
use App\Seguranca\Usuario;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class GerarAction
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private CurrentUser $currentUser,
        private TokenAssinado $tokenAssinado,
    ) {}

    public function __invoke(): ResponseInterface
    {
        /** @var Usuario $usuario */
        $usuario = $this->currentUser->getIdentity();

        $token = $this->tokenAssinado->gerar(['convidadoPor' => $usuario->nome], validadeEmSegundos: 600);

        return $this->viewRenderer->render(__DIR__ . '/gerado', ['token' => $token]);
    }
}
```

A view `gerado.php` monta o link com `$urlGenerator->generate('seguranca/convite', queryParameters: ['token' => $token])` e o mostra na página. A action que abre o convite é pública. Crie `src/Web/Seguranca/Convite/AbrirAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Convite;

use App\Seguranca\Cripto\TokenAssinado;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Status;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class AbrirAction
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private TokenAssinado $tokenAssinado,
    ) {}

    public function __invoke(#[Query('token')] string $token = ''): ResponseInterface
    {
        $dados = $this->tokenAssinado->ler($token);

        if ($dados === null) {
            return $this->viewRenderer
                ->render(__DIR__ . '/invalido')
                ->withStatus(Status::FORBIDDEN);
        }

        return $this->viewRenderer->render(__DIR__ . '/aberto', [
            'convidadoPor' => $dados['convidadoPor'],
            'expira' => $dados['expira'],
        ]);
    }
}
```

A view `aberto.php` mostra "Você foi convidado por" com o nome e o horário de expiração, e a `invalido.php` mostra "Convite inválido". As rotas, no grupo `/seguranca`:

```php
// fora do grupo que exige login
Route::get('/convite')
    ->action(Web\Seguranca\Convite\AbrirAction::class)
    ->name('seguranca/convite'),

// dentro do grupo que exige login
Route::get('/convite/novo')
    ->action(Web\Seguranca\Convite\GerarAction::class)
    ->name('seguranca/convite/novo'),
```

### Testando o convite

Entre como Ana e abra `/seguranca/convite/novo`. A página mostra um link como `/seguranca/convite?token=N2Y2NWZl...`. Abrindo o link, aparece "Você foi convidado por Ana" e o horário de expiração.

O token não é secreto. Decodificando o base64, os dados aparecem em texto, depois dos 64 caracteres do código HMAC:

```plaintext
7f65fe9ef547d29ef2501bef96867b8a4acbd5295b5424ad205f8bfcb1186d68{"convidadoPor":"Ana","expira":1791437035}
```

Trocando `Ana` por `Eva` e codificando de novo, o link passa a dar "Convite inválido", com status 403, porque o código não confere mais. O mesmo acontece com um token qualquer (`?token=lixo`) e com um token gerado com validade já vencida. Se os dados não podem ser vistos pelo usuário, assinar não basta: é preciso cifrar.

## Cifrando dados com Crypt

O `Crypt` cifra com AES e inclui um HMAC no resultado, então ele também detecta alterações. Há dois pares de métodos:

- `encryptByKey()` e `decryptByKey()`: para uma chave aleatória, como a do app. São rápidos.
- `encryptByPassword()` e `decryptByPassword()`: para uma senha escolhida por uma pessoa. Derivam a chave com PBKDF2, de propósito lento (100 mil iterações por padrão).

Um uso comum é guardar no banco algo que o app precisa ler de volta, como a chave de API de um serviço externo ou um documento do cliente. Crie `src/Seguranca/Cripto/Cofre.php`:

```php
<?php

declare(strict_types=1);

namespace App\Seguranca\Cripto;

use InvalidArgumentException;
use SensitiveParameter;
use Yiisoft\Security\AuthenticationException;
use Yiisoft\Security\Crypt;

/**
 * Cifra textos para guardar no banco e decifra quando precisar usar.
 */
final readonly class Cofre
{
    private Crypt $crypt;

    public function __construct(
        #[SensitiveParameter]
        private string $chaveSecreta,
    ) {
        if ($chaveSecreta === '') {
            throw new InvalidArgumentException('A chave secreta não foi configurada.');
        }

        $this->crypt = new Crypt('AES-256-CBC');
    }

    public function cifrar(#[SensitiveParameter] string $texto): string
    {
        return base64_encode($this->crypt->encryptByKey($texto, $this->chaveSecreta, 'cofre'));
    }

    /**
     * @throws AuthenticationException se o conteúdo foi alterado ou a chave é outra
     */
    public function decifrar(string $cifrado): string
    {
        return $this->crypt->decryptByKey((string) base64_decode($cifrado, true), $this->chaveSecreta, 'cofre');
    }
}
```

O `Crypt` aceita `AES-128-CBC` (o padrão), `AES-192-CBC` e `AES-256-CBC`. O resultado é binário, por isso o `base64_encode()` antes de guardar numa coluna de texto. O terceiro argumento, `'cofre'`, é o `info` da derivação de chave (HKDF): com valores diferentes de `info`, a mesma chave principal gera chaves de cifra diferentes para cada finalidade.

Para experimentar, um comando de console cifra um texto, decifra de volta e tenta decifrar uma versão alterada. Crie `src/Seguranca/Console/CofreCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Seguranca\Console;

use App\Seguranca\Cripto\Cofre;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Security\AuthenticationException;
use Yiisoft\Yii\Console\ExitCode;

#[AsCommand(
    name: 'seguranca:cofre',
    description: 'Cifra um texto e decifra de volta, para testar o Cofre',
)]
final class CofreCommand extends Command
{
    public function __construct(
        private readonly Cofre $cofre,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('texto', InputArgument::REQUIRED, 'Texto a cifrar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cifrado = $this->cofre->cifrar($input->getArgument('texto'));
        $output->writeln("Cifrado:  $cifrado");
        $output->writeln('Decifrado: ' . $this->cofre->decifrar($cifrado));

        // Trocando um caractere no meio, a verificação falha
        $adulterado = substr_replace($cifrado, $cifrado[20] === 'A' ? 'B' : 'A', 20, 1);
        try {
            $this->cofre->decifrar($adulterado);
        } catch (AuthenticationException $e) {
            $output->writeln('Adulterado: ' . $e->getMessage());
        }

        return ExitCode::OK;
    }
}
```

```bash
./yii seguranca:cofre "chave-de-api-de-exemplo"
```

```plaintext
Cifrado:  iwX/5NCM7rtpBJYVsbAyH73lMdKpzKfA9OdTSaFKb2w1MzUwOWM3NTZlMDAzYmYzZWJkNzI4ZjNjMTQzNTg2ZTY5ZDc3ZjJjY2M5OWMwZDVlYWJlYTllYzhlZjczMTlhC0ECFlGopjdpaSlZV2koU+2rL+3Sj4QxW2xmGejL0PeU8TljQluONkdtzelxISbI
Decifrado: chave-de-api-de-exemplo
Adulterado: Failed to decrypt data.
```

Rodando de novo, o texto cifrado muda, porque cada cifragem usa um salt e um vetor de inicialização novos. Isso é esperado: dois registros com o mesmo conteúdo não ficam iguais no banco. A consequência é que não dá para buscar no banco por um valor cifrado. Se precisar buscar, guarde ao lado um hash do valor (com HMAC e uma chave própria) e busque pelo hash.

Perder a chave significa perder os dados cifrados com ela. Trocar a chave exige decifrar tudo com a antiga e cifrar de novo com a nova.

## TokenMask

A última classe do pacote, `TokenMask`, aplica uma máscara aleatória a um token, de modo que o mesmo token apareça diferente em cada página. É o que o `yiisoft/csrf` usa para o token CSRF, contra o ataque BREACH, que explora a compressão HTTP para descobrir segredos que se repetem nas respostas. Você dificilmente vai usá-la direto.

## Comparando com o Yii2

- O componente `Yii::$app->security` foi dividido em classes: `Random::string()` substitui o `generateRandomString()`, o `Mac` substitui o `hashData()`/`validateData()`, o `Crypt` substitui o `encryptByKey()`/`encryptByPassword()` e o `PasswordHasher` cuida das senhas.
- No Yii2, o `validateData()` devolvia `false` para dados alterados. O `Mac::getMessage()` lança uma exceção.
- O `cookieValidationKey` do Yii2 não existe no template. Cada classe que precisa de uma chave a recebe pelo container, como mostrado acima.
