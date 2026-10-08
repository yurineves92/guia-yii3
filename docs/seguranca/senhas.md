# Senhas

Uma senha nunca deve ser guardada como foi digitada, nem com criptografia que dê para desfazer. O que vai para o banco é um hash: um resumo calculado de um jeito lento e com um valor aleatório (o *salt*), para que um vazamento do banco não entregue as senhas e para que adivinhá-las uma a uma custe caro. Este capítulo mostra o `PasswordHasher` do `yiisoft/security`, como atualizar hashes antigos e como impor uma política de senha num formulário de troca de senha.

## PasswordHasher

O `Yiisoft\Security\PasswordHasher` é uma camada fina sobre as funções `password_hash()`, `password_verify()` e `password_needs_rehash()` do PHP. Ele tem três métodos:

```php
$hasher = new PasswordHasher();

$hash = $hasher->hash('senha-de-exemplo');
// $2y$13$...

$hasher->validate('senha-de-exemplo', $hash); // true
$hasher->validate('outra-senha', $hash);      // false

$hasher->needsRehash($hash); // false
```

- `hash()` gera o hash, com um salt novo a cada chamada. A mesma senha gera hashes diferentes cada vez, e todos são válidos.
- `validate()` confere uma senha com um hash. Ele lança `InvalidArgumentException` se a senha for uma string vazia, por isso o formulário de login tem `Required` na senha.
- `needsRehash()` diz se o hash foi gerado com um algoritmo ou custo diferente do configurado agora.

Sem argumentos, o algoritmo é o `PASSWORD_DEFAULT` do PHP, que no PHP 8.4 é o bcrypt, e o custo é 13. O prefixo `$2y$13$` do hash guarda o algoritmo e o custo. Cada ponto a mais no custo dobra o tempo de cálculo. Na máquina usada para escrever este guia, um hash com custo 13 levou cerca de 380 ms: lento o bastante para atrapalhar quem tenta milhões de senhas, e aceitável para um login.

O `PasswordHasher` não precisa de configuração. Peça no construtor da action e o container cria um com os valores padrão. Para trocar o algoritmo, por exemplo para o Argon2id, defina no container:

```php
use Yiisoft\Security\PasswordHasher;

return [
    PasswordHasher::class => [
        '__construct()' => ['algorithm' => PASSWORD_ARGON2ID],
    ],
];
```

Os hashes antigos continuam funcionando depois da troca: o `password_verify()` lê o algoritmo do próprio hash. O `needsRehash()` dos hashes antigos passa a devolver `true`, e é isso que permite migrar aos poucos.

## Atualizando hashes no login

O momento de atualizar um hash é o login, porque é o único momento em que o app tem a senha em texto. A action de login do capítulo [Autenticação](/seguranca/autenticacao) faz isso logo depois de validar a senha (trecho):

```php
if ($usuario !== null && $this->passwordHasher->validate($form->senha, $usuario->senhaHash)) {
    if ($this->passwordHasher->needsRehash($usuario->senhaHash)) {
        $this->usuarios->alterarSenha($usuario->id, $this->passwordHasher->hash($form->senha));
    }

    $this->currentUser->login($usuario);

    return $this->irParaOPainel();
}
```

Para testar, troque o hash da Ana por um gerado com custo 10 (`password_hash('senha-de-exemplo', PASSWORD_BCRYPT, ['cost' => 10])`) e faça login. Antes do login, o hash começa com `$2y$10$`. Depois, com `$2y$13$`.

## O limite de 72 bytes do bcrypt

O bcrypt só usa os primeiros 72 bytes da senha. No PHP 8.4, o resto é ignorado em silêncio:

```php
$hasher = new PasswordHasher();
$base = str_repeat('a', 72);

$hash = $hasher->hash($base . 'PRIMEIRO');
$hasher->validate($base . 'OUTRO-FINAL', $hash); // true
```

Por isso a política de senha abaixo limita o tamanho. Com o Argon2id, esse limite não existe.

## Política de senha

As recomendações atuais (como as do NIST, na publicação SP 800-63B) privilegiam o comprimento sobre regras de composição como "uma maiúscula, um número e um símbolo". Na prática:

- Exija um tamanho mínimo razoável (12 caracteres, por exemplo) e aceite senhas longas, como frases.
- Recuse senhas comuns e senhas que contêm dados do próprio usuário.
- Não obrigue a troca periódica sem motivo.

Essas regras viram regras de validação num formulário. Crie `src/Web/Seguranca/Senha/TrocarSenhaForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Senha;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Label;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\Rule\Callback;
use Yiisoft\Validator\Rule\CompareType;
use Yiisoft\Validator\Rule\Equal;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

final class TrocarSenhaForm extends FormModel
{
    private const SENHAS_COMUNS = [
        '123456789012',
        'senha1234567',
        'qwertyuiopas',
        'abcdefghijkl',
    ];

    #[Label('Senha atual')]
    #[Required(message: 'Informe a senha atual.')]
    public string $senhaAtual = '';

    #[Label('Nova senha')]
    #[Required(message: 'Informe a nova senha.')]
    #[Length(
        min: 12,
        max: 64,
        lessThanMinMessage: 'A senha precisa ter pelo menos {min} caracteres.',
        greaterThanMaxMessage: 'A senha pode ter no máximo {max} caracteres.',
        skipOnEmpty: true,
    )]
    #[Callback(method: 'validarNovaSenha', skipOnEmpty: true, skipOnError: true)]
    public string $novaSenha = '';

    #[Label('Repita a nova senha')]
    #[Equal(targetProperty: 'novaSenha', type: CompareType::STRING, message: 'As senhas não conferem.')]
    public string $confirmacao = '';

    public function __construct(
        private readonly string $email,
    ) {}

    private function validarNovaSenha(string $senha): Result
    {
        $result = new Result();
        $senhaMinuscula = mb_strtolower($senha);
        $usuarioDoEmail = mb_strtolower(explode('@', $this->email)[0]);

        if (in_array($senhaMinuscula, self::SENHAS_COMUNS, true)) {
            $result->addError('Esta senha é comum demais. Escolha outra.');
        } elseif (str_contains($senhaMinuscula, $usuarioDoEmail)) {
            $result->addError('A senha não pode conter o seu e-mail.');
        } elseif ($senha === $this->senhaAtual) {
            $result->addError('A nova senha precisa ser diferente da atual.');
        }

        return $result;
    }
}
```

Pontos a notar:

- O `max: 64` conta caracteres, e o limite do bcrypt é em bytes. Com acentos e outros caracteres de mais de um byte, 64 caracteres podem passar de 72 bytes. Se isso importa no seu caso, use o Argon2id ou confira o tamanho com `strlen()` no `Callback`.
- O `#[Callback(method: ...)]` chama um método do próprio formulário, que pode até ser privado. O método recebe o valor e devolve um `Result`. O `skipOnError: true` faz o `Callback` só rodar se o `Length` passou.
- O e-mail chega pelo construtor. Como a propriedade `$email` não tem regra, o `FormHydrator` não a preenche com o que vier do POST.
- A lista de senhas comuns aqui é só ilustrativa. Num sistema real, use uma lista grande (as listas de senhas vazadas mais usadas têm milhares de itens) ou consulte um serviço como o Have I Been Pwned, que permite checar uma senha sem enviá-la inteira.

## A troca de senha

A action confere a senha atual antes de trocar. Crie `src/Web/Seguranca/Senha/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Senha;

use App\Seguranca\Usuario;
use App\Seguranca\UsuarioRepositorio;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private FormHydrator $formHydrator,
        private CurrentUser $currentUser,
        private UsuarioRepositorio $usuarios,
        private PasswordHasher $passwordHasher,
        private FlashInterface $flash,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        /** @var Usuario $usuario */
        $usuario = $this->currentUser->getIdentity();
        $form = new TrocarSenhaForm($usuario->email);

        if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
            if ($this->passwordHasher->validate($form->senhaAtual, $usuario->senhaHash)) {
                $this->usuarios->alterarSenha($usuario->id, $this->passwordHasher->hash($form->novaSenha));
                $this->flash->set('aviso', 'Senha alterada.');

                return $this->responseFactory
                    ->createResponse(Status::FOUND)
                    ->withHeader('Location', $this->urlGenerator->generate('seguranca/painel'));
            }

            $form->addError('A senha atual está incorreta.', ['senhaAtual']);
        }

        // Senhas nunca voltam preenchidas para o navegador
        $form->senhaAtual = $form->novaSenha = $form->confirmacao = '';

        return $this->viewRenderer->render(__DIR__ . '/formulario', ['form' => $form]);
    }
}
```

Pedir a senha atual impede que alguém que encontrou uma sessão aberta troque a senha e tome a conta. A mensagem de sucesso vai como [mensagem flash](/requisicoes/sessoes-e-cookies) e aparece no painel.

A view, em `src/Web/Seguranca/Senha/formulario.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var App\Web\Seguranca\Senha\TrocarSenhaForm $form
 */

$this->setTitle('Trocar a senha');
?>

<h1>Trocar a senha</h1>

<?= Html::form()->post($urlGenerator->generate('seguranca/senha'))->csrf($csrf)->open() ?>
    <?= Field::password($form, 'senhaAtual') ?>
    <?= Field::password($form, 'novaSenha')->hint('De 12 a 64 caracteres. Uma frase com várias palavras funciona bem.') ?>
    <?= Field::password($form, 'confirmacao') ?>
    <?= Field::submitButton('Trocar') ?>
<?= Html::form()->close() ?>
```

A rota fica no grupo que exige login:

```php
Route::methods([Method::GET, Method::POST], '/senha')
    ->action(Web\Seguranca\Senha\Action::class)
    ->name('seguranca/senha'),
```

## Testando

Entre como `bruno@exemplo.com` e abra `/seguranca/senha`. Com a senha atual `senha-de-exemplo`:

- Nova senha `curta`: "A senha precisa ter pelo menos 12 caracteres.".
- `123456789012`: "Esta senha é comum demais. Escolha outra.".
- `bruno-e-legal-2026`: "A senha não pode conter o seu e-mail.".
- `senha-de-exemplo`: "A nova senha precisa ser diferente da atual.".
- Confirmação diferente da nova senha: "As senhas não conferem.".
- Senha atual errada: "A senha atual está incorreta.".
- `cavalo correto bateria grampo` nos dois campos: o painel abre com "Senha alterada.", e daí em diante o login só funciona com a senha nova.

## Comparando com o Yii2

- O `Yii::$app->security->generatePasswordHash()` e o `validatePassword()` viraram `PasswordHasher::hash()` e `validate()`, numa classe só para isso.
- O Yii2 usava bcrypt com o custo do `passwordHashCost` (13 por padrão). O `PasswordHasher` usa o algoritmo padrão do PHP com o mesmo custo, e aceita outros algoritmos no construtor.
- O `needsRehash()` não tinha equivalente direto no componente `security` do Yii2.
