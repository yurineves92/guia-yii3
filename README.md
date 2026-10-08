# Guia Yii3

Um guia do Yii3 em português, com a estrutura do [guia do Yii2](https://www.yiiframework.com/doc/guide/2.0/pt-br) e exemplos testados num app de verdade.

Não é a documentação oficial. A referência oficial, em inglês, fica em [yiisoft.github.io/docs](https://yiisoft.github.io/docs/guide/).

## Estrutura

```plaintext
docs/                    # o site (VitePress)
  .vitepress/sumario.mjs # o sumário: menu lateral e lista de esboços
  primeiros-passos/      # um diretório por seção
app/                     # o app Yii3 de onde vem o código dos capítulos
exemplos/                # código citado que não roda dentro do app (pacote separado, workers)
scripts/gerar-esbocos.mjs
scripts/revisar.mjs      # revisão automática dos capítulos
ESTILO.md                # regras de tom, código e organização
```

## Rodando o site

```bash
npm install
npm run dev
```

## Rodando os exemplos

```bash
cd app
composer install
cp .env.example .env
./yii migrate:up
./yii serve
```

Depois acesse `/ola`, `/cadastro` e `/paises` em http://127.0.0.1:8080. Cada seção do guia tem as suas páginas sob o próprio prefixo, como `/seguranca/login` e `/api/v1/tarefas`. Algumas páginas pedem dados de exemplo: rode `./yii seguranca:rbac` antes de testar a seção de Segurança.

## Escrevendo um capítulo

Antes, leia o [ESTILO.md](ESTILO.md).

1. Escreva o código no `app/` e teste.
2. Substitua o esboço em `docs/<secao>/<capitulo>.md` pelo texto, copiando o código do `app/`.
3. Em `docs/.vitepress/sumario.mjs`, remova os `topicos` do capítulo. É isso que tira a marca "em breve" do menu.
4. Rode `npm run esbocos` para atualizar o `docs/sumario.md`, `npm run revisar` para a revisão automática e `npm run build` para conferir links quebrados.

## Licença

O texto está sob [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/deed.pt_BR) e o código dos exemplos sob BSD-3-Clause, a mesma licença do Yii.
