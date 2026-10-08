# Boas práticas

Os capítulos anteriores trataram de mecanismos: CSRF, login, permissões, senhas e criptografia. Este reúne os cuidados que não pertencem a nenhum deles, mas que fazem diferença quando o app vai para produção: headers HTTP que ativam proteções do navegador, o que não deve aparecer em logs e uma lista para conferir antes de publicar.

## Headers de segurança

Alguns headers de resposta ligam proteções que o navegador já tem, mas só usa se o site pedir. O app de exemplo os adiciona com um middleware. Crie `src/Web/Seguranca/Shared/CabecalhosDeSeguranca.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Acrescenta headers de segurança em todas as respostas que passam por ele.
 */
final class CabecalhosDeSeguranca implements MiddlewareInterface
{
    private const HEADERS = [
        // Não deixa o navegador "adivinhar" o tipo do conteúdo
        'X-Content-Type-Options' => 'nosniff',
        // Não deixa outros sites abrirem as páginas dentro de um <iframe>
        'X-Frame-Options' => 'DENY',
        // Ao seguir um link para outro site, envia só a origem (protocolo e domínio) como Referer
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        // De onde a página pode carregar scripts, estilos, imagens...
        'Content-Security-Policy' => "default-src 'self'; style-src 'self' 'unsafe-inline'; "
            . "frame-ancestors 'none'; form-action 'self'; base-uri 'self'",
        // Recursos do navegador que a página não usa
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        foreach (self::HEADERS as $nome => $valor) {
            if (!$response->hasHeader($nome)) {
                $response = $response->withHeader($nome, $valor);
            }
        }

        return $response;
    }
}
```

O middleware chama o próximo da fila primeiro e acrescenta os headers na resposta que volta. Se uma action já definiu um desses headers, o valor dela é mantido.

O que cada header faz:

- `X-Content-Type-Options: nosniff`: o navegador respeita o `Content-Type` da resposta. Sem ele, um arquivo enviado por um usuário e servido como texto poderia ser interpretado como HTML ou script.
- `X-Frame-Options: DENY` e o `frame-ancestors 'none'` da CSP: impedem que outro site carregue as suas páginas num `<iframe>` invisível e induza o usuário a clicar onde não queria (*clickjacking*).
- `Referrer-Policy`: limita o que vai no header `Referer` quando o usuário segue um link para fora: só a origem, sem o caminho e a query string. URLs com tokens, como o link de convite do capítulo [Criptografia](/seguranca/criptografia), não vazam para outros sites.
- `Content-Security-Policy`: diz de onde a página pode carregar recursos. Com `default-src 'self'`, scripts, imagens e folhas de estilo só vêm do próprio domínio, e scripts escritos dentro do HTML não rodam. É a segunda barreira contra XSS, depois da codificação na view.
- `Permissions-Policy`: desliga recursos do navegador que o app não usa.

O `style-src 'self' 'unsafe-inline'` existe porque o layout do template tem estilos dentro do HTML (no SVG do logotipo) e porque os exemplos de formulário usam `registerCss()`. Para scripts, a política não abre essa exceção. Ao montar a sua política, comece restritivo e libere só o que o app precisa: uma CSP errada quebra a página sem aviso, a não ser no console do navegador.

No app de exemplo, o middleware fica no grupo de rotas `/seguranca` (veja o arquivo de rotas no capítulo [Autenticação](/seguranca/autenticacao)), para não alterar as páginas das outras seções. Num app seu, o lugar dele é a pilha global de middlewares, em `config/web/di/application.php`, para valer em todas as respostas, inclusive as páginas de erro.

Conferindo (trecho da resposta):

```bash
curl -I http://127.0.0.1:8080/seguranca/busca
```

```plaintext
HTTP/1.1 200 OK
Content-Type: text/html; charset=UTF-8
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'
Permissions-Policy: camera=(), microphone=(), geolocation=()
Set-Cookie: PHPSESSID=4v1vikmkiofdgkfkddm3f15h5k; Domain=127.0.0.1; Path=/; HttpOnly; SameSite=Lax
```

### HTTPS e HSTS

Em produção, o app deve funcionar só com HTTPS. O pacote `yiisoft/yii-middleware` traz o `ForceSecureConnection`, que redireciona requisições HTTP para HTTPS e adiciona o header `Strict-Transport-Security` (HSTS), que faz o navegador usar HTTPS direto nas próximas visitas. Ele também adiciona uma CSP própria por padrão (`upgrade-insecure-requests; default-src https:`). Se você já define a sua CSP, desligue a dele com `withoutCSP()`:

```php
use Yiisoft\Yii\Middleware\ForceSecureConnection;

[
    'class' => ForceSecureConnection::class,
    'withoutCSP()' => [],
]
```

Essa definição vai no início da pilha global. Ela não foi testada no app de exemplo, que roda só em HTTP local. Se o HTTPS termina num proxy ou balanceador, o app recebe as requisições em HTTP, e o redirecionamento entraria em loop. Nesse caso, faça o redirecionamento e o HSTS no próprio proxy.

## Dados sensíveis em logs

Logs costumam ser copiados para outros sistemas e lidos por mais gente do que o banco de dados. Uma senha ou um token num log é uma senha vazada.

O que vai para o log com o `yiisoft/log` é a mensagem e o contexto que você passa. Diferente do Yii2, que por padrão anexava `$_POST`, `$_COOKIE` e `$_SESSION` às mensagens (a opção `logVars`), os targets do Yii3 não acrescentam variáveis da requisição. Em compensação, todo o contexto vai para o arquivo. Com:

```php
$this->logger->warning('Falha de login', ['email' => $email, 'senha' => $senha]);
```

o arquivo `runtime/logs/app.log` recebe:

```plaintext
2026-10-08 05:21:15.497800 [warning][application] Falha de login

Message context:

...
email: 'ana@exemplo.com'
senha: 'senha-de-exemplo'
```

As regras práticas:

- Nunca coloque no contexto senhas, tokens, chaves, números de cartão ou o corpo inteiro de uma requisição.
- Prefira identificar o usuário pelo id, e não pelo e-mail ou pelo nome: `['usuarioId' => 1]`.
- Ao logar uma requisição de API, remova o header `Authorization`.

Os stack traces são outro caminho de vazamento: por padrão, o PHP mostra os argumentos das funções em `getTraceAsString()`. O atributo `#[SensitiveParameter]`, do PHP 8.2, troca o valor por um objeto `SensitiveParameterValue` no trace. Com duas funções iguais, uma com o atributo e outra sem, o trace de uma exceção fica assim:

```plaintext
#0 sens.php(4): semAtributo('ana@exemplo.com', 'senha-de-exempl...')
#0 sens.php(5): comAtributo('ana@exemplo.com', Object(SensitiveParameterValue))
```

O `PasswordHasher`, o `Crypt` e o `Mac` já marcam senhas e chaves com esse atributo. Faça o mesmo nos seus métodos que recebem segredos, como o `TokenAssinado` e o `Cofre` do capítulo [Criptografia](/seguranca/criptografia). Em produção, a diretiva `zend.exception_ignore_args = On` do `php.ini` (o valor do `php.ini-production`) tira todos os argumentos dos traces.

O capítulo [Logs](/requisicoes/logs) mostra como configurar os targets e as categorias.

## Checklist antes de publicar

Ambiente e configuração:

- `APP_ENV=prod` e `APP_DEBUG=false`. Com o debug ligado, a página de erro mostra código-fonte e o stack trace.
- A raiz do servidor web aponta para `public/`, e não para a raiz do projeto. Assim, `config/`, `runtime/`, `.env` e `vendor/` não ficam acessíveis pela URL.
- Segredos (chave do app, senha do banco, chaves de APIs externas) vêm de variáveis de ambiente, e não de arquivos do repositório.
- `composer install --no-dev --optimize-autoloader` no servidor, sem os pacotes de desenvolvimento.
- No `php.ini`: `display_errors = Off`, `expose_php = Off` e `zend.exception_ignore_args = On`.

HTTPS e sessão:

- HTTPS em todo o site, com HSTS.
- Cookie de sessão com `Secure` (`'cookie_secure' => 1` nos parâmetros do `yiisoft/session`, como em [Visão geral](/seguranca/visao-geral)).
- Headers de segurança na pilha global.

Código:

- Todo formulário com `->csrf($csrf)`, e toda ação que altera dados aceita só POST (ou PUT, PATCH, DELETE).
- Toda saída de dado do usuário passa por `Html::encode()`, `Html::encodeAttribute()` ou pelos helpers de tag.
- Nenhum SQL montado com concatenação de valores do usuário.
- Toda rota que altera ou mostra dados privados tem verificação de login e de permissão, e as verificações que dependem do registro estão na action.
- Uploads com tipo conferido pelo conteúdo, nome gerado pelo app e pasta sem execução de PHP ([Upload de arquivos](/formularios/upload)).
- Senhas com `PasswordHasher`, nunca em logs, e com política de tamanho mínimo.

Operação:

- `composer audit` para conferir se alguma dependência tem vulnerabilidade conhecida, e atualizações periódicas dos pacotes.
- Limite de tentativas no login e em outros pontos sensíveis. O pacote `yiisoft/rate-limiter`, instalado no app, traz o `LimitRequestsMiddleware`, que conta requisições por IP (política `LimitPerIp`) num armazenamento de cache e responde 429 quando o limite estoura.
- Backups do banco e dos arquivos enviados, guardados com o mesmo cuidado que o banco.
- Logs sem dados sensíveis e com retenção definida.

## Comparando com o Yii2

- O Yii2 não adicionava headers de segurança por padrão, e o Yii3 também não. A diferença é que no Yii3 um middleware PSR-15 de poucas linhas resolve, sem precisar de eventos `beforeSend` na resposta.
- O `logVars` do Yii2 não tem equivalente nos targets do `yiisoft/log`, o que tira um caminho comum de vazamento de senhas para os logs.
- O `YII_DEBUG` e o `YII_ENV` viraram as variáveis `APP_DEBUG` e `APP_ENV` do template.
