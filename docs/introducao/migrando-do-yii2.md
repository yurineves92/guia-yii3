# Migrando do Yii2

Não existe migração automática do Yii2 para o Yii3. Os dois compartilham o nome, a filosofia de ser rápido e explícito e boa parte da comunidade, mas a base é outra. Um projeto Yii2 grande normalmente é reescrito aos poucos, módulo por módulo, ou continua no Yii2, que segue recebendo correções.

Esta página serve como mapa: para cada coisa que você conhece do Yii2, onde ela está no Yii3.

## O que mudou na base

### Sem `Yii::$app`

No Yii2, os serviços ficavam num service locator global e eram acessados de qualquer lugar:

```php
// Yii2
$posts = Yii::$app->db->createCommand('SELECT * FROM post')->queryAll();
```

No Yii3, a classe declara o que precisa no construtor, e o container de DI entrega:

```php
// Yii3
final readonly class PostRepository
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function findAll(): array
    {
        return $this->db->select()->from('post')->all();
    }
}
```

### Configuração

O `config/web.php` com o array `components` deu lugar a vários arquivos pequenos em `config/`, montados pelo `yiisoft/config`. Cada pacote instalado também traz a própria configuração. O capítulo [Configuração](/conceitos/configuracao) explica como isso funciona.

### Controllers não estendem nada

No Yii2, todo controller estendia `yii\web\Controller`. No Yii3, uma action pode ser qualquer classe com um método público. O template usa uma classe por action, com `__invoke()`, mas controllers com vários métodos também funcionam. Ela recebe os serviços pelo construtor e retorna uma resposta PSR-7.

### Filtros e behaviors viraram middleware

`AccessControl`, `VerbFilter`, CORS e afins eram filtros ou behaviors no Yii2. No Yii3, tudo isso é middleware PSR-15, aplicado na pilha global ou num grupo de rotas.

### Imutabilidade

Muitos objetos do Yii3 são imutáveis. Em vez de alterar uma propriedade, você chama um método `with*()`, que devolve uma cópia alterada:

```php
$response = $response->withHeader('X-Total', '42');
```

## Onde está cada coisa

| Yii2 | Yii3 |
|---|---|
| `Yii::$app->db` | `ConnectionInterface` no construtor (`yiisoft/db`) |
| `Yii::$app->request` | `ServerRequestInterface` (PSR-7) |
| `Yii::$app->session` | `SessionInterface` (`yiisoft/session`) |
| `Yii::$app->user` | `CurrentUser` (`yiisoft/user`) |
| `Yii::$app->cache` | `CacheInterface` (`yiisoft/cache`, PSR-16) |
| `Yii::$app->authManager` | `yiisoft/rbac` |
| `Yii::$app->mailer` | `yiisoft/mailer` |
| `Yii::t()` | `yiisoft/translator` |
| `Yii::getAlias()` | `Aliases` (`yiisoft/aliases`) |
| `UrlManager` e regras de URL | `yiisoft/router`, com rotas em `config/common/routes.php` |
| `ActiveRecord` | `yiisoft/active-record`, pacote separado e opcional |
| `Model` e `rules()` | `FormModel` com regras em atributos (`yiisoft/form-model`) |
| `ActiveForm` | `Field::text()`, `Field::email()`… (`yiisoft/form-model`) |
| `GridView`, `ListView` | `yiisoft/yii-dataview` |
| `ActiveDataProvider` | `yiisoft/data` e `yiisoft/data-db` |
| Filtros e behaviors | Middleware PSR-15 |
| Eventos (`on()`, `trigger()`) | `yiisoft/event-dispatcher` (PSR-14) |
| `yii migrate` | `./yii migrate:up` (`yiisoft/db-migration`) |
| Gii e Debug Toolbar | `yiisoft/yii-gii` e `yiisoft/yii-debug`, ainda sem versão estável |

## Por onde começar

Se você conhece o Yii2, os capítulos de [Primeiros passos](/primeiros-passos/o-que-voce-precisa-saber) vão parecer familiares, porque seguem os mesmos exemplos do guia antigo: dizer "Olá", um formulário e uma lista de países vinda do banco. A diferença está no caminho, não no destino.
