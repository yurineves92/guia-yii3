# Docker

O template `yiisoft/app` vem com uma configuração Docker pronta para desenvolvimento, testes e produção. Com ela, você não precisa instalar PHP, extensões nem servidor web na sua máquina: tudo roda em containers, com as mesmas versões para todo o time. Este capítulo percorre os arquivos que o template cria e os comandos do `Makefile` que os usam.

::: warning
O guia foi escrito num ambiente sem Docker, então os comandos abaixo não foram executados aqui. O que está descrito vem da leitura dos arquivos do template.
:::

## Os arquivos

```plaintext
docker/
  Dockerfile          # imagem com estágios: base, dev, prod-builder e prod
  Caddyfile           # configuração do servidor em produção
  compose.yml         # o que é comum a todos os ambientes
  .env                # variáveis do compose (não chegam ao app)
  dev/
    compose.yml       # ambiente de desenvolvimento
    Caddyfile
    .env              # variáveis do app em desenvolvimento
    override.env.example
  test/
    compose.yml       # ambiente de testes
    .env
  prod/
    compose.yml       # deploy em Docker Swarm
    .env
Makefile              # atalhos para os comandos do docker compose
.dockerignore
```

## A imagem: FrankenPHP

O `Dockerfile` parte da imagem `dunglas/frankenphp:1-php8.2-bookworm`. O [FrankenPHP](https://frankenphp.dev/) é um servidor de aplicação PHP construído sobre o Caddy: o mesmo processo serve os arquivos estáticos e executa o PHP, sem PHP-FPM nem Nginx à parte.

O estágio `base` instala as extensões que o Yii usa (`opcache`, `mbstring`, `intl`, `pdo` e outras). Os outros estágios partem dele:

- `dev`: adiciona o Xdebug e o Composer, cria um usuário com o mesmo UID e GID do seu usuário na máquina e copia o `docker/dev/Caddyfile`.
- `prod-builder`: copia o projeto e roda `composer install --no-dev --classmap-authoritative`.
- `prod`: define `APP_ENV=prod`, copia o `docker/Caddyfile` e o resultado do `prod-builder`, e roda como `www-data`.

O `Caddyfile` manda toda requisição que não é um arquivo existente para o `index.php`, com `/app/public` como raiz:

```plaintext
{$SERVER_NAME::80} {
    encode zstd br gzip
    php_server {
        try_files {path} index.php
        root /app/public
    }
}
```

Nessa configuração, o FrankenPHP roda em modo clássico: cada requisição executa o `public/index.php` do zero, como no PHP-FPM. O modo worker, em que a aplicação fica carregada na memória, aparece em [Servidores de longa duração](/topicos/longa-duracao).

## Variáveis de ambiente

Há dois tipos de arquivo `.env` em `docker/`, e eles não se misturam:

- `docker/.env` alimenta o `docker compose` e o `Makefile`: nome da stack (`STACK_NAME=app`), porta de desenvolvimento (`DEV_PORT=80`), domínio e endereço SSH de produção, nome e tag da imagem. Essas variáveis não chegam à aplicação.
- `docker/dev/.env`, `docker/test/.env` e `docker/prod/.env` são carregados dentro do container e chegam à aplicação. O de desenvolvimento define `APP_ENV=dev`, `APP_DEBUG=true` e `XDEBUG_MODE=develop`.

Cada ambiente aceita ainda um `override.env`, opcional e ignorado pelo Git, para ajustes da sua máquina. O exemplo do template define `APP_HOST_PATH`. Essa variável é usada pela configuração do tratamento de erros: os links "abrir no editor" da página de erro trocam o caminho `/app/` do container pelo caminho do projeto na sua máquina.

E o `.env` da raiz do projeto, usado com `./yii serve`? Dentro dos containers ele não é lido. O `src/bootstrap.php` só carrega esse arquivo quando a variável `APP_ENV` não está definida no ambiente e o `vlucas/phpdotenv` está instalado. Nos containers, `APP_ENV` sempre vem definida, e na imagem de produção o `phpdotenv` nem existe, porque é instalado com `--no-dev`.

## Desenvolvimento: make up, make shell

Os comandos do dia a dia estão no `Makefile`, que exige `make` (Linux, macOS ou WSL no Windows). Todos usam `docker compose -f docker/compose.yml -f docker/dev/compose.yml`.

```bash
make build    # constrói a imagem de desenvolvimento
make up       # sobe o ambiente em segundo plano
make down     # derruba os containers
make stop     # para sem remover
make clear    # remove containers e volumes
make help     # lista todos os alvos
```

Com o ambiente no ar, o app responde em http://localhost (a porta vem de `DEV_PORT`). O `docker/dev/compose.yml` monta a pasta do projeto em `/app`, então o que você edita na sua máquina vale na hora, sem reconstruir a imagem.

Para entrar no container:

```bash
make shell
```

Isso roda `docker compose exec app /bin/bash`, então o ambiente precisa estar de pé. Lá dentro, você usa `./yii`, `composer` e `vendor/bin/*` normalmente.

Para comandos isolados, há atalhos que criam um container temporário (`docker compose run --rm`), então funcionam mesmo com o ambiente parado:

```bash
make yii migrate:up
make yii pais:listar
make composer outdated
```

Tudo que vem depois do alvo é repassado ao comando. O `Makefile` tem um truque no início para que o `make` não trate `migrate:up` como outro alvo.

O `make build` passa o seu UID e GID para a imagem. Assim, os arquivos criados dentro do container (em `runtime/` ou `vendor/`) pertencem ao seu usuário, e não ao root.

### Xdebug

A imagem de desenvolvimento já tem o Xdebug, no modo `develop` (mensagens de erro melhoradas, sem depurador passo a passo). O `docker/compose.yml` mapeia `host.docker.internal` para a máquina hospedeira, que é o endereço que o Xdebug usa para chegar à sua IDE. Para depurar passo a passo, mude `XDEBUG_MODE` no `docker/dev/override.env`, por exemplo para `debug`, e suba o ambiente de novo.

## Testes e ferramentas

O ambiente de testes usa `docker/test/compose.yml`, com a mesma imagem de desenvolvimento e o `docker/test/.env`: `APP_ENV=test`, `APP_DEBUG=false`, `APP_C3=true` e `XDEBUG_MODE=coverage`.

```bash
make test             # vendor/bin/codecept run
make test-coverage    # com relatório de cobertura em HTML
make codecept run Unit
```

O `APP_C3=true` faz o `public/index.php` carregar o `c3.php`, que coleta a cobertura das requisições feitas pelos testes web. Os detalhes das suítes estão em [Testes: visão geral](/testes/visao-geral).

As ferramentas de [qualidade de código](/ferramentas/qualidade) também têm atalho: `make psalm`, `make rector`, `make cs-fix` e `make composer-dependency-analyser`.

## Produção

O template prepara o deploy em Docker Swarm:

```bash
make prod-build     # docker build --target prod
make prod-push      # envia a imagem para o registro
make prod-deploy    # docker stack deploy no servidor de PROD_SSH
```

O `docker/prod/compose.yml` define duas réplicas e atualização gradual: cada réplica nova sobe antes de a antiga cair (`order: start-first`), e se a atualização falhar o Swarm volta para a versão anterior (`failure_action: rollback`). A pasta `runtime/` fica num volume, para os logs sobreviverem a um novo deploy.

O serviço não publica portas. Ele entra na rede externa `caddy_public` e declara as labels `caddy` e `caddy.reverse_proxy`, que são lidas por um proxy Caddy rodando no mesmo Swarm (no formato usado pelo projeto caddy-docker-proxy). Esse proxy não faz parte do template: você precisa tê-lo no servidor, ou adaptar o compose para o proxy que já usa.

Antes do primeiro deploy, ajuste `PROD_HOST`, `PROD_SSH`, `IMAGE` e `IMAGE_TAG` em `docker/.env`, e coloque segredos em `docker/prod/override.env`, que não vai para o Git.

O `.dockerignore` deixa entrar na imagem só o necessário: `config/`, `public/`, `src/`, o `yii`, os arquivos do Composer, os `Caddyfile` e os `.env`. Testes, `runtime/` e `vendor/` ficam de fora. O `vendor/` de produção é criado dentro da imagem.

## Comparando com o Yii2

Os templates do Yii2 traziam um `docker-compose.yml` simples, baseado na imagem `yiisoftware/yii2-php`. O template do Yii3 usa FrankenPHP, separa desenvolvimento, testes e produção em arquivos próprios e inclui um caminho de deploy com Docker Swarm.
