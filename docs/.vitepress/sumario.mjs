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
      { text: 'Visão geral', link: 'visao-geral' },
      { text: 'Script de entrada', link: 'script-de-entrada' },
      { text: 'Aplicação', link: 'aplicacao' },
      { text: 'Actions e controllers', link: 'actions-e-controllers' },
      { text: 'Middleware', link: 'middleware' },
      { text: 'Views', link: 'views' },
      { text: 'Domínio: entidades e serviços', link: 'dominio' },
      { text: 'Widgets', link: 'widgets' },
      { text: 'Assets', link: 'assets' },
      { text: 'Pacotes', link: 'pacotes' },
    ],
  },
  {
    text: 'Tratando requisições',
    dir: 'requisicoes',
    items: [
      { text: 'Roteamento e URLs', link: 'roteamento' },
      { text: 'Requisição', link: 'requisicao' },
      { text: 'Resposta', link: 'resposta' },
      { text: 'Sessões e cookies', link: 'sessoes-e-cookies' },
      { text: 'Tratamento de erros', link: 'tratamento-de-erros' },
      { text: 'Logs', link: 'logs' },
    ],
  },
  {
    text: 'Conceitos-chave',
    dir: 'conceitos',
    items: [
      { text: 'Configuração', link: 'configuracao' },
      { text: 'Container de DI', link: 'container-di' },
      { text: 'Aliases', link: 'aliases' },
      { text: 'Autoload', link: 'autoload' },
      { text: 'Eventos', link: 'eventos' },
      { text: 'Imutabilidade', link: 'imutabilidade' },
    ],
  },
  {
    text: 'Banco de dados',
    dir: 'banco-de-dados',
    items: [
      { text: 'Conexão e comandos', link: 'conexao' },
      { text: 'Query builder', link: 'query-builder' },
      { text: 'Active Record', link: 'active-record' },
      { text: 'Migrations', link: 'migrations' },
    ],
  },
  {
    text: 'Coletando dados do usuário',
    dir: 'formularios',
    items: [
      { text: 'Criando formulários', link: 'criando-formularios' },
      { text: 'Validação', link: 'validacao' },
      { text: 'Upload de arquivos', link: 'upload' },
      { text: 'Dados tabulares', link: 'dados-tabulares' },
    ],
  },
  {
    text: 'Exibindo dados',
    dir: 'dados',
    items: [
      { text: 'Formatação', link: 'formatacao' },
      { text: 'Paginação', link: 'paginacao' },
      { text: 'Ordenação', link: 'ordenacao' },
      { text: 'Data readers', link: 'data-readers' },
      { text: 'Widgets de dados', link: 'widgets-de-dados' },
    ],
  },
  {
    text: 'Segurança',
    dir: 'seguranca',
    items: [
      { text: 'Visão geral', link: 'visao-geral' },
      { text: 'Autenticação', link: 'autenticacao' },
      { text: 'Autorização', link: 'autorizacao' },
      { text: 'Senhas', link: 'senhas' },
      { text: 'Criptografia', link: 'criptografia' },
      { text: 'Boas práticas', link: 'boas-praticas' },
    ],
  },
  {
    text: 'Cache',
    dir: 'cache',
    items: [
      { text: 'Visão geral', link: 'visao-geral' },
      { text: 'Cache de dados', link: 'cache-de-dados' },
      { text: 'Cache HTTP', link: 'cache-http' },
    ],
  },
  {
    text: 'APIs REST',
    dir: 'api',
    items: [
      { text: 'Início rápido', link: 'inicio-rapido' },
      { text: 'Recursos e respostas', link: 'recursos' },
      { text: 'Autenticação', link: 'autenticacao' },
      { text: 'Versionamento', link: 'versionamento' },
      { text: 'Erros', link: 'erros' },
      { text: 'Documentação OpenAPI', link: 'openapi' },
    ],
  },
  {
    text: 'Ferramentas',
    dir: 'ferramentas',
    items: [
      { text: 'Debug', link: 'debug' },
      { text: 'Qualidade de código', link: 'qualidade' },
    ],
  },
  {
    text: 'Testes',
    dir: 'testes',
    items: [
      { text: 'Visão geral', link: 'visao-geral' },
      { text: 'Testes unitários', link: 'unitarios' },
      { text: 'Testes funcionais', link: 'funcionais' },
      { text: 'Testes web', link: 'web' },
    ],
  },
  {
    text: 'Tópicos especiais',
    dir: 'topicos',
    items: [
      { text: 'Comandos de console', link: 'console' },
      { text: 'Docker', link: 'docker' },
      { text: 'Internacionalização', link: 'i18n' },
      { text: 'Envio de e-mails', link: 'emails' },
      { text: 'Desempenho', link: 'desempenho' },
      { text: 'Servidores de longa duração', link: 'longa-duracao' },
    ],
  },
  {
    text: 'Helpers',
    dir: 'helpers',
    items: [
      { text: 'Visão geral', link: 'visao-geral' },
    ],
  },
]
