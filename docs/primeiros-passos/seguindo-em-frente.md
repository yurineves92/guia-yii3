# Seguindo em frente

Se você chegou até aqui, já tem um app Yii3 que mostra páginas, recebe e valida formulários e lê dados do banco com paginação. É o suficiente para entender como as peças se encaixam.

## O que ler agora

O caminho que recomendo, dependendo do que você quer fazer:

- **Entender o que está por baixo**: [Configuração](/conceitos/configuracao), [Container de DI](/conceitos/container-di) e [Middleware](/estrutura/middleware). São os três conceitos que mais mudaram em relação ao Yii2.
- **Fazer um CRUD completo**: o projeto [crud-yii3](https://github.com/yurineves92/crud-yii3) junta tudo destes capítulos num cadastro de posts, com criar, editar e apagar.
- **Criar uma API**: [APIs REST](/api/inicio-rapido). Existe também um template próprio para isso, o `yiisoft/app-api`.
- **Colocar login e permissões**: [Autenticação](/seguranca/autenticacao) e [Autorização](/seguranca/autorizacao).

## Outros lugares para aprender

- [Guia oficial do Yii3](https://yiisoft.github.io/docs/guide/), em inglês. É a referência de cada pacote.
- [Repositórios no GitHub](https://github.com/yiisoft): cada pacote tem um README com exemplos, e muitas vezes é o jeito mais rápido de tirar uma dúvida.
- [Fórum do Yii](https://www.yiiframework.com/forum/) e o [grupo no Telegram](https://t.me/yii3en), onde fica a comunidade.

## O código dos exemplos

Todos os exemplos destes capítulos estão na pasta `app/` do [repositório deste guia](https://github.com/yurineves92/guia-yii3). Para rodar:

```bash
cd app
composer install
cp .env.example .env
./yii migrate:up
./yii serve
```
