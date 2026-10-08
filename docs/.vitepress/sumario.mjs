// Sumário do guia. Alimenta o menu lateral (config.mts) e o script que cria
// os esboços dos capítulos ainda não escritos (scripts/gerar-esbocos.mjs).
//
// Capítulo com `topicos` = esboço: o script cria a página listando o que ela vai cobrir.
// Capítulo sem `topicos` = já escrito.

export const sumario = [
  {
    text: 'Introdução',
    dir: 'introducao',
    items: [
      { text: 'Sobre o Yii3', link: 'sobre-o-yii' },
      { text: 'Migrando do Yii2', link: 'migrando-do-yii2' },
    ],
  },
  {
    text: 'Primeiros passos',
    dir: 'primeiros-passos',
    items: [
      { text: 'O que você precisa saber', link: 'o-que-voce-precisa-saber' },
      { text: 'Instalando o Yii3', link: 'instalando' },
      { text: 'Executando a aplicação', link: 'executando' },
      { text: 'Dizendo "Olá"', link: 'dizendo-ola' },
      { text: 'Trabalhando com formulários', link: 'formularios' },
      { text: 'Trabalhando com banco de dados', link: 'banco-de-dados' },
      { text: 'Seguindo em frente', link: 'seguindo-em-frente' },
    ],
  },
  {
    text: 'Estrutura da aplicação',
    dir: 'estrutura',
    items: [
      { text: 'Visão geral', link: 'visao-geral', topicos: ['As camadas de um app Yii3', 'O que fica em src/, config/ e public/', 'Organizando por funcionalidade'] },
      { text: 'Script de entrada', link: 'script-de-entrada', topicos: ['public/index.php e o ./yii', 'HttpApplicationRunner e ConsoleApplicationRunner', 'Variáveis de ambiente e o .env'] },
      { text: 'Aplicação', link: 'aplicacao', topicos: ['A classe Application', 'O fallback handler (404)', 'Parâmetros da aplicação'] },
      { text: 'Actions e controllers', link: 'actions-e-controllers', topicos: ['Action de uma classe só, com __invoke()', 'Controllers com vários métodos', 'Parâmetros vindos da rota, da query e do corpo'] },
      { text: 'Middleware', link: 'middleware', topicos: ['PSR-15 na prática', 'A pilha global e a pilha por rota', 'Escrevendo o seu middleware'] },
      { text: 'Views', link: 'views', topicos: ['WebView e WebViewRenderer', 'Layouts e parâmetros comuns', 'Injeções de view'] },
      { text: 'Domínio: entidades e serviços', link: 'dominio', topicos: ['Onde fica a regra de negócio', 'Repositories e serviços', 'Mantendo o domínio longe do HTTP'] },
      { text: 'Widgets', link: 'widgets', topicos: ['yiisoft/widget', 'Criando um widget próprio', 'Widgets com configuração por DI'] },
      { text: 'Assets', link: 'assets', topicos: ['AssetBundle e AssetManager', 'Publicação dos arquivos', 'Integração com Vite ou outro bundler'] },
      { text: 'Pacotes', link: 'pacotes', topicos: ['Usando pacotes yiisoft/*', 'Configuração que vem com o pacote', 'Criando o seu pacote'] },
    ],
  },
  {
    text: 'Tratando requisições',
    dir: 'requisicoes',
    items: [
      { text: 'Roteamento e URLs', link: 'roteamento', topicos: ['Route, Group e nomes de rota', 'Parâmetros e padrões', 'Gerando URLs com UrlGeneratorInterface'] },
      { text: 'Requisição', link: 'requisicao', topicos: ['ServerRequestInterface (PSR-7)', 'Lendo query, corpo e headers', 'Hydrator e atributos de entrada'] },
      { text: 'Resposta', link: 'resposta', topicos: ['ResponseFactoryInterface', 'Redirects, JSON e downloads', 'DataResponse'] },
      { text: 'Sessões e cookies', link: 'sessoes-e-cookies', topicos: ['SessionInterface', 'Mensagens flash', 'Cookies assinados'] },
      { text: 'Tratamento de erros', link: 'tratamento-de-erros', topicos: ['ErrorCatcher e ErrorHandler', 'Páginas de erro por ambiente', 'Exceções amigáveis'] },
      { text: 'Logs', link: 'logs', topicos: ['PSR-3 com yiisoft/log', 'Targets: arquivo, stream, e-mail', 'Contexto e categorias'] },
    ],
  },
  {
    text: 'Conceitos-chave',
    dir: 'conceitos',
    items: [
      { text: 'Configuração', link: 'configuracao', topicos: ['yiisoft/config e o merge plan', 'Grupos: params, di, routes, events', 'Ambientes dev, prod e test'] },
      { text: 'Container de DI', link: 'container-di', topicos: ['Autowiring', 'Definições: class, __construct(), métodos', 'Referências, fábricas e reset'] },
      { text: 'Aliases', link: 'aliases', topicos: ['@root, @runtime, @public…', 'Criando aliases próprios', 'Usando Aliases nas classes'] },
      { text: 'Autoload', link: 'autoload', topicos: ['PSR-4 com Composer', 'Namespace App\\ e a pasta src/'] },
      { text: 'Eventos', link: 'eventos', topicos: ['PSR-14 com yiisoft/event-dispatcher', 'Registrando listeners na configuração', 'Eventos da aplicação'] },
      { text: 'Imutabilidade', link: 'imutabilidade', topicos: ['Por que tantos with*()', 'Serviços compartilhados e estado', 'Servidores de longa duração'] },
    ],
  },
  {
    text: 'Banco de dados',
    dir: 'banco-de-dados',
    items: [
      { text: 'Conexão e comandos', link: 'conexao', topicos: ['Drivers: SQLite, MySQL, PostgreSQL…', 'ConnectionInterface e createCommand()', 'Transações'] },
      { text: 'Query builder', link: 'query-builder', topicos: ['select, where, join, orderBy', 'Condições em array e operadores', 'Inserir, atualizar, apagar e upsert'] },
      { text: 'Active Record', link: 'active-record', topicos: ['yiisoft/active-record', 'Relações', 'Quando usar e quando evitar'] },
      { text: 'Migrations', link: 'migrations', topicos: ['migrate:create e os geradores', 'ColumnBuilder', 'Revertendo e refazendo'] },
    ],
  },
  {
    text: 'Coletando dados do usuário',
    dir: 'formularios',
    items: [
      { text: 'Criando formulários', link: 'criando-formularios', topicos: ['FormModel', 'Field e temas de formulário', 'Labels, hints e placeholders'] },
      { text: 'Validação', link: 'validacao', topicos: ['Regras como atributos', 'Regras nativas', 'Escrevendo uma regra própria'] },
      { text: 'Upload de arquivos', link: 'upload', topicos: ['UploadedFileInterface', 'Validando tipo e tamanho', 'Onde e como salvar'] },
      { text: 'Dados tabulares', link: 'dados-tabulares', topicos: ['Vários registros num formulário só', 'Formulários aninhados'] },
    ],
  },
  {
    text: 'Exibindo dados',
    dir: 'dados',
    items: [
      { text: 'Formatação', link: 'formatacao', topicos: ['Datas, números e moeda com intl', 'Formatando na view'] },
      { text: 'Paginação', link: 'paginacao', topicos: ['OffsetPaginator', 'KeysetPaginator', 'Links de página'] },
      { text: 'Ordenação', link: 'ordenacao', topicos: ['Sort', 'Ordenação vinda da URL'] },
      { text: 'Data readers', link: 'data-readers', topicos: ['yiisoft/data e yiisoft/data-db', 'Filtros', 'Lendo de arrays e de consultas'] },
      { text: 'Widgets de dados', link: 'widgets-de-dados', topicos: ['GridView e ListView', 'Colunas e ações'] },
    ],
  },
  {
    text: 'Segurança',
    dir: 'seguranca',
    items: [
      { text: 'Visão geral', link: 'visao-geral', topicos: ['O que o template já protege', 'CSRF, XSS e SQL injection'] },
      { text: 'Autenticação', link: 'autenticacao', topicos: ['yiisoft/user e CurrentUser', 'Login por sessão', 'Tokens para API'] },
      { text: 'Autorização', link: 'autorizacao', topicos: ['RBAC com yiisoft/rbac', 'Papéis, permissões e regras', 'Middleware de acesso'] },
      { text: 'Senhas', link: 'senhas', topicos: ['PasswordHasher', 'Política de senha'] },
      { text: 'Criptografia', link: 'criptografia', topicos: ['yiisoft/security', 'Gerando tokens aleatórios'] },
      { text: 'Boas práticas', link: 'boas-praticas', topicos: ['Headers de segurança', 'Dados sensíveis em logs', 'Checklist antes de publicar'] },
    ],
  },
  {
    text: 'Cache',
    dir: 'cache',
    items: [
      { text: 'Visão geral', link: 'visao-geral', topicos: ['PSR-16 com yiisoft/cache', 'Drivers: arquivo, Redis, memória'] },
      { text: 'Cache de dados', link: 'cache-de-dados', topicos: ['getOrSet()', 'Dependências e invalidação'] },
      { text: 'Cache HTTP', link: 'cache-http', topicos: ['ETag e Last-Modified', 'Cache-Control'] },
    ],
  },
  {
    text: 'APIs REST',
    dir: 'api',
    items: [
      { text: 'Início rápido', link: 'inicio-rapido', topicos: ['Template yiisoft/app-api', 'Primeiro endpoint JSON'] },
      { text: 'Recursos e respostas', link: 'recursos', topicos: ['Formatando a saída', 'Paginação em API'] },
      { text: 'Autenticação', link: 'autenticacao', topicos: ['Bearer token', 'Middleware de autenticação'] },
      { text: 'Versionamento', link: 'versionamento', topicos: ['Versão na URL', 'Grupos de rota por versão'] },
      { text: 'Erros', link: 'erros', topicos: ['Problem Details (RFC 9457)', 'Erros de validação'] },
      { text: 'Documentação OpenAPI', link: 'openapi', topicos: ['swagger-php com atributos', 'Swagger UI'] },
    ],
  },
  {
    text: 'Ferramentas',
    dir: 'ferramentas',
    items: [
      { text: 'Debug', link: 'debug', topicos: ['yiisoft/yii-debug', 'Painel de debug'] },
      { text: 'Qualidade de código', link: 'qualidade', topicos: ['Psalm', 'Rector', 'PHP CS Fixer'] },
    ],
  },
  {
    text: 'Testes',
    dir: 'testes',
    items: [
      { text: 'Visão geral', link: 'visao-geral', topicos: ['As suítes do template', 'composer test'] },
      { text: 'Testes unitários', link: 'unitarios', topicos: ['Testando domínio sem framework'] },
      { text: 'Testes funcionais', link: 'funcionais', topicos: ['Rodando a aplicação dentro do teste'] },
      { text: 'Testes web', link: 'web', topicos: ['Codeception com o servidor rodando'] },
    ],
  },
  {
    text: 'Tópicos especiais',
    dir: 'topicos',
    items: [
      { text: 'Comandos de console', link: 'console', topicos: ['Criando um comando', 'Registrando em config/console'] },
      { text: 'Docker', link: 'docker', topicos: ['O Docker do template', 'make up, make shell'] },
      { text: 'Internacionalização', link: 'i18n', topicos: ['yiisoft/translator', 'Mensagens em vários idiomas'] },
      { text: 'Envio de e-mails', link: 'emails', topicos: ['yiisoft/mailer', 'Templates de e-mail'] },
      { text: 'Desempenho', link: 'desempenho', topicos: ['Opcache e preload', 'Cache de configuração'] },
      { text: 'Servidores de longa duração', link: 'longa-duracao', topicos: ['RoadRunner', 'FrankenPHP', 'Cuidados com estado'] },
    ],
  },
  {
    text: 'Helpers',
    dir: 'helpers',
    items: [
      { text: 'Visão geral', link: 'visao-geral', topicos: ['Html', 'Arrays', 'Strings', 'Json'] },
    ],
  },
]
