// Gera as páginas de esboço dos capítulos ainda não escritos e o docs/sumario.md.
// Nunca sobrescreve um capítulo que já existe. Rode com: npm run esbocos

import { existsSync, mkdirSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { sumario } from '../docs/.vitepress/sumario.mjs'

const docs = join(dirname(fileURLToPath(import.meta.url)), '..', 'docs')
let criados = 0

for (const secao of sumario) {
  for (const item of secao.items) {
    const arquivo = join(docs, secao.dir, `${item.link}.md`)
    if (existsSync(arquivo)) continue
    if (!item.topicos) {
      console.warn(`Faltando capítulo escrito: ${secao.dir}/${item.link}.md`)
      continue
    }

    mkdirSync(dirname(arquivo), { recursive: true })
    writeFileSync(arquivo, [
      `# ${item.text}`,
      '',
      '::: info Capítulo em construção',
      'Esta página ainda é um esboço. Enquanto isso, o [guia oficial em inglês](https://yiisoft.github.io/docs/guide/) cobre parte do assunto.',
      ':::',
      '',
      'O que este capítulo vai cobrir:',
      '',
      ...item.topicos.map((t) => `- ${t}`),
      '',
    ].join('\n'))
    criados++
  }
}

const linhas = ['# Sumário', '']
for (const secao of sumario) {
  linhas.push(`## ${secao.text}`, '')
  for (const item of secao.items) {
    const marca = item.topicos ? ' *(em breve)*' : ''
    linhas.push(`- [${item.text}](/${secao.dir}/${item.link})${marca}`)
  }
  linhas.push('')
}
writeFileSync(join(docs, 'sumario.md'), linhas.join('\n'))

console.log(`${criados} esboço(s) criado(s), sumario.md atualizado.`)
