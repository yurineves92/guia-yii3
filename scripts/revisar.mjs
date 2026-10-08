// Revisão automática dos capítulos. Rode com: npm run revisar
//
// Confere, para cada página em docs/:
// - se ainda é esboço
// - emoji e frases proibidas pelo ESTILO.md
// - se todo arquivo PHP mostrado inteiro existe, idêntico, no app/
// - links internos para páginas que não existem
// - tamanho (palavras fora dos blocos de código)

import { readdirSync, readFileSync, statSync, existsSync } from 'node:fs'
import { dirname, join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..')
const docs = join(raiz, 'docs')
const app = join(raiz, 'app')

const proibidas = [
  'poderoso', 'poderosa', 'incrível', 'vamos mergulhar', 'mergulhar fundo', 'neste capítulo você vai aprender',
  'em resumo', 'em conclusão', '# conclusão', 'robusto', 'robusta', 'sem esforço', 'de forma simples e elegante', 'elegante',
  'game changer', 'divisor de águas', 'no final do dia', 'vale ressaltar', 'é importante ressaltar',
]

function arquivos(dir, filtro, ignorar = []) {
  const lista = []
  for (const nome of readdirSync(dir)) {
    const caminho = join(dir, nome)
    if (ignorar.some((i) => caminho.includes(i))) continue
    if (statSync(caminho).isDirectory()) lista.push(...arquivos(caminho, filtro, ignorar))
    else if (filtro(nome)) lista.push(caminho)
  }
  return lista
}

const normalizar = (s) => s.replace(/\r/g, '').replace(/^<\?php \/\/ [\w.\-/]+/, '<?php').trim()

// Código que não roda dentro do app (um pacote separado, um worker de RoadRunner)
// fica em exemplos/, e também conta como "existe".
const exemplos = join(raiz, 'exemplos')
const codigoDoApp = new Set(
  [app, ...(existsSync(exemplos) ? [exemplos] : [])]
    .flatMap((dir) => arquivos(dir, (n) => n.endsWith('.php'), ['vendor', 'runtime', 'node_modules']))
    // Trechos citados dos próprios pacotes yiisoft/* também valem.
    .concat(arquivos(join(app, 'vendor', 'yiisoft'), (n) => n.endsWith('.php'), ['tests']))
    .map((f) => normalizar(readFileSync(f, 'utf8'))),
)

// Blocos de código com cerca de 3 ou mais crases (```` é usado quando o código contém ```).
const blocos = /(`{3,})(\w*)\n([\s\S]*?)\n\1(?!`)/g

const paginas = arquivos(docs, (n) => n.endsWith('.md'), ['.vitepress', 'node_modules'])
const existe = (link) => {
  const limpo = link.split('#')[0].replace(/\/$/, '')
  if (limpo === '') return true
  return existsSync(join(docs, `${limpo}.md`)) || existsSync(join(docs, limpo, 'index.md'))
}

let problemas = 0
const linhas = []

for (const pagina of paginas.sort()) {
  const nome = relative(docs, pagina).replace(/\\/g, '/')
  if (nome === 'index.md' || nome === 'sumario.md') continue
  const texto = readFileSync(pagina, 'utf8')
  const avisos = []

  if (texto.includes('Capítulo em construção')) avisos.push('ainda é esboço')

  const semBlocos = texto.replace(blocos, '')
  const semCodigo = semBlocos.replace(/`[^`]*`/g, '')
  const emojis = semCodigo.match(/\p{Extended_Pictographic}/gu)
  if (emojis) avisos.push(`emoji: ${[...new Set(emojis)].join(' ')}`)

  const minusculo = semBlocos.toLowerCase()
  for (const p of proibidas) if (minusculo.includes(p)) avisos.push(`frase proibida: "${p}"`)

  for (const [, , linguagem, bloco] of texto.matchAll(blocos)) {
    if (linguagem !== 'php') continue
    const codigo = normalizar(bloco)
    const arquivoInteiro = codigo.startsWith('<?php') && /\n(namespace|return|use) /.test(codigo)
    if (arquivoInteiro && !codigoDoApp.has(codigo)) {
      const primeira = codigo.split('\n').find((l) => /^(namespace|final|class|return)/.test(l)) ?? codigo.split('\n')[0]
      avisos.push(`PHP sem arquivo idêntico no app: ${primeira.slice(0, 70)}`)
    }
  }

  for (const [, link] of semCodigo.matchAll(/\]\((\/[^)\s]*)\)/g)) {
    if (!existe(link)) avisos.push(`link quebrado: ${link}`)
  }

  const palavras = semCodigo.split(/\s+/).filter(Boolean).length
  if (palavras < 150 && !texto.includes('Capítulo em construção')) avisos.push(`curto: ${palavras} palavras`)

  problemas += avisos.length
  linhas.push(`${avisos.length ? 'X' : 'ok'}  ${nome} (${palavras} palavras)`)
  for (const a of avisos) linhas.push(`      - ${a}`)
}

console.log(linhas.join('\n'))
console.log(`\n${paginas.length} páginas, ${problemas} problema(s).`)
process.exitCode = problemas ? 1 : 0
