# Desempenho

Antes de otimizar consultas ou adicionar cache, vale garantir que o básico está certo no servidor: o PHP não pode recompilar o código a cada requisição, o Composer não pode procurar classes no disco, e a aplicação não pode rodar com as verificações do modo de depuração. Este capítulo cobre esses ajustes e explica como a configuração do Yii3 é montada a cada requisição.

## O que acontece numa requisição

No modo clássico (PHP-FPM, Apache com mod_php ou FrankenPHP sem worker), cada requisição ao `public/index.php`:

1. Carrega o autoload do Composer.
2. Lê o merge plan (`config/.merge-plan.php`) e os arquivos de configuração que ele aponta, do app e dos pacotes.
3. Cria o container de DI com essas definições.
4. Passa a requisição pela pilha de middlewares e pela action.

Os passos 1 a 3 se repetem igualzinho em toda requisição. Os ajustes abaixo deixam esse trabalho barato. A alternativa é não repeti-lo, com um [servidor de longa duração](/topicos/longa-duracao).

## Opcache

O OPcache guarda na memória o bytecode dos arquivos PHP já compilados. Como a configuração do Yii3 é feita de arquivos PHP que devolvem arrays, ela também se beneficia: o merge plan, os `params.php` e as definições de DI são compilados uma vez e reaproveitados.

A imagem Docker do template instala a extensão `opcache`, mas não traz um `.ini` com ajustes para produção. Um ponto de partida comum:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
```

O ajuste que mais pesa é o `opcache.validate_timestamps=0`. Com ele, o PHP para de conferir se cada arquivo mudou no disco. Em troca, código novo só entra em vigor quando o PHP reinicia, então faça isso em todo deploy. Com Docker, isso já acontece, porque cada deploy sobe containers novos.

O `max_accelerated_files` precisa ser maior que o número de arquivos PHP do projeto, contando o `vendor/`. Para contar:

```bash
find . -name "*.php" | wc -l
```

No app de exemplo deste guia, com todos os pacotes de desenvolvimento, o resultado passa de 10 mil arquivos, que é o padrão do PHP 8.4 instalado aqui (`opcache.max_accelerated_files=10000`). Em produção, sem os pacotes de desenvolvimento, o número cai bastante.

No servidor embutido (`./yii serve`), o OPcache normalmente fica desligado, porque ele roda pela linha de comando e o padrão é `opcache.enable_cli=0`. Não tire conclusões de desempenho medindo ali.

## Preload

Desde o PHP 7.4, o OPcache pode pré-carregar arquivos quando o servidor sobe (`opcache.preload`). As classes pré-carregadas ficam disponíveis para todas as requisições, sem passar pelo autoload.

A configuração aponta para um script que compila os arquivos desejados:

```ini
opcache.preload=/app/preload.php
opcache.preload_user=www-data
```

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$arquivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/vendor/yiisoft/di/src'));

foreach ($arquivos as $arquivo) {
    if ($arquivo->getExtension() === 'php') {
        opcache_compile_file($arquivo->getPathname());
    }
}
```

Alguns cuidados:

- O Yii3 não traz um script de preload pronto. Você escolhe o que pré-carregar, e o ganho deve ser medido no seu caso. Comece pelos pacotes usados em toda requisição (DI, roteador, middlewares).
- Qualquer mudança nos arquivos pré-carregados exige reiniciar o servidor.
- O preload não funciona no Windows. O PHP recusa a opção com a mensagem `Preloading is not supported on Windows`.

O preload não pôde ser testado no ambiente em que o guia foi escrito (Windows), então o script acima é um ponto de partida, não uma receita verificada.

## Autoload otimizado

Sem otimização, o autoload do Composer transforma o nome da classe num caminho e confere se o arquivo existe. Em produção, gere um mapa de classes:

```bash
composer install --no-dev --classmap-authoritative
```

É exatamente o que o estágio `prod-builder` do `Dockerfile` do template faz. O `--classmap-authoritative` faz o Composer confiar só no mapa: se uma classe não está nele, ela não existe, sem procurar no disco. O `--no-dev` deixa de fora ferramentas como Codeception e Psalm.

Não use o `--classmap-authoritative` em desenvolvimento. Classes criadas depois de gerar o mapa não seriam encontradas.

## APP_DEBUG=false

O modo de depuração não muda só a página de erro. O `public/index.php` repassa o `APP_DEBUG` ao runner em dois parâmetros:

- `debug`: com `true`, o container valida todas as definições ao ser criado. Com `false`, essa validação é pulada.
- `checkEvents`: com `true`, o runner confere se todos os listeners de eventos configurados podem ser chamados.

As duas verificações ajudam a achar erros de configuração no desenvolvimento, mas custam tempo em toda requisição. Em produção, `APP_DEBUG` deve ser `false`, como no `docker/prod/.env` do template.

## Cache de configuração

Quem vem de outros frameworks costuma procurar um comando como `config:cache`. O Yii3 não tem esse passo. A parte cara de juntar a configuração, que é descobrir quais arquivos de quais pacotes entram em cada grupo e em que ordem, é feita pelo plugin do `yiisoft/config` durante o `composer install`, `update` e `dump-autoload`. O resultado é o merge plan, em `config/.merge-plan.php`.

A cada requisição, o runner só lê esse plano, inclui os arquivos (já em bytecode, graças ao OPcache) e faz o merge dos arrays. Os padrões com `*`, como `common/di/*.php`, ficam guardados no plano como padrão e são resolvidos nesse momento. Por isso um arquivo novo em `config/common/di/` passa a valer sem nenhum comando.

Se você mudar o `config/configuration.php` (criar um grupo, apontar para outra pasta), atualize o plano:

```bash
composer yii-config-rebuild
```

O comando não imprime nada quando dá certo. O [capítulo de Configuração](/conceitos/configuracao) detalha os grupos e o merge plan.

## Outras fontes de lentidão

Depois dos ajustes acima, o tempo de uma requisição costuma estar no seu código: consultas ao banco, chamadas HTTP, renderização. Algumas pistas:

- O schema do banco é lido e guardado em cache pelo `yiisoft/db`. No template, esse cache é um `ArrayCache` (em `config/common/di/db.php`), que vive só durante uma requisição. Em produção, um cache persistente evita reler o schema a cada requisição. Veja [Cache](/cache/visao-geral).
- Dados caros de calcular podem ir para o [cache de dados](/cache/cache-de-dados), e páginas que mudam pouco podem usar [cache HTTP](/cache/cache-http).
- Um servidor de longa duração elimina o custo de inicialização por requisição, com os cuidados descritos em [Servidores de longa duração](/topicos/longa-duracao).

## Comparando com o Yii2

- No Yii2, a configuração era um array montado à mão no `web.php`, e o custo de juntar arquivos de pacotes não existia. No Yii3, o merge plan gerado pelo Composer resolve isso uma vez.
- O `YII_DEBUG` do Yii2 corresponde ao `APP_DEBUG`, e continua valendo a regra de desligar em produção.
- Opcache, preload e autoload otimizado funcionam do mesmo jeito nos dois.
