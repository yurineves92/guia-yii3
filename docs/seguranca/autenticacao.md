# Autenticação

Autenticar é descobrir quem está fazendo a requisição. Num app web com páginas, o caminho mais comum é o login por sessão: o usuário informa e-mail e senha uma vez, e o app guarda na sessão quem ele é. Este capítulo monta esse fluxo com o pacote `yiisoft/user`: tabela de usuários, formulário de login, logout e páginas que só abrem para quem está logado. No fim, há uma nota sobre tokens para APIs.

## As peças do yiisoft/user

O `yiisoft/user` trabalha com três conceitos, dois deles definidos no pacote `yiisoft/auth`:

- `Yiisoft\Auth\IdentityInterface`: o usuário. A interface tem um único método, `getId()`. A sua classe de usuário implementa essa interface.
- `Yiisoft\Auth\IdentityRepositoryInterface`: onde buscar um usuário pelo id, com o método `findIdentity()`. Você implementa e registra no container.
- `Yiisoft\User\CurrentUser`: o usuário da requisição atual. É o serviço que você injeta nas actions. Os métodos principais são `getIdentity()`, `getId()`, `isGuest()`, `login()`, `logout()` e `can()`, este último usado no capítulo [Autorização](/seguranca/autorizacao).

Quando ninguém está logado, `getIdentity()` devolve um `GuestIdentity`, cujo `getId()` é `null`, e `isGuest()` devolve `true`. Assim o código não precisa tratar `null` em todo lugar.

## A tabela de usuários

A migration cria a tabela `usuario_exemplo` e três usuários de exemplo, todos com a senha `senha-de-exemplo`. Ela foi gerada com `./yii migrate:create usuario_exemplo --command=table` e completada com os registros:

```php
<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;
use Yiisoft\Security\PasswordHasher;

/**
 * Handles the creation of table `usuario_exemplo`.
 */
final class M261008050722CreateUsuarioExemploTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $columnBuilder = $b->columnBuilder();

        $b->createTable('usuario_exemplo', [
            'id' => $columnBuilder::primaryKey(),
            'email' => $columnBuilder::string(255)->notNull()->unique(),
            'nome' => $columnBuilder::string(100)->notNull(),
            'senha_hash' => $columnBuilder::string(255)->notNull(),
        ]);

        // Usuários de exemplo. Todos com a senha "senha-de-exemplo".
        $hash = (new PasswordHasher())->hash('senha-de-exemplo');

        $b->insertBatch('usuario_exemplo', [
            ['ana@exemplo.com', 'Ana', $hash],
            ['bruno@exemplo.com', 'Bruno', $hash],
            ['carla@exemplo.com', 'Carla', $hash],
        ], ['email', 'nome', 'senha_hash']);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('usuario_exemplo');
    }
}
```

A senha nunca é guardada: a coluna `senha_hash` recebe o resultado do `PasswordHasher`, assunto do capítulo [Senhas](/seguranca/senhas). Aplique com `./yii migrate:up`.

## O usuário e o repositório

O usuário é uma classe imutável que implementa `IdentityInterface`. Crie `src/Seguranca/Usuario.php`:

```php
<?php

declare(strict_types=1);

namespace App\Seguranca;

use Yiisoft\Auth\IdentityInterface;

final readonly class Usuario implements IdentityInterface
{
    public function __construct(
        public int $id,
        public string $email,
        public string $nome,
        public string $senhaHash,
    ) {}

    public function getId(): string
    {
        return (string) $this->id;
    }
}
```

O `getId()` devolve string porque é assim que o id é guardado na sessão e passado ao repositório.

O repositório busca os usuários no banco. Crie `src/Seguranca/UsuarioRepositorio.php`:

```php
<?php

declare(strict_types=1);

namespace App\Seguranca;

use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class UsuarioRepositorio implements IdentityRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function findIdentity(string $id): ?Usuario
    {
        return $this->buscar(['id' => (int) $id]);
    }

    public function porEmail(string $email): ?Usuario
    {
        return $this->buscar(['email' => mb_strtolower(trim($email))]);
    }

    public function alterarSenha(int $id, string $senhaHash): void
    {
        $this->db->createCommand()
            ->update('usuario_exemplo', ['senha_hash' => $senhaHash], ['id' => $id])
            ->execute();
    }

    public function alterarNome(int $id, string $nome): void
    {
        $this->db->createCommand()
            ->update('usuario_exemplo', ['nome' => $nome], ['id' => $id])
            ->execute();
    }

    private function buscar(array $condicao): ?Usuario
    {
        $linha = $this->db->select()->from('usuario_exemplo')->where($condicao)->one();

        if ($linha === null) {
            return null;
        }

        return new Usuario(
            id: (int) $linha['id'],
            email: $linha['email'],
            nome: $linha['nome'],
            senhaHash: $linha['senha_hash'],
        );
    }
}
```

O `findIdentity()` é o método da interface. O `yiisoft/user` chama esse método em cada requisição, com o id guardado na sessão, para montar o usuário atual. Os outros métodos são do app.

## Configurando

Duas definições no container ligam as peças. A primeira diz qual repositório usar. Ela fica em `config/common/di/seguranca.php` (trecho):

```php
use App\Seguranca\UsuarioRepositorio;
use Yiisoft\Auth\IdentityRepositoryInterface;

return [
    // Onde o yiisoft/user busca o usuário logado a partir do id guardado na sessão
    IdentityRepositoryInterface::class => UsuarioRepositorio::class,
    // ...
];
```

A segunda configura o `CurrentUser`. A configuração que vem com o pacote não passa a sessão para ele, e sem sessão o login não sobrevive à requisição seguinte: o `CurrentUser` só guarda o id do usuário se tiver uma `SessionInterface`. Como a sessão só existe na web, a definição fica em `config/web/di/seguranca.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Access\AccessCheckerInterface;
use Yiisoft\Definitions\Reference;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;

return [
    CurrentUser::class => [
        'withSession()' => [Reference::to(SessionInterface::class)],
        'withAccessChecker()' => [Reference::to(AccessCheckerInterface::class)],
        'reset' => function () {
            $this->clear();
        },
    ],
];
```

O `withAccessChecker()` é para as permissões, e fica explicado no próximo capítulo. O `reset` repete o que a configuração do pacote já fazia: como esta definição substitui a do pacote, ela precisa trazer tudo. Ele limpa o usuário em memória entre requisições quando o app roda num [servidor de longa duração](/topicos/longa-duracao).

Por fim, nos parâmetros (`config/common/params/seguranca.php`, trecho), o endereço da página de login:

```php
'yiisoft/user' => [
    // Para onde o usuário não logado é mandado
    'authUrl' => '/seguranca/login',
],
```

## O login

O formulário de login, em `src/Web/Seguranca/Login/LoginForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Login;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Label;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Required;

final class LoginForm extends FormModel
{
    #[Label('E-mail')]
    #[Required(message: 'Informe o e-mail.')]
    #[Email(message: 'Este e-mail não parece válido.', skipOnEmpty: true)]
    public string $email = '';

    #[Required(message: 'Informe a senha.')]
    public string $senha = '';
}
```

A action, em `src/Web/Seguranca/Login/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Login;

use App\Seguranca\UsuarioRepositorio;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
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
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->currentUser->isGuest()) {
            return $this->irParaOPainel();
        }

        $form = new LoginForm();

        if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
            $usuario = $this->usuarios->porEmail($form->email);

            if ($usuario !== null && $this->passwordHasher->validate($form->senha, $usuario->senhaHash)) {
                if ($this->passwordHasher->needsRehash($usuario->senhaHash)) {
                    $this->usuarios->alterarSenha($usuario->id, $this->passwordHasher->hash($form->senha));
                }

                $this->currentUser->login($usuario);

                return $this->irParaOPainel();
            }

            // A mesma mensagem para e-mail inexistente e senha errada
            $form->addError('E-mail ou senha incorretos.', ['senha']);
        }

        $form->senha = '';

        return $this->viewRenderer->render(__DIR__ . '/formulario', ['form' => $form]);
    }

    private function irParaOPainel(): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse(Status::FOUND)
            ->withHeader('Location', $this->urlGenerator->generate('seguranca/painel'));
    }
}
```

O fluxo:

1. Quem já está logado vai direto para o painel.
2. O formulário é validado como qualquer outro. Se o e-mail existe e a senha confere com o hash, o `login()` guarda o id do usuário na sessão.
3. Se algo não confere, a mensagem é a mesma para e-mail inexistente e senha errada. Mensagens diferentes contariam a um atacante quais e-mails têm conta.
4. A senha nunca volta preenchida para o navegador: o `$form->senha = ''` limpa o campo antes de mostrar o formulário de novo.

O trecho com `needsRehash()` atualiza o hash de senhas antigas no momento do login, e é explicado em [Senhas](/seguranca/senhas).

O `login()` também troca o id da sessão (com `regenerateID()`). Isso impede o ataque de fixação de sessão, em que alguém planta um id de sessão conhecido no navegador da vítima antes de ela fazer login. O `logout()` faz o mesmo.

A view, em `src/Web/Seguranca/Login/formulario.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var App\Web\Seguranca\Login\LoginForm $form
 */

$this->setTitle('Entrar');
?>

<h1>Entrar</h1>

<?= Html::form()->post($urlGenerator->generate('seguranca/login'))->csrf($csrf)->open() ?>
    <?= Field::email($form, 'email') ?>
    <?= Field::password($form, 'senha') ?>
    <?= Field::submitButton('Entrar') ?>
<?= Html::form()->close() ?>
```

## O logout

O logout é um POST, para ficar protegido pelo token CSRF. Com um link comum (GET), qualquer site poderia deslogar os seus usuários com uma tag `<img>`. Crie `src/Web/Seguranca/Logout/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Logout;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\User\CurrentUser;

final readonly class Action
{
    public function __construct(
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $this->currentUser->logout();

        return $this->responseFactory
            ->createResponse(Status::FOUND)
            ->withHeader('Location', $this->urlGenerator->generate('seguranca/login'));
    }
}
```

## Páginas só para quem está logado

O `yiisoft/auth` traz o middleware `Authentication`. Ele pede a um "método de autenticação" que identifique o usuário da requisição. Se não houver usuário, o método decide a resposta. O `yiisoft/user` fornece o `WebAuth`, que identifica pelo `CurrentUser` (ou seja, pela sessão) e, para quem não está logado, responde com um redirecionamento para o `authUrl`.

No arquivo de rotas, as páginas que exigem login ficam num grupo com esse middleware:

```php
Group::create('/seguranca')
    ->middleware(CabecalhosDeSeguranca::class)
    ->routes(
        Route::methods([Method::GET, Method::POST], '/login')
            ->action(Web\Seguranca\Login\Action::class)
            ->name('seguranca/login'),

        // Daqui para baixo, só com login
        Group::create()
            ->middleware([
                'class' => Authentication::class,
                '__construct()' => ['authenticationMethod' => Reference::to(WebAuth::class)],
            ])
            ->routes(
                Route::get('/painel')
                    ->action(Web\Seguranca\Painel\Action::class)
                    ->name('seguranca/painel'),
                Route::post('/logout')
                    ->action(Web\Seguranca\Logout\Action::class)
                    ->name('seguranca/logout'),
                // ...
            ),
    ),
```

O middleware é passado como uma definição de array, com o `WebAuth` explícito no construtor. Sem isso, o `Authentication` usaria o que estiver registrado para `AuthenticationMethodInterface` no container, que um app com API pode ter apontado para outro método. O `CabecalhosDeSeguranca` do grupo de fora é do capítulo [Boas práticas](/seguranca/boas-praticas).

Repare que o middleware fica num grupo, e não na pilha global do app. Assim, só as rotas desse grupo exigem login, e as outras páginas do app continuam abertas.

## O painel

A action do painel lê o usuário logado do `CurrentUser`. Crie `src/Web/Seguranca/Painel/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Painel;

use App\Seguranca\Usuario;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private CurrentUser $currentUser,
        private ManagerInterface $rbac,
        private FlashInterface $flash,
    ) {}

    public function __invoke(): ResponseInterface
    {
        /** @var Usuario $usuario */
        $usuario = $this->currentUser->getIdentity();

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'usuario' => $usuario,
            'papeis' => array_keys($this->rbac->getRolesByUserId($usuario->id)),
            'currentUser' => $this->currentUser,
            'aviso' => $this->flash->get('aviso'),
        ]);
    }
}
```

Como a rota está protegida, o `getIdentity()` sempre devolve um `Usuario` aqui, e o comentário `@var` informa isso ao editor. Os papéis vêm do RBAC, e a mensagem de aviso é uma [mensagem flash](/requisicoes/sessoes-e-cookies) que a troca de senha deixa.

A view, em `src/Web/Seguranca/Painel/template.php`:

```php
<?php

declare(strict_types=1);

use App\Seguranca\Rbac\Permissao;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var App\Seguranca\Usuario $usuario
 * @var string[] $papeis
 * @var Yiisoft\User\CurrentUser $currentUser
 * @var string|null $aviso
 */

$this->setTitle('Painel');
?>

<h1>Olá, <?= Html::encode($usuario->nome) ?></h1>

<?php if ($aviso !== null): ?>
    <p><strong><?= Html::encode($aviso) ?></strong></p>
<?php endif ?>

<p>Papéis: <?= Html::encode(implode(', ', $papeis) ?: 'nenhum') ?></p>

<ul>
    <li>
        <a href="<?= $urlGenerator->generate('seguranca/perfil', ['id' => $usuario->id]) ?>">Editar meu perfil</a>
    </li>
    <li><a href="<?= $urlGenerator->generate('seguranca/senha') ?>">Trocar a senha</a></li>
    <li><a href="<?= $urlGenerator->generate('seguranca/convite/novo') ?>">Gerar um link de convite</a></li>
    <?php if ($currentUser->can(Permissao::VerRelatorios)): ?>
        <li><a href="<?= $urlGenerator->generate('seguranca/relatorios') ?>">Relatórios</a></li>
    <?php endif ?>
</ul>

<?= Html::form()->post($urlGenerator->generate('seguranca/logout'))->csrf($csrf)->open() ?>
    <?= Html::submitButton('Sair') ?>
<?= Html::form()->close() ?>
```

As páginas de perfil, senha, convite e relatórios são dos próximos capítulos.

## Testando

Abra http://127.0.0.1:8080/seguranca/painel sem estar logado. A resposta é um redirecionamento:

```plaintext
HTTP/1.1 302 Found
Location: /seguranca/login
```

Na página de login:

- Com `ana@exemplo.com` e uma senha errada, o formulário volta com "E-mail ou senha incorretos." e o campo de senha vazio.
- Com `ana@exemplo.com` e `senha-de-exemplo`, o app redireciona para o painel, que mostra "Olá, Ana". A resposta do login traz um `Set-Cookie` com um novo id de sessão, diferente do que o navegador tinha antes.
- Clicando em "Sair", o app volta para o login, e o painel volta a redirecionar.

## Duração da sessão e "lembrar de mim"

Por padrão, o login dura enquanto a sessão PHP durar. O `CurrentUser` tem dois métodos para limitar isso, a serem chamados na mesma definição de `config/web/di/seguranca.php`:

- `withAuthTimeout(segundos)`: desloga depois de um tempo sem requisições.
- `withAbsoluteAuthTimeout(segundos)`: desloga depois de um tempo desde o login, mesmo com o usuário ativo.

Para manter o usuário logado depois que o navegador fecha, o `yiisoft/user` tem o `CookieLoginMiddleware`. Ele exige que a classe de usuário implemente `CookieLoginIdentityInterface`, com uma chave aleatória por usuário guardada no banco. No login, o `CookieLogin::addCookie()` grava um cookie com o id do usuário e essa chave. Nas visitas seguintes, o middleware lê o cookie, confere a chave com `validateCookieLoginKey()` e faz o login. Trocar a chave no banco invalida todos os cookies daquele usuário.

## Tokens para API

Uma API não usa sessão nem formulário de login. O cliente envia um token em cada requisição, normalmente no header `Authorization: Bearer <token>`. As peças são as mesmas: o middleware `Authentication`, agora com o método `HttpBearer` do `yiisoft/auth`, e um repositório que implementa `IdentityWithTokenRepositoryInterface`, com o método `findIdentityByToken()`. O capítulo [Autenticação de APIs](/api/autenticacao) monta esse fluxo.

## Comparando com o Yii2

- O componente `Yii::$app->user` virou o serviço `CurrentUser`, injetado na action. O `IdentityInterface` do Yii2, que concentrava busca por id, por token e a chave de "lembrar de mim", foi dividido: a classe de usuário só tem `getId()`, e a busca fica no repositório.
- O `AccessControl` com `'roles' => ['@']` virou o middleware `Authentication` num grupo de rotas.
- O `loginUrl` virou o parâmetro `authUrl`.
