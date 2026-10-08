# Autorização

Depois de saber quem é o usuário ([Autenticação](/seguranca/autenticacao)), falta decidir o que ele pode fazer. Este capítulo usa o RBAC (controle de acesso baseado em papéis) do pacote `yiisoft/rbac` para dar a cada usuário um papel, ligar permissões aos papéis e conferir essas permissões nas rotas, nas actions e nas views. O exemplo continua o da autenticação: só administradores veem os relatórios, e cada um só edita o próprio perfil, a não ser o administrador, que edita qualquer um.

## Como o RBAC funciona

O RBAC tem quatro ingredientes:

- Permissão: uma ação que pode ser liberada, como `relatorio.ver`.
- Papel: um conjunto de permissões, como `admin` ou `membro`.
- Hierarquia: papéis e permissões podem ter filhos. Um papel com o filho `membro` tem tudo o que `membro` tem.
- Atribuição: a ligação entre um usuário e um papel.

Opcionalmente, uma permissão ou papel pode ter uma regra: uma classe que decide, na hora da verificação, se o item vale para aquele caso. É o que permite dizer "pode editar o perfil, desde que seja o próprio".

O `yiisoft/rbac` traz o `Manager`, que monta a hierarquia e responde às perguntas, e as interfaces dos armazenamentos: `ItemsStorageInterface` (papéis, permissões e hierarquia) e `AssignmentsStorageInterface` (atribuições). Quem implementa os armazenamentos é outro pacote. Aqui usamos o `yiisoft/rbac-php`, que guarda tudo em arquivos PHP. Para guardar no banco, existem o `yiisoft/rbac-db` e o `yiisoft/rbac-cycle-db`, que não estão instalados no app de exemplo.

## Configurando

As definições ficam em `config/common/di/seguranca.php`, o mesmo arquivo do repositório de usuários (trecho):

```php
use Yiisoft\Access\AccessCheckerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Definitions\Reference;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Php\AssignmentsStorage;
use Yiisoft\Rbac\Php\ItemsStorage;

return [
    // RBAC: papéis e permissões num arquivo PHP, atribuições em outro
    ItemsStorageInterface::class => static fn(Aliases $aliases) => new ItemsStorage(
        $aliases->get('@runtime/rbac/itens.php'),
    ),
    AssignmentsStorageInterface::class => static fn(Aliases $aliases) => new AssignmentsStorage(
        $aliases->get('@runtime/rbac/atribuicoes.php'),
    ),

    // Quem responde "este usuário tem esta permissão?" é o Manager do RBAC
    AccessCheckerInterface::class => Reference::to(ManagerInterface::class),
];
```

A definição `ManagerInterface => Manager` já vem na configuração do `yiisoft/rbac`. A última linha liga o RBAC ao `CurrentUser`: o `withAccessChecker()` configurado no capítulo anterior recebe o `AccessCheckerInterface`, que aqui é o `Manager`. Sem isso, o `CurrentUser::can()` sempre devolve `false`.

Os arquivos ficam em `runtime/`, que normalmente não vai para o controle de versão, e são recriados por um comando de console, mostrado adiante. Se preferir versionar a hierarquia, aponte o `ItemsStorage` para uma pasta do projeto e deixe só as atribuições em `runtime/`, ou no banco.

## Nomes de permissão num enum

Para não espalhar strings como `'relatorio.ver'` pelo código, as permissões ficam num enum. Crie `src/Seguranca/Rbac/Permissao.php`:

```php
<?php

declare(strict_types=1);

namespace App\Seguranca\Rbac;

enum Permissao: string
{
    case VerRelatorios = 'relatorio.ver';
    case EditarPerfil = 'perfil.editar';
    case EditarProprioPerfil = 'perfil.editar-proprio';
}
```

O `CurrentUser::can()` aceita tanto a string quanto um enum com valor string, então `can(Permissao::VerRelatorios)` funciona direto.

## A regra

Uma regra implementa `Yiisoft\Rbac\RuleInterface`. O método `execute()` recebe o id do usuário, o item ao qual a regra está ligada e um `RuleContext`, com os parâmetros passados na verificação. Crie `src/Seguranca/Rbac/ProprioPerfilRule.php`:

```php
<?php

declare(strict_types=1);

namespace App\Seguranca\Rbac;

use Yiisoft\Rbac\Item;
use Yiisoft\Rbac\RuleContext;
use Yiisoft\Rbac\RuleInterface;

/**
 * Libera a permissão só quando o perfil sendo editado é o do próprio usuário.
 */
final class ProprioPerfilRule implements RuleInterface
{
    public function execute(?string $userId, Item $item, RuleContext $context): bool
    {
        return $userId !== null
            && $userId === (string) $context->getParameterValue('usuarioId');
    }
}
```

O `Manager` cria a regra pelo nome da classe, com `new`, usando a `SimpleRuleFactory`. Para regras com dependências no construtor, existe o pacote `yiisoft/rbac-rules-container`, que cria as regras pelo container (não instalado aqui).

## Montando a hierarquia

A hierarquia é criada com os métodos do `Manager`: `addPermission()`, `addRole()`, `addChild()` e `assign()`. O lugar natural para isso é um comando de console, rodado na instalação e sempre que a hierarquia mudar. Crie `src/Seguranca/Console/RbacCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Seguranca\Console;

use App\Seguranca\Rbac\Permissao;
use App\Seguranca\Rbac\ProprioPerfilRule;
use App\Seguranca\UsuarioRepositorio;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Yii\Console\ExitCode;

#[AsCommand(
    name: 'seguranca:rbac',
    description: 'Recria os papéis e as permissões de exemplo',
)]
final class RbacCommand extends Command
{
    private const PAPEIS_DOS_USUARIOS = [
        'ana@exemplo.com' => 'admin',
        'bruno@exemplo.com' => 'membro',
        'carla@exemplo.com' => 'membro',
    ];

    public function __construct(
        private readonly ManagerInterface $manager,
        private readonly ItemsStorageInterface $itens,
        private readonly AssignmentsStorageInterface $atribuicoes,
        private readonly UsuarioRepositorio $usuarios,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Começa do zero, para o comando poder rodar mais de uma vez
        $this->atribuicoes->clear();
        $this->itens->clear();

        $this->manager
            ->addPermission(
                (new Permission(Permissao::VerRelatorios->value))->withDescription('Ver os relatórios'),
            )
            ->addPermission(
                (new Permission(Permissao::EditarPerfil->value))->withDescription('Editar qualquer perfil'),
            )
            ->addPermission(
                (new Permission(Permissao::EditarProprioPerfil->value))
                    ->withDescription('Editar o próprio perfil')
                    ->withRuleName(ProprioPerfilRule::class),
            )
            ->addRole((new Role('membro'))->withDescription('Usuário comum'))
            ->addRole((new Role('admin'))->withDescription('Administrador'))
            // Quem pode editar o próprio perfil pode "editar perfil", desde que a regra passe
            ->addChild(Permissao::EditarProprioPerfil->value, Permissao::EditarPerfil->value)
            ->addChild('membro', Permissao::EditarProprioPerfil->value)
            // O admin tem tudo o que o membro tem, e mais
            ->addChild('admin', 'membro')
            ->addChild('admin', Permissao::EditarPerfil->value)
            ->addChild('admin', Permissao::VerRelatorios->value);

        foreach (self::PAPEIS_DOS_USUARIOS as $email => $papel) {
            $usuario = $this->usuarios->porEmail($email);
            if ($usuario !== null) {
                $this->manager->assign($papel, $usuario->id);
                $output->writeln("$email: $papel");
            }
        }

        return ExitCode::OK;
    }
}
```

A parte mais importante é a das duas permissões de perfil. O código das actions sempre pergunta por `perfil.editar`. O `admin` tem essa permissão direto. O `membro` só chega a ela passando por `perfil.editar-proprio`, que tem a regra. Na verificação, o `Manager` percorre os caminhos da hierarquia entre os papéis do usuário e a permissão pedida, e só aceita um caminho se todas as regras dele passarem. Para o admin, existe um caminho sem regra. Para o membro, o único caminho exige que o perfil seja o dele.

O comando é registrado na lista de comandos do console. No app de exemplo, isso é feito nos parâmetros da seção, em `config/common/params/seguranca.php` (trecho):

```php
'yiisoft/yii-console' => [
    'commands' => [
        'seguranca:rbac' => RbacCommand::class,
    ],
],
```

Rodando:

```bash
./yii seguranca:rbac
```

```plaintext
ana@exemplo.com: admin
bruno@exemplo.com: membro
carla@exemplo.com: membro
```

O `runtime/rbac/itens.php` gerado tem um registro por item, com os filhos. O do papel `admin`, por exemplo:

```php
[
    'name' => 'admin',
    'description' => 'Administrador',
    'type' => 'role',
    'updated_at' => 1791436102,
    'created_at' => 1791436102,
    'children' => [
        'membro',
        'perfil.editar',
        'relatorio.ver',
    ],
],
```

## Protegendo rotas com um middleware

Para uma permissão que não depende de parâmetros, a verificação pode ficar na rota. O app tem um middleware próprio para isso. Crie `src/Web/Seguranca/Shared/ExigePermissao.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Shared;

use App\Seguranca\Rbac\Permissao;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\User\CurrentUser;

/**
 * Deixa a requisição passar só se o usuário atual tem a permissão.
 *
 * Na rota: ->middleware(ExigePermissao::para(Permissao::VerRelatorios))
 */
final class ExigePermissao implements MiddlewareInterface
{
    private ?Permissao $permissao = null;

    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly Proibido $proibido,
    ) {}

    /**
     * Definição do middleware para usar na rota.
     */
    public static function para(Permissao $permissao): array
    {
        return [
            'class' => self::class,
            'withPermissao()' => [$permissao],
        ];
    }

    public function withPermissao(Permissao $permissao): self
    {
        $new = clone $this;
        $new->permissao = $permissao;
        return $new;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->permissao === null) {
            throw new LogicException('Informe a permissão com ExigePermissao::para().');
        }

        if (!$this->currentUser->can($this->permissao)) {
            return $this->proibido->handle($request);
        }

        return $handler->handle($request);
    }
}
```

O método estático `para()` devolve uma definição de array: a classe e um método a chamar depois de criá-la. O router aceita essa forma em `->middleware()`, e o container cria o middleware com as dependências do construtor e chama `withPermissao()`.

A resposta de acesso negado fica numa classe separada, porque as actions também vão usá-la. Crie `src/Web/Seguranca/Shared/Proibido.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Status;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Responde 403 com uma página de "acesso negado".
 */
final readonly class Proibido implements RequestHandlerInterface
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->viewRenderer
            ->render(__DIR__ . '/acesso-negado')
            ->withStatus(Status::FORBIDDEN);
    }
}
```

A view `acesso-negado.php` é só um título e um link de volta ao painel.

No arquivo de rotas, dentro do grupo que exige login:

```php
Route::get('/relatorios')
    ->middleware(ExigePermissao::para(Permissao::VerRelatorios))
    ->action(Web\Seguranca\Relatorios\Action::class)
    ->name('seguranca/relatorios'),
```

A ordem importa: o grupo de fora aplica o `Authentication`, então quando o `ExigePermissao` roda, o usuário já está logado. Um visitante sem login é mandado para a página de login, e um usuário logado sem a permissão recebe 403.

## Verificando dentro da action

Quando a permissão depende do registro, a verificação fica na action, que conhece o registro. A edição de perfil passa o id do perfil para a regra. Crie `src/Web/Seguranca/Perfil/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Perfil;

use App\Seguranca\Rbac\Permissao;
use App\Seguranca\UsuarioRepositorio;
use App\Web\NotFound\NotFoundHandler;
use App\Web\Seguranca\Shared\Proibido;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Http\Status;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private FormHydrator $formHydrator,
        private CurrentUser $currentUser,
        private UsuarioRepositorio $usuarios,
        private NotFoundHandler $notFoundHandler,
        private Proibido $proibido,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(ServerRequestInterface $request, #[RouteArgument('id')] int $id): ResponseInterface
    {
        $usuario = $this->usuarios->findIdentity((string) $id);
        if ($usuario === null) {
            return $this->notFoundHandler->handle($request);
        }

        // A regra do RBAC recebe o id do perfil para comparar com o usuário logado
        if (!$this->currentUser->can(Permissao::EditarPerfil, ['usuarioId' => $usuario->id])) {
            return $this->proibido->handle($request);
        }

        $form = new PerfilForm();
        $form->nome = $usuario->nome;

        if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
            $this->usuarios->alterarNome($usuario->id, $form->nome);

            return $this->responseFactory
                ->createResponse(Status::FOUND)
                ->withHeader('Location', $this->urlGenerator->generate('seguranca/painel'));
        }

        return $this->viewRenderer->render(__DIR__ . '/formulario', [
            'form' => $form,
            'usuario' => $usuario,
        ]);
    }
}
```

O segundo argumento de `can()` é o array de parâmetros que chega à regra pelo `RuleContext`. O `PerfilForm` tem só a propriedade `nome`, com `Required` e `Length`, e a view mostra esse campo. A rota:

```php
Route::methods([Method::GET, Method::POST], '/perfil/{id:\d+}')
    ->action(Web\Seguranca\Perfil\Action::class)
    ->name('seguranca/perfil'),
```

A verificação acontece antes de qualquer leitura do POST, então um usuário sem permissão não consegue alterar nada, nem enviando o formulário direto.

## Verificando na view

Esconder um link não protege nada, mas evita mostrar opções que o usuário não pode usar. O painel do capítulo anterior já faz isso:

```php
<?php if ($currentUser->can(Permissao::VerRelatorios)): ?>
    <li><a href="<?= $urlGenerator->generate('seguranca/relatorios') ?>">Relatórios</a></li>
<?php endif ?>
```

A proteção de verdade continua sendo o middleware da rota.

## Testando

Rode `./yii seguranca:rbac` antes, para criar os arquivos do RBAC. Depois, entre como cada usuário (senha `senha-de-exemplo`) e abra as páginas:

| Página | Ana (admin) | Bruno (membro, id 2) |
|---|---|---|
| `/seguranca/relatorios` | 200 | 403 |
| `/seguranca/perfil/2` | 200 | 200 |
| `/seguranca/perfil/1` | 200 | 403 |
| `/seguranca/perfil/99` | 404 | 404 |

No painel, o link "Relatórios" só aparece para a Ana, e a linha de papéis mostra "admin, membro" para ela, porque o `getRolesByUserId()` inclui os papéis filhos.

## Papéis padrão e visitantes

Dois recursos do `Manager` que este exemplo não usa, mas que aparecem com frequência:

- `setDefaultRoleNames(['membro'])`: papéis atribuídos a todo usuário, sem precisar de `assign()`.
- `setGuestRoleName('visitante')`: um papel para quem não está logado. Sem ele, qualquer verificação com usuário `null` devolve `false`.

Os dois são chamados na definição do `ManagerInterface` no container.

## Comparando com o Yii2

- O componente `authManager` virou o serviço `ManagerInterface`. O `PhpManager` e o `DbManager` viraram armazenamentos separados, em pacotes próprios.
- O `Yii::$app->user->can()` virou `CurrentUser::can()`, com a mesma ideia de parâmetros para as regras.
- As regras deixaram de estender `yii\rbac\Rule` com um `$name`. Agora são classes que implementam `RuleInterface`, referenciadas pelo nome da classe.
- O `AccessControl` com `'roles' => ['admin']` virou um middleware na rota. Os pacotes usados aqui não trazem um middleware de RBAC pronto, mas ele tem poucas linhas, como o `ExigePermissao` acima.
