import { defineConfig } from 'vitepress'
import { sumario } from './sumario.mjs'

const repo = 'https://github.com/yurineves92/guia-yii3'

const sidebar = sumario.map((secao) => ({
  text: secao.text,
  // Seções só com esboços começam fechadas, para o menu não ficar enorme.
  collapsed: secao.items.every((item) => item.topicos),
  items: secao.items.map((item) => ({
    text: item.topicos ? `${item.text} <span class="em-breve">em breve</span>` : item.text,
    link: `/${secao.dir}/${item.link}`,
  })),
}))

export default defineConfig({
  lang: 'pt-BR',
  title: 'Guia Yii3',
  description: 'Guia do Yii3 em português, com exemplos testados.',
  base: process.env.BASE ?? '/',
  cleanUrls: true,
  lastUpdated: true,

  head: [['link', { rel: 'icon', href: `${process.env.BASE ?? '/'}favicon.svg` }]],

  themeConfig: {
    logo: '/favicon.svg',
    nav: [
      { text: 'Começar', link: '/introducao/sobre-o-yii' },
      { text: 'Sumário', link: '/sumario' },
      { text: 'Guia oficial (inglês)', link: 'https://yiisoft.github.io/docs/guide/' },
    ],
    sidebar,
    socialLinks: [{ icon: 'github', link: repo }],
    editLink: {
      pattern: `${repo}/edit/main/docs/:path`,
      text: 'Sugerir uma correção nesta página',
    },
    outline: { label: 'Nesta página', level: [2, 3] },
    docFooter: { prev: 'Anterior', next: 'Próximo' },
    lastUpdated: { text: 'Atualizado em' },
    darkModeSwitchLabel: 'Aparência',
    lightModeSwitchTitle: 'Mudar para o tema claro',
    darkModeSwitchTitle: 'Mudar para o tema escuro',
    sidebarMenuLabel: 'Menu',
    returnToTopLabel: 'Voltar ao topo',
    langMenuLabel: 'Idioma',
    notFound: {
      title: 'Página não encontrada',
      quote: 'Esse endereço não existe, ou o capítulo mudou de lugar.',
      linkText: 'Ir para o início',
    },
    footer: {
      message: 'Conteúdo independente, não é a documentação oficial do Yii.',
      copyright: 'Feito por <a href="https://github.com/yurineves92">Yuri Neves</a>',
    },
    search: {
      provider: 'local',
      options: {
        translations: {
          button: { buttonText: 'Buscar', buttonAriaLabel: 'Buscar' },
          modal: {
            displayDetails: 'Mostrar detalhes',
            resetButtonTitle: 'Limpar busca',
            backButtonTitle: 'Fechar busca',
            noResultsText: 'Nada encontrado para',
            footer: { selectText: 'abrir', navigateText: 'navegar', closeText: 'fechar' },
          },
        },
      },
    },
  },
})
