# Visão geral

Um app Yii3 não tem uma classe central que sabe tudo. Ele é montado a partir de pacotes independentes, ligados pela configuração e pelo container de DI. Isso dá liberdade, mas também deixa a dúvida: onde cada coisa fica e por onde uma requisição passa? Este capítulo responde a isso e serve de mapa para os outros capítulos da seção.

## As camadas de um app Yii3

Uma requisição HTTP atravessa estas camadas, de fora para dentro:

```plaintext
public/index.php                  script de entrada
  HttpApplicationRunner           lê a configuração e monta o container
    Application                   recebe a requisição e devolve a resposta
      middlewares globais         erros, sessão, CSRF...
        Router                    escolhe a rota
          middlewares da rota     só para aquela rota ou grupo
            action                traduz HTTP em chamadas ao domínio
              domínio             entidades, serviços, repositórios
```

A resposta faz o caminho inverso: a action devolve um `ResponseInterface`, cada middleware pode alterá-lo na volta, e o runner envia o resultado para o navegador.

Cada camada tem o seu capítulo:

- [Script de entrada](/estrutura/script-de-entrada): o `public/index.php`, o `./yii` e os runners.
- [Aplicação](/estrutura/aplicacao): a classe `Application`, o handler de 404 e os parâmetros.
- [Middleware](/estrutura/middleware): a pilha global e a pilha de cada rota.
- [Actions e controllers](/estrutura/actions-e-controllers): o código que responde a uma rota.
- [Views](/estrutura/views), [Widgets](/estrutura/widgets) e [Assets](/estrutura/assets): a parte visual.
- [Domínio: entidades e serviços](/estrutura/dominio): a regra de negócio, separada do HTTP.

O roteamento em si fica em [Roteamento e URLs](/requisicoes/roteamento), e o container que liga tudo em [Container de DI](/conceitos/container-di).

O console segue o mesmo desenho. O `./yii` cria um `ConsoleApplicationRunner`, que monta o container com a configuração de console e executa um comando em vez de uma action.

## O que fica em src/, config/ e public/

A raiz do template `yiisoft/app` tem esta cara (sem os arquivos de ferramentas, como `psalm.xml` e `rector.php`):

```plaintext
assets/          arquivos-fonte de CSS e JS (publicados em public/assets)
config/          toda a configuração
docker/          arquivos do Docker do template
public/          a única pasta exposta pelo servidor web
runtime/         arquivos gerados: logs, cache, banco SQLite
src/             o código da aplicação (namespace App\)
tests/           as suítes do Codeception
vendor/          dependências do Composer
.env             variáveis de ambiente locais
yii              script de entrada do console
```

### public/

Só o que o navegador pode acessar diretamente: o `index.php`, o `favicon`, o `robots.txt` e a pasta `assets/`, onde os bundles de CSS e JS são publicados. O código PHP da aplicação nunca fica aqui. Configure o servidor web para usar `public/` como raiz do site.

### config/

A configuração é dividida por grupo e por ambiente:

```plaintext
config/
  common/          vale para web e console
    di/            definições do container
    params.php     parâmetros
    routes.php     rotas
    aliases.php
    application.php
    bootstrap.php
  web/             só para a aplicação web
    di/
    params.php
  console/         só para o console
    commands.php
    params.php
  environments/    sobrescritas por ambiente (dev, prod, test)
  configuration.php
  .merge-plan.php
```

O `configuration.php` diz quais arquivos formam cada grupo (`params`, `di`, `routes`...). O `.merge-plan.php` é gerado pelo Composer e junta a configuração dos pacotes com a da aplicação. O capítulo [Configuração](/conceitos/configuracao) explica esse mecanismo.

Uma consequência prática da divisão: uma definição em `config/common/di/` existe também no console, e uma em `config/web/di/` só existe na web. Nesta seção, por exemplo, a definição de um middleware fica em `config/web/di/estrutura.php`, porque middleware não faz sentido no console. Já o repositório de tarefas fica em `config/common/di/estrutura.php`, porque um comando de console também poderia usá-lo.

### src/

O template começa com poucas pastas:

```plaintext
src/
  Console/           comandos de console
  Shared/            o que é usado em mais de um lugar (ApplicationParams)
  Web/               tudo que responde a HTTP
    HomePage/
    NotFound/
    Shared/Layout/Main/
  Environment.php    leitura das variáveis de ambiente
  bootstrap.php      autoload, .env e ambiente
```

O namespace `App\` aponta para `src/`, pelo `autoload` do `composer.json`. Detalhes em [Autoload](/conceitos/autoload).

### runtime/

Arquivos que a aplicação escreve enquanto roda: logs, cache e, no app de exemplo deste guia, o banco SQLite. A pasta precisa de permissão de escrita e não vai para o repositório.

## Organizando por funcionalidade

O Yii2 organizava o código por tipo: todos os controllers em `controllers/`, todas as views em `views/`, todos os models em `models/`. O template do Yii3 organiza por funcionalidade. Cada página tem uma pasta com a action e a view lado a lado:

```plaintext
src/Web/HomePage/
  Action.php
  template.php
```

A action renderiza a view pelo caminho absoluto, com `__DIR__ . '/template'`, então não existe convenção de pastas para seguir. Quando você mexe numa página, tudo o que ela usa está na mesma pasta.

Os exemplos desta seção seguem essa ideia. Uma parte da árvore:

```plaintext
src/Web/Estrutura/
  Artigo/
    ArtigoController.php
    listar.php
    ver.php
  Assets/
    Action.php
    AlpineAsset.php
    EstruturaAsset.php
    template.php
  Middleware/
    ExigeChaveMiddleware.php
    TempoDeRespostaMiddleware.php
  Tarefa/
    ConcluirAction.php
    CriarAction.php
    ListarAction.php
    listar.php
  Widget/
    Alerta.php
    Cartao.php
    MenuSecao.php

src/Estrutura/
  Tarefa/
    ConcluirTarefa.php
    DbTarefaRepository.php
    Tarefa.php
    TarefaRepositoryInterface.php
    ...
```

Repare que existem duas raízes. Em `src/Web/` fica o código que conhece HTTP: actions, views, middlewares, widgets. Em `src/Estrutura/` fica o domínio, que não sabe que existe uma requisição. As tarefas aparecem nos dois lugares: `src/Estrutura/Tarefa/` tem a entidade e as regras, e `src/Web/Estrutura/Tarefa/` tem as páginas que as usam. O capítulo [Domínio: entidades e serviços](/estrutura/dominio) mostra essa separação em detalhe.

Não existe regra fixa para os nomes. Algumas orientações que funcionam bem:

- Uma pasta por página ou por recurso dentro de `src/Web/`.
- Código usado por várias páginas vai para uma pasta `Shared/` no nível em que é compartilhado. O layout do template, por exemplo, está em `src/Web/Shared/Layout/Main/`.
- O domínio fica fora de `src/Web/`, agrupado por assunto (`Tarefa`, `Pedido`, `Cliente`), e não por tipo (`Entities`, `Services`).

## Comparando com o Yii2

- No Yii2, `Yii::$app` era um service locator acessível de qualquer lugar. No Yii3 não há estado global: cada classe recebe o que precisa pelo construtor.
- A pasta `web/` virou `public/`, e `views/`, `controllers/` e `models/` deram lugar a pastas por funcionalidade dentro de `src/`.
- O `config/web.php` com um array enorme foi substituído por vários arquivos pequenos, juntados pelo `yiisoft/config`.
- O que eram componentes da aplicação (`request`, `db`, `user`) agora são serviços no container.
