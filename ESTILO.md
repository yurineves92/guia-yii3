# Como escrever um capítulo

Regras para quem escreve no guia, gente ou agente. Os capítulos já prontos de `docs/introducao/` e `docs/primeiros-passos/` são a referência de tom e formato: leia pelo menos `primeiros-passos/formularios.md` e `primeiros-passos/banco-de-dados.md` antes de começar.

## Público

Dev PHP que já conhece PHP moderno (classes, namespaces, Composer). Muitos vêm do Yii2. Não precisa explicar o que é uma classe, mas precisa explicar cada conceito do Yii3.

## Tom

- Português do Brasil, direto, falando com "você". Frases curtas.
- Nada de emoji ou ícone, nem em títulos.
- Nada de frase de efeito ou marketing: "poderoso", "incrível", "vamos mergulhar", "neste capítulo você vai aprender", "em resumo", "conclusão".
- Não invente experiência pessoal ("eu passei horas…", "isso me salvou…").
- Use negrito pouco. Tabela só quando é comparação de verdade. No máximo um ou dois blocos `::: tip` / `::: warning` por capítulo.
- Abra com um parágrafo curto dizendo que problema o capítulo resolve. Termine quando o assunto acabar, sem seção de conclusão.
- Quando fizer sentido, feche com `## Comparando com o Yii2`, curto e só com afirmações corretas.
- Tamanho: de 600 a 1800 palavras, conforme o assunto pede.

## Código

- **Todo código precisa ter rodado.** Escreva no `app/`, execute (servidor, curl, console ou teste) e só então copie para o capítulo.
- Arquivo PHP mostrado inteiro no capítulo deve ser idêntico ao arquivo do `app/`. Trecho parcial é permitido quando o texto deixa claro que é um trecho, e esse trecho também precisa existir e funcionar no app.
- Confira classes, métodos e parâmetros no código-fonte em `app/vendor/`. Não confie na memória: as APIs do Yii3 mudaram muito entre versões.
- Se algo não pode ser executado aqui (RoadRunner, envio real de e-mail, Docker, produção), explique a partir do código e da documentação do pacote, sem afirmar resultados que você não viu.
- Blocos de código sempre com linguagem: `php`, `bash`, `plaintext`, `ini`, `json`, `yaml`.

## Organização no app de exemplo

Cada seção do guia tem o seu espaço, para que os capítulos não interfiram uns nos outros:

| O quê | Onde |
|---|---|
| Código web | `app/src/Web/<Secao>/<Exemplo>/` |
| Código fora da web (domínio, serviços) | `app/src/<Secao>/` |
| Rotas | `app/config/common/routes/<dir-da-secao>.php` |
| Definições de DI | `app/config/common/di/<dir-da-secao>.php` (ou `config/web/di/` se for só web) |
| Parâmetros | `app/config/common/params/<dir-da-secao>.php` |
| URLs | prefixadas com `/<dir-da-secao>/…` |
| Tabelas | nomes próprios da seção; migrations em `app/src/Migration/` |

`<Secao>` é o nome da seção em PascalCase, sem acento (por exemplo `Seguranca`, `Api`, `BancoDeDados`).

Não altere: os exemplos de Primeiros passos, `config/web/di/application.php` (a pilha global de middlewares), `composer.json` e arquivos de outras seções. Se precisar de um pacote que não está instalado, não instale: descreva no texto e avise.

No texto, as rotas são apresentadas como parte "do arquivo de rotas" (`config/common/routes.php` no template). O capítulo de Configuração explica que o app de exemplo divide as rotas em vários arquivos.

Migrations: escreva o arquivo completo de uma vez antes de rodar `./yii migrate:up`, porque outras seções podem aplicar migrations ao mesmo tempo.

## Formato das páginas

- O arquivo do capítulo já existe como esboço em `docs/<dir>/<capitulo>.md`. Substitua todo o conteúdo, inclusive o aviso de "em construção".
- Comece com `# Título`, igual ao do sumário.
- Links internos com caminho absoluto e sem `.md`: `[Container de DI](/conceitos/container-di)`. Só para páginas que existem em `docs/.vitepress/sumario.mjs`.
- Não edite `sumario.mjs`, `config.mts` nem os capítulos de outras seções.
